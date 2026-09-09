"""Re-run CAM-2 reference after crop-padding fix — expect EBD814."""

from __future__ import annotations

import json
import os
import sys
import time
from pathlib import Path

import cv2

BASE = Path(__file__).resolve().parent
sys.path.insert(0, str(BASE))

ROOT = BASE.parent.parent
ENV_FILE = ROOT / ".env"
if ENV_FILE.is_file():
    for line in ENV_FILE.read_text(encoding="utf-8", errors="ignore").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        key, val = key.strip(), val.strip().strip('"').strip("'")
        if key:
            os.environ[key] = val

os.environ["AI_PARKING_OCR_ENABLED"] = "1"
os.environ["AI_PARKING_OCR_DEBUG"] = "1"

from plate_detector import detect_plate_xyxy  # noqa: E402
from plate_ocr import PlateOCR  # noqa: E402
from plate_text import is_known_ph_format, parse_plate_candidate  # noqa: E402
from parking_rules import TrackMemory  # noqa: E402
from ultralytics import YOLO  # noqa: E402
from yolo_models import ensure_model, resolve_model_path  # noqa: E402

REF = BASE / "debug_plates" / "ref_cam2_ebd814.jpg"
OUT = BASE / "debug_plates" / "CAM-2" / "track_ref_fixed"
OUT.mkdir(parents=True, exist_ok=True)
VEHICLE_CLASSES = {2, 3, 5, 7}
NAMES = {2: "car", 3: "motorcycle", 5: "bus", 7: "truck"}


def main() -> int:
    frame = cv2.imread(str(REF))
    if frame is None:
        print("FAIL: no reference frame")
        return 1
    h, w = frame.shape[:2]
    ensure_model()
    model = YOLO(str(resolve_model_path()))
    t0 = time.perf_counter()
    results = model.predict(frame, conf=0.22, verbose=False, classes=list(VEHICLE_CLASSES))
    det_ms = int((time.perf_counter() - t0) * 1000)

    boxes = []
    for r in results:
        if r.boxes is None:
            continue
        for b in r.boxes:
            boxes.append((float(b.conf.item()), int(b.cls.item()), tuple(int(v) for v in b.xyxy[0].tolist())))
    boxes.sort(reverse=True)
    conf, cls_id, xyxy = boxes[0]
    name = NAMES.get(cls_id, str(cls_id))
    print(f"Vehicle: {name} conf={conf:.3f} ROI={xyxy} det_ms={det_ms}")

    bumper = PlateOCR.crop_plate_region(frame, xyxy, cls_id=cls_id)
    cv2.imwrite(str(OUT / "vehicle_bumper.jpg"), bumper)
    print(f"Bumper: {bumper.shape[1]}x{bumper.shape[0]}")

    t1 = time.perf_counter()
    pxyxy, pconf = detect_plate_xyxy(bumper)
    plate_ms = int((time.perf_counter() - t1) * 1000)
    if pxyxy is None:
        print(f"Plate detector: NOT FOUND ({plate_ms} ms)")
        plate_crop = None
    else:
        x1, y1, x2, y2 = pxyxy
        plate_crop = bumper[y1:y2, x1:x2].copy()
        cv2.imwrite(str(OUT / "plate_crop_expanded.jpg"), plate_crop)
        print(
            f"Plate detector: FOUND conf={pconf:.3f} bbox={pxyxy} "
            f"crop={plate_crop.shape[1]}x{plate_crop.shape[0]} ms={plate_ms}"
        )

    ocr = PlateOCR()
    ocr._debug_camera_id = "CAM-2"
    ocr._debug_track_id = 1
    t2 = time.perf_counter()
    read = ocr.read_crop(bumper, cls_id=cls_id, fast=True, plate_only=True)
    ocr_ms = int((time.perf_counter() - t2) * 1000)
    parsed, known = parse_plate_candidate(read.plate or "")
    print(
        f"OCR: plate={read.plate!r} conf={read.confidence:.3f} status={read.status} "
        f"normalized={parsed} valid_ph={known or is_known_ph_format(read.plate or '')} ms={ocr_ms}"
    )

    mem = TrackMemory(first_seen=time.time())
    mem.apply_ocr_vote(read.plate, read.status, read.confidence)
    if read.status == "ok" and read.plate and is_known_ph_format(read.plate):
        # Simulate second confirming frame (multi-frame vote).
        mem.apply_ocr_vote(read.plate, read.status, min(0.95, read.confidence + 0.2))
    print(f"Vote lock: status={mem.plate_status} plate={mem.plate!r}")

    summary = {
        "vehicle": name,
        "plate_detector": "FOUND" if pxyxy else "NOT_FOUND",
        "plate_bbox": list(pxyxy) if pxyxy else None,
        "plate_conf": pconf,
        "plate_crop": f"{plate_crop.shape[1]}x{plate_crop.shape[0]}" if plate_crop is not None else None,
        "ocr_raw": read.plate,
        "ocr_conf": read.confidence,
        "ocr_status": read.status,
        "normalized": parsed,
        "validation": bool(known or (read.plate and is_known_ph_format(read.plate))),
        "vote_status": mem.plate_status,
        "vote_plate": mem.plate,
        "timings_ms": {"vehicle": det_ms, "plate": plate_ms, "ocr": ocr_ms},
        "pass": bool(mem.plate_status == "ok" and mem.plate and "EBD814" in (mem.plate or "")),
    }
    (OUT / "report.json").write_text(json.dumps(summary, indent=2), encoding="utf-8")
    print(json.dumps(summary, indent=2))
    if not summary["pass"]:
        print("FAIL: expected locked plate EBD814")
        return 1
    print("PASS: CAM-2 reference locked EBD814")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
