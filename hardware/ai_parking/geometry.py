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


def load_zones(path: Path) -> dict[str, Any]:
    if not path.is_file():
        return {
            "version": 1,
            "calibrated": False,
            "image_width": 0,
            "image_height": 0,
            "zones": [],
        }
    with path.open("r", encoding="utf-8") as f:
        data = json.load(f)
    data.setdefault("calibrated", False)
    data.setdefault("zones", [])
    return data


def save_zones(path: Path, data: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as f:
        json.dump(data, f, indent=2)
        f.write("\n")


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
    """Map snapshot-pixel polygons onto a live frame without cropping (stretch to fill)."""
    sw, sh = src_wh
    dw, dh = dst_wh
    if sw <= 0 or sh <= 0 or (sw == dw and sh == dh):
        return [[int(round(p[0])), int(round(p[1]))] for p in points]
    sx, sy = dw / sw, dh / sh
    return [[int(round(p[0] * sx)), int(round(p[1] * sy))] for p in points]


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


def box_iou_with_polygon(xyxy: tuple[int, int, int, int], points: list[list[float]], frame_shape: tuple[int, int]) -> float:
    """Approximate IoU between axis-aligned box and polygon via masks."""
    h, w = frame_shape[:2]
    x1, y1, x2, y2 = xyxy
    x1, y1 = max(0, x1), max(0, y1)
    x2, y2 = min(w - 1, x2), min(h - 1, y2)
    if x2 <= x1 or y2 <= y1:
        return 0.0

    poly_mask = np.zeros((h, w), dtype=np.uint8)
    contour = np.array(points, dtype=np.int32)
    cv2.fillPoly(poly_mask, [contour], 1)

    box_mask = np.zeros((h, w), dtype=np.uint8)
    box_mask[y1:y2, x1:x2] = 1

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
    """Return zones the vehicle belongs to (center inside or IoU above threshold)."""
    cx, cy = box_center(xyxy)
    matched = []
    for z in zones:
        pts = z.get("points") or []
        if len(pts) < 3:
            continue
        by_center = point_in_polygon(cx, cy, pts)
        iou = box_iou_with_polygon(xyxy, pts, frame_shape) if not by_center else 1.0
        if by_center or iou >= iou_threshold:
            matched.append({**z, "_iou": round(float(iou if not by_center else max(iou, 0.5)), 3)})
    return matched


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
