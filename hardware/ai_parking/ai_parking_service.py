"""
AI Parking service — YOLOv9c + zone polygons + rules + tracking + OCR + Laravel.

Run:
  cd hardware/ai_parking
  python -u ai_parking_service.py

Calibrate slots first (recommended):
  python calibrate_zones.py

Requires Laravel:
  php artisan serve --host=0.0.0.0 --port=8000
  php artisan db:seed --class=AiTestLotSeeder
"""

from __future__ import annotations

import json
import os
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import quote
from urllib import request as urlrequest
from urllib.error import URLError, HTTPError

import cv2
from ultralytics import YOLO

from geometry import draw_zones, load_zones
from parking_rules import ParkingIntelligence
from plate_ocr import OCR_EVERY_SEC, PlateOCR

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "yolov9c.pt"
ZONES_PATH = BASE_DIR / "zones.json"

# ========== CONFIGURE THESE ==========
WIFI_CAMERA_IP = os.getenv("AI_CAMERA_IP", "192.168.1.108")
CAMERA_USER = os.getenv("AI_CAMERA_USER", "admin")
CAMERA_PASS = os.getenv("AI_CAMERA_PASS", "CSPCcapstone12345")
CAMERA_PORT = int(os.getenv("AI_CAMERA_PORT", "554"))
RTSP_PATH = os.getenv("AI_CAMERA_RTSP_PATH", "/cam/realmonitor?channel=1&subtype=0")

API_BASE = os.getenv("AI_LARAVEL_API_BASE", "http://127.0.0.1:8000").rstrip("/")
AI_API_TOKEN = os.getenv("AI_PARKING_API_TOKEN", "capstone-ai-parking-dev-token-change-me")
AREA_ID = int(os.getenv("AI_PARKING_AREA_ID", "19"))
CAMERA_ID = os.getenv("AI_CAMERA_ID", "CAM-AI-1")

STREAM_HOST = os.getenv("AI_STREAM_HOST", "0.0.0.0")
STREAM_PORT = int(os.getenv("AI_STREAM_PORT", "8090"))

IMG_SIZE = 640
CONF = 0.45
IOU = 0.50
MAX_DET = 50
POST_EVERY_SEC = 2.5
USE_WEBCAM = os.getenv("AI_USE_WEBCAM", "0") == "1"
TRACKER = os.getenv("AI_PARKING_TRACKER", "bytetrack.yaml")

DETECT_CLASS_IDS = [0, 2, 3, 5, 7]  # person, car, motorcycle, bus, truck
VEHICLE_CLASS_IDS = {2, 3, 5, 7}
COCO_NAMES = {
    0: "person",
    2: "car",
    3: "motorcycle",
    5: "bus",
    7: "truck",
}
CLASS_COLORS = {
    "person": (255, 160, 0),
    "car": (0, 220, 0),
    "motorcycle": (0, 200, 80),
    "bus": (0, 180, 255),
    "truck": (0, 140, 255),
}
# =====================================


class LatestFrameReader:
    def __init__(self, cap):
        self.cap = cap
        self.frame = None
        self.lock = threading.Lock()
        self.running = True
        self.thread = threading.Thread(target=self._loop, daemon=True)
        self.thread.start()

    def _loop(self):
        while self.running:
            ret, frame = self.cap.read()
            if not ret:
                time.sleep(0.01)
                continue
            with self.lock:
                self.frame = frame

    def read(self):
        with self.lock:
            if self.frame is None:
                return False, None
            return True, self.frame.copy()

    def stop(self):
        self.running = False
        self.thread.join(timeout=1.0)


class StreamState:
    def __init__(self):
        self.lock = threading.Lock()
        self.jpeg = None
        self.vehicle_count = 0
        self.detections = []

    def set_frame(self, jpeg_bytes, vehicle_count, detections):
        with self.lock:
            self.jpeg = jpeg_bytes
            self.vehicle_count = vehicle_count
            self.detections = detections

    def get_jpeg(self):
        with self.lock:
            return self.jpeg


STATE = StreamState()


def open_rtsp(ip, user, password, port, path):
    u = quote(user, safe="")
    p = quote(password, safe="")
    url = f"rtsp://{u}:{p}@{ip}:{port}{path}"
    print(f"Trying RTSP: rtsp://{user}:***@{ip}:{port}{path}")
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    if not cap.isOpened():
        cap.release()
        return None
    ret, frame = cap.read()
    if not ret or frame is None:
        cap.release()
        return None
    print(f"Camera OK — {frame.shape[1]}x{frame.shape[0]}")
    return cap


def parse_tracks(result, frame, ocr: PlateOCR, intelligence: ParkingIntelligence):
    """Build detection list + vehicle dicts with track ids and optional plates."""
    annotated_boxes = []
    detections = []
    vehicles = []
    person_count = 0
    vehicle_count = 0
    boxes = result.boxes
    if boxes is None or len(boxes) == 0:
        return annotated_boxes, detections, vehicles, 0, 0

    now = time.time()
    for box in boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])
        name = COCO_NAMES.get(cls_id, f"class_{cls_id}")
        track_id = None
        if box.id is not None:
            track_id = int(box.id[0])

        det = {"class": name, "confidence": round(conf, 3), "track_id": track_id}
        detections.append(det)
        annotated_boxes.append((x1, y1, x2, y2, name, conf, track_id, None))

        if cls_id == 0:
            person_count += 1
            continue
        if cls_id not in VEHICLE_CLASS_IDS:
            continue

        vehicle_count += 1
        plate = None
        if track_id is not None:
            mem = intelligence.tracks.get(track_id)
            if mem and mem.plate:
                plate = mem.plate
            elif ocr.enabled and (mem is None or now - mem.last_ocr_at >= OCR_EVERY_SEC):
                plate = ocr.read_plate(frame, (x1, y1, x2, y2))
                if mem is None:
                    from parking_rules import TrackMemory

                    intelligence.tracks[track_id] = TrackMemory(first_seen=now, last_ocr_at=now, plate=plate)
                else:
                    mem.last_ocr_at = now
                    if plate:
                        mem.plate = plate
                if plate:
                    det["plate"] = plate

        if plate:
            annotated_boxes[-1] = (x1, y1, x2, y2, name, conf, track_id, plate)

        vehicles.append({
            "xyxy": (x1, y1, x2, y2),
            "track_id": track_id,
            "class": name,
            "confidence": conf,
            "plate": plate,
        })

    return annotated_boxes, detections, vehicles, person_count, vehicle_count


def draw_scene(frame, annotated_boxes, zones_data, occupied_slots, active_events, person_count, vehicle_count, use_poly):
    annotated = frame.copy()
    draw_zones(annotated, zones_data, occupied_slots)

    for x1, y1, x2, y2, name, conf, track_id, plate in annotated_boxes:
        color = CLASS_COLORS.get(name, (0, 220, 0))
        label = f"{name} {conf * 100:.0f}%"
        if track_id is not None:
            label = f"#{track_id} {label}"
        if plate:
            label = f"{label} [{plate}]"
        cv2.rectangle(annotated, (x1, y1), (x2, y2), color, 2)
        cv2.putText(
            annotated,
            label,
            (x1, max(y1 - 8, 20)),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.55,
            color,
            2,
            cv2.LINE_AA,
        )

    mode = "slots" if use_poly else "count-fallback"
    summary = f"People: {person_count} | Vehicles: {vehicle_count} | Mode: {mode}"
    cv2.rectangle(annotated, (8, 8), (min(8 + 10 * len(summary) + 24, annotated.shape[1] - 8), 42), (0, 0, 0), -1)
    cv2.putText(annotated, summary, (16, 34), cv2.FONT_HERSHEY_SIMPLEX, 0.65, (0, 255, 0), 2, cv2.LINE_AA)

    y = 70
    for evt in active_events[:6]:
        text = f"{evt.get('type')} @ {evt.get('zone_id')}"
        if evt.get("plate"):
            text += f" [{evt['plate']}]"
        cv2.putText(annotated, text, (16, y), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 80, 255), 2, cv2.LINE_AA)
        y += 22

    return annotated


def post_json(path: str, payload: dict) -> bool:
    url = f"{API_BASE}{path}"
    body = json.dumps(payload).encode("utf-8")
    req = urlrequest.Request(
        url,
        data=body,
        headers={
            "Content-Type": "application/json",
            "X-AI-TOKEN": AI_API_TOKEN,
            "Accept": "application/json",
        },
        method="POST",
    )
    try:
        with urlrequest.urlopen(req, timeout=8) as resp:
            print(f"Laravel HTTP {resp.status}: {path} vehicles={payload.get('vehicle_count')} slots={len(payload.get('slots') or [])} events={len(payload.get('events') or [])}")
            return True
    except HTTPError as e:
        print(f"Laravel HTTP {e.code}: {e.read().decode('utf-8', errors='replace')}")
    except URLError as e:
        print(f"Laravel connection failed: {e.reason}")
    except Exception as e:
        print(f"Laravel POST error: {e}")
    return False


def post_occupancy(vehicle_count, detections, slots, events):
    payload = {
        "camera_id": CAMERA_ID,
        "area_id": AREA_ID,
        "vehicle_count": vehicle_count,
        "detections": detections,
        "slots": slots,
        "events": events,
        "mode": "slots" if slots else "count",
    }
    return post_json("/api/ai-parking/occupancy", payload)


class MjpegHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        return

    def do_GET(self):
        if self.path not in ("/stream.mjpg", "/"):
            self.send_error(404)
            return

        if self.path == "/":
            self.send_response(200)
            self.send_header("Content-Type", "text/html")
            self.end_headers()
            self.wfile.write(
                b"<html><body><h3>AI Parking Stream</h3>"
                b'<img src="/stream.mjpg" style="max-width:100%"/></body></html>'
            )
            return

        self.send_response(200)
        self.send_header("Age", "0")
        self.send_header("Cache-Control", "no-cache, private")
        self.send_header("Pragma", "no-cache")
        self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        self.end_headers()

        try:
            while True:
                jpeg = STATE.get_jpeg()
                if jpeg is None:
                    time.sleep(0.05)
                    continue
                self.wfile.write(b"--frame\r\n")
                self.wfile.write(b"Content-Type: image/jpeg\r\n")
                self.wfile.write(f"Content-Length: {len(jpeg)}\r\n\r\n".encode("ascii"))
                self.wfile.write(jpeg)
                self.wfile.write(b"\r\n")
                time.sleep(0.05)
        except (BrokenPipeError, ConnectionResetError):
            return


def start_stream_server():
    server = ThreadingHTTPServer((STREAM_HOST, STREAM_PORT), MjpegHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    print(f"MJPEG stream: http://{STREAM_HOST}:{STREAM_PORT}/stream.mjpg")
    return server


def __blank_frame():
    import numpy as np

    frame = np.zeros((480, 640, 3), dtype=np.uint8)
    cv2.putText(
        frame,
        "Camera offline — AI service running",
        (40, 240),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.7,
        (0, 200, 255),
        2,
        cv2.LINE_AA,
    )
    return frame


def main():
    if not MODEL_PATH.is_file():
        raise SystemExit(f"Model not found: {MODEL_PATH}")

    print(f"Loading {MODEL_PATH}...")
    model = YOLO(str(MODEL_PATH))

    try:
        import torch

        device = 0 if torch.cuda.is_available() else "cpu"
    except Exception:
        device = "cpu"
    print(f"Device: {device}")

    zones_data = load_zones(ZONES_PATH)
    print(f"Zones: {ZONES_PATH} calibrated={zones_data.get('calibrated')} count={len(zones_data.get('zones', []))}")

    intelligence = ParkingIntelligence()
    ocr = PlateOCR()

    if USE_WEBCAM:
        cap = cv2.VideoCapture(0)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    else:
        cap = open_rtsp(WIFI_CAMERA_IP, CAMERA_USER, CAMERA_PASS, CAMERA_PORT, RTSP_PATH)

    if cap is None or not cap.isOpened():
        print("WARNING: Camera unavailable — generating blank frames for stream/API testing.")
        reader = None
    else:
        reader = LatestFrameReader(cap)

    start_stream_server()
    print(f"Posting occupancy to {API_BASE}/api/ai-parking/occupancy (area_id={AREA_ID})")
    last_post = 0.0
    zones_mtime = ZONES_PATH.stat().st_mtime if ZONES_PATH.is_file() else 0

    while True:
        # hot-reload zones.json after calibration
        try:
            mtime = ZONES_PATH.stat().st_mtime
            if mtime != zones_mtime:
                zones_data = load_zones(ZONES_PATH)
                zones_mtime = mtime
                print(f"Reloaded zones.json (calibrated={zones_data.get('calibrated')})")
        except OSError:
            pass

        if reader is None:
            frame = __blank_frame()
        else:
            ret, frame = reader.read()
            if not ret:
                time.sleep(0.01)
                continue

        try:
            results = model.track(
                frame,
                imgsz=IMG_SIZE,
                conf=CONF,
                iou=IOU,
                max_det=MAX_DET,
                classes=DETECT_CLASS_IDS,
                device=device,
                verbose=False,
                persist=True,
                tracker=TRACKER,
            )
        except Exception as e:
            # Fallback if tracker weights missing
            print(f"track() failed ({e}); using predict()")
            results = model(
                frame,
                imgsz=IMG_SIZE,
                conf=CONF,
                iou=IOU,
                max_det=MAX_DET,
                classes=DETECT_CLASS_IDS,
                device=device,
                verbose=False,
            )

        annotated_boxes, detections, vehicles, person_count, vehicle_count = parse_tracks(
            results[0], frame, ocr, intelligence
        )
        slot_statuses, events, occupied_slots, use_poly = intelligence.analyze(
            vehicles, zones_data, frame.shape
        )

        # unauthorized hint: plate present but Laravel decides; flag event when plate OCR returns
        # (Laravel maps unknown/banned plates). Python only forwards plates on vehicles/events.

        annotated = draw_scene(
            frame,
            annotated_boxes,
            zones_data,
            occupied_slots,
            intelligence.active_events,
            person_count,
            vehicle_count,
            use_poly,
        )
        ok, jpeg = cv2.imencode(".jpg", annotated, [int(cv2.IMWRITE_JPEG_QUALITY), 75])
        if ok:
            STATE.set_frame(jpeg.tobytes(), vehicle_count, detections)

        now = time.time()
        if now - last_post >= POST_EVERY_SEC:
            # Enrich events with latest known plates from track memory
            for evt in events:
                tid = evt.get("track_id")
                if tid is not None and not evt.get("plate"):
                    mem = intelligence.tracks.get(int(tid))
                    if mem and mem.plate:
                        evt["plate"] = mem.plate
            post_occupancy(vehicle_count, detections, slot_statuses, events)
            last_post = now


if __name__ == "__main__":
    main()
