"""Polygon helpers for parking zone assignment."""

from __future__ import annotations

import json
from pathlib import Path
from typing import Any

import cv2
import numpy as np

ZONE_COLORS = {
    "slot": (80, 180, 255),
    "no_parking": (0, 0, 255),
    "aisle": (0, 200, 255),
}


def _empty_zones() -> dict[str, Any]:
    return {
        "version": 1,
        "calibrated": False,
        "image_width": 0,
        "image_height": 0,
        "zones": [],
    }


def load_zones(path: Path) -> dict[str, Any]:
    """Load zone JSON. Empty / half-written files return an empty template (never raise)."""
    if not path.is_file():
        return _empty_zones()
    try:
        raw = path.read_text(encoding="utf-8").strip()
    except OSError:
        return _empty_zones()
    if not raw:
        # Common while another process is still writing (or OneDrive syncing).
        return _empty_zones()
    try:
        data = json.loads(raw)
    except json.JSONDecodeError:
        return _empty_zones()
    if not isinstance(data, dict):
        return _empty_zones()
    data.setdefault("calibrated", False)
    data.setdefault("zones", [])
    return data


def save_zones(path: Path, data: dict[str, Any]) -> None:
    """Atomic write so live reload never reads a truncated file."""
    path = Path(path)
    path.parent.mkdir(parents=True, exist_ok=True)
    payload = json.dumps(data, indent=2) + "\n"
    tmp = path.with_suffix(path.suffix + ".tmp")
    tmp.write_text(payload, encoding="utf-8")
    tmp.replace(path)


def usable_zones(zones_data: dict[str, Any]) -> list[dict[str, Any]]:
    """Zones with at least 3 points."""
    out = []
    for z in zones_data.get("zones", []):
        pts = z.get("points") or []
        if len(pts) >= 3:
            out.append(z)
    return out


def calibration_size(zones_data: dict[str, Any]) -> tuple[int, int] | None:
    """Snapshot size the polygons were drawn on, if recorded."""
    w = int(zones_data.get("image_width") or 0)
    h = int(zones_data.get("image_height") or 0)
    if w > 0 and h > 0:
        return w, h
    return None


def scale_points_to_frame(
    points: list[list[float]],
    src_wh: tuple[int, int],
    dst_wh: tuple[int, int],
) -> list[list[int]]:
    """Map snapshot polygons onto a live frame.

    Uses cover+center (uniform scale) when aspect ratios differ so ACAD/Duran
    portrait calibrations still land on landscape RTSP frames.
    """
    sw, sh = src_wh
    dw, dh = dst_wh
    if sw <= 0 or sh <= 0 or (sw == dw and sh == dh):
        return [[int(round(p[0])), int(round(p[1]))] for p in points]

    src_aspect = sw / float(sh)
    dst_aspect = dw / float(dh)
    # Near-matching aspect: simple stretch is fine.
    if abs(src_aspect - dst_aspect) < 0.08:
        sx, sy = dw / sw, dh / sh
        return [[int(round(p[0] * sx)), int(round(p[1] * sy))] for p in points]

    scale = max(dw / sw, dh / sh)
    nw, nh = sw * scale, sh * scale
    ox, oy = (dw - nw) / 2.0, (dh - nh) / 2.0
    return [[int(round(p[0] * scale + ox)), int(round(p[1] * scale + oy))] for p in points]


def usable_zones_for_frame(zones_data: dict[str, Any], frame_shape: tuple[int, int]) -> list[dict[str, Any]]:
    """Calibrated polygons scaled to the current frame (full frame, no zoom-crop)."""
    zones = usable_zones(zones_data)
    src = calibration_size(zones_data)
    if not src:
        return zones
    dh, dw = frame_shape[:2]
    if src == (dw, dh):
        return zones
    out = []
    for z in zones:
        z2 = dict(z)
        z2["points"] = scale_points_to_frame(z.get("points") or [], src, (dw, dh))
        out.append(z2)
    return out


def has_calibrated_slots(zones_data: dict[str, Any]) -> bool:
    if not zones_data.get("calibrated"):
        return False
    return any(z.get("type") == "slot" and len(z.get("points") or []) >= 3 for z in zones_data.get("zones", []))


def point_in_polygon(x: float, y: float, points: list[list[float]]) -> bool:
    contour = np.array(points, dtype=np.float32)
    return cv2.pointPolygonTest(contour, (float(x), float(y)), False) >= 0


def box_center(xyxy: tuple[int, int, int, int]) -> tuple[float, float]:
    x1, y1, x2, y2 = xyxy
    return (x1 + x2) / 2.0, (y1 + y2) / 2.0


def box_ground_point(xyxy: tuple[int, int, int, int]) -> tuple[float, float]:
    """Bottom-center of the bbox — better proxy for where the vehicle sits on asphalt."""
    x1, y1, x2, y2 = xyxy
    return (x1 + x2) / 2.0, float(y2)


def box_iou_with_polygon(xyxy: tuple[int, int, int, int], points: list[list[float]], frame_shape: tuple[int, int]) -> float:
    """Approximate IoU between axis-aligned box and polygon via ROI masks (not full-frame)."""
    h, w = frame_shape[:2]
    x1, y1, x2, y2 = [int(v) for v in xyxy]
    x1, y1 = max(0, x1), max(0, y1)
    x2, y2 = min(w - 1, x2), min(h - 1, y2)
    if x2 <= x1 or y2 <= y1:
        return 0.0

    pts = np.array(points, dtype=np.float32)
    if pts.ndim != 2 or pts.shape[0] < 3:
        return 0.0

    px1 = int(max(0, min(w - 1, np.floor(pts[:, 0].min()))))
    py1 = int(max(0, min(h - 1, np.floor(pts[:, 1].min()))))
    px2 = int(max(0, min(w - 1, np.ceil(pts[:, 0].max()))))
    py2 = int(max(0, min(h - 1, np.ceil(pts[:, 1].max()))))

    rx1 = min(x1, px1)
    ry1 = min(y1, py1)
    rx2 = max(x2, px2)
    ry2 = max(y2, py2)
    if rx2 <= rx1 or ry2 <= ry1:
        return 0.0

    rh = ry2 - ry1 + 1
    rw = rx2 - rx1 + 1
    # Cap ROI work for 4K frames — keep assignment realtime.
    max_side = 480
    scale = 1.0
    if max(rh, rw) > max_side:
        scale = max_side / float(max(rh, rw))
        rh = max(1, int(round(rh * scale)))
        rw = max(1, int(round(rw * scale)))

    poly_mask = np.zeros((rh, rw), dtype=np.uint8)
    box_mask = np.zeros((rh, rw), dtype=np.uint8)
    shifted = (pts - np.array([rx1, ry1], dtype=np.float32)) * scale
    cv2.fillPoly(poly_mask, [shifted.astype(np.int32)], 1)

    bx1 = int(round((x1 - rx1) * scale))
    by1 = int(round((y1 - ry1) * scale))
    bx2 = int(round((x2 - rx1) * scale))
    by2 = int(round((y2 - ry1) * scale))
    bx1, by1 = max(0, bx1), max(0, by1)
    bx2, by2 = min(rw, bx2), min(rh, by2)
    if bx2 > bx1 and by2 > by1:
        box_mask[by1:by2, bx1:bx2] = 1

    inter = int(np.logical_and(poly_mask, box_mask).sum())
    if inter == 0:
        return 0.0
    union = int(np.logical_or(poly_mask, box_mask).sum())
    return inter / union if union else 0.0


def assign_zones_for_box(
    xyxy: tuple[int, int, int, int],
    zones: list[dict[str, Any]],
    frame_shape: tuple[int, int],
    iou_threshold: float = 0.12,
) -> list[dict[str, Any]]:
    """Return zones the vehicle overlaps (ground point or IoU).

    For occupancy use :func:`primary_slot_for_box` (ground point only).
    """
    gx, gy = box_ground_point(xyxy)
    matched = []
    for z in zones:
        pts = z.get("points") or []
        if len(pts) < 3:
            continue
        by_ground = point_in_polygon(gx, gy, pts)
        iou = box_iou_with_polygon(xyxy, pts, frame_shape) if not by_ground else 1.0
        if by_ground or iou >= iou_threshold:
            matched.append({
                **z,
                "_iou": round(float(iou if not by_ground else max(iou, 0.5)), 3),
                "_by_ground": bool(by_ground),
            })
    return matched


def _zone_bbox_area(zone: dict[str, Any]) -> float:
    pts = zone.get("points") or []
    xs = [float(p[0]) for p in pts]
    ys = [float(p[1]) for p in pts]
    if not xs or not ys:
        return 1.0
    return max(1.0, (max(xs) - min(xs)) * (max(ys) - min(ys)))


def _bottom_sample_points(xyxy: tuple[int, int, int, int]) -> list[tuple[float, float]]:
    """Points along the tire line + lower body, used when bottom-center misses the bay."""
    x1, y1, x2, y2 = xyxy
    width = float(x2) - float(x1)
    height = float(y2) - float(y1)
    bottom = float(y2)
    points = [(float(x1) + width * frac, bottom) for frac in (0.10, 0.25, 0.40, 0.50, 0.60, 0.75, 0.90)]
    # Slightly above the bumper — helps when the tire line sits just outside the polygon.
    for frac in (0.25, 0.50, 0.75):
        points.append((float(x1) + width * frac, bottom - max(1.0, height * 0.08)))
        points.append((float(x1) + width * frac, bottom - max(1.0, height * 0.18)))
    points.append((float(x1) + width * 0.5, bottom - max(1.0, height * 0.12)))
    return points


def primary_slot_for_box(
    xyxy: tuple[int, int, int, int],
    zones: list[dict[str, Any]],
) -> dict[str, Any] | None:
    """One slot per vehicle.

    Bottom-center inside a polygon wins (tightest polygon if several overlap).
    If that point falls on a line, the bay that contains the most of the tire
    line wins. A clear single-bay hit is enough; only contested ties stay unmatched.
    """
    gx, gy = box_ground_point(xyxy)
    hits: list[tuple[float, dict[str, Any]]] = []
    for z in zones:
        pts = z.get("points") or []
        if len(pts) < 3:
            continue
        if not point_in_polygon(gx, gy, pts):
            continue
        hits.append((_zone_bbox_area(z), {**z, "_by_ground": True, "_iou": 1.0}))
    if hits:
        hits.sort(key=lambda item: item[0])
        return hits[0][1]

    votes: dict[str, tuple[int, float, dict[str, Any]]] = {}
    for px, py in _bottom_sample_points(xyxy):
        for z in zones:
            pts = z.get("points") or []
            if len(pts) < 3 or not point_in_polygon(px, py, pts):
                continue
            sid = str(z.get("id"))
            count, _area, _zone = votes.get(sid, (0, 0.0, z))
            votes[sid] = (count + 1, _zone_bbox_area(z), z)
    if not votes:
        return None
    ranked = sorted(votes.values(), key=lambda item: (-item[0], item[1]))
    best_count, _best_area, best = ranked[0]
    second_count = ranked[1][0] if len(ranked) > 1 else 0
    # Clear winner: majority of samples, or any unique hit with ≥1 sample.
    if best_count > second_count and best_count >= 1:
        return {**best, "_by_ground": False, "_iou": 0.5}
    return None


def draw_zones(frame, zones_data: dict[str, Any], occupied_slot_ids: set[str] | None = None):
    occupied_slot_ids = occupied_slot_ids or set()
    annotated = frame
    for z in usable_zones_for_frame(zones_data, frame.shape):
        pts = np.array(z["points"], dtype=np.int32)
        ztype = z.get("type", "slot")
        color = ZONE_COLORS.get(ztype, (200, 200, 200))
        zid = str(z.get("id", ""))
        if ztype == "slot" and zid in occupied_slot_ids:
            color = (0, 0, 220)
        overlay = annotated.copy()
        cv2.fillPoly(overlay, [pts], color)
        cv2.addWeighted(overlay, 0.18, annotated, 0.82, 0, annotated)
        cv2.polylines(annotated, [pts], True, color, 2)
        label = z.get("label") or zid
        if ztype == "slot" and zid in occupied_slot_ids:
            label = f"{label} OCC"
        mx, my = int(pts[:, 0].mean()), int(pts[:, 1].mean())
        cv2.putText(
            annotated,
            label,
            (mx - 20, my),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.5,
            color,
            2,
            cv2.LINE_AA,
        )
    return annotated
