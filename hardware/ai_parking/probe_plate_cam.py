"""Probe plate OCR on a specific camera (default CAM-2)."""

from __future__ import annotations

import os
import sys
from pathlib import Path

BASE = Path(__file__).resolve().parent
sys.path.insert(0, str(BASE))

from load_env import load_project_env

load_project_env()
os.environ.setdefault("AI_PARKING_OCR_ENABLED", "1")

import cv2
from camera_registry import load_cameras
from plate_ocr import PlateOCR
from ultralytics import YOLO
from yolo_models import ensure_model, resolve_model_path
from urllib.parse import quote

TARGET = os.getenv("PROBE_CAMERA_ID", "CAM-2")
VEHICLE_CLASSES = {2, 3, 5, 7}


def main() -> int:
    cameras = load_cameras(BASE)
    cam = next((c for c in cameras if c.camera_id == TARGET), None)
    if cam is None:
        print(f"FAIL: camera {TARGET} not loaded")
        return 1

    print(f"Camera: {cam.camera_id}  {cam.ip}{cam.rtsp_path}")
    print(f"OCR enabled: {os.getenv('AI_PARKING_OCR_ENABLED')}")
    print(f"OCR min conf: {os.getenv('AI_PARKING_OCR_MIN_CONF', '0.35')}")
    print(f"OCR parked only: {os.getenv('AI_PARKING_OCR_PARKED_ONLY', '1')}")

    u, p = quote(cam.user, safe=""), quote(cam.password, safe="")
    transport = (cam.rtsp_transport or "tcp").lower()
    os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = f"rtsp_transport;{transport}"
    url = f"rtsp://{u}:{p}@{cam.ip}:{cam.port}{cam.rtsp_path}"
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    if not cap.isOpened():
        print("FAIL: cannot open RTSP")
        return 1

    ok, frame = False, None
    for _ in range(15):
        ok, frame = cap.read()
        if ok and frame is not None:
            break
    cap.release()
    if not ok or frame is None:
        print("FAIL: no frame")
        return 1

    h, w = frame.shape[:2]
    print(f"Frame: {w}x{h}")

    ensure_model()
    model = YOLO(str(resolve_model_path()))
    results = model.predict(frame, conf=float(os.getenv("AI_PARKING_CONF", "0.28")), imgsz=640, verbose=False)
    boxes = []
    for r in results:
        if r.boxes is None:
            continue
        for b in r.boxes:
            cls_id = int(b.cls.item())
            if cls_id not in VEHICLE_CLASSES:
                continue
            xyxy = tuple(int(v) for v in b.xyxy[0].tolist())
            boxes.append((xyxy, float(b.conf.item()), cls_id))

    print(f"Vehicles detected: {len(boxes)}")
    if not boxes:
        return 0

    ocr = PlateOCR()
    ocr.enabled = True
    ocr._ensure_reader()
    if not ocr.enabled or ocr._reader is None:
        print("FAIL: EasyOCR not available")
        return 1

    plated = 0
    for i, (xyxy, conf, cls_id) in enumerate(boxes[:8], 1):
        read = ocr.read_plate(frame, xyxy, cls_id=cls_id)
        print(
            f"  #{i} cls={cls_id} box={xyxy} det={conf:.2f} "
            f"status={read.status} plate={read.plate or '-'} ocr={read.confidence:.2f}"
        )
        if read.plate:
            plated += 1

    print("---")
    if plated:
        print(f"PASS: read {plated}/{len(boxes)} plate(s)")
        return 0
    print("RESULT: vehicles found but no plate passed OCR filters (small/blurry/angle/distance)")
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
