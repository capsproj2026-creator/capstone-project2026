"""View-only plate scan for the AI Parking Monitor (no occupancy / DB writes)."""

from __future__ import annotations

import base64
from typing import Any, Optional

import cv2
import numpy as np


def clamp01(value: float) -> float:
    return max(0.0, min(1.0, float(value)))


def view_to_xyxy(
    frame_shape: tuple[int, ...],
    view: dict[str, Any] | None,
    pad: float = 0.04,
) -> tuple[int, int, int, int]:
    """Map a normalized visible rect (0–1) onto frame pixels, with padding."""
    h, w = int(frame_shape[0]), int(frame_shape[1])
    view = view if isinstance(view, dict) else {}
    x = clamp01(view.get("x", 0.0))
    y = clamp01(view.get("y", 0.0))
    vw = clamp01(view.get("w", 1.0))
    vh = clamp01(view.get("h", 1.0))
    if vw <= 0.001 or vh <= 0.001:
        x, y, vw, vh = 0.0, 0.0, 1.0, 1.0

    x1 = (x - pad) * w
    y1 = (y - pad) * h
    x2 = (x + vw + pad) * w
    y2 = (y + vh + pad) * h
    ix1 = max(0, min(w - 1, int(x1)))
    iy1 = max(0, min(h - 1, int(y1)))
    ix2 = max(ix1 + 1, min(w, int(x2)))
    iy2 = max(iy1 + 1, min(h, int(y2)))
    return ix1, iy1, ix2, iy2


def boxes_intersect(a: tuple[int, int, int, int], b: tuple[int, int, int, int]) -> bool:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b
    return ax1 < bx2 and ax2 > bx1 and ay1 < by2 and ay2 > by1


def encode_crop_jpeg(crop, *, quality: int = 92, max_side: int = 640) -> Optional[str]:
    if crop is None or getattr(crop, "size", 0) == 0:
        return None
    try:
        image = crop
        ch, cw = image.shape[:2]
        scale = min(1.0, float(max_side) / max(ch, cw, 1))
        if scale < 1.0:
            image = cv2.resize(
                image,
                (max(1, int(cw * scale)), max(1, int(ch * scale))),
                interpolation=cv2.INTER_AREA,
            )
        ok, buf = cv2.imencode(".jpg", image, [int(cv2.IMWRITE_JPEG_QUALITY), int(quality)])
        if not ok:
            return None
        return base64.b64encode(buf.tobytes()).decode("ascii")
    except Exception:
        return None


def encode_crop_jpeg_bytes(crop, *, quality: int = 88, max_side: int = 320) -> Optional[bytes]:
    if crop is None or getattr(crop, "size", 0) == 0:
        return None
    try:
        image = crop
        ch, cw = image.shape[:2]
        scale = min(1.0, float(max_side) / max(ch, cw, 1))
        if scale < 1.0:
            image = cv2.resize(
                image,
                (max(1, int(cw * scale)), max(1, int(ch * scale))),
                interpolation=cv2.INTER_CUBIC if scale > 1 else cv2.INTER_AREA,
            )
        elif cw < 160:
            scale_up = 160.0 / max(cw, 1)
            image = cv2.resize(
                image,
                (max(1, int(cw * scale_up)), max(1, int(ch * scale_up))),
                interpolation=cv2.INTER_LANCZOS4,
            )
        ok, buf = cv2.imencode(".jpg", image, [int(cv2.IMWRITE_JPEG_QUALITY), int(quality)])
        if not ok:
            return None
        return buf.tobytes()
    except Exception:
        return None


def enhance_monitor_frame(bgr):
    """Light contrast + unsharp for ~20 m lot viewing on the AI overlay stream."""
    if bgr is None or getattr(bgr, "size", 0) == 0:
        return bgr
    try:
        lab = cv2.cvtColor(bgr, cv2.COLOR_BGR2LAB)
        l_ch, a_ch, b_ch = cv2.split(lab)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        l_ch = clahe.apply(l_ch)
        merged = cv2.merge((l_ch, a_ch, b_ch))
        out = cv2.cvtColor(merged, cv2.COLOR_LAB2BGR)
        blur = cv2.GaussianBlur(out, (0, 0), 0.7)
        return cv2.addWeighted(out, 1.28, blur, -0.28, 0)
    except Exception:
        return bgr


def scan_visible_region(
    frame,
    view: dict[str, Any] | None,
    ocr,
    tracks: Optional[dict] = None,
) -> dict[str, Any]:
    """Crop the zoomed view, OCR the plate, return JSON (caller must not persist)."""
    if frame is None or getattr(frame, "size", 0) == 0:
        return {
            "ok": False,
            "saved": False,
            "message": "No camera frame.",
            "plate": None,
            "plate_status": "empty",
        }

    xyxy = view_to_xyxy(frame.shape, view)
    x1, y1, x2, y2 = xyxy
    region = frame[y1:y2, x1:x2]
    if region is None or region.size == 0:
        return {
            "ok": False,
            "saved": False,
            "message": "Zoomed region is empty.",
            "plate": None,
            "plate_status": "empty",
        }

    from plate_detector import detect_plate_crop
    from plate_ocr import PlateOCR

    candidates: list[np.ndarray] = []
    plate_region = detect_plate_crop(region)
    if plate_region is not None:
        candidates.append(plate_region)
    candidates.append(region)

    if tracks:
        for mem in list(tracks.values()):
            box = getattr(mem, "last_ocr_xyxy", None) or getattr(mem, "last_xyxy", None)
            if not box or not boxes_intersect(box, xyxy):
                continue
            cls_id = getattr(mem, "cls_id", None)
            crop = PlateOCR.crop_plate_region(frame, box, cls_id=cls_id)
            if crop is not None:
                candidates.append(crop)
            stored = getattr(mem, "last_plate_crop", None)
            if stored is not None and getattr(stored, "size", 0) > 0:
                candidates.append(stored)

    best_plate = None
    best_status = "unreadable"
    best_conf = 0.0
    best_crop = candidates[0]
    ocr_enabled = bool(ocr and getattr(ocr, "enabled", False))

    if ocr_enabled:
        seen = 0
        for crop in candidates:
            if crop is None or getattr(crop, "size", 0) == 0:
                continue
            seen += 1
            if seen > 8:
                break
            try:
                read = ocr.read_crop(crop, cls_id=None)
            except Exception:
                continue
            conf = float(getattr(read, "confidence", 0.0) or 0.0)
            if conf > best_conf:
                best_conf = conf
                best_plate = getattr(read, "plate", None)
                best_status = getattr(read, "status", "unreadable") or "unreadable"
                best_crop = crop
            if best_plate and conf >= 0.65:
                break
    else:
        best_status = "empty"
        best_crop = plate_region if plate_region is not None else region

    crop_b64 = encode_crop_jpeg(best_crop, quality=92, max_side=720)
    result = {
        "ok": True,
        "saved": False,
        "plate": best_plate,
        "plate_text": best_plate,
        "ocr_text": best_plate,
        "plate_status": best_status if best_plate or best_status == "unreadable" else "empty",
        "ocr_confidence": round(best_conf, 3),
        "crop_jpeg_base64": crop_b64,
        "message": None,
    }
    if not ocr_enabled:
        result["message"] = "OCR is off. Enable AI_PARKING_OCR_ENABLED=1 and restart the AI service."
        result["ok"] = False
    elif not best_plate:
        result["message"] = "Plate Unreadable"
    return result
