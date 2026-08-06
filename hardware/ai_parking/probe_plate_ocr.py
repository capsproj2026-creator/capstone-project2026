"""One-shot plate OCR probe: grab RTSP frame → YOLO vehicles → EasyOCR plates."""

from __future__ import annotations

import os
import sys
from pathlib import Path

import cv2

BASE = Path(__file__).resolve().parent
sys.path.insert(0, str(BASE))

# Load Laravel .env into process env (same keys start-ai-parking uses).
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

os.environ.setdefault("AI_PARKING_OCR_ENABLED", "1")

from camera_registry import load_cameras  # noqa: E402
from plate_ocr import PlateOCR  # noqa: E402
from ultralytics import YOLO  # noqa: E402

MODEL = BASE / "models" / "yolov9c.pt"
OUT_DIR = BASE / "debug_plates"
OUT_DIR.mkdir(exist_ok=True)

VEHICLE_CLASSES = {2, 3, 5, 7}  # car, motorcycle, bus, truck


def main() -> int:
    cameras = load_cameras(BASE)
    if not cameras:
        print("FAIL: no cameras configured")
        return 1

    from urllib.parse import quote

    cam = cameras[0]
    print(f"Camera: {cam.camera_id}  {cam.ip}{cam.rtsp_path}")
    print(f"OCR enabled env: {os.getenv('AI_PARKING_OCR_ENABLED')}")

    u = quote(cam.user, safe="")
    p = quote(cam.password, safe="")
    url = f"rtsp://{u}:{p}@{cam.ip}:{cam.port}{cam.rtsp_path}"
    os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = "rtsp_transport;udp|fflags;nobuffer|flags;low_delay"
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    if not cap.isOpened():
        print("FAIL: cannot open RTSP")
        return 1

    ok, frame = False, None
    for _ in range(8):
        ok, frame = cap.read()
        if ok and frame is not None:
            break
    cap.release()
    if not ok or frame is None:
        print("FAIL: no frame from camera")
        return 1

    h, w = frame.shape[:2]
    print(f"Frame: {w}x{h}")
    snap = OUT_DIR / "probe_frame.jpg"
    cv2.imwrite(str(snap), frame)
    print(f"Saved frame: {snap}")

    print(f"Loading YOLO {MODEL}...")
    model = YOLO(str(MODEL))
    results = model.predict(frame, conf=0.28, imgsz=640, verbose=False)
    boxes = []
    for r in results:
        if r.boxes is None:
            continue
        for b in r.boxes:
            cls_id = int(b.cls.item())
            if cls_id not in VEHICLE_CLASSES:
                continue
            xyxy = tuple(int(v) for v in b.xyxy[0].tolist())
            conf = float(b.conf.item())
            boxes.append((xyxy, conf, cls_id))

    print(f"Vehicles detected: {len(boxes)}")
    if not boxes:
        print("RESULT: No vehicles in frame — cannot test plate OCR.")
        return 0

    ocr = PlateOCR()
    ocr.enabled = True
    plated = 0
    for i, (xyxy, conf, cls_id) in enumerate(boxes, 1):
        crop = PlateOCR.crop_plate_region(frame, xyxy, cls_id=cls_id)
        crop_path = OUT_DIR / f"probe_crop_{i}.jpg"
        if crop is not None:
            cv2.imwrite(str(crop_path), crop)

        read = ocr.read_plate(frame, xyxy, cls_id=cls_id)
        status = read.status
        plate = read.plate or "—"
        score = read.confidence
        print(
            f"  Vehicle #{i} cls={cls_id} box={xyxy} det_conf={conf:.2f} "
            f"ocr_status={status} plate={plate} ocr_conf={score:.2f}"
        )
        if read.plate:
            plated += 1

    print("---")
    if plated:
        print(f"PASS: OCR read {plated}/{len(boxes)} plate(s).")
        return 0

    print(
        "RESULT: Vehicles found, but no plate text passed filters "
        f"(min conf={os.getenv('AI_PARKING_OCR_MIN_CONF', '0.35')}). "
        "Check debug_plates/ crops — plate may be too small/blurry/angle."
    )
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
