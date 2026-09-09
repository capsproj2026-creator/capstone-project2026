"""Offline CAM-2 plate pipeline diagnosis on the reference frame.

Answers: plate YOLO hit/miss, OCR crop, EasyOCR raw, normalize, validate.
"""

from __future__ import annotations

import json
import os
import sys
import time
from pathlib import Path

import cv2

BASE = Path(__file__).resolve().parent
sys.path.insert(0, str(BASE))

# Prefer repo .env for OCR flags.
ROOT = BASE.parent.parent
ENV_FILE = ROOT / ".env"
if ENV_FILE.is_file():
    for line in ENV_FILE.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        key, val = key.strip(), val.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = val

os.environ.setdefault("AI_PARKING_OCR_ENABLED", "1")

from plate_detector import (  # noqa: E402
    PLATE_YOLO_CONF,
    _detect_opencv,
    _detect_white_plate,
    _detect_yolo,
    _load_plate_yolo,
    detect_plate_crop,
)
from plate_ocr import PlateOCR  # noqa: E402
from plate_text import best_from_results, is_known_ph_format, parse_plate_candidate  # noqa: E402
from ultralytics import YOLO  # noqa: E402
from yolo_models import ensure_model, resolve_model_path  # noqa: E402

REF = BASE / "debug_plates" / "ref_cam2_ebd814.jpg"
OUT = BASE / "debug_plates" / "CAM-2" / "track_ref"
OUT.mkdir(parents=True, exist_ok=True)

VEHICLE_CLASSES = {2, 3, 5, 7}
NAMES = {2: "car", 3: "motorcycle", 5: "bus", 7: "truck"}


def main() -> int:
    if not REF.is_file():
        print(f"FAIL: missing reference image {REF}")
        return 1

    frame = cv2.imread(str(REF))
    if frame is None:
        print("FAIL: cannot read reference image")
        return 1

    # Screenshot may include UI chrome; still try full image as camera-like frame.
    h, w = frame.shape[:2]
    print(f"Frame: {w}x{h}  plate_yolo_conf={PLATE_YOLO_CONF}")
    cv2.imwrite(str(OUT / "00_frame.jpg"), frame)

    ensure_model()
    model = YOLO(str(resolve_model_path()))
    t0 = time.perf_counter()
    results = model.predict(frame, conf=0.22, verbose=False, classes=list(VEHICLE_CLASSES))
    det_ms = int((time.perf_counter() - t0) * 1000)
    print(f"Vehicle detection: {det_ms} ms")

    boxes = []
    for r in results:
        if r.boxes is None:
            continue
        for b in r.boxes:
            cls_id = int(b.cls.item())
            conf = float(b.conf.item())
            x1, y1, x2, y2 = (int(v) for v in b.xyxy[0].tolist())
            boxes.append((conf, cls_id, (x1, y1, x2, y2)))
    boxes.sort(reverse=True)
    if not boxes:
        print("FAIL: no vehicle boxes")
        return 1

    conf, cls_id, xyxy = boxes[0]
    x1, y1, x2, y2 = xyxy
    name = NAMES.get(cls_id, str(cls_id))
    print(f"Track ref Vehicle: {name} conf={conf:.3f} ROI={xyxy}")

    vehicle_full = frame[y1:y2, x1:x2].copy()
    cv2.imwrite(str(OUT / "01_vehicle_crop.jpg"), vehicle_full)

    ocr = PlateOCR()
    bumper = PlateOCR.crop_plate_region(frame, xyxy, cls_id=cls_id)
    if bumper is None:
        print("FAIL: bumper crop empty")
        return 1
    cv2.imwrite(str(OUT / "02_vehicle_bumper_band.jpg"), bumper)
    print(f"Bumper band: {bumper.shape[1]}x{bumper.shape[0]}")

    # Plate detector stages
    _load_plate_yolo()
    t1 = time.perf_counter()
    yolo_plate = _detect_yolo(bumper)
    yolo_ms = int((time.perf_counter() - t1) * 1000)
    white = _detect_white_plate(bumper)
    opencv = _detect_opencv(bumper)
    combined = detect_plate_crop(bumper, cls_id=cls_id)

    print(f"Plate YOLO: {'FOUND' if yolo_plate is not None else 'NOT FOUND'} ({yolo_ms} ms)")
    if yolo_plate is not None:
        cv2.imwrite(str(OUT / "03_plate_yolo.jpg"), yolo_plate)
        print(f"  YOLO crop: {yolo_plate.shape[1]}x{yolo_plate.shape[0]}")
    if white is not None:
        cv2.imwrite(str(OUT / "03b_plate_white.jpg"), white)
        print(f"  White HSV crop: {white.shape[1]}x{white.shape[0]}")
    else:
        print("  White HSV: NOT FOUND")
    if opencv is not None:
        cv2.imwrite(str(OUT / "03c_plate_opencv.jpg"), opencv)
        print(f"  OpenCV crop: {opencv.shape[1]}x{opencv.shape[0]}")
    else:
        print("  OpenCV: NOT FOUND")
    print(f"detect_plate_crop: {'FOUND' if combined is not None else 'NOT FOUND'}")
    if combined is not None:
        cv2.imwrite(str(OUT / "04_plate_crop_raw.jpg"), combined)
        print(f"  Combined crop: {combined.shape[1]}x{combined.shape[0]}")

    # Targeted lower-front/center fallback (bounded)
    bh, bw = bumper.shape[:2]
    ty1 = int(bh * 0.35)
    ty2 = bh
    tx1 = int(bw * 0.22)
    tx2 = int(bw * 0.78)
    targeted = bumper[ty1:ty2, tx1:tx2].copy()
    cv2.imwrite(str(OUT / "05_targeted_lower_center.jpg"), targeted)
    print(f"Targeted lower-center: {targeted.shape[1]}x{targeted.shape[0]}")

    # OCR on each candidate
    ocr._ensure_reader()
    report = []
    for label, crop in (
        ("plate_yolo", yolo_plate),
        ("detect_plate_crop", combined),
        ("targeted", targeted),
        ("bumper_full", bumper),
    ):
        if crop is None:
            report.append({"label": label, "status": "missing"})
            continue
        up = PlateOCR._upscale_crop(crop, fast=True)
        cv2.imwrite(str(OUT / f"06_ocr_input_{label}.jpg"), up)
        t2 = time.perf_counter()
        # Direct EasyOCR on upscaled crop variants
        variants = list(PlateOCR._ocr_variants(crop, quick=True, fast=True))
        best_plate = None
        best_score = 0.0
        best_any = 0.0
        raw_texts = []
        for img in variants[:4]:
            try:
                results = ocr._reader.readtext(
                    img,
                    allowlist="0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ-",
                    detail=1,
                    paragraph=False,
                    decoder="greedy",
                    beamWidth=1,
                    batch_size=1,
                    workers=0,
                    contrast_ths=0.05,
                    adjust_contrast=0.35,
                    text_threshold=0.5,
                    low_text=0.25,
                    mag_ratio=1.4,
                    slope_ths=0.2,
                )
            except Exception as e:
                results = []
                raw_texts.append(f"ERR:{e}")
            for _b, text, conf in results or []:
                raw_texts.append(f"{text}|{float(conf):.3f}")
            plate, score, any_s = best_from_results(results or [], 0.18)
            best_any = max(best_any, any_s)
            if plate and score > best_score:
                best_score = score
                best_plate = plate
        ms = int((time.perf_counter() - t2) * 1000)
        parsed, known = parse_plate_candidate(best_plate or "")
        entry = {
            "label": label,
            "crop": f"{crop.shape[1]}x{crop.shape[0]}",
            "ocr_input": f"{up.shape[1]}x{up.shape[0]}",
            "raw": raw_texts[:12],
            "best": best_plate,
            "score": round(best_score, 3),
            "any": round(best_any, 3),
            "normalized": parsed,
            "valid_ph": bool(known or (parsed and is_known_ph_format(parsed))),
            "ms": ms,
        }
        report.append(entry)
        print(
            f"OCR[{label}] crop={entry['crop']} in={entry['ocr_input']} "
            f"best={best_plate} valid={entry['valid_ph']} raw={raw_texts[:6]} ms={ms}"
        )

        # Also run read_crop plate-only vs with fallback
    t3 = time.perf_counter()
    read_only = ocr.read_crop(bumper, cls_id=cls_id, fast=True, plate_only=True)
    only_ms = int((time.perf_counter() - t3) * 1000)
    t4 = time.perf_counter()
    read_fb = ocr.read_crop(bumper, cls_id=cls_id, fast=True, plate_only=False)
    fb_ms = int((time.perf_counter() - t4) * 1000)
    print(f"read_crop plate_only=True  -> {read_only.status} plate={read_only.plate} conf={read_only.confidence:.3f} ms={only_ms}")
    print(f"read_crop plate_only=False -> {read_fb.status} plate={read_fb.plate} conf={read_fb.confidence:.3f} ms={fb_ms}")

    summary = {
        "frame": f"{w}x{h}",
        "vehicle": {"class": name, "conf": conf, "xyxy": list(xyxy)},
        "plate_yolo": yolo_plate is not None,
        "detect_plate_crop": combined is not None,
        "ocr_candidates": report,
        "read_crop_plate_only": {"status": read_only.status, "plate": read_only.plate, "conf": read_only.confidence, "ms": only_ms},
        "read_crop_with_bumper": {"status": read_fb.status, "plate": read_fb.plate, "conf": read_fb.confidence, "ms": fb_ms},
        "out_dir": str(OUT),
    }
    (OUT / "report.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(f"Wrote {OUT / 'report.json'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
