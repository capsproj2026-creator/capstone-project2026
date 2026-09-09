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
OCR_EVERY_SEC = float(os.getenv("AI_PARKING_OCR_EVERY_SEC", "1.0"))
OCR_MIN_CONF = float(os.getenv("AI_PARKING_OCR_MIN_CONF", "0.26"))
OCR_UNREADABLE_BELOW = float(os.getenv("AI_PARKING_OCR_UNREADABLE_BELOW", "0.18"))
OCR_QUEUE_SIZE = int(os.getenv("AI_PARKING_OCR_QUEUE_SIZE", "8"))
OCR_UPSCALE_MIN_WIDTH = int(os.getenv("AI_PARKING_OCR_UPSCALE_MIN_WIDTH", "640"))
OCR_UPSCALE_FACTOR = float(os.getenv("AI_PARKING_OCR_UPSCALE_FACTOR", "6"))
OCR_GPU = os.getenv("AI_PARKING_OCR_GPU", "0") == "1"
OCR_HIGH_CONF_LOCK = float(os.getenv("AI_PARKING_OCR_HIGH_CONF_LOCK", "0.55"))
OCR_PLATE_ONLY = os.getenv("AI_PARKING_OCR_PLATE_ONLY", "1").strip().lower() in (
    "1",
    "true",
    "yes",
    "on",
)
OCR_MIN_PLATE_CROP_W = int(os.getenv("AI_PARKING_OCR_MIN_PLATE_CROP_W", "28"))
OCR_MIN_PLATE_CROP_H = int(os.getenv("AI_PARKING_OCR_MIN_PLATE_CROP_H", "12"))
# Fast mode (default on CPU): fewer crops/variants so EasyOCR finishes and YOLO stays live.
_fast_env = os.getenv("AI_PARKING_OCR_FAST", "").strip().lower()
if _fast_env in ("1", "true", "yes", "on"):
    OCR_FAST = True
elif _fast_env in ("0", "false", "no", "off"):
    OCR_FAST = False
else:
    OCR_FAST = not OCR_GPU
# Blocking sync OCR inside the infer loop freezes CPU boxes — off by default.
OCR_SYNC_ENABLED = os.getenv("AI_PARKING_OCR_SYNC", "0") == "1"
OCR_SYNC_EVERY_SEC = float(os.getenv("AI_PARKING_OCR_SYNC_EVERY_SEC", "4.0"))
# Mild angles help front plates under carports; empty disables rotations.
_default_angles = "" if OCR_FAST else "-8,-4,4,8"
OCR_ROTATION_ANGLES = [
    float(v.strip())
    for v in os.getenv("AI_PARKING_OCR_ROTATION_ANGLES", _default_angles).split(",")
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
            y1p = y1 + int(box_h * 0.12)
            y2p = y2 - int(box_h * 0.02)
            x_pad = int(box_w * 0.02)
        else:
            # Front-facing lots (plate on bumper) + rear plates: keep mid→bottom.
            y1p = y1 + int(box_h * 0.20)
            y2p = y2
            x_pad = int(box_w * 0.03)
        x1 = max(0, x1 + x_pad)
        y1p = max(0, y1p)
        x2 = min(w, x2 - x_pad)
        y2p = min(h, y2p)

        if x2 - x1 < 20 or y2p - y1p < 10:
            return None
        crop = frame[y1p:y2p, x1:x2]
        if crop is None or crop.size == 0:
            return None
        # Keep the bumper/vehicle band. Optional tighter crops are tried inside read_crop
        # so a bad OpenCV "plate" ROI cannot permanently hide the real plate.
        return crop.copy()

    @staticmethod
    def _upscale_crop(crop, *, fast: bool = False):
        _ch, cw = crop.shape[:2]
        factor = OCR_UPSCALE_FACTOR
        min_w = OCR_UPSCALE_MIN_WIDTH
        if fast:
            # Large LANCZOS upscales dominate CPU time before EasyOCR even runs.
            factor = min(factor, 3.0)
            min_w = min(min_w, 420)
        elif cw < 64:
            factor = max(factor, 10.0)
            min_w = max(min_w, 800)
        target = max(min_w, int(cw * factor))
        if cw >= target:
            return crop
        scale = target / max(cw, 1)
        interp = cv2.INTER_LINEAR if fast else cv2.INTER_LANCZOS4
        return cv2.resize(crop, None, fx=scale, fy=scale, interpolation=interp)

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
    def _ocr_variants(crop, quick: bool = False, *, fast: bool = False):
        crop = PlateOCR._upscale_crop(crop, fast=fast or quick)
        gray = cv2.cvtColor(crop, cv2.COLOR_BGR2GRAY)
        if not fast:
            gray = cv2.bilateralFilter(gray, 5, 50, 50)
        clahe_img = gray
        try:
            clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
            clahe_img = clahe.apply(gray)
        except Exception:
            pass

        if quick or fast:
            # 2–3 EasyOCR calls max — enough for most clear PH plates on CPU.
            bright = PlateOCR._gamma_correct(clahe_img, 0.72)
            return [
                ("clahe", clahe_img),
                ("bright", bright),
                ("color", crop),
            ]

        gamma = PlateOCR._gamma_correct(clahe_img, 1.2)
        bright = PlateOCR._gamma_correct(clahe_img, 0.72)  # lift dark/shaded plates
        unsharp = PlateOCR._unsharp_mask(clahe_img)
        variants = [
            ("color", crop),
            ("gray", gray),
            ("clahe", clahe_img),
            ("gamma", gamma),
            ("bright", bright),
            ("unsharp", unsharp),
        ]
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

    def _scan_variants(
        self,
        crop,
        quick: bool = False,
        *,
        fast: bool = False,
    ) -> tuple[Optional[str], float, float]:
        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0
        # Accept a decent PH-format hit early so we do not burn the whole variant list.
        early_lock = max(OCR_MIN_CONF, OCR_HIGH_CONF_LOCK - (0.20 if fast or quick else 0.0))
        for _label, img in self._ocr_variants(crop, quick=quick, fast=fast):
            try:
                results = self._readtext(img, fast=fast or quick)
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
            if best and is_known_ph_format(best) and best_score >= early_lock:
                break
            if best and best_score >= OCR_HIGH_CONF_LOCK and is_known_ph_format(best):
                break
        return best, best_score, best_any_score

    def _readtext(self, img, *, fast: bool = False):
        short = min(img.shape[:2])
        if fast:
            mag = 1.4 if short < 80 else 1.2
        else:
            mag = 2.0 if short < 80 else 1.6
        with self._lock:
            return self._reader.readtext(
                img,
                allowlist=_OCR_ALLOWLIST,
                paragraph=False,
                min_size=4 if not fast else 6,
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
        *,
        fast: bool | None = None,
    ) -> PlateRead:
        """Attempt plate OCR on the rear portion of a vehicle box."""
        if not self.enabled:
            return PlateRead(status="empty")
        crop = self.crop_plate_region(frame, xyxy, cls_id=cls_id)
        if crop is None:
            return PlateRead(status="empty")
        return self.read_crop(crop, cls_id=cls_id, fast=fast)

    @staticmethod
    def _sub_crops(crop, cls_id: int | None = None, *, fast: bool = False, plate_only: bool | None = None):
        """Try plate-YOLO crop first; optionally skip large bumper bands."""
        ch, cw = crop.shape[:2]
        use_plate_only = OCR_PLATE_ONLY if plate_only is None else bool(plate_only)
        plate_roi = None
        try:
            from plate_detector import detect_plate_crop

            tighter = detect_plate_crop(crop, cls_id=cls_id)
            if tighter is not None and tighter.size > 0:
                th, tw = tighter.shape[:2]
                if tw >= OCR_MIN_PLATE_CROP_W and th >= OCR_MIN_PLATE_CROP_H:
                    plate_roi = tighter
        except Exception:
            plate_roi = None

        if plate_roi is not None:
            return [plate_roi]

        if use_plate_only:
            # No usable plate ROI — do not EasyOCR the whole bumper (slow + noisy).
            return []

        out = [crop]
        if fast:
            # One bumper/mid band only — keeps CPU EasyOCR under a few seconds.
            if cls_id == MOTORCYCLE_CLS_ID and ch >= 24:
                mid_y1 = max(0, int(ch * 0.10))
                mid_y2 = min(ch, int(ch * 0.70))
                mid = crop[mid_y1:mid_y2, :]
                if mid.size > 0 and mid.shape[0] >= 12:
                    out.insert(0, mid)
            elif ch >= 24:
                bottom = crop[max(0, int(ch * 0.35)) : ch, :]
                if bottom.size > 0 and bottom.shape[0] >= 12:
                    out.insert(0, bottom)
            # Prefer plate-YOLO / bumper first; drop the raw full crop if we have a tighter one.
            return out[:2]

        if cls_id == MOTORCYCLE_CLS_ID and ch >= 24:
            mid_y1 = max(0, int(ch * 0.10))
            mid_y2 = min(ch, int(ch * 0.70))
            mid = crop[mid_y1:mid_y2, :]
            if mid.size > 0 and mid.shape[0] >= 12:
                out.insert(0, mid)
        elif ch >= 24:
            # Bumper / grille band (front-facing cars and multicabs).
            bottom = crop[max(0, int(ch * 0.35)) : ch, :]
            if bottom.size > 0 and bottom.shape[0] >= 12:
                out.insert(0, bottom)
            top = crop[0 : max(12, int(ch * 0.50)), :]
            if top.size > 0 and top.shape[0] >= 12:
                out.append(top)
        if ch >= 48:
            mid_y1 = max(0, int(ch * 0.16))
            mid_y2 = min(ch, int(ch * 0.64))
            mid = crop[mid_y1:mid_y2, :]
            if mid.size > 0 and mid.shape[0] >= 12:
                out.append(mid)
        return out

    def read_crop(
        self,
        crop,
        cls_id: int | None = None,
        *,
        fast: bool | None = None,
        plate_only: bool | None = None,
    ) -> PlateRead:
        if not self.enabled:
            return PlateRead(status="empty")
        self._ensure_reader()
        if self._reader is None:
            return PlateRead(status="empty")
        if crop is None or getattr(crop, "size", 0) == 0:
            return PlateRead(status="empty")

        use_fast = OCR_FAST if fast is None else bool(fast)
        use_plate_only = OCR_PLATE_ONLY if plate_only is None else bool(plate_only)

        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0
        t0 = time.perf_counter()
        crop_meta = f"{crop.shape[1]}x{crop.shape[0]}" if hasattr(crop, "shape") else "?"

        try:
            subs = self._sub_crops(crop, cls_id=cls_id, fast=use_fast, plate_only=use_plate_only)
            if not subs:
                ms = int((time.perf_counter() - t0) * 1000)
                print(f"[OCR] plate=no crop={crop_meta} OCR='' conf=0 valid=NO ms={ms} (no plate ROI)")
                return PlateRead(status="unreadable", confidence=0.0)

            if use_fast:
                for sub in subs:
                    b, s, any_s = self._scan_variants(sub, quick=True, fast=True)
                    best_any_score = max(best_any_score, any_s)
                    if s > best_score:
                        best_score = s
                        best = b
                    if best and is_known_ph_format(best) and best_score >= OCR_MIN_CONF:
                        break
                # Fast miss but crop has text — one medium pass (still no heavy rotations).
                if not (best and is_known_ph_format(best) and best_score >= OCR_MIN_CONF):
                    if best_any_score >= OCR_UNREADABLE_BELOW or best is not None:
                        # Medium pass may use bumper bands only when plate-only is off.
                        for sub in self._sub_crops(
                            crop, cls_id=cls_id, fast=False, plate_only=use_plate_only
                        )[:2]:
                            b, s, any_s = self._scan_variants(sub, quick=True, fast=False)
                            best_any_score = max(best_any_score, any_s)
                            if s > best_score:
                                best_score = s
                                best = b
                            if best and is_known_ph_format(best) and best_score >= OCR_MIN_CONF:
                                break
            else:
                quick_crop = subs[0]
                best, best_score, best_any_score = self._scan_variants(
                    quick_crop, quick=True, fast=False
                )
                if best and best_score >= OCR_HIGH_CONF_LOCK and is_known_ph_format(best):
                    ms = int((time.perf_counter() - t0) * 1000)
                    print(
                        f"[OCR] plate=yes crop={crop_meta} OCR={best!r} conf={best_score:.2f} "
                        f"valid=YES ms={ms}"
                    )
                    return PlateRead(
                        plate=best,
                        confidence=round(min(best_score, 1.0), 3),
                        status="ok",
                    )

                for sub in subs:
                    b, s, any_s = self._scan_variants(sub, quick=False, fast=False)
                    best_any_score = max(best_any_score, any_s)
                    if s > best_score:
                        best_score = s
                        best = b
                    if best and best_score >= OCR_HIGH_CONF_LOCK and is_known_ph_format(best):
                        break
        except Exception as e:
            print(f"OCR pipeline error: {e}")
            return PlateRead(status="unreadable", confidence=0.0)

        ms = int((time.perf_counter() - t0) * 1000)
        if best and best_score >= OCR_MIN_CONF:
            valid = "YES" if is_known_ph_format(best) else "NO"
            print(
                f"[OCR] plate=yes crop={crop_meta} OCR={best!r} conf={best_score:.2f} "
                f"valid={valid} ms={ms}"
            )
            return PlateRead(plate=best, confidence=round(min(best_score, 1.0), 3), status="ok")

        print(
            f"[OCR] plate=yes crop={crop_meta} OCR={best!r} conf={best_any_score:.2f} "
            f"valid=NO ms={ms}"
        )
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
        mode = "fast-CPU" if OCR_FAST else ("GPU" if OCR_GPU else "full")
        plate_mode = "plate-only" if OCR_PLATE_ONLY else "bumper+plate"
        print(f"Plate OCR queue ready ({mode}, {plate_mode}, sync={'on' if OCR_SYNC_ENABLED else 'off'})")

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
        if mem is not None:
            mem.tick_plate_deadline()
            if mem.is_plate_terminal():
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
            if mem is not None:
                mem.mark_ocr_attempt(now)
                mem.tick_plate_deadline(now)
            return
        # Keep a private copy; the infer loop reuses the live frame buffer.
        crop = crop.copy()
        xyxy_i = tuple(int(v) for v in xyxy)

        if mem is not None:
            mem.last_ocr_at = now
            mem.cls_id = cls_id
            mem.last_plate_crop = crop
            mem.last_ocr_xyxy = xyxy_i
            mem.last_xyxy = xyxy_i
            mem.mark_ocr_attempt(now)

        try:
            self._q.put_nowait((key, crop, intelligence, int(track_id), cls_id, xyxy_i, camera_id))
        except queue.Full:
            if mem is not None:
                mem.last_ocr_at = 0.0
            with self._lock:
                self._inflight.discard(key)

    def _loop(self):
        while True:
            item = self._q.get()
            camera_id = "?"
            if len(item) >= 7:
                key, crop, intelligence, track_id, cls_id, xyxy, camera_id = item[:7]
            elif len(item) >= 6:
                key, crop, intelligence, track_id, cls_id, xyxy = item[:6]
            else:
                key, crop, intelligence, track_id, cls_id = item[:5]
                xyxy = None
            try:
                t0 = time.perf_counter()
                # Always use fast path on the async worker when OCR_FAST is set (CPU default).
                read = self.ocr.read_crop(crop, cls_id=cls_id, fast=OCR_FAST)
                mem = intelligence.tracks.get(track_id)
                if mem is None and xyxy is not None and hasattr(intelligence, "find_track_near_xyxy"):
                    # Track IDs often change while CPU OCR runs; reattach by bbox overlap.
                    mem = intelligence.find_track_near_xyxy(xyxy, pending_only=True)
                if mem is not None:
                    mem.last_plate_crop = crop
                    mem.apply_ocr_vote(read.plate, read.status, read.confidence)
                    mem.tick_plate_deadline()
                    ms = int((time.perf_counter() - t0) * 1000)
                    ch = crop.shape[0] if hasattr(crop, "shape") else 0
                    cw = crop.shape[1] if hasattr(crop, "shape") else 0
                    print(
                        f"[{camera_id}] Track #{track_id} "
                        f"OCR={read.plate!r} conf={read.confidence:.2f} "
                        f"status={mem.plate_status} crop={cw}x{ch} ms={ms}"
                    )
                    if mem.needs_owner_lookup():
                        from plate_owner_lookup import lookup_plate_async

                        lookup_plate_async(mem)
            except Exception as e:
                print(f"Async OCR error: {e}")
            finally:
                with self._lock:
                    self._inflight.discard(key)
