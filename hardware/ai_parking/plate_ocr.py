"""Optional plate OCR (EasyOCR). Returns confidence + readable/unreadable status."""

from __future__ import annotations

import os
import re
import threading
from dataclasses import dataclass
from typing import Optional

import cv2
import numpy as np

OCR_ENABLED = os.getenv("AI_PARKING_OCR_ENABLED", "0") == "1"
OCR_EVERY_SEC = float(os.getenv("AI_PARKING_OCR_EVERY_SEC", "6"))
OCR_MIN_CONF = float(os.getenv("AI_PARKING_OCR_MIN_CONF", "0.45"))
# Below this, treat a read as noise even if something was decoded.
OCR_UNREADABLE_BELOW = float(os.getenv("AI_PARKING_OCR_UNREADABLE_BELOW", "0.28"))

_PLATE_RE = re.compile(r"[A-Z0-9]{5,10}")
# Common PH private plate shapes after strip: ABC1234 / AB1234
_PH_PLATE_RE = re.compile(r"^[A-Z]{2,3}\d{3,4}$")


@dataclass
class PlateRead:
    """OCR outcome for one vehicle crop."""

    plate: Optional[str] = None
    confidence: float = 0.0
    # ok | unreadable | empty
    status: str = "empty"


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

    def read_plate(self, frame, xyxy: tuple[int, int, int, int]) -> PlateRead:
        """
        Attempt plate OCR on the lower portion of a vehicle box.

        Returns PlateRead:
          - status=ok + plate text when confident
          - status=unreadable when OCR ran but confidence/pattern failed
          - status=empty when crop too small / OCR disabled
        """
        if not self.enabled:
            return PlateRead(status="empty")
        self._ensure_reader()
        if self._reader is None:
            return PlateRead(status="empty")

        h, w = frame.shape[:2]
        x1, y1, x2, y2 = xyxy
        box_h = max(1, y2 - y1)
        # Prefer lower ~45% of the vehicle (typical plate mount).
        y1p = y1 + int(box_h * 0.55)
        x1 = max(0, x1)
        y1p = max(0, y1p)
        x2 = min(w, x2)
        y2 = min(h, y2)
        if x2 - x1 < 24 or y2 - y1p < 12:
            return PlateRead(status="empty")

        crop = frame[y1p:y2, x1:x2]
        if crop.size == 0:
            return PlateRead(status="empty")

        ch, cw = crop.shape[:2]
        if cw < 180:
            scale = 180 / max(cw, 1)
            crop = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)

        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        gray = cv2.bilateralFilter(gray, 5, 50, 50)
        try:
            clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
            gray = clahe.apply(gray)
        except Exception:
            pass

        try:
            with self._lock:
                results = self._reader.readtext(gray)
        except Exception as e:
            print(f"OCR read error: {e}")
            return PlateRead(status="unreadable", confidence=0.0)

        if not results:
            return PlateRead(status="unreadable", confidence=0.0)

        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0
        for _bbox, text, conf in results:
            conf_f = float(conf)
            best_any_score = max(best_any_score, conf_f)
            cleaned = re.sub(r"[^A-Za-z0-9]", "", str(text)).upper()
            if len(cleaned) < 4 or len(cleaned) > 10:
                continue
            # Prefer PH-like plates; still allow other alphanumerics in range.
            if not (_PH_PLATE_RE.fullmatch(cleaned) or _PLATE_RE.fullmatch(cleaned) or (4 <= len(cleaned) <= 9)):
                continue
            score = conf_f
            if _PH_PLATE_RE.fullmatch(cleaned):
                score += 0.05
            if conf_f < OCR_MIN_CONF and not _PH_PLATE_RE.fullmatch(cleaned):
                continue
            if score > best_score:
                best_score = score
                best = cleaned

        if best and best_score >= OCR_MIN_CONF:
            return PlateRead(plate=best, confidence=round(min(best_score, 1.0), 3), status="ok")

        # Something was seen but not trustworthy — never invent a plate string.
        if best_any_score >= OCR_UNREADABLE_BELOW or best is not None:
            return PlateRead(
                plate=None,
                confidence=round(best_any_score if best is None else min(best_score, 1.0), 3),
                status="unreadable",
            )
        return PlateRead(status="unreadable", confidence=round(best_any_score, 3))
