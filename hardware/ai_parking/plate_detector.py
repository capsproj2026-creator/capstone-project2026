"""Software plate localization inside a vehicle crop.

Uses an optional YOLO plate model (`models/plate.pt`) when present.
Otherwise finds a high-contrast rectangular plate region with OpenCV.
No extra camera hardware required.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Optional

import cv2
import numpy as np

BASE = Path(__file__).resolve().parent
PLATE_MODEL_PATH = Path(os.getenv("AI_PARKING_PLATE_MODEL", str(BASE / "models" / "plate.pt")))
PLATE_YOLO_CONF = float(os.getenv("AI_PARKING_PLATE_YOLO_CONF", "0.22"))

# COCO motorcycle class — yellow LTO plates are common.
MOTORCYCLE_CLS_ID = 3

_yolo = None
_yolo_tried = False


def _load_plate_yolo():
    global _yolo, _yolo_tried
    if _yolo_tried:
        return _yolo
    _yolo_tried = True
    if not PLATE_MODEL_PATH.is_file():
        return None
    try:
        from ultralytics import YOLO

        _yolo = YOLO(str(PLATE_MODEL_PATH))
        print(f"Plate detector YOLO loaded: {PLATE_MODEL_PATH}")
    except Exception as e:
        print(f"Plate YOLO unavailable ({e}) — using OpenCV locator.")
        _yolo = None
    return _yolo


def detect_plate_crop(vehicle_crop, cls_id: int | None = None) -> Optional[np.ndarray]:
    """Return a tighter plate crop, or None to keep the vehicle-band crop."""
    if vehicle_crop is None or getattr(vehicle_crop, "size", 0) == 0:
        return None
    ch, cw = vehicle_crop.shape[:2]
    if cw < 24 or ch < 12:
        return None

    yolo_crop = _detect_yolo(vehicle_crop)
    if yolo_crop is not None:
        return yolo_crop

    yellow = _detect_yellow_plate(vehicle_crop) if cls_id == MOTORCYCLE_CLS_ID else None
    if yellow is not None:
        return yellow

    return _detect_opencv(vehicle_crop)


def _detect_yolo(crop):
    model = _load_plate_yolo()
    if model is None:
        return None
    try:
        results = model.predict(crop, conf=PLATE_YOLO_CONF, verbose=False)
    except Exception:
        return None

    best = None
    best_conf = 0.0
    h, w = crop.shape[:2]
    for r in results:
        if r.boxes is None:
            continue
        for b in r.boxes:
            conf = float(b.conf.item())
            if conf <= best_conf:
                continue
            x1, y1, x2, y2 = (int(v) for v in b.xyxy[0].tolist())
            x1 = max(0, x1 - 6)
            y1 = max(0, y1 - 4)
            x2 = min(w, x2 + 6)
            y2 = min(h, y2 + 4)
            if x2 - x1 < 16 or y2 - y1 < 8:
                continue
            best_conf = conf
            best = crop[y1:y2, x1:x2]
    if best is None or best.size == 0:
        return None
    return best.copy()


def _detect_yellow_plate(crop) -> Optional[np.ndarray]:
    """Locate yellow PH motorcycle plate regions."""
    h, w = crop.shape[:2]
    hsv = cv2.cvtColor(crop, cv2.COLOR_BGR2HSV)
    # Yellow plate body with dark embossed text.
    mask = cv2.inRange(hsv, (12, 60, 80), (40, 255, 255))
    mask = cv2.morphologyEx(mask, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_RECT, (5, 3)))
    contours, _ = cv2.findContours(mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

    best = None
    best_score = 0.0
    min_area = max(60, int(w * h * 0.012))
    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < min_area:
            continue
        x, y, bw, bh = cv2.boundingRect(cnt)
        if bh < 8 or bw < 24:
            continue
        ratio = bw / max(bh, 1)
        if ratio < 1.5 or ratio > 7.5:
            continue
        cy = (y + bh / 2) / max(h, 1)
        score = area * (1.2 if 2.0 <= ratio <= 5.0 else 0.85) * (1.1 if 0.1 <= cy <= 0.75 else 0.8)
        if score > best_score:
            best_score = score
            pad_x = max(2, int(bw * 0.05))
            pad_y = max(2, int(bh * 0.10))
            x1 = max(0, x - pad_x)
            y1 = max(0, y - pad_y)
            x2 = min(w, x + bw + pad_x)
            y2 = min(h, y + bh + pad_y)
            best = crop[y1:y2, x1:x2]

    if best is None or best.size == 0 or best.shape[0] < 10 or best.shape[1] < 20:
        return None
    return best.copy()


def _detect_opencv(crop):
    """Find a bright, wide rectangle typical of PH plates."""
    h, w = crop.shape[:2]
    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    gray = cv2.bilateralFilter(gray, 9, 75, 75)
    try:
        gray = cv2.createCLAHE(clipLimit=2.5, tileGridSize=(8, 8)).apply(gray)
    except Exception:
        pass

    hsv = cv2.cvtColor(crop, cv2.COLOR_BGR2HSV)
    white = cv2.inRange(hsv, (0, 0, 140), (180, 90, 255))
    yellow = cv2.inRange(hsv, (12, 50, 70), (42, 255, 255))
    grad_x = cv2.Sobel(gray, cv2.CV_8U, 1, 0, ksize=3)
    edges = cv2.Canny(gray, 35, 140)
    combo = cv2.bitwise_or(white, edges)
    combo = cv2.bitwise_or(combo, yellow)
    combo = cv2.bitwise_or(combo, grad_x)
    combo = cv2.morphologyEx(combo, cv2.MORPH_CLOSE, cv2.getStructuringElement(cv2.MORPH_RECT, (5, 3)))

    contours, _ = cv2.findContours(combo, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    best = None
    best_score = 0.0
    min_area = max(70, int(w * h * 0.012))
    max_area = int(w * h * 0.58)

    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < min_area or area > max_area:
            continue
        x, y, bw, bh = cv2.boundingRect(cnt)
        if bh < 8 or bw < 20:
            continue
        ratio = bw / max(bh, 1)
        if ratio < 1.35 or ratio > 8.5:
            continue
        cy = (y + bh / 2) / max(h, 1)
        if cy > 0.90:
            continue
        score = area * (1.0 if 2.0 <= ratio <= 5.5 else 0.72) * (1.18 if 0.12 <= cy <= 0.72 else 0.82)
        if score > best_score:
            best_score = score
            pad_x = max(2, int(bw * 0.07))
            pad_y = max(2, int(bh * 0.14))
            x1 = max(0, x - pad_x)
            y1 = max(0, y - pad_y)
            x2 = min(w, x + bw + pad_x)
            y2 = min(h, y + bh + pad_y)
            best = crop[y1:y2, x1:x2]

    if best is None or best.size == 0:
        return None
    if best.shape[0] < 10 or best.shape[1] < 20:
        return None
    return best.copy()
