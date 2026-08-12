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
PLATE_YOLO_CONF = float(os.getenv("AI_PARKING_PLATE_YOLO_CONF", "0.25"))

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


def detect_plate_crop(vehicle_crop) -> Optional[np.ndarray]:
    """Return a tighter plate crop, or None to keep the vehicle-band crop."""
    if vehicle_crop is None or getattr(vehicle_crop, "size", 0) == 0:
        return None
    ch, cw = vehicle_crop.shape[:2]
    if cw < 24 or ch < 12:
        return None

    yolo_crop = _detect_yolo(vehicle_crop)
    if yolo_crop is not None:
        return yolo_crop

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
            x1 = max(0, x1 - 4)
            y1 = max(0, y1 - 4)
            x2 = min(w, x2 + 4)
            y2 = min(h, y2 + 4)
            if x2 - x1 < 16 or y2 - y1 < 8:
                continue
            best_conf = conf
            best = crop[y1:y2, x1:x2]
    if best is None or best.size == 0:
        return None
    return best.copy()


def _detect_opencv(crop):
    """Find a bright, wide rectangle typical of PH plates."""
    h, w = crop.shape[:2]
    gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
    gray = cv2.bilateralFilter(gray, 9, 75, 75)
    try:
        gray = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8)).apply(gray)
    except Exception:
        pass

    hsv = cv2.cvtColor(crop, cv2.COLOR_BGR2HSV)
    white = cv2.inRange(hsv, (0, 0, 150), (180, 80, 255))
    edges = cv2.Canny(gray, 40, 160)
    combo = cv2.bitwise_or(white, edges)
    combo = cv2.dilate(combo, cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3)), iterations=1)

    contours, _ = cv2.findContours(combo, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    best = None
    best_score = 0.0
    min_area = max(80, int(w * h * 0.015))
    max_area = int(w * h * 0.55)

    for cnt in contours:
        area = cv2.contourArea(cnt)
        if area < min_area or area > max_area:
            continue
        x, y, bw, bh = cv2.boundingRect(cnt)
        if bh < 8 or bw < 20:
            continue
        ratio = bw / max(bh, 1)
        if ratio < 1.4 or ratio > 8.0:
            continue
        # Prefer mid/upper band of the vehicle crop (plates, not tires).
        cy = (y + bh / 2) / max(h, 1)
        if cy > 0.88:
            continue
        score = area * (1.0 if 2.0 <= ratio <= 5.5 else 0.7) * (1.15 if 0.15 <= cy <= 0.7 else 0.85)
        if score > best_score:
            best_score = score
            pad_x = max(2, int(bw * 0.06))
            pad_y = max(2, int(bh * 0.12))
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
