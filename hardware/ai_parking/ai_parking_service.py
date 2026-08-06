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

# Load .env before parking_rules / plate_ocr read tunables at import time.
load_project_env()

from parking_rules import ParkingIntelligence, SimpleIoUTracker
from plate_ocr import OCR_EVERY_SEC, AsyncPlateQueue, PlateOCR

# Default; open_rtsp() may override per-camera under OPEN_LOCK.
os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "yolov9c.pt"

API_BASE = os.getenv("AI_LARAVEL_API_BASE", "http://127.0.0.1:8000").rstrip("/")
AI_API_TOKEN = os.getenv("AI_PARKING_API_TOKEN", "capstone-ai-parking-dev-token-change-me")

STREAM_HOST = os.getenv("AI_STREAM_HOST", "0.0.0.0")
STREAM_PORT = int(os.getenv("AI_STREAM_PORT", "8090"))

IMG_SIZE = int(os.getenv("AI_PARKING_IMG_SIZE", "640"))
# Lower conf for distant lot cameras; raise via .env if too many false positives.
CONF = float(os.getenv("AI_PARKING_CONF", "0.28"))
IOU = float(os.getenv("AI_PARKING_IOU", "0.50"))
MAX_DET = int(os.getenv("AI_PARKING_MAX_DET", "30"))
# Tiny-box filter (fraction of infer frame). Keep low so distant cars remain.
MIN_BOX_AREA_FRAC = float(os.getenv("AI_PARKING_MIN_BOX_AREA", "0.0005"))
# Infer width for live boxes (OCR still uses full-res RTSP crops asynchronously).
INFER_MAX_WIDTH = int(os.getenv("AI_PARKING_INFER_MAX_WIDTH", "960"))
# Shared YOLO + ByteTrack persist=True breaks multi-cam; default to predict + IoU IDs.
USE_ULTRALYTICS_TRACK = os.getenv("AI_PARKING_USE_TRACKER", "0") == "1"
POST_EVERY_SEC = float(os.getenv("AI_PARKING_POST_EVERY_SEC", "1.5"))
USE_WEBCAM = os.getenv("AI_USE_WEBCAM", "0") == "1"
TRACKER = os.getenv("AI_PARKING_TRACKER", "bytetrack.yaml")
# Target YOLO cadence; actual rate is also limited by CPU + model lock.
INFER_EVERY_SEC = float(os.getenv("AI_PARKING_INFER_EVERY_SEC", "0.30"))
# Keep last boxes briefly when a frame misses, so overlays don't flicker.
BOX_HOLD_SEC = float(os.getenv("AI_PARKING_BOX_HOLD_SEC", "0.45"))
PREVIEW_MAX_WIDTH = int(os.getenv("AI_PARKING_PREVIEW_MAX_WIDTH", "960"))
STREAM_TARGET_FPS = float(os.getenv("AI_PARKING_STREAM_FPS", "20"))
STREAM_JPEG_QUALITY = int(os.getenv("AI_PARKING_STREAM_JPEG_QUALITY", "65"))
RECONNECT_EVERY_SEC = float(os.getenv("AI_CAMERA_RECONNECT_SEC", "5"))
OPEN_LOCK = threading.Lock()

DETECT_CLASS_IDS = [2, 3, 5, 7]  # vehicles only (faster); set AI_PARKING_DETECT_PERSONS=1 for people
VEHICLE_CLASS_IDS = {2, 3, 5, 7}
if os.getenv("AI_PARKING_DETECT_PERSONS", "0") == "1":
    DETECT_CLASS_IDS = [0, 2, 3, 5, 7]
POST_PERSON_DETECTIONS = os.getenv("AI_PARKING_POST_PERSONS", "0") == "1"
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


def _box_iou(a, b) -> float:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b
    ix1, iy1 = max(ax1, bx1), max(ay1, by1)
    ix2, iy2 = min(ax2, bx2), min(ay2, by2)
    iw, ih = max(0, ix2 - ix1), max(0, iy2 - iy1)
    inter = iw * ih
    if inter <= 0:
        return 0.0
    area_a = max(0, ax2 - ax1) * max(0, ay2 - ay1)
    area_b = max(0, bx2 - bx1) * max(0, by2 - by1)
    union = area_a + area_b - inter
    return inter / union if union > 0 else 0.0


def _dedupe_vehicle_rows(rows: list[dict], iou_thresh: float = 0.65) -> list[dict]:
    """Keep highest-confidence box when two same-class vehicles heavily overlap."""
    if len(rows) <= 1:
        return rows
    rows = sorted(rows, key=lambda r: float(r.get("confidence") or 0), reverse=True)
    kept: list[dict] = []
    for row in rows:
        xy = row["xyxy"]
        cls = row.get("class")
        if any(
            k.get("class") == cls and _box_iou(xy, k["xyxy"]) >= iou_thresh
            for k in kept
        ):
            continue
        kept.append(row)
    return kept


def parse_tracks(
    result,
    frame,
    intelligence: ParkingIntelligence,
    tracker: SimpleIoUTracker | None = None,
    ocr_frame=None,
    box_to_ocr_scale: float = 1.0,
    plate_queue: AsyncPlateQueue | None = None,
    camera_id: str = "",
):
    """Build detection list + vehicle dicts with track ids and optional plates.

    Plate OCR is queued asynchronously so this stays realtime.
    ocr_frame: full-resolution frame for plate OCR (defaults to `frame`).
    box_to_ocr_scale: multiply infer-frame box coords to map onto ocr_frame.
    """
    annotated_boxes = []
    detections = []
    vehicles = []
    person_count = 0
    boxes = result.boxes
    if boxes is None or len(boxes) == 0:
        return annotated_boxes, detections, vehicles, 0, 0

    now = time.time()
    fh, fw = frame.shape[:2]
    frame_area = max(1, fh * fw)
    raw_vehicles: list[dict] = []
    ocr_src = ocr_frame if ocr_frame is not None else frame
    scale_ocr = float(box_to_ocr_scale) if box_to_ocr_scale else 1.0

    for box in boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])
        name = COCO_NAMES.get(cls_id, f"class_{cls_id}")
        track_id = None
        if box.id is not None:
            track_id = int(box.id[0])

        if cls_id == 0:
            person_count += 1
            annotated_boxes.append((x1, y1, x2, y2, name, conf, track_id, None, "pending", None))
            if POST_PERSON_DETECTIONS:
                detections.append({"class": name, "confidence": round(conf, 3), "track_id": track_id})
            continue
        if cls_id not in VEHICLE_CLASS_IDS:
            continue

        box_area = max(0, x2 - x1) * max(0, y2 - y1)
        if box_area / frame_area < MIN_BOX_AREA_FRAC:
            continue

        raw_vehicles.append({
            "xyxy": (x1, y1, x2, y2),
            "track_id": track_id,
            "class": name,
            "confidence": conf,
            "cls_id": cls_id,
        })

    raw_vehicles = _dedupe_vehicle_rows(raw_vehicles)
    if tracker is not None:
        for row in raw_vehicles:
            row["track_id"] = None
        raw_vehicles = tracker.update(raw_vehicles, now)

    for row in raw_vehicles:
        x1, y1, x2, y2 = row["xyxy"]
        conf = float(row["confidence"])
        name = row["class"]
        track_id = row.get("track_id")

        plate = None
        plate_status = "pending"
        ocr_confidence = 0.0

        ox1 = int(x1 * scale_ocr)
        oy1 = int(y1 * scale_ocr)
        ox2 = int(x2 * scale_ocr)
        oy2 = int(y2 * scale_ocr)

        if track_id is not None:
            mem = intelligence.touch_track(track_id, now)
            plate = mem.plate
            plate_status = mem.plate_status
            ocr_confidence = mem.ocr_confidence
            mem.last_xyxy = (x1, y1, x2, y2)
            if plate_queue is not None:
                plate_queue.submit(
                    camera_id,
                    track_id,
                    ocr_src,
                    (ox1, oy1, ox2, oy2),
                    intelligence,
                    OCR_EVERY_SEC,
                    cls_id=row.get("cls_id"),
                )
            # Refresh after possible prior async result
            plate = mem.plate
            plate_status = mem.plate_status
            ocr_confidence = mem.ocr_confidence
            if mem.needs_owner_lookup():
                from plate_owner_lookup import lookup_plate_async

                lookup_plate_async(mem)

        owner_label = None
        owner_name = None
        vehicle_details = None
        department = None
        registration_status = None
        registered = None
        if track_id is not None:
            mem = intelligence.tracks.get(int(track_id))
            if mem is not None:
                owner_label = mem.overlay_owner_line()
                owner_name = mem.owner_name
                vehicle_details = mem.vehicle_details
                department = mem.department
                registration_status = mem.registration_status
                registered = mem.registered

        # Normalized box for UIs (0–1).
        nx1 = round(x1 / max(fw, 1), 4)
        ny1 = round(y1 / max(fh, 1), 4)
        nx2 = round(x2 / max(fw, 1), 4)
        ny2 = round(y2 / max(fh, 1), 4)

        det = {
            "class": name,
            "confidence": round(conf, 3),
            "track_id": track_id,
            "plate_status": plate_status,
            "xyxy": [nx1, ny1, nx2, ny2],
        }
        if plate:
            det["plate"] = plate
        if ocr_confidence > 0:
            det["ocr_confidence"] = round(float(ocr_confidence), 3)
        if plate_status == "unreadable":
            det["plate"] = None
        if owner_name:
            det["owner_name"] = owner_name
        if owner_label:
            det["owner_label"] = owner_label
        if vehicle_details:
            det["vehicle_details"] = vehicle_details
        if department:
            det["department"] = department
        if registration_status:
            det["registration_status"] = registration_status
        if registered is not None:
            det["registered"] = registered

        detections.append(det)
        annotated_boxes.append((x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label))
        vehicles.append({
            "xyxy": (x1, y1, x2, y2),
            "track_id": track_id,
            "class": name,
            "confidence": conf,
            "plate": plate,
            "plate_status": plate_status,
        })

    vehicle_count = len(vehicles)
    return annotated_boxes, detections, vehicles, person_count, vehicle_count


def _draw_box_labels(annotated, x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, lite=False):
    color = CLASS_COLORS.get(name, (0, 220, 0))
    cv2.rectangle(annotated, (x1, y1), (x2, y2), color, 2)
    lines = []
    class_label = name if not lite else (name[0].upper() if name else "?")
    if lite:
        head = f"{class_label}{conf * 100:.0f}"
    else:
        head = f"{name} {conf * 100:.0f}%"
    if track_id is not None:
        head = f"#{track_id} {head}"
    lines.append(head)
    if plate_status == "unreadable":
        lines.append("Plate Unreadable")
    elif plate:
        lines.append(str(plate))
        if owner_label:
            lines.append(str(owner_label)[:28])
    font = cv2.FONT_HERSHEY_SIMPLEX
    scale = 0.42 if lite else 0.52
    thick = 1 if lite else 2
    ty = max(y1 - 8, 16 + 16 * (len(lines) - 1))
    for i, line in enumerate(lines[:3] if lite else lines[:4]):
        cv2.putText(
            annotated,
            line,
            (x1, max(16, ty - (len(lines) - 1 - i) * (14 if lite else 16))),
            font,
            scale,
            color,
            thick,
            cv2.LINE_AA,
        )


def draw_scene(frame, annotated_boxes, zones_data, occupied_slots, active_events, person_count, vehicle_count, use_poly):
    annotated = frame.copy()
    draw_zones(annotated, zones_data, occupied_slots)

    for box in annotated_boxes:
        if len(box) >= 10:
            x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label = box[:10]
        else:
            x1, y1, x2, y2, name, conf, track_id, plate = box[:8]
            plate_status, owner_label = ("ok" if plate else "pending"), None
        _draw_box_labels(annotated, x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, lite=False)

    mode = "slots" if use_poly else "count-fallback"
    summary = f"People: {person_count} | Vehicles: {vehicle_count} | Mode: {mode}"
    # Bottom-left so we do not cover the camera OSD date/time (usually top of frame).
    h, w = annotated.shape[:2]
    bar_top = h - 40
    bar_bottom = h - 8
    bar_right = min(w - 8, 16 + int(len(summary) * 11))
    cv2.rectangle(annotated, (8, bar_top), (bar_right, bar_bottom), (0, 0, 0), -1)
    cv2.putText(
        annotated,
        summary,
        (16, h - 16),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.65,
        (0, 255, 0),
        2,
        cv2.LINE_AA,
    )

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
    for box in annotated_boxes:
        if len(box) >= 10:
            x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label = box[:10]
        else:
            x1, y1, x2, y2, name, conf, track_id, plate = box[:8]
            plate_status, owner_label = ("ok" if plate else "pending"), None
        _draw_box_labels(annotated, x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, lite=True)
    n_occ = len(occupied_slots) if occupied_slots else 0
    summary = f"P:{person_count} V:{vehicle_count} Occ:{n_occ}"
    h = annotated.shape[0]
    cv2.putText(
        annotated,
        summary,
        (12, h - 12),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.6,
        (0, 255, 0),
        2,
        cv2.LINE_AA,
    )
    return annotated


def encode_evidence_jpeg(frame, xyxy=None, max_side: int = 640, quality: int = 70) -> str | None:
    """Crop (optional) and return base64 JPEG for violation evidence (size-capped)."""
    import base64

    if frame is None:
        return None
    try:
        crop = frame
        if xyxy is not None:
            h, w = frame.shape[:2]
            x1, y1, x2, y2 = [int(v) for v in xyxy]
            pad = 12
            x1 = max(0, x1 - pad)
            y1 = max(0, y1 - pad)
            x2 = min(w, x2 + pad)
            y2 = min(h, y2 + pad)
            if x2 > x1 and y2 > y1:
                crop = frame[y1:y2, x1:x2]
        ch, cw = crop.shape[:2]
        scale = min(1.0, max_side / max(ch, cw, 1))
        if scale < 1.0:
            crop = cv2.resize(crop, (max(1, int(cw * scale)), max(1, int(ch * scale))), interpolation=cv2.INTER_AREA)
        ok, buf = cv2.imencode(".jpg", crop, [int(cv2.IMWRITE_JPEG_QUALITY), quality])
        if not ok:
            return None
        raw = buf.tobytes()
        if len(raw) > 450000:
            ok, buf = cv2.imencode(".jpg", crop, [int(cv2.IMWRITE_JPEG_QUALITY), 50])
            if not ok:
                return None
            raw = buf.tobytes()
        return base64.b64encode(raw).decode("ascii")
    except Exception:
        return None


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
        with urlrequest.urlopen(req, timeout=4) as resp:
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
    for box in boxes:
        x1, y1, x2, y2 = box[0], box[1], box[2], box[3]
        rest = box[4:]
        scaled.append((int(x1 * inv), int(y1 * inv), int(x2 * inv), int(y2 * inv), *rest))
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
    for box in boxes:
        x1, y1, x2, y2 = box[0], box[1], box[2], box[3]
        rest = box[4:]
        scaled.append((int(x1 * sx), int(y1 * sy), int(x2 * sx), int(y2 * sy), *rest))
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

    def __init__(
        self,
        config: CameraConfig,
        model,
        device,
        model_lock: threading.Lock,
        plate_queue: AsyncPlateQueue,
    ):
        self.config = config
        self.model = model
        self.device = device
        self.model_lock = model_lock
        self.plate_queue = plate_queue
        self.intelligence = ParkingIntelligence()
        self.tracker = SimpleIoUTracker()
        self.scene = SharedSceneState()
        self.state = StreamState()
        self.running = threading.Event()
        self.preview_reader = None
        self.infer_reader = None
        self._shared_reader = False
        self._last_detect_log = 0.0
        self._held_boxes = []
        self._held_vehicles = []
        self._held_detections = []
        self._held_until = 0.0
        self._held_counts = (0, 0)
        zones_path = Path(config.zones_file)
        if not zones_path.is_file():
            zones_path = BASE_DIR / "zones.json"
        self.zones_holder = [load_zones(zones_path)]
        self.zones_path = zones_path
        self.preview_max_width = int(config.preview_max_width or PREVIEW_MAX_WIDTH)
        self.infer_max_width = int(config.infer_max_width) if getattr(config, "infer_max_width", 0) else INFER_MAX_WIDTH
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
            f"{self.stream_fps:.0f}fps@{self.preview_max_width}px infer≤{self.infer_max_width}px q={self.jpeg_quality}"
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
            wait = INFER_EVERY_SEC - (now - last_infer)
            if wait > 0:
                time.sleep(min(wait, 0.05))
                continue

            if self.infer_reader is None:
                time.sleep(0.1)
                continue

            if self.infer_reader is not None and not getattr(self.infer_reader, "online", True):
                time.sleep(0.2)
                continue

            ret, frame = self.infer_reader.read()
            if not ret:
                time.sleep(0.02)
                continue

            # Faster live boxes; plate OCR still uses full-res crops asynchronously.
            # Tapo can set AI_CAMERA_N_INFER_MAX_WIDTH=2304 for sharper plate crops.
            infer_max = max(int(self.infer_max_width or INFER_MAX_WIDTH), 640)
            infer_frame, scale = resize_for_infer(frame, infer_max)
            box_to_ocr_scale = (1.0 / scale) if scale and scale > 0 else 1.0
            try:
                with self.model_lock:
                    if USE_ULTRALYTICS_TRACK:
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
                            results = self.model.predict(
                                infer_frame,
                                imgsz=IMG_SIZE,
                                conf=CONF,
                                iou=IOU,
                                max_det=MAX_DET,
                                classes=DETECT_CLASS_IDS,
                                device=self.device,
                                verbose=False,
                            )
                    else:
                        results = self.model.predict(
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
                time.sleep(0.25)
                continue

            raw_boxes = 0
            try:
                if results and results[0].boxes is not None:
                    raw_boxes = len(results[0].boxes)
            except Exception:
                raw_boxes = 0

            annotated_boxes, detections, vehicles, person_count, vehicle_count = parse_tracks(
                results[0],
                infer_frame,
                self.intelligence,
                tracker=None if USE_ULTRALYTICS_TRACK else self.tracker,
                ocr_frame=frame,
                box_to_ocr_scale=box_to_ocr_scale,
                plate_queue=self.plate_queue,
                camera_id=self.config.camera_id,
            )
            vehicles = scale_vehicles(vehicles, scale)
            annotated_boxes = scale_annotated_boxes(annotated_boxes, scale)

            # Hold last boxes briefly when YOLO misses a frame (smoother overlay).
            if annotated_boxes:
                self._held_boxes = list(annotated_boxes)
                self._held_vehicles = list(vehicles)
                self._held_detections = list(detections)
                self._held_counts = (person_count, vehicle_count)
                self._held_until = now + BOX_HOLD_SEC
            elif now < self._held_until and self._held_boxes:
                annotated_boxes = list(self._held_boxes)
                vehicles = list(self._held_vehicles)
                detections = list(self._held_detections)
                person_count, vehicle_count = self._held_counts

            slot_statuses, events, occupied_slots, use_poly = self.intelligence.analyze(
                vehicles, self.zones_holder[0], frame.shape
            )

            if now - self._last_detect_log >= 10.0:
                plated = sum(1 for d in detections if d.get("plate"))
                print(
                    f"[{self.config.camera_id}] detect raw={raw_boxes} vehicles={vehicle_count} "
                    f"plates={plated} conf>={CONF} infer={infer_frame.shape[1]}x{infer_frame.shape[0]} imgsz={IMG_SIZE}"
                )
                self._last_detect_log = now

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
            last_infer = time.time()

            if now - last_post >= POST_EVERY_SEC:
                post_events = list(events)
                for evt in post_events:
                    tid = evt.get("track_id")
                    xyxy = None
                    if tid is not None:
                        mem = self.intelligence.tracks.get(int(tid))
                        if mem:
                            if not evt.get("plate") and mem.plate:
                                evt["plate"] = mem.plate
                            if mem.plate_status:
                                evt["plate_status"] = mem.plate_status
                            if mem.ocr_confidence:
                                evt["ocr_confidence"] = round(float(mem.ocr_confidence), 3)
                            if mem.vehicle_details:
                                evt["vehicle_details"] = mem.vehicle_details
                            if mem.owner_name:
                                evt["owner_name"] = mem.owner_name
                            mem.violation_flag = True
                            xyxy = mem.last_xyxy
                    evt["camera_id"] = self.config.camera_id
                    evt["area_id"] = self.config.area_id
                    evidence = encode_evidence_jpeg(frame, xyxy)
                    if evidence:
                        evt["evidence_jpeg_base64"] = evidence
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
    plate_queue = AsyncPlateQueue(ocr)
    model_lock = threading.Lock()
    workers: list[CameraWorker] = []

    for cfg in cameras:
        worker = CameraWorker(cfg, model, device, model_lock, plate_queue)
        worker.start()
        workers.append(worker)

    start_stream_server()
    print(f"Posting occupancy to {API_BASE}/api/ai-parking/occupancy")
    print(
        f"Stream {STREAM_TARGET_FPS:.0f} fps @ preview {PREVIEW_MAX_WIDTH}px | "
        f"infer ≤{INFER_MAX_WIDTH}px imgsz={IMG_SIZE} conf={CONF} | "
        f"every {INFER_EVERY_SEC}s | OCR={'async' if ocr.enabled else 'off'} | "
        f"tracker={'ultralytics' if USE_ULTRALYTICS_TRACK else 'iou'}"
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
