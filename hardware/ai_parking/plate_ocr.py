"""Optional plate OCR (EasyOCR). Async queue keeps YOLO inference non-blocking."""

from __future__ import annotations

import os
import queue
import threading
import time
from dataclasses import dataclass
from pathlib import Path
from typing import TYPE_CHECKING, Optional

import cv2
import numpy as np

from parking_rules import OCR_MAX_ATTEMPTS
from plate_text import (
    best_from_results,
    is_known_ph_format,
    looks_like_plate_text,
    reconcile_partial_plates,
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
# Cap EasyOCR input width — large LANCZOS upscales hang for minutes on CPU.
OCR_UPSCALE_MAX_WIDTH = int(os.getenv("AI_PARKING_OCR_UPSCALE_MAX_WIDTH", "420"))
OCR_READ_TIMEOUT_SEC = float(os.getenv("AI_PARKING_OCR_READ_TIMEOUT_SEC", "10"))
OCR_GPU = os.getenv("AI_PARKING_OCR_GPU", "0") == "1"
OCR_HIGH_CONF_LOCK = float(os.getenv("AI_PARKING_OCR_HIGH_CONF_LOCK", "0.90"))
OCR_PLATE_ONLY = os.getenv("AI_PARKING_OCR_PLATE_ONLY", "1").strip().lower() in (
    "1",
    "true",
    "yes",
    "on",
)
OCR_MIN_PLATE_CROP_W = int(os.getenv("AI_PARKING_OCR_MIN_PLATE_CROP_W", "28"))
OCR_MIN_PLATE_CROP_H = int(os.getenv("AI_PARKING_OCR_MIN_PLATE_CROP_H", "12"))
OCR_DEBUG = os.getenv("AI_PARKING_OCR_DEBUG", "0").strip().lower() in ("1", "true", "yes", "on")
OCR_DEBUG_DIR = Path(
    os.getenv(
        "AI_PARKING_OCR_DEBUG_DIR",
        str(Path(__file__).resolve().parent / "debug_plates"),
    )
)
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
        max_w = max(160, OCR_UPSCALE_MAX_WIDTH)
        if fast:
            # Large LANCZOS upscales dominate CPU time before EasyOCR even runs.
            factor = min(factor, 2.0)
            min_w = min(min_w, 280)
            max_w = min(max_w, 360)
        elif cw < 64:
            factor = max(factor, 10.0)
            min_w = max(min_w, 800)
            max_w = max(max_w, 800)
        target = max(min_w, int(cw * factor))
        target = min(target, max_w)
        if cw >= target:
            # Still shrink oversized plate-YOLO crops so EasyOCR stays responsive.
            if cw > max_w:
                scale = max_w / float(cw)
                return cv2.resize(
                    crop,
                    None,
                    fx=scale,
                    fy=scale,
                    interpolation=cv2.INTER_AREA,
                )
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
            # clahe first; color as backup when the first pass looks truncated.
            return [
                ("clahe", clahe_img),
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
        deadline: float | None = None,
    ) -> tuple[Optional[str], float, float]:
        best: Optional[str] = None
        best_score = 0.0
        best_any_score = 0.0
        raw_texts: list[str] = []
        # Accept a solid known-PH hit early so CPU OCR does not burn every variant.
        # Typical clear-plate EasyOCR scores are 0.50–0.80 (not 0.90).
        early_lock = max(OCR_MIN_CONF, 0.48 if (fast or quick) else max(0.55, OCR_HIGH_CONF_LOCK - 0.25))
        variants = self._ocr_variants(crop, quick=quick, fast=fast)
        # Fast path: try clahe first; only run color if the first result looks truncated.
        if fast or quick:
            variants = variants[:2]
        for idx, (_label, img) in enumerate(variants):
            if deadline is not None and time.perf_counter() >= deadline:
                print(f"[OCR] variant budget exhausted ({OCR_READ_TIMEOUT_SEC:.0f}s) — stopping")
                break
            # Skip 2nd variant when first already looks like a solid 3-letter plate.
            if idx > 0 and best and best_score >= early_lock and is_known_ph_format(best):
                letters = sum(1 for ch in best if ch.isalpha())
                if letters >= 3:
                    break
            try:
                results = self._readtext(img, fast=fast or quick, deadline=deadline)
            except Exception as e:
                print(f"OCR read error: {e}")
                continue
            if not results:
                continue
            for _bbox, text, conf in results:
                raw_texts.append(str(text))
                best_any_score = max(best_any_score, float(conf))
            b, s, any_s = best_from_results(results, OCR_MIN_CONF)
            best_any_score = max(best_any_score, any_s)
            if s > best_score:
                best_score = s
                best = b
            if best and best_score >= early_lock and (
                is_known_ph_format(best) or looks_like_plate_text(best)
            ):
                letters = sum(1 for ch in best if ch.isalpha())
                # Keep going to 2nd variant when result looks like FC259 truncation.
                if letters >= 3 or not is_known_ph_format(best):
                    break
            if best and best_score >= OCR_HIGH_CONF_LOCK and (
                is_known_ph_format(best) or looks_like_plate_text(best)
            ):
                letters = sum(1 for ch in best if ch.isalpha())
                if letters >= 3:
                    break

        # Merge truncated bull-bar reads across preprocess variants (EBD81 + EBD84).
        merged = reconcile_partial_plates(raw_texts + ([best] if best else []))
        if merged and is_known_ph_format(merged):
            if best is None or not is_known_ph_format(best) or len(merged) >= len(best or ""):
                best = merged
                best_score = max(best_score, 0.55)
        return best, best_score, best_any_score

    def _readtext(self, img, *, fast: bool = False, deadline: float | None = None):
        short = min(img.shape[:2])
        if fast:
            mag = 1.2 if short < 80 else 1.0
        else:
            mag = 2.0 if short < 80 else 1.6
        timeout = float(OCR_READ_TIMEOUT_SEC) if OCR_READ_TIMEOUT_SEC > 0 else 10.0
        if deadline is not None:
            timeout = max(0.4, min(timeout, deadline - time.perf_counter()))
        if timeout <= 0.05:
            return []

        canvas = 720 if fast else 960
        kwargs = dict(
            allowlist=_OCR_ALLOWLIST,
            paragraph=False,
            min_size=4 if not fast else 6,
            text_threshold=0.5 if not fast else 0.55,
            low_text=0.3 if not fast else 0.35,
            link_threshold=0.3 if not fast else 0.4,
            canvas_size=canvas,
            mag_ratio=mag,
            detail=1,
            batch_size=1,
        )
        # Run EasyOCR in a worker thread with a hard join timeout. A hung call may keep
        # holding the lock until it finishes, but later attempts fail-fast on lock busy
        # so tracks are not stuck on "Scanning... 1/10" forever.
        box: list = []
        err: list = []

        def _call() -> None:
            acquired = False
            try:
                acquired = self._lock.acquire(timeout=min(1.5, timeout))
                if not acquired:
                    err.append(TimeoutError("OCR engine busy"))
                    return
                box.append(self._reader.readtext(img, **kwargs))
            except Exception as e:
                err.append(e)
            finally:
                if acquired:
                    self._lock.release()

        worker = threading.Thread(target=_call, daemon=True, name="easyocr-readtext")
        worker.start()
        worker.join(timeout=timeout)
        if worker.is_alive():
            print(f"[OCR] readtext timed out after {timeout:.1f}s — skipping")
            return []
        if err:
            print(f"[OCR] readtext skipped: {err[0]}")
            return []
        return box[0] if box else []

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
    def _targeted_plate_rois(crop, cls_id: int | None = None) -> list:
        """Bounded lower-front / rear plate bands — never the full vehicle."""
        ch, cw = crop.shape[:2]
        if ch < 20 or cw < 40:
            return []
        out = []
        if cls_id == MOTORCYCLE_CLS_ID:
            bands = ((0.12, 0.72, 0.08, 0.92),)
        else:
            # Front bumper plates can sit left/center/right (CAM-2 multicab was left-biased).
            bands = (
                (0.42, 1.0, 0.02, 0.70),  # lower-left
                (0.42, 1.0, 0.15, 0.85),  # lower-center
                (0.42, 1.0, 0.30, 0.98),  # lower-right
                (0.55, 1.0, 0.05, 0.95),  # tight bumper strip
            )
        for y0, y1, x0, x1 in bands:
            y_a, y_b = int(ch * y0), int(ch * y1)
            x_a, x_b = int(cw * x0), int(cw * x1)
            if y_b - y_a < 14 or x_b - x_a < 40:
                continue
            roi = crop[y_a:y_b, x_a:x_b]
            if roi is not None and roi.size > 0:
                out.append(roi.copy())
        return out

    @staticmethod
    def _save_debug_crops(
        camera_id: str,
        track_id: int | None,
        *,
        vehicle_crop=None,
        plate_crop=None,
        processed=None,
        label: str = "ocr",
    ) -> None:
        if not OCR_DEBUG:
            return
        try:
            cam = (camera_id or "CAM").replace("/", "_")
            tid = f"track_{track_id}" if track_id is not None else "track_unknown"
            dest = OCR_DEBUG_DIR / cam / tid
            dest.mkdir(parents=True, exist_ok=True)
            stamp = int(time.time() * 1000) % 100000
            if vehicle_crop is not None and getattr(vehicle_crop, "size", 0) > 0:
                cv2.imwrite(str(dest / f"{stamp}_vehicle_crop.jpg"), vehicle_crop)
            if plate_crop is not None and getattr(plate_crop, "size", 0) > 0:
                cv2.imwrite(str(dest / f"{stamp}_plate_crop_raw.jpg"), plate_crop)
            if processed is not None and getattr(processed, "size", 0) > 0:
                cv2.imwrite(str(dest / f"{stamp}_plate_crop_processed.jpg"), processed)
            elif plate_crop is not None and getattr(plate_crop, "size", 0) > 0:
                up = PlateOCR._upscale_crop(plate_crop, fast=True)
                cv2.imwrite(str(dest / f"{stamp}_plate_crop_processed.jpg"), up)
            meta = dest / f"{stamp}_{label}.txt"
            meta.write_text(
                f"camera={camera_id} track={track_id} label={label}\n",
                encoding="utf-8",
            )
        except Exception as e:
            print(f"[OCR debug] save failed: {e}")

    @staticmethod
    def _sub_crops(crop, cls_id: int | None = None, *, fast: bool = False, plate_only: bool | None = None):
        """Plate YOLO (expanded) first; targeted lower bands if miss — never full bumper OCR when plate_only."""
        ch, cw = crop.shape[:2]
        use_plate_only = OCR_PLATE_ONLY if plate_only is None else bool(plate_only)
        out: list = []
        plate_meta = "NOT_FOUND"
        plate_conf = 0.0

        try:
            from plate_detector import detect_plate_crop, detect_plate_xyxy

            xyxy, conf = detect_plate_xyxy(crop)
            plate_conf = float(conf or 0.0)
            if xyxy is not None:
                x1, y1, x2, y2 = xyxy
                tighter = crop[y1:y2, x1:x2]
                if tighter is not None and tighter.size > 0:
                    th, tw = tighter.shape[:2]
                    if tw >= OCR_MIN_PLATE_CROP_W and th >= OCR_MIN_PLATE_CROP_H:
                        out.append(tighter.copy())
                        plate_meta = f"FOUND bbox={xyxy} conf={plate_conf:.3f} crop={tw}x{th}"
            if not out:
                tighter = detect_plate_crop(crop, cls_id=cls_id)
                if tighter is not None and tighter.size > 0:
                    th, tw = tighter.shape[:2]
                    if tw >= OCR_MIN_PLATE_CROP_W and th >= OCR_MIN_PLATE_CROP_H:
                        out.append(tighter.copy())
                        plate_meta = f"FOUND(opencv/hsv) crop={tw}x{th}"
        except Exception as e:
            plate_meta = f"ERROR:{e}"

        print(f"[OCR] Plate detector: {plate_meta}")

        targets = PlateOCR._targeted_plate_rois(crop, cls_id=cls_id)
        # Plate-first: YOLO/OpenCV plate crop first, then lower-band fallbacks.
        ordered: list = []
        ordered.extend(out)
        if use_plate_only or targets:
            ordered.extend(targets[:3] if not fast else targets[:2])
        # de-dupe by shape id
        uniq: list = []
        seen_ids: set[int] = set()
        for roi in ordered:
            rid = id(roi)
            if rid in seen_ids:
                continue
            seen_ids.add(rid)
            uniq.append(roi)

        if uniq:
            # Fast path: prefer plate-YOLO ROI only (1 crop) so EasyOCR finishes.
            if fast and out:
                return out[:1]
            return uniq[:2] if fast else uniq[:4]

        if use_plate_only:
            return targets[:1] if fast else targets[:3]

        out = list(targets[:1]) if targets else []
        if fast:
            if not out and cls_id == MOTORCYCLE_CLS_ID and ch >= 24:
                mid = crop[max(0, int(ch * 0.10)) : min(ch, int(ch * 0.70)), :]
                if mid.size > 0 and mid.shape[0] >= 12:
                    out.append(mid)
            elif not out and ch >= 24:
                bottom = crop[max(0, int(ch * 0.35)) : ch, :]
                if bottom.size > 0 and bottom.shape[0] >= 12:
                    out.append(bottom)
            return out[:1] or [crop]

        if not out:
            out = [crop]
        if cls_id == MOTORCYCLE_CLS_ID and ch >= 24:
            mid = crop[max(0, int(ch * 0.10)) : min(ch, int(ch * 0.70)), :]
            if mid.size > 0 and mid.shape[0] >= 12:
                out.insert(0, mid)
        elif ch >= 24:
            bottom = crop[max(0, int(ch * 0.35)) : ch, :]
            if bottom.size > 0 and bottom.shape[0] >= 12:
                out.insert(0, bottom)
        return out[:4]

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
        seen_plates: list[str] = []
        t0 = time.perf_counter()
        deadline = (
            (t0 + float(OCR_READ_TIMEOUT_SEC))
            if OCR_READ_TIMEOUT_SEC and OCR_READ_TIMEOUT_SEC > 0
            else None
        )
        crop_meta = f"{crop.shape[1]}x{crop.shape[0]}" if hasattr(crop, "shape") else "?"
        first_sub = None

        try:
            subs = self._sub_crops(crop, cls_id=cls_id, fast=use_fast, plate_only=use_plate_only)
            if use_fast and subs:
                subs = subs[:1]
            if not subs:
                ms = int((time.perf_counter() - t0) * 1000)
                print(f"[OCR] plate=no crop={crop_meta} OCR='' conf=0 valid=NO ms={ms} (no plate ROI)")
                return PlateRead(status="unreadable", confidence=0.0)

            first_sub = subs[0]
            if OCR_DEBUG:
                PlateOCR._save_debug_crops(
                    getattr(self, "_debug_camera_id", "CAM"),
                    getattr(self, "_debug_track_id", None),
                    vehicle_crop=crop,
                    plate_crop=first_sub,
                    label="read_crop",
                )

            def _note(b: Optional[str], s: float, any_s: float) -> None:
                nonlocal best, best_score, best_any_score
                best_any_score = max(best_any_score, any_s)
                if b:
                    seen_plates.append(b)
                if s > best_score and b:
                    best_score = s
                    best = b

            if use_fast:
                for sub in subs:
                    if deadline is not None and time.perf_counter() >= deadline:
                        print(f"[OCR] read_crop budget exhausted ({OCR_READ_TIMEOUT_SEC:.0f}s)")
                        break
                    b, s, any_s = self._scan_variants(
                        sub, quick=True, fast=True, deadline=deadline
                    )
                    _note(b, s, any_s)
                    # Accept known PH or loose prototype plates — stop extra subs.
                    if best and best_score >= max(OCR_MIN_CONF, 0.40) and (
                        is_known_ph_format(best) or looks_like_plate_text(best)
                    ):
                        break
                # Do NOT fall back to slow/non-fast OCR on CPU — that path hangs for minutes
                # and freezes the single OCR worker (UI stuck on "Scanning... 1/10").
            else:
                quick_crop = subs[0]
                b, s, any_s = self._scan_variants(
                    quick_crop, quick=True, fast=False, deadline=deadline
                )
                _note(b, s, any_s)
                if best and best_score >= OCR_HIGH_CONF_LOCK and (
                    is_known_ph_format(best) or looks_like_plate_text(best)
                ):
                    ms = int((time.perf_counter() - t0) * 1000)
                    print(
                        f"[OCR] OCR_SUCCESS plate={best!r} conf={best_score:.2f} "
                        f"validation=PASS ms={ms} crop={crop_meta}"
                    )
                    return PlateRead(
                        plate=best,
                        confidence=round(min(best_score, 1.0), 3),
                        status="ok",
                    )

                for sub in subs:
                    if deadline is not None and time.perf_counter() >= deadline:
                        break
                    b, s, any_s = self._scan_variants(
                        sub, quick=False, fast=False, deadline=deadline
                    )
                    _note(b, s, any_s)
                    if best and best_score >= OCR_HIGH_CONF_LOCK and (
                        is_known_ph_format(best) or looks_like_plate_text(best)
                    ):
                        break
        except Exception as e:
            print(f"OCR pipeline error: {e}")
            return PlateRead(status="unreadable", confidence=0.0)

        merged = reconcile_partial_plates(seen_plates + ([best] if best else []))
        if merged and is_known_ph_format(merged):
            print(f"[OCR] reconciled partials {seen_plates} → {merged}")
            best = merged
            best_score = max(best_score, 0.55)

        ms = int((time.perf_counter() - t0) * 1000)
        known = bool(best and is_known_ph_format(best))
        loose = bool(best and looks_like_plate_text(best))
        cam = getattr(self, "_debug_camera_id", "CAM")
        tid = getattr(self, "_debug_track_id", None)
        sub_meta = (
            f"{first_sub.shape[1]}x{first_sub.shape[0]}"
            if first_sub is not None and hasattr(first_sub, "shape")
            else "?"
        )
        if best and best_score >= OCR_MIN_CONF and (known or loose):
            # For loose/prototype plates, prefer raw EasyOCR confidence for lock voting.
            out_conf = best_score
            if loose and not known:
                out_conf = max(best_score, best_any_score * 0.85)
            print(
                f"[{cam}][Track #{tid}] PlateCrop={sub_meta} "
                f"RawOCR={best!r} Normalized={best} Valid={'YES' if known else 'LOOSE'} "
                f"conf={out_conf:.2f} OCR={ms}ms"
            )
            print(
                f"[OCR] OCR_SUCCESS plate={best!r} conf={out_conf:.2f} "
                f"validation={'PASS' if known else 'LOOSE'} ms={ms} crop={crop_meta}"
            )
            return PlateRead(plate=best, confidence=round(min(out_conf, 1.0), 3), status="ok")

        print(
            f"[{cam}][Track #{tid}] PlateCrop={sub_meta} "
            f"RawOCR={best!r} Valid=NO conf={best_any_score:.2f} OCR={ms}ms Reason=LOW_CONFIDENCE"
        )
        print(
            f"[OCR] OCR_FAILED plate={best!r} conf={best_any_score:.2f} "
            f"validation=FAIL ms={ms} crop={crop_meta}"
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
        self._inflight_since: dict[tuple, float] = {}
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
                reason = mem.ocr_skip_reason() or "PLATE_TERMINAL"
                if (time.time() - getattr(mem, "last_ocr_at", 0)) > 3.0:
                    print(
                        f"[OCR] tracking_id={track_id} skipped reason={reason}"
                    )
                    mem.last_ocr_at = time.time()
                return
            # Defense in depth: never queue OCR past terminal / attempt budget.
            if not mem.allows_ocr():
                reason = mem.ocr_skip_reason() or "OCR_NOT_ALLOWED"
                if (time.time() - getattr(mem, "_last_submit_skip_log", 0.0)) > 3.0:
                    mem._last_submit_skip_log = time.time()  # type: ignore[attr-defined]
                    print(f"[OCR] tracking_id={track_id} skipped reason={reason}")
                return
        now = time.time()
        if mem and (now - mem.last_ocr_at) < every_sec:
            return

        key = (camera_id, int(track_id))
        with self._lock:
            # If a prior job has been "in flight" longer than the OCR budget, drop the
            # reservation so a hung EasyOCR call cannot block this track forever.
            started = self._inflight_since.get(key)
            stale_after = max(20.0, float(OCR_READ_TIMEOUT_SEC or 10) * 2.5)
            if key in self._inflight and started and (now - started) >= stale_after:
                print(
                    f"[OCR] tracking_id={track_id} clearing stale inflight "
                    f"age={now - started:.0f}s"
                )
                self._inflight.discard(key)
                self._inflight_since.pop(key, None)
            if key in self._inflight:
                return
            self._inflight.add(key)
            self._inflight_since[key] = now

        crop = PlateOCR.crop_plate_region(frame, xyxy, cls_id=cls_id)
        if crop is None:
            with self._lock:
                self._inflight.discard(key)
                self._inflight_since.pop(key, None)
            # No plate crop — not an OCR attempt. The budget counts only real reads.
            if mem is not None:
                print(
                    f"[OCR] tracking_id={track_id} skipped reason=NO_PLATE_CROP "
                    f"attempts={mem.ocr_attempts}/{OCR_MAX_ATTEMPTS}"
                )
            return
        # Keep a private copy; the infer loop reuses the live frame buffer.
        crop = crop.copy()
        crop_h, crop_w = crop.shape[:2]
        if crop_w < OCR_MIN_PLATE_CROP_W or crop_h < OCR_MIN_PLATE_CROP_H:
            with self._lock:
                self._inflight.discard(key)
            if mem is not None:
                print(
                    f"[OCR] tracking_id={track_id} skipped reason=CROP_TOO_SMALL "
                    f"crop={crop_w}x{crop_h} attempts={mem.ocr_attempts}/{OCR_MAX_ATTEMPTS}"
                )
            return
        xyxy_i = tuple(int(v) for v in xyxy)

        if mem is not None:
            mem.last_ocr_at = now
            mem.cls_id = cls_id
            mem.last_plate_crop = crop
            # OCR-frame box only — never overwrite infer-frame last_xyxy (breaks reattach).
            mem.last_ocr_xyxy = xyxy_i
            # Attempt count is incremented in the worker only after OCR actually runs
            # (or is skipped without counting if the vehicle became stationary).

        try:
            self._q.put_nowait((key, crop, intelligence, int(track_id), cls_id, xyxy_i, camera_id))
        except queue.Full:
            if mem is not None:
                mem.last_ocr_at = 0.0
            with self._lock:
                self._inflight.discard(key)
                self._inflight_since.pop(key, None)

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
                self.ocr._debug_camera_id = str(camera_id or "CAM")
                self.ocr._debug_track_id = int(track_id) if track_id is not None else None
                mem = intelligence.tracks.get(track_id)
                if mem is None and xyxy is not None and hasattr(intelligence, "find_track_near_xyxy"):
                    # Track IDs often change while CPU OCR runs; reattach by bbox / session.
                    mem = intelligence.find_track_near_xyxy(
                        xyxy, pending_only=False, prefer_locked=True
                    )
                # Re-check eligibility immediately before OCR executes.
                if mem is not None:
                    skip = mem.ocr_skip_reason()
                    if skip is not None or not mem.allows_ocr():
                        reason = skip or "OCR_NOT_ALLOWED"
                        print(f"[OCR] tracking_id={track_id} skipped reason={reason}")
                        continue

                # Count only when OCR actually executes.
                if mem is not None:
                    mem.mark_ocr_attempt()
                    print(
                        f"[OCR] tracking_id={track_id} attempt={mem.ocr_attempts}/{OCR_MAX_ATTEMPTS}"
                    )

                # Always use fast path on the async worker when OCR_FAST is set (CPU default).
                read = self.ocr.read_crop(crop, cls_id=cls_id, fast=OCR_FAST)
                if mem is None and xyxy is not None and hasattr(intelligence, "find_track_near_xyxy"):
                    mem = intelligence.find_track_near_xyxy(
                        xyxy, pending_only=False, prefer_locked=True
                    )
                if mem is not None:
                    from parking_rules import plate_source_label as _ps_label

                    if mem.is_plate_locked() or _ps_label(mem.plate_source) == "MANUAL":
                        print(
                            f"[OCR] tracking_id={track_id} skipped reason=PLATE_ALREADY_CONFIRMED"
                        )
                        continue
                    mem.last_plate_crop = crop
                    mem.last_ocr_at = time.time()
                    before = mem.plate_status
                    mem.apply_ocr_vote(read.plate, read.status, read.confidence)
                    mem.tick_plate_deadline()
                    ms = int((time.perf_counter() - t0) * 1000)
                    ch = crop.shape[0] if hasattr(crop, "shape") else 0
                    cw = crop.shape[1] if hasattr(crop, "shape") else 0
                    known = "YES" if (read.plate and is_known_ph_format(read.plate)) else "NO"
                    sid = getattr(mem, "recognition_session_id", None)
                    print(
                        f"[{camera_id}] Track #{track_id} Session #{sid} "
                        f"OCR={read.plate!r} conf={read.confidence:.2f} valid_ph={known} "
                        f"vote={before}->{mem.plate_status} plate={mem.plate!r} "
                        f"crop={cw}x{ch} ms={ms}"
                    )
                    if mem.plate_status == "not_read":
                        print(
                            f"[OCR] tracking_id={track_id} stopped reason=MAX_ATTEMPTS "
                            f"attempts={mem.ocr_attempts}"
                        )
                    if mem.needs_owner_lookup():
                        from plate_owner_lookup import lookup_plate_async

                        lookup_plate_async(mem)
            except Exception as e:
                print(f"Async OCR error: {e}")
            finally:
                with self._lock:
                    self._inflight.discard(key)
                    self._inflight_since.pop(key, None)
