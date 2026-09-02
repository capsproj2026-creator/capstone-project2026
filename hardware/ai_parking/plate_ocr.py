"""Optional plate OCR (EasyOCR). Async queue keeps YOLO inference non-blocking."""

from __future__ import annotations

import os
import queue
import threading
import time
from dataclasses import dataclass
from typing import TYPE_CHECKING, Optional

import cv2
import numpy as np

from plate_text import (
    best_from_results,
    is_known_ph_format,
)

if TYPE_CHECKING:
    from parking_rules import ParkingIntelligence

OCR_ENABLED = os.getenv("AI_PARKING_OCR_ENABLED", "0") == "1"
OCR_EVERY_SEC = float(os.getenv("AI_PARKING_OCR_EVERY_SEC", "2.0"))
OCR_MIN_CONF = float(os.getenv("AI_PARKING_OCR_MIN_CONF", "0.26"))
OCR_UNREADABLE_BELOW = float(os.getenv("AI_PARKING_OCR_UNREADABLE_BELOW", "0.18"))
OCR_QUEUE_SIZE = int(os.getenv("AI_PARKING_OCR_QUEUE_SIZE", "8"))
OCR_UPSCALE_MIN_WIDTH = int(os.getenv("AI_PARKING_OCR_UPSCALE_MIN_WIDTH", "720"))
OCR_UPSCALE_FACTOR = float(os.getenv("AI_PARKING_OCR_UPSCALE_FACTOR", "8"))
OCR_GPU = os.getenv("AI_PARKING_OCR_GPU", "0") == "1"
OCR_HIGH_CONF_LOCK = float(os.getenv("AI_PARKING_OCR_HIGH_CONF_LOCK", "0.65"))
OCR_ROTATION_ANGLES = [
    float(v.strip())
    for v in os.getenv("AI_PARKING_OCR_ROTATION_ANGLES", "-6,-3,3,6").split(",")
    if v.strip()
]

# COCO class id for motorcycle (tighter rear-plate crop).
MOTORCYCLE_CLS_ID = 3

_OCR_ALLOWLIST = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-"


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

            print(f"Loading EasyOCR (gpu={OCR_GPU}, first run may download models)...")
            self._reader = easyocr.Reader(["en"], gpu=OCR_GPU, verbose=False)
            print("EasyOCR ready.")
        except Exception as e:
            print(f"OCR disabled — EasyOCR unavailable: {e}")
            self._reader = None
            self.enabled = False

    @staticmethod
    def crop_plate_region(
        frame,
        xyxy: tuple[int, int, int, int],
        cls_id: int | None = None,
    ):
        """Return vehicle crop suitable for OCR, or None if too small."""
        h, w = frame.shape[:2]
        x1, y1, x2, y2 = xyxy
        box_h = max(1, y2 - y1)
        box_w = max(1, x2 - x1)

        # Distant vehicles (~20 m): keep most of the box so plate-YOLO can still find the plate.
        distant = box_h < 110 or box_w < 140
        if cls_id == MOTORCYCLE_CLS_ID:
            # Motorcycle plates sit mid-rear; bottom crop often catches tire/mudguard only.
            y1p = y1 + int(box_h * (0.08 if distant else 0.15))
            y2p = y1 + int(box_h * (0.88 if distant else 0.76))
            x_pad = int(box_w * (0.01 if distant else 0.03))
        elif distant:
            y1p = y1 + int(box_h * 0.18)
            y2p = y2 - int(box_h * 0.02)
            x_pad = int(box_w * 0.02)
        else:
            y1p = y1 + int(box_h * 0.48)
            y2p = y2
            x_pad = int(box_w * 0.05)
        x1 = max(0, x1 + x_pad)
        y1p = max(0, y1p)
        x2 = min(w, x2 - x_pad)
        y2p = min(h, y2p)

        if x2 - x1 < 20 or y2p - y1p < 10:
            return None
        crop = frame[y1p:y2p, x1:x2]
        if crop is None or crop.size == 0:
            return None
        try:
            from plate_detector import detect_plate_crop

            tighter = detect_plate_crop(crop, cls_id=cls_id)
            if tighter is not None:
                return tighter
        except Exception:
            pass
        return crop.copy()

    @staticmethod
    def _upscale_crop(crop):
        _ch, cw = crop.shape[:2]
        factor = OCR_UPSCALE_FACTOR
        min_w = OCR_UPSCALE_MIN_WIDTH
        if cw < 64:
            factor = max(factor, 10.0)
            min_w = max(min_w, 800)
        target = max(min_w, int(cw * factor))
        if cw >= target:
            return crop
        scale = target / max(cw, 1)
        return cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_LANCZOS4)

    @staticmethod
    def _sharpen(gray):
        kernel = np.array([[0, -1, 0], [-1, 5, -1], [0, -1, 0]], dtype=np.float32)
        return cv2.filter2D(gray, -1, kernel)

    @staticmethod
    def _gamma_correct(img, gamma: float = 1.25):
        if gamma <= 0:
            return img
        inv = 1.0 / gamma
        table = np.array([((i / 255.0) ** inv) * 255 for i in range(256)]).astype("uint8")
        return cv2.LUT(img, table)

    @staticmethod
    def _unsharp_mask(gray, amount: float = 1.2):
        blurred = cv2.GaussianBlur(gray, (0, 0), 1.2)
        return cv2.addWeighted(gray, 1.0 + amount, blurred, -amount, 0)

    @staticmethod
    def _rotate_image(img, angle: float):
        if abs(angle) < 0.01:
            return img
        h, w = img.shape[:2]
        center = (w / 2.0, h / 2.0)
        matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
        return cv2.warpAffine(
            img,
            matrix,
            (w, h),
            flags=cv2.INTER_CUBIC,
            borderMode=cv2.BORDER_REPLICATE,
        )

    @staticmethod
    def _ocr_variants(crop, quick: bool = False):
        crop = PlateOCR._upscale_crop(crop)
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        gray = cv2.bilateralFilter(gray, 5, 50, 50)
        clahe_img = gray
        try:
            clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
            clahe_img = clahe.apply(gray)
        except Exception:
            pass

        gamma = PlateOCR._gamma_correct(clahe_img, 1.2)
        unsharp = PlateOCR._unsharp_mask(clahe_img)
        variants = [
            ("color", crop),
            ("gray", gray),
            ("clahe", clahe_img),
            ("gamma", gamma),
            ("unsharp", unsharp),
        ]
        if not quick:
            sharpen = PlateOCR._sharpen(clahe_img)
            variants.append(("sharp", sharpen))
            try:
                kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (3, 3))
                tophat = cv2.morphologyEx(clahe_img, cv2.MORPH_TOPHAT, kernel)
                variants.append(("tophat", tophat))
            except Exception:
                pass
            try:
                _, otsu = cv2.threshold(clahe_img, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
                variants.append(("otsu", otsu))
                variants.append(("inv_otsu", cv2.bitwise_not(otsu)))
            except Exception:
                pass
            try:
                adaptive = cv2.adaptiveThreshold(
                    clahe_img,
                    255,
                    cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                    cv2.THRESH_BINARY,
                    31,
                    8,
                )
                variants.append(("adaptive", adaptive))
            except Exception:
                pass
            for angle in OCR_ROTATION_ANGLES:
                variants.append((f"rot_{angle}", PlateOCR._rotate_image(clahe_img, angle)))
        return variants

    def _scan_variants(self, crop, quick: bool = False) -> tuple[Optional[str], float, float]:
        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0
        for _label, img in self._ocr_variants(crop, quick=quick):
            try:
                results = self._readtext(img)
            except Exception as e:
                print(f"OCR read error: {e}")
                continue
            if not results:
                continue
            b, s, any_s = best_from_results(results, OCR_MIN_CONF)
            best_any_score = max(best_any_score, any_s)
            if s > best_score:
                best_score = s
                best = b
        return best, best_score, best_any_score

    def _readtext(self, img):
        mag = 2.0 if min(img.shape[:2]) < 80 else 1.6
        with self._lock:
            return self._reader.readtext(
                img,
                allowlist=_OCR_ALLOWLIST,
                paragraph=False,
                min_size=4,
                contrast_ths=0.05,
                adjust_contrast=0.7,
                text_threshold=0.42,
                low_text=0.18,
                mag_ratio=mag,
                slope_ths=0.15,
            )

    def read_plate(
        self,
        frame,
        xyxy: tuple[int, int, int, int],
        cls_id: int | None = None,
    ) -> PlateRead:
        """Attempt plate OCR on the rear portion of a vehicle box."""
        if not self.enabled:
            return PlateRead(status="empty")
        crop = self.crop_plate_region(frame, xyxy, cls_id=cls_id)
        if crop is None:
            return PlateRead(status="empty")
        return self.read_crop(crop, cls_id=cls_id)

    @staticmethod
    def _sub_crops(crop, cls_id: int | None = None):
        """Try full frame plus tighter bands where plates usually appear."""
        ch, cw = crop.shape[:2]
        out = [crop]
        if cls_id == MOTORCYCLE_CLS_ID and ch >= 24:
            mid_y1 = max(0, int(ch * 0.10))
            mid_y2 = min(ch, int(ch * 0.70))
            mid = crop[mid_y1:mid_y2, :]
            if mid.size > 0 and mid.shape[0] >= 12:
                out.insert(0, mid)
        elif ch >= 24:
            top = crop[0 : max(12, int(ch * 0.50)), :]
            if top.size > 0 and top.shape[0] >= 12:
                out.insert(0, top)
        if ch >= 48:
            mid_y1 = max(0, int(ch * 0.16))
            mid_y2 = min(ch, int(ch * 0.64))
            mid = crop[mid_y1:mid_y2, :]
            if mid.size > 0 and mid.shape[0] >= 12:
                out.append(mid)
        return out

    def read_crop(self, crop, cls_id: int | None = None) -> PlateRead:
        if not self.enabled:
            return PlateRead(status="empty")
        self._ensure_reader()
        if self._reader is None:
            return PlateRead(status="empty")
        if crop is None or getattr(crop, "size", 0) == 0:
            return PlateRead(status="empty")

        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0

        try:
            subs = self._sub_crops(crop, cls_id=cls_id)
            quick_crop = subs[0]
            best, best_score, best_any_score = self._scan_variants(quick_crop, quick=True)
            if best and best_score >= OCR_HIGH_CONF_LOCK and is_known_ph_format(best):
                return PlateRead(
                    plate=best,
                    confidence=round(min(best_score, 1.0), 3),
                    status="ok",
                )

            for sub in subs:
                b, s, any_s = self._scan_variants(sub, quick=False)
                best_any_score = max(best_any_score, any_s)
                if s > best_score:
                    best_score = s
                    best = b
        except Exception as e:
            print(f"OCR pipeline error: {e}")
            return PlateRead(status="unreadable", confidence=0.0)

        if best and best_score >= OCR_MIN_CONF:
            return PlateRead(plate=best, confidence=round(min(best_score, 1.0), 3), status="ok")

        if best_any_score >= OCR_UNREADABLE_BELOW or best is not None:
            return PlateRead(
                plate=None,
                confidence=round(best_any_score if best is None else min(best_score, 1.0), 3),
                status="unreadable",
            )
        return PlateRead(status="unreadable", confidence=round(best_any_score, 3))


class AsyncPlateQueue:
    """Background OCR so YOLO / preview keep running in real time."""

    def __init__(self, ocr: PlateOCR, maxsize: int = OCR_QUEUE_SIZE):
        self.ocr = ocr
        self._q: queue.Queue = queue.Queue(maxsize=max(1, maxsize))
        self._inflight: set[tuple] = set()
        self._lock = threading.Lock()
        self._thread = threading.Thread(target=self._loop, daemon=True, name="plate-ocr-async")
        self._thread.start()

    def submit(
        self,
        camera_id: str,
        track_id: int,
        frame,
        xyxy: tuple[int, int, int, int],
        intelligence: "ParkingIntelligence",
        every_sec: float = OCR_EVERY_SEC,
        cls_id: int | None = None,
    ) -> None:
        if not self.ocr.enabled or track_id is None:
            return
        mem = intelligence.tracks.get(int(track_id))
        if mem and mem.plate_status == "ok" and mem.plate:
            return
        now = time.time()
        if mem and (now - mem.last_ocr_at) < every_sec:
            return

        key = (camera_id, int(track_id))
        with self._lock:
            if key in self._inflight:
                return
            self._inflight.add(key)

        crop = PlateOCR.crop_plate_region(frame, xyxy, cls_id=cls_id)
        if crop is None:
            with self._lock:
                self._inflight.discard(key)
            return

        if mem is not None:
            mem.last_ocr_at = now
            mem.cls_id = cls_id
            mem.last_plate_crop = crop

        try:
            self._q.put_nowait((key, crop, intelligence, int(track_id), cls_id))
        except queue.Full:
            with self._lock:
                self._inflight.discard(key)

    def _loop(self):
        while True:
            key, crop, intelligence, track_id, cls_id = self._q.get()
            try:
                read = self.ocr.read_crop(crop, cls_id=cls_id)
                mem = intelligence.tracks.get(track_id)
                if mem is not None:
                    mem.last_plate_crop = crop
                    mem.apply_ocr_vote(read.plate, read.status, read.confidence)
                    if mem.needs_owner_lookup():
                        from plate_owner_lookup import lookup_plate_async

                        lookup_plate_async(mem)
            except Exception as e:
                print(f"Async OCR error: {e}")
            finally:
                with self._lock:
                    self._inflight.discard(key)
