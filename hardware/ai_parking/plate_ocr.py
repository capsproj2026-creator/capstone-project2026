"""Optional plate OCR (EasyOCR). Degrades gracefully if unavailable."""

from __future__ import annotations

import os
import re
import threading
from typing import Optional

import cv2
import numpy as np

OCR_ENABLED = os.getenv("AI_PARKING_OCR_ENABLED", "0") == "1"
OCR_EVERY_SEC = float(os.getenv("AI_PARKING_OCR_EVERY_SEC", "8"))
OCR_MIN_CONF = float(os.getenv("AI_PARKING_OCR_MIN_CONF", "0.35"))

_PLATE_RE = re.compile(r"[A-Z0-9]{5,10}")


class PlateOCR:
    def __init__(self):
        self._reader = None
        self._lock = threading.Lock()
        self._init_attempted = False
        self.enabled = OCR_ENABLED

    def _ensure_reader(self):
        if not self.enabled or self._init_attempted:
            return
        self._init_attempted = True
        try:
            import easyocr

            print("Loading EasyOCR (first run may download models)...")
            self._reader = easyocr.Reader(["en"], gpu=False, verbose=False)
            print("EasyOCR ready.")
        except Exception as e:
            print(f"OCR disabled — EasyOCR unavailable: {e}")
            self._reader = None
            self.enabled = False

    def read_plate(self, frame, xyxy: tuple[int, int, int, int]) -> Optional[str]:
        if not self.enabled:
            return None
        self._ensure_reader()
        if self._reader is None:
            return None

        h, w = frame.shape[:2]
        x1, y1, x2, y2 = xyxy
        # Focus on lower third of vehicle box (typical plate region)
        box_h = max(1, y2 - y1)
        y1p = y1 + int(box_h * 0.55)
        x1 = max(0, x1)
        y1p = max(0, y1p)
        x2 = min(w, x2)
        y2 = min(h, y2)
        if x2 - x1 < 20 or y2 - y1p < 10:
            return None

        crop = frame[y1p:y2, x1:x2]
        if crop.size == 0:
            return None

        # Upscale small crops
        ch, cw = crop.shape[:2]
        if cw < 160:
            scale = 160 / cw
            crop = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        gray = cv2.bilateralFilter(gray, 5, 50, 50)

        try:
            with self._lock:
                results = self._reader.readtext(gray)
        except Exception as e:
            print(f"OCR read error: {e}")
            return None

        best = None
        best_score = 0.0
        for _bbox, text, conf in results:
            if conf < OCR_MIN_CONF:
                continue
            cleaned = re.sub(r"[^A-Za-z0-9]", "", str(text)).upper()
            if not _PLATE_RE.fullmatch(cleaned):
                # allow near-misses with length 4-9
                if not (4 <= len(cleaned) <= 9):
                    continue
            if conf > best_score:
                best_score = conf
                best = cleaned
        return best
