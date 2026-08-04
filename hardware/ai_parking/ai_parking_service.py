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
from camera_registry import CameraConfig, load_cameras
from load_env import load_project_env
from parking_rules import ParkingIntelligence
from plate_ocr import OCR_EVERY_SEC, PlateOCR

load_project_env()

# Default; open_rtsp() may override per-camera under OPEN_LOCK.
os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "yolov9c.pt"

API_BASE = os.getenv("AI_LARAVEL_API_BASE", "http://127.0.0.1:8000").rstrip("/")
AI_API_TOKEN = os.getenv("AI_PARKING_API_TOKEN", "capstone-ai-parking-dev-token-change-me")

STREAM_HOST = os.getenv("AI_STREAM_HOST", "0.0.0.0")
STREAM_PORT = int(os.getenv("AI_STREAM_PORT", "8090"))

IMG_SIZE = 640
CONF = 0.45
IOU = 0.50
MAX_DET = 50
POST_EVERY_SEC = 2.5
USE_WEBCAM = os.getenv("AI_USE_WEBCAM", "0") == "1"
TRACKER = os.getenv("AI_PARKING_TRACKER", "bytetrack.yaml")
INFER_EVERY_SEC = float(os.getenv("AI_PARKING_INFER_EVERY_SEC", "0.8"))
PREVIEW_MAX_WIDTH = int(os.getenv("AI_PARKING_PREVIEW_MAX_WIDTH", "960"))
STREAM_TARGET_FPS = float(os.getenv("AI_PARKING_STREAM_FPS", "20"))
STREAM_JPEG_QUALITY = int(os.getenv("AI_PARKING_STREAM_JPEG_QUALITY", "65"))
RECONNECT_EVERY_SEC = float(os.getenv("AI_CAMERA_RECONNECT_SEC", "5"))
OPEN_LOCK = threading.Lock()

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
    """Background RTSP reader with automatic reconnect (failure isolated per camera)."""

    def __init__(self, open_fn, label: str = "camera", flush_frames: int = 1):
        self.open_fn = open_fn
        self.label = label
        self.flush_frames = max(1, int(flush_frames))
        self.cap = None
        self.frame = None
        self.seq = 0
        self.lock = threading.Lock()
        self.running = True
        self.online = False
        self.thread = threading.Thread(target=self._loop, daemon=True, name=f"reader-{label}")
        self.thread.start()

    def _open(self):
        try:
            if self.cap is not None:
                self.cap.release()
        except Exception:
            pass
        self.cap = self.open_fn()
        self.online = self.cap is not None and self.cap.isOpened()
        return self.online

    def _read_newest(self):
        """Drop buffered/stale RTSP frames; keep only the newest."""
        # First frame via read(); then drain any already-buffered frames within a
        # short window so we don't wait on future frames (that would add latency).
        ret, frame = self.cap.read()
        if not ret or frame is None:
            return False, None
        if self.flush_frames <= 1:
            return True, frame
        deadline = time.perf_counter() + 0.008
        drained = 1
        while drained < self.flush_frames and time.perf_counter() < deadline:
            if not self.cap.grab():
                break
            ok, newer = self.cap.retrieve()
            if ok and newer is not None:
                frame = newer
            drained += 1
        return True, frame

    def _loop(self):
        fail_streak = 0
        while self.running:
            if self.cap is None or not self.cap.isOpened():
                if not self._open():
                    self.online = False
                    time.sleep(max(RECONNECT_EVERY_SEC, 8.0))
                    continue
                print(f"[{self.label}] RTSP connected")
                fail_streak = 0

            ret, frame = self._read_newest()
            if not ret or frame is None:
                fail_streak += 1
                if fail_streak >= 30:
                    print(f"[{self.label}] RTSP stalled — reconnecting…")
                    self.online = False
                    try:
                        self.cap.release()
                    except Exception:
                        pass
                    self.cap = None
                    fail_streak = 0
                    time.sleep(RECONNECT_EVERY_SEC)
                else:
                    time.sleep(0.005)
                continue

            fail_streak = 0
            self.online = True
            with self.lock:
                self.frame = frame
                self.seq += 1

    def read(self):
        with self.lock:
            if self.frame is None:
                return False, None
            return True, self.frame.copy()

    def read_if_newer(self, last_seq: int):
        """Return (ok, frame, seq). Frame is None when no newer frame than last_seq."""
        with self.lock:
            if self.frame is None:
                return False, None, last_seq
            if self.seq == last_seq:
                return True, None, last_seq
            return True, self.frame.copy(), self.seq

    def stop(self):
        self.running = False
        self.thread.join(timeout=2.0)
        try:
            if self.cap is not None:
                self.cap.release()
        except Exception:
            pass


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


# camera_id -> StreamState (multi-camera MJPEG)
STREAM_STATES: dict[str, StreamState] = {}
STREAM_PATH_INDEX: dict[str, str] = {}  # path -> camera_id


def open_rtsp(
    ip,
    user,
    password,
    port,
    path,
    *,
    label: str = "camera",
    alt_paths: list[str] | None = None,
    transport: str = "tcp",
):
    """Open RTSP. transport=tcp|udp (wired UDP is often lower latency on LAN)."""
    if not (user or "").strip() or not (password or "").strip():
        print(
            f"[{label}] ERROR: RTSP username/password missing in .env. "
            "Tapo requires Camera Account (Tapo app → Settings → Advanced → Camera Account)."
        )
        return None

    u = quote(user, safe="")
    p = quote(password, safe="")
    candidates = [path]
    if alt_paths:
        for ap in alt_paths:
            if ap and ap not in candidates:
                candidates.append(ap)

    if path.startswith("/stream"):
        for ap in ("/stream1", "/stream2"):
            if ap not in candidates:
                candidates.append(ap)

    transport = (transport or "tcp").lower()
    if transport not in ("tcp", "udp"):
        transport = "tcp"

    last_err = None
    for try_path in candidates:
        if not try_path.startswith("/"):
            try_path = "/" + try_path
        url = f"rtsp://{u}:{p}@{ip}:{port}{try_path}"
        print(f"[{label}] Trying RTSP ({transport}): rtsp://{user}:***@{ip}:{port}{try_path}")
        with OPEN_LOCK:
            # Per-open transport + low-delay hints (OpenCV FFmpeg option string).
            os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
                f"rtsp_transport;{transport}|fflags;nobuffer|flags;low_delay|max_delay;0"
            )
            cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if not cap.isOpened():
            cap.release()
            last_err = "open_failed"
            continue
        ret, frame = cap.read()
        if not ret or frame is None:
            cap.release()
            last_err = "no_frame"
            continue
        print(f"[{label}] Camera OK — {frame.shape[1]}x{frame.shape[0]} via {try_path} ({transport})")
        return cap

    print(f"[{label}] RTSP failed ({last_err}) via {transport}. Check IP, credentials, and path.")
    if transport == "udp":
        print(f"[{label}] Retrying with TCP…")
        return open_rtsp(
            ip, user, password, port, path,
            label=label, alt_paths=alt_paths, transport="tcp",
        )
    return None


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


def draw_scene_lite(frame, annotated_boxes, occupied_slots, person_count, vehicle_count):
    """Minimal overlay for low-latency live preview (skip zone polygons / event list)."""
    annotated = frame  # in-place; caller passes a disposable resize copy
    for x1, y1, x2, y2, name, conf, track_id, plate in annotated_boxes:
        color = CLASS_COLORS.get(name, (0, 220, 0))
        cv2.rectangle(annotated, (x1, y1), (x2, y2), color, 1)
        if track_id is not None:
            cv2.putText(
                annotated,
                f"#{track_id}",
                (x1, max(y1 - 6, 16)),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.45,
                color,
                1,
                cv2.LINE_AA,
            )
    n_occ = len(occupied_slots) if occupied_slots else 0
    summary = f"P:{person_count} V:{vehicle_count} Occ:{n_occ}"
    cv2.putText(annotated, summary, (12, 28), cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 255, 0), 2, cv2.LINE_AA)
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
        with urlrequest.urlopen(req, timeout=15) as resp:
            print(f"Laravel HTTP {resp.status}: {path} cam={payload.get('camera_id')} vehicles={payload.get('vehicle_count')} slots={len(payload.get('slots') or [])} events={len(payload.get('events') or [])}")
            return True
    except HTTPError as e:
        print(f"Laravel HTTP {e.code}: {e.read().decode('utf-8', errors='replace')}")
    except URLError as e:
        print(f"Laravel connection failed: {e.reason}")
    except Exception as e:
        print(f"Laravel POST error: {e}")
    return False


def post_occupancy_async(camera_id: str, area_id: int, vehicle_count, detections, slots, events):
    payload = {
        "camera_id": camera_id,
        "area_id": area_id,
        "vehicle_count": vehicle_count,
        "detections": list(detections),
        "slots": list(slots) if slots else [],
        "events": list(events),
        "mode": "slots" if slots else "count",
    }
    thread = threading.Thread(
        target=post_json,
        args=("/api/ai-parking/occupancy", payload),
        daemon=True,
        name=f"post-{camera_id}",
    )
    thread.start()


def resize_for_infer(frame, max_width: int):
    h, w = frame.shape[:2]
    if w <= max_width:
        return frame, 1.0
    scale = max_width / w
    new_size = (max_width, max(1, int(h * scale)))
    return cv2.resize(frame, new_size, interpolation=cv2.INTER_AREA), scale


def scale_annotated_boxes(boxes, scale: float):
    if scale == 1.0:
        return boxes
    inv = 1.0 / scale
    scaled = []
    for x1, y1, x2, y2, name, conf, track_id, plate in boxes:
        scaled.append((
            int(x1 * inv), int(y1 * inv), int(x2 * inv), int(y2 * inv),
            name, conf, track_id, plate,
        ))
    return scaled


def scale_vehicles(vehicles, scale: float):
    if scale == 1.0:
        return vehicles
    inv = 1.0 / scale
    scaled = []
    for vehicle in vehicles:
        x1, y1, x2, y2 = vehicle["xyxy"]
        scaled.append({
            **vehicle,
            "xyxy": (int(x1 * inv), int(y1 * inv), int(x2 * inv), int(y2 * inv)),
        })
    return scaled


def scale_boxes_to_frame(boxes, src_shape, dst_shape):
    """Scale box coords from src frame size to dst frame size."""
    sh, sw = src_shape[:2]
    dh, dw = dst_shape[:2]
    if sw == dw and sh == dh:
        return boxes
    sx = dw / sw
    sy = dh / sh
    scaled = []
    for x1, y1, x2, y2, name, conf, track_id, plate in boxes:
        scaled.append((
            int(x1 * sx), int(y1 * sy), int(x2 * sx), int(y2 * sy),
            name, conf, track_id, plate,
        ))
    return scaled


class SharedSceneState:
    def __init__(self):
        self.lock = threading.Lock()
        self.data = {
            "annotated_boxes": [],
            "person_count": 0,
            "vehicle_count": 0,
            "occupied_slots": set(),
            "active_events": [],
            "use_poly": False,
            "detections": [],
            "slot_statuses": [],
            "events": [],
            "source_shape": None,
        }

    def update(self, **kwargs):
        with self.lock:
            self.data.update(kwargs)

    def snapshot(self):
        with self.lock:
            return {
                "annotated_boxes": list(self.data["annotated_boxes"]),
                "person_count": self.data["person_count"],
                "vehicle_count": self.data["vehicle_count"],
                "occupied_slots": set(self.data["occupied_slots"]),
                "active_events": list(self.data["active_events"]),
                "use_poly": self.data["use_poly"],
                "detections": list(self.data["detections"]),
                "slot_statuses": list(self.data["slot_statuses"]),
                "events": list(self.data["events"]),
                "source_shape": self.data.get("source_shape"),
            }


def post_occupancy(camera_id: str, area_id: int, vehicle_count, detections, slots, events):
    payload = {
        "camera_id": camera_id,
        "area_id": area_id,
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

    def _resolve_camera_id(self) -> str | None:
        path = self.path.split("?", 1)[0]
        if path in STREAM_PATH_INDEX:
            return STREAM_PATH_INDEX[path]
        if path in ("/stream.mjpg", "/"):
            # Legacy alias → first registered camera
            return next(iter(STREAM_STATES.keys()), None)
        # /CAM-AI-2/stream.mjpg style
        parts = [p for p in path.split("/") if p]
        if len(parts) >= 2 and parts[-1] == "stream.mjpg":
            cam = parts[0]
            if cam in STREAM_STATES:
                return cam
        return None

    def do_GET(self):
        path = self.path.split("?", 1)[0]
        if path == "/":
            self.send_response(200)
            self.send_header("Content-Type", "text/html")
            self.end_headers()
            links = "".join(
                f'<li><a href="{p}">{cid}</a></li>' for p, cid in STREAM_PATH_INDEX.items()
            )
            self.wfile.write(
                f"<html><body><h3>AI Parking Streams</h3><ul>{links}</ul></body></html>".encode("utf-8")
            )
            return

        if path == "/health":
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            body = json.dumps({"cameras": list(STREAM_STATES.keys()), "ok": True}).encode("utf-8")
            self.wfile.write(body)
            return

        camera_id = self._resolve_camera_id()
        if camera_id is None or camera_id not in STREAM_STATES:
            self.send_error(404)
            return

        state = STREAM_STATES[camera_id]
        self.send_response(200)
        self.send_header("Age", "0")
        self.send_header("Cache-Control", "no-cache, private")
        self.send_header("Pragma", "no-cache")
        self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        self.end_headers()

        last_sent = None
        try:
            while True:
                jpeg = state.get_jpeg()
                if jpeg is None:
                    time.sleep(0.01)
                    continue
                if jpeg is last_sent:
                    time.sleep(0.005)
                    continue
                last_sent = jpeg
                self.wfile.write(b"--frame\r\n")
                self.wfile.write(b"Content-Type: image/jpeg\r\n")
                self.wfile.write(f"Content-Length: {len(jpeg)}\r\n\r\n".encode("ascii"))
                self.wfile.write(jpeg)
                self.wfile.write(b"\r\n")
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            return


def start_stream_server():
    server = ThreadingHTTPServer((STREAM_HOST, STREAM_PORT), MjpegHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True, name="mjpeg-http")
    thread.start()
    print(f"MJPEG base: http://{STREAM_HOST}:{STREAM_PORT}/")
    for path, cam in STREAM_PATH_INDEX.items():
        print(f"  {cam}: http://127.0.0.1:{STREAM_PORT}{path}")
    return server


def blank_frame(label: str = "Camera offline"):
    import numpy as np

    frame = np.zeros((480, 640, 3), dtype=np.uint8)
    cv2.putText(
        frame,
        label[:48],
        (24, 240),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.65,
        (0, 200, 255),
        2,
        cv2.LINE_AA,
    )
    return frame


class CameraWorker:
    """Independent preview + YOLO + occupancy pipeline for one RTSP camera."""

    def __init__(self, config: CameraConfig, model, device, model_lock: threading.Lock, ocr: PlateOCR):
        self.config = config
        self.model = model
        self.device = device
        self.model_lock = model_lock
        self.ocr = ocr
        self.intelligence = ParkingIntelligence()
        self.scene = SharedSceneState()
        self.state = StreamState()
        self.running = threading.Event()
        self.preview_reader = None
        self.infer_reader = None
        self._shared_reader = False
        zones_path = Path(config.zones_file)
        if not zones_path.is_file():
            zones_path = BASE_DIR / "zones.json"
        self.zones_holder = [load_zones(zones_path)]
        self.zones_path = zones_path
        self.preview_max_width = int(config.preview_max_width or PREVIEW_MAX_WIDTH)
        self.stream_fps = float(config.stream_fps or STREAM_TARGET_FPS)
        self.jpeg_quality = int(config.jpeg_quality or STREAM_JPEG_QUALITY)
        self.lite_preview = bool(config.lite_preview)

    def start(self):
        STREAM_STATES[self.config.camera_id] = self.state
        STREAM_PATH_INDEX[self.config.stream_path] = self.config.camera_id
        alt = f"/{self.config.camera_id}/stream.mjpg"
        if alt not in STREAM_PATH_INDEX:
            STREAM_PATH_INDEX[alt] = self.config.camera_id

        self.running.set()

        if not self.config.has_credentials:
            print(
                f"[{self.config.camera_id}] Cannot open RTSP without credentials. "
                "Fill AI_CAMERA_N_USER / AI_CAMERA_N_PASS then restart this service."
            )

        preview_path = self.config.preview_path
        infer_path = self.config.rtsp_path
        dual = (
            not USE_WEBCAM
            and preview_path
            and infer_path
            and preview_path != infer_path
        )

        def open_path(rtsp_path: str, role: str):
            if not self.config.has_credentials:
                return None
            if USE_WEBCAM and self.config.camera_id.endswith("1"):
                cap = cv2.VideoCapture(0)
                cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
                return cap if cap.isOpened() else None
            return open_rtsp(
                self.config.ip,
                self.config.user,
                self.config.password,
                self.config.port,
                rtsp_path,
                label=f"{self.config.camera_id}/{role}",
                transport=self.config.rtsp_transport,
            )

        flush = self.config.flush_frames
        self.preview_reader = LatestFrameReader(
            lambda: open_path(preview_path, "preview"),
            label=f"{self.config.camera_id}-preview",
            flush_frames=flush,
        )
        if dual:
            self.infer_reader = LatestFrameReader(
                lambda: open_path(infer_path, "infer"),
                label=f"{self.config.camera_id}-infer",
                flush_frames=max(1, flush // 2),
            )
            self._shared_reader = False
        else:
            self.infer_reader = self.preview_reader
            self._shared_reader = True

        threading.Thread(
            target=self._preview_loop,
            name=f"preview-{self.config.camera_id}",
            daemon=True,
        ).start()
        threading.Thread(
            target=self._inference_loop,
            name=f"infer-{self.config.camera_id}",
            daemon=True,
        ).start()
        dual_note = f" preview={preview_path}" if dual else ""
        print(
            f"[{self.config.camera_id}] started → area_id={self.config.area_id} "
            f"rtsp={self.config.ip}{infer_path}{dual_note} "
            f"transport={self.config.rtsp_transport} "
            f"stream={self.config.stream_path} "
            f"{self.stream_fps:.0f}fps@{self.preview_max_width}px q={self.jpeg_quality}"
            f"{' lite' if self.lite_preview else ''}"
        )

    def stop(self):
        self.running.clear()
        if self.preview_reader:
            self.preview_reader.stop()
        if self.infer_reader and not self._shared_reader:
            self.infer_reader.stop()

    def _preview_loop(self):
        interval = 1.0 / max(1.0, self.stream_fps)
        encode_params = [int(cv2.IMWRITE_JPEG_QUALITY), self.jpeg_quality]
        last_seq = -1
        while self.running.is_set():
            started = time.perf_counter()
            zones_data = self.zones_holder[0]
            offline_reason = f"{self.config.camera_id} offline"
            if not self.config.has_credentials:
                offline_reason = f"{self.config.camera_id}: set USER/PASS in .env"
            elif self.preview_reader is not None and not getattr(self.preview_reader, "online", False):
                offline_reason = f"{self.config.camera_id}: RTSP reconnecting"

            if self.preview_reader is None:
                frame = blank_frame(offline_reason)
                src_shape = frame.shape
                is_new = True
            else:
                ret, frame, last_seq = self.preview_reader.read_if_newer(last_seq)
                if not ret:
                    frame = blank_frame(offline_reason)
                    src_shape = frame.shape
                    is_new = True
                elif frame is None:
                    # No newer frame — skip encode to stay caught up
                    time.sleep(0.001)
                    continue
                else:
                    src_shape = frame.shape
                    is_new = True

            if not is_new:
                time.sleep(0.001)
                continue

            display, _ = resize_for_infer(frame, self.preview_max_width)
            if display is frame and self.lite_preview:
                display = frame.copy()
            state = self.scene.snapshot()
            # Boxes are in infer-stream coordinates (may differ from preview substream)
            box_src = state.get("source_shape") or src_shape
            boxes = scale_boxes_to_frame(state["annotated_boxes"], box_src, display.shape)
            if self.lite_preview:
                annotated = draw_scene_lite(
                    display,
                    boxes,
                    state["occupied_slots"],
                    state["person_count"],
                    state["vehicle_count"],
                )
            else:
                annotated = draw_scene(
                    display,
                    boxes,
                    zones_data,
                    state["occupied_slots"],
                    state["active_events"],
                    state["person_count"],
                    state["vehicle_count"],
                    state["use_poly"],
                )
            cv2.putText(
                annotated,
                self.config.camera_id,
                (16, annotated.shape[0] - 16),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.55,
                (255, 255, 255),
                2,
                cv2.LINE_AA,
            )
            ok, jpeg = cv2.imencode(".jpg", annotated, encode_params)
            if ok:
                self.state.set_frame(jpeg.tobytes(), state["vehicle_count"], state["detections"])
            elapsed = time.perf_counter() - started
            time.sleep(max(0.0, interval - elapsed))

    def _inference_loop(self):
        last_post = 0.0
        last_infer = 0.0
        zones_mtime = self.zones_path.stat().st_mtime if self.zones_path.is_file() else 0

        while self.running.is_set():
            try:
                if self.zones_path.is_file():
                    mtime = self.zones_path.stat().st_mtime
                    if mtime != zones_mtime:
                        self.zones_holder[0] = load_zones(self.zones_path)
                        zones_mtime = mtime
                        print(f"[{self.config.camera_id}] Reloaded zones")
            except OSError:
                pass

            now = time.time()
            if now - last_infer < INFER_EVERY_SEC:
                time.sleep(0.05)
                continue

            if self.infer_reader is None:
                time.sleep(0.2)
                continue

            ret, frame = self.infer_reader.read()
            if not ret:
                time.sleep(0.05)
                continue

            # YOLO uses its own downscale; keep infer resolution independent of preview width
            infer_max = max(self.preview_max_width, 960)
            infer_frame, scale = resize_for_infer(frame, infer_max)
            try:
                with self.model_lock:
                    try:
                        results = self.model.track(
                            infer_frame,
                            imgsz=IMG_SIZE,
                            conf=CONF,
                            iou=IOU,
                            max_det=MAX_DET,
                            classes=DETECT_CLASS_IDS,
                            device=self.device,
                            verbose=False,
                            persist=True,
                            tracker=TRACKER,
                        )
                    except Exception as e:
                        print(f"[{self.config.camera_id}] track() failed ({e}); using predict()")
                        results = self.model(
                            infer_frame,
                            imgsz=IMG_SIZE,
                            conf=CONF,
                            iou=IOU,
                            max_det=MAX_DET,
                            classes=DETECT_CLASS_IDS,
                            device=self.device,
                            verbose=False,
                        )
            except Exception as e:
                print(f"[{self.config.camera_id}] inference error: {e}")
                time.sleep(0.5)
                continue

            annotated_boxes, detections, vehicles, person_count, vehicle_count = parse_tracks(
                results[0], infer_frame, self.ocr, self.intelligence
            )
            vehicles = scale_vehicles(vehicles, scale)
            annotated_boxes = scale_annotated_boxes(annotated_boxes, scale)
            slot_statuses, events, occupied_slots, use_poly = self.intelligence.analyze(
                vehicles, self.zones_holder[0], frame.shape
            )

            self.scene.update(
                annotated_boxes=annotated_boxes,
                person_count=person_count,
                vehicle_count=vehicle_count,
                occupied_slots=occupied_slots,
                active_events=list(self.intelligence.active_events),
                use_poly=use_poly,
                detections=detections,
                slot_statuses=slot_statuses,
                events=events,
                source_shape=frame.shape,
            )
            last_infer = now

            if now - last_post >= POST_EVERY_SEC:
                post_events = list(events)
                for evt in post_events:
                    tid = evt.get("track_id")
                    if tid is not None and not evt.get("plate"):
                        mem = self.intelligence.tracks.get(int(tid))
                        if mem and mem.plate:
                            evt["plate"] = mem.plate
                post_occupancy_async(
                    self.config.camera_id,
                    self.config.area_id,
                    vehicle_count,
                    detections,
                    slot_statuses,
                    post_events,
                )
                last_post = now


def main():
    if not MODEL_PATH.is_file():
        raise SystemExit(f"Model not found: {MODEL_PATH}")

    cameras = load_cameras(BASE_DIR)
    if not cameras:
        raise SystemExit("No cameras configured. Set AI_CAMERA_1_IP (and optionally AI_CAMERA_2_IP / AI_CAMERA_3_IP).")

    print(f"Loading {MODEL_PATH}...")
    model = YOLO(str(MODEL_PATH))
    try:
        import torch

        device = 0 if torch.cuda.is_available() else "cpu"
    except Exception:
        device = "cpu"
    print(f"Device: {device}")
    print(f"Cameras: {len(cameras)}")

    ocr = PlateOCR()
    model_lock = threading.Lock()
    workers: list[CameraWorker] = []

    for cfg in cameras:
        worker = CameraWorker(cfg, model, device, model_lock, ocr)
        worker.start()
        workers.append(worker)

    start_stream_server()
    print(f"Posting occupancy to {API_BASE}/api/ai-parking/occupancy")
    print(
        f"Stream {STREAM_TARGET_FPS:.0f} fps @ {PREVIEW_MAX_WIDTH}px | "
        f"inference every {INFER_EVERY_SEC}s | OCR={'on' if ocr.enabled else 'off'}"
    )

    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        for w in workers:
            w.stop()
        print("Stopped.")


if __name__ == "__main__":
    main()
