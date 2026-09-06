"""
AI Parking service — YOLOv9 + zone polygons + rules + tracking + OCR + Laravel.

Run:
  cd hardware/ai_parking
  python -u ai_parking_service.py

Calibrate slots first (recommended):
  python calibrate_zones.py

Requires Laravel:
  php artisan serve --host=0.0.0.0 --port=8000
  php artisan db:seed
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
from monitor_scan import encode_crop_jpeg_bytes, enhance_monitor_frame, scan_visible_region
from yolo_models import ensure_model, resolve_model_name, resolve_model_path

# Default; open_rtsp() may override per-camera under OPEN_LOCK.
os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = resolve_model_path()

API_BASE = os.getenv("AI_LARAVEL_API_BASE", "http://127.0.0.1:8000").rstrip("/")
AI_API_TOKEN = os.getenv("AI_PARKING_API_TOKEN", "capstone-ai-parking-dev-token-change-me")

STREAM_HOST = os.getenv("AI_STREAM_HOST", "0.0.0.0")
STREAM_PORT = int(os.getenv("AI_STREAM_PORT", "8090"))

IMG_SIZE = int(os.getenv("AI_PARKING_IMG_SIZE", "640"))
# Lower conf for distant lot cameras; raise via .env if too many false positives.
CONF = float(os.getenv("AI_PARKING_CONF", "0.22"))
IOU = float(os.getenv("AI_PARKING_IOU", "0.50"))
MAX_DET = int(os.getenv("AI_PARKING_MAX_DET", "40"))
# Tiny-box filter (fraction of infer frame). Keep low so distant cars remain.
MIN_BOX_AREA_FRAC = float(os.getenv("AI_PARKING_MIN_BOX_AREA", "0.0005"))
if os.getenv("AI_PARKING_LONG_RANGE", "0") == "1":
    MIN_BOX_AREA_FRAC = float(os.getenv("AI_PARKING_LONG_RANGE_MIN_BOX_AREA", "0.00012"))
# Infer width for live boxes (OCR still uses full-res RTSP crops asynchronously).
INFER_MAX_WIDTH = int(os.getenv("AI_PARKING_INFER_MAX_WIDTH", "1280"))
# Shared YOLO + ByteTrack persist=True breaks multi-cam; default to predict + IoU IDs.
USE_ULTRALYTICS_TRACK = os.getenv("AI_PARKING_USE_TRACKER", "0") == "1"
POST_EVERY_SEC = float(os.getenv("AI_PARKING_POST_EVERY_SEC", "1.5"))
USE_WEBCAM = os.getenv("AI_USE_WEBCAM", "0") == "1"
TRACKER = os.getenv("AI_PARKING_TRACKER", "bytetrack.yaml")
# Target YOLO cadence; actual rate is also limited by CPU + model lock.
INFER_EVERY_SEC = float(os.getenv("AI_PARKING_INFER_EVERY_SEC", "0.22"))
# Keep last boxes briefly when a frame misses, so overlays don't flicker.
BOX_HOLD_SEC = float(os.getenv("AI_PARKING_BOX_HOLD_SEC", "0.9"))
PREVIEW_MAX_WIDTH = int(os.getenv("AI_PARKING_PREVIEW_MAX_WIDTH", "1280"))
# Max width for AI overlay MJPEG (browser). Raise for distant plate viewing (e.g. 2560).
AI_STREAM_MAX_WIDTH = int(os.getenv("AI_PARKING_AI_STREAM_MAX_WIDTH", "1280"))
STREAM_TARGET_FPS = float(os.getenv("AI_PARKING_STREAM_FPS", "18"))
STREAM_JPEG_QUALITY = int(os.getenv("AI_PARKING_STREAM_JPEG_QUALITY", "72"))
# AI overlay stream (monitor). Higher than live preview so ~20 m plates stay readable.
AI_STREAM_JPEG_QUALITY = int(os.getenv("AI_PARKING_AI_STREAM_JPEG_QUALITY", "78"))
MONITOR_SHARPEN = os.getenv("AI_PARKING_MONITOR_SHARPEN", "0") == "1"
RECONNECT_EVERY_SEC = float(os.getenv("AI_CAMERA_RECONNECT_SEC", "5"))
OPEN_LOCK = threading.Lock()
# FP16 on CUDA only — free speedup for realtime multi-cam.
USE_HALF = os.getenv("AI_PARKING_HALF", "1") == "1"

MOTORCYCLE_CLS_ID = 3
# Per-class detect toggles (all on by default — cars + motorcycles + buses + trucks)
_detect_cars = os.getenv("AI_PARKING_DETECT_CARS", "1") == "1"
_detect_motorcycles = os.getenv("AI_PARKING_DETECT_MOTORCYCLES", "1") == "1"
_detect_buses = os.getenv("AI_PARKING_DETECT_BUSES", "1") == "1"
_detect_trucks = os.getenv("AI_PARKING_DETECT_TRUCKS", "1") == "1"
DETECT_CLASS_IDS = []
if _detect_cars:
    DETECT_CLASS_IDS.append(2)
if _detect_motorcycles:
    DETECT_CLASS_IDS.append(3)
if _detect_buses:
    DETECT_CLASS_IDS.append(5)
if _detect_trucks:
    DETECT_CLASS_IDS.append(7)
if not DETECT_CLASS_IDS:
    DETECT_CLASS_IDS = [2, 3, 5, 7]
VEHICLE_CLASS_IDS = set(DETECT_CLASS_IDS)
# Motorcycles are smaller in frame — use a lower area threshold so distant bikes are kept.
MOTORCYCLE_MIN_BOX_FRAC = float(os.getenv("AI_PARKING_MOTORCYCLE_MIN_BOX_AREA", "0.00008"))
if os.getenv("AI_PARKING_LONG_RANGE", "0") == "1":
    MOTORCYCLE_MIN_BOX_FRAC = float(os.getenv("AI_PARKING_MOTORCYCLE_MIN_BOX_AREA", "0.00006"))
# Reject implausible vehicle aspect ratios (poles, shadows, partial boxes).
MIN_VEHICLE_ASPECT = float(os.getenv("AI_PARKING_MIN_VEHICLE_ASPECT", "0.28"))
MAX_VEHICLE_ASPECT = float(os.getenv("AI_PARKING_MAX_VEHICLE_ASPECT", "4.5"))
MIN_VEHICLE_SHORT_SIDE_PX = int(os.getenv("AI_PARKING_MIN_VEHICLE_SHORT_SIDE_PX", "18"))
# Vehicles only — never detect/draw/post persons or other COCO objects.
VEHICLES_ONLY = os.getenv("AI_PARKING_VEHICLES_ONLY", "1") == "1"
if not VEHICLES_ONLY and os.getenv("AI_PARKING_DETECT_PERSONS", "0") == "1":
    DETECT_CLASS_IDS = [0, 2, 3, 5, 7]
    VEHICLE_CLASS_IDS = set(DETECT_CLASS_IDS)
POST_PERSON_DETECTIONS = (not VEHICLES_ONLY) and os.getenv("AI_PARKING_POST_PERSONS", "0") == "1"
OCR_PARKED_ONLY = os.getenv("AI_PARKING_OCR_PARKED_ONLY", "0") == "1"
COCO_NAMES = {
    0: "person",
    2: "car",
    3: "motorcycle",
    5: "bus",
    7: "truck",
}
ALLOWED_VEHICLE_NAMES = frozenset(COCO_NAMES.get(i, "") for i in VEHICLE_CLASS_IDS if i in COCO_NAMES)
# BGR colors — stable per vehicle type across all cameras (not overridden by motion).
# COCO YOLOv9 detects car/motorcycle/bus/truck.
# Tricycles have no COCO class — treat as motorcycle. SUV/Van share "car" unless refined by registry.
CLASS_COLORS = {
    "person": (0, 165, 255),       # orange
    "car": (40, 180, 40),          # green
    "suv": (0, 140, 255),          # orange-amber
    "van": (180, 90, 40),          # teal/brown
    "motorcycle": (0, 215, 255),   # amber/yellow
    "bus": (200, 60, 220),         # magenta
    "truck": (255, 120, 40),       # blue
}
CLASS_DISPLAY_NAMES = {
    "person": "Person",
    "car": "Car",
    "suv": "SUV",
    "van": "Van",
    "motorcycle": "Motorcycle",
    "bus": "Bus",
    "truck": "Truck",
}


def _normalize_vehicle_type_key(raw: str | None) -> str | None:
    if not raw:
        return None
    key = str(raw).strip().lower().replace("-", " ").replace("_", " ")
    if "motor" in key or "tricycle" in key or "trike" in key or "sidecar" in key or key in {"mc", "bike"}:
        return "motorcycle"
    if "suv" in key:
        return "suv"
    if "van" in key:
        return "van"
    if "truck" in key or "lorry" in key:
        return "truck"
    if "bus" in key:
        return "bus"
    if "car" in key or "sedan" in key or "auto" in key:
        return "car"
    return None


def _overlay_type_key(yolo_name: str, vehicle_details: str | None = None) -> str:
    """Prefer registered vehicle type (SUV/Van/Tricycle→motorcycle) when known; else YOLO class."""
    registered = _normalize_vehicle_type_key(vehicle_details)
    if registered:
        return registered
    return yolo_name or "car"
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
        fail_since = None
        open_failures = 0
        while self.running:
            if self.cap is None or not self.cap.isOpened():
                if not self._open():
                    self.online = False
                    open_failures = min(open_failures + 1, 6)
                    # Back off when the camera is on another LAN so it cannot
                    # spam OPENCV_FFMPEG_CAPTURE_OPTIONS and break other cams.
                    time.sleep(min(60.0, max(RECONNECT_EVERY_SEC, 8.0) * open_failures))
                    continue
                print(f"[{self.label}] RTSP connected")
                fail_since = None
                open_failures = 0

            ret, frame = self._read_newest()
            if not ret or frame is None:
                if fail_since is None:
                    fail_since = time.monotonic()
                # Tapo/Wi-Fi can miss a few packets; 150ms used to force a
                # reconnect loop that never recovered.
                if time.monotonic() - fail_since >= 3.0:
                    print(f"[{self.label}] RTSP stalled — reconnecting…")
                    self.online = False
                    try:
                        self.cap.release()
                    except Exception:
                        pass
                    self.cap = None
                    fail_since = None
                    time.sleep(RECONNECT_EVERY_SEC)
                else:
                    time.sleep(0.02)
                continue

            fail_since = None
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
        self.jpeg_raw = None
        self.jpeg_ai = None
        self.vehicle_count = 0
        self.detections = []
        self.rtsp_online = False
        self.plate_crops: dict[int, bytes] = {}
        self.vehicle_crops: dict[int, bytes] = {}

    def set_frame(self, raw_jpeg, ai_jpeg, vehicle_count, detections, rtsp_online=None):
        with self.lock:
            if raw_jpeg is not None:
                self.jpeg_raw = raw_jpeg
            if ai_jpeg is not None:
                self.jpeg_ai = ai_jpeg
            self.vehicle_count = vehicle_count
            self.detections = detections
            if rtsp_online is not None:
                self.rtsp_online = bool(rtsp_online)

    def set_plate_crops(self, crops: dict[int, bytes]):
        with self.lock:
            self.plate_crops = dict(crops)

    def set_vehicle_crops(self, crops: dict[int, bytes]):
        with self.lock:
            self.vehicle_crops = dict(crops)

    def get_plate_crop(self, track_id: int):
        with self.lock:
            return self.plate_crops.get(int(track_id))

    def get_vehicle_crop(self, track_id: int):
        with self.lock:
            return self.vehicle_crops.get(int(track_id))

    def get_jpeg(self, ai: bool = False):
        with self.lock:
            if ai:
                return self.jpeg_ai or self.jpeg_raw
            return self.jpeg_raw or self.jpeg_ai


# camera_id -> StreamState (multi-camera MJPEG)
STREAM_STATES: dict[str, StreamState] = {}
# camera_id -> CameraWorker (for view-only test-scan)
CAMERA_WORKERS: dict[str, "CameraWorker"] = {}
# path -> (camera_id, ai_overlay)
STREAM_PATH_INDEX: dict[str, tuple[str, bool]] = {}


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
        # Hold the lock through the first frame. OPENCV_FFMPEG_CAPTURE_OPTIONS is
        # process-global; another camera retrying UDP must not change it mid-open.
        with OPEN_LOCK:
            if transport == "udp":
                os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
                    "rtsp_transport;udp|fflags;nobuffer|flags;low_delay|max_delay;0"
                )
            else:
                # Tapo / Wi-Fi: max_delay;0 drops the first GOP and never recovers.
                os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
                    "rtsp_transport;tcp|stimeout;5000000"
                )
            cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
            opened = cap.isOpened()
            ret, frame = (False, None)
            if opened:
                ret, frame = cap.read()
        if not opened:
            cap.release()
            last_err = "open_failed"
            continue
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


def _vehicle_box_valid(x1: int, y1: int, x2: int, y2: int, cls_id: int, frame_shape=None) -> bool:
    """Filter obvious false positives before tracking/OCR."""
    bw = max(1, x2 - x1)
    bh = max(1, y2 - y1)
    aspect = bw / bh
    if cls_id == MOTORCYCLE_CLS_ID:
        if aspect < 0.45 or aspect > 3.2:
            return False
        if min(bw, bh) < max(12, MIN_VEHICLE_SHORT_SIDE_PX - 4):
            return False
        return True
    if aspect < MIN_VEHICLE_ASPECT or aspect > MAX_VEHICLE_ASPECT:
        return False
    if min(bw, bh) < MIN_VEHICLE_SHORT_SIDE_PX:
        return False
    # Tall/skinny wall posters and banners are often misread as trucks.
    if cls_id in (5, 7) and aspect < 0.55 and bh > bw * 1.35:
        return False
    if frame_shape is not None:
        fh, fw = frame_shape[:2]
        area_frac = (bw * bh) / max(1, fh * fw)
        # Tiny edge blobs (banner corners) — keep only if reasonably large.
        if area_frac < max(MIN_BOX_AREA_FRAC * 3.0, 0.008) and (x1 < fw * 0.04 or x2 > fw * 0.96):
            return False
    return True


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


def _sync_detection_from_mem(det: dict, mem) -> None:
    """Copy latest plate + owner fields from track memory into a detection dict."""
    det["plate_status"] = mem.plate_status
    if mem.plate_status == "ok" and mem.plate:
        det["plate"] = mem.plate
    else:
        det.pop("plate", None)
    if mem.ocr_confidence > 0:
        det["ocr_confidence"] = round(float(mem.ocr_confidence), 3)
    else:
        det.pop("ocr_confidence", None)
    owner_label = mem.overlay_owner_line()
    if mem.owner_name:
        det["owner_name"] = mem.owner_name
    if owner_label:
        det["owner_label"] = owner_label
    if mem.vehicle_details:
        det["vehicle_details"] = mem.vehicle_details
    if mem.department:
        det["department"] = mem.department
    if mem.registration_status:
        det["registration_status"] = mem.registration_status
    if mem.registered is not None:
        det["registered"] = mem.registered
    if mem.motion_state:
        _attach_motion(det, mem.motion_state)


def refresh_plates_from_tracks(
    detections: list[dict],
    vehicles: list[dict],
    annotated_boxes: list[tuple],
    intelligence: ParkingIntelligence,
) -> list[tuple]:
    """Apply async/sync OCR results from track memory onto outgoing payloads."""
    tracks = intelligence.tracks

    for det in detections:
        tid = det.get("track_id")
        if tid is None:
            continue
        mem = tracks.get(int(tid))
        if mem is not None:
            _sync_detection_from_mem(det, mem)

    for veh in vehicles:
        tid = veh.get("track_id")
        if tid is None:
            continue
        mem = tracks.get(int(tid))
        if mem is None:
            continue
        veh["plate_status"] = mem.plate_status
        veh["plate"] = mem.plate if mem.plate_status == "ok" else None

    refreshed: list[tuple] = []
    for box in annotated_boxes:
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details = _unpack_box(box)
        if track_id is not None:
            mem = tracks.get(int(track_id))
            if mem is not None:
                plate_status = mem.plate_status
                plate = mem.plate if plate_status == "ok" else None
                owner_label = mem.overlay_owner_line() or owner_label
                motion_state = mem.motion_state
                vehicle_details = mem.vehicle_details or vehicle_details
        refreshed.append((x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details))
    return refreshed


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
            if not VEHICLES_ONLY:
                person_count += 1
                annotated_boxes.append((x1, y1, x2, y2, name, conf, track_id, None, "pending", None))
                if POST_PERSON_DETECTIONS:
                    detections.append({"class": name, "confidence": round(conf, 3), "track_id": track_id})
            continue
        if cls_id not in VEHICLE_CLASS_IDS:
            continue
        if VEHICLES_ONLY and name not in ALLOWED_VEHICLE_NAMES:
            continue

        box_area = max(0, x2 - x1) * max(0, y2 - y1)
        min_frac = MOTORCYCLE_MIN_BOX_FRAC if cls_id == MOTORCYCLE_CLS_ID else MIN_BOX_AREA_FRAC
        if box_area / frame_area < min_frac:
            continue
        if not _vehicle_box_valid(x1, y1, x2, y2, cls_id, frame.shape):
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
        motion_state = None

        ox1 = int(x1 * scale_ocr)
        oy1 = int(y1 * scale_ocr)
        ox2 = int(x2 * scale_ocr)
        oy2 = int(y2 * scale_ocr)

        if track_id is not None:
            mem = intelligence.touch_track(track_id, now)
            plate = mem.plate
            plate_status = mem.plate_status
            ocr_confidence = mem.ocr_confidence
            motion_state = mem.update_motion((x1, y1, x2, y2), now)
            mem.last_ocr_xyxy = (ox1, oy1, ox2, oy2)
            mem.cls_id = row.get("cls_id")
            try:
                fh, fw = ocr_src.shape[:2]
                pad_x = max(2, int((ox2 - ox1) * 0.04))
                pad_y = max(2, int((oy2 - oy1) * 0.04))
                vx1 = max(0, ox1 - pad_x)
                vy1 = max(0, oy1 - pad_y)
                vx2 = min(fw, ox2 + pad_x)
                vy2 = min(fh, oy2 + pad_y)
                if vx2 - vx1 >= 24 and vy2 - vy1 >= 24:
                    mem.last_vehicle_crop = ocr_src[vy1:vy2, vx1:vx2].copy()
            except Exception:
                pass
            if plate_queue is not None:
                ocr_ok = not OCR_PARKED_ONLY or motion_state in (None, "parked", "idle")
                # Prefer real vehicles for OCR; still allow mid-size parked cars/multicabs.
                box_w = max(1, ox2 - ox1)
                box_h = max(1, oy2 - oy1)
                frame_h, frame_w = ocr_src.shape[:2]
                area_frac = (box_w * box_h) / max(1, frame_w * frame_h)
                if area_frac < 0.006 or box_w < 64 or box_h < 48:
                    ocr_ok = False
                if ocr_ok:
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

        else:
            motion_state = None

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
            "vehicle_type": _overlay_type_key(name, vehicle_details if isinstance(vehicle_details, str) else None) or name,
            "confidence": round(conf, 3),
            "track_id": track_id,
            "plate_status": plate_status,
            "xyxy": [nx1, ny1, nx2, ny2],
        }
        if plate:
            det["plate"] = plate
            det["ocr_text"] = plate
            det["plate_text"] = plate
        if ocr_confidence > 0:
            det["ocr_confidence"] = round(float(ocr_confidence), 3)
        if plate_status == "unreadable":
            det["plate"] = None
            det["ocr_text"] = None
            det["plate_text"] = None
        if track_id is not None:
            mem_crop = intelligence.tracks.get(int(track_id))
            if mem_crop is not None and getattr(mem_crop, "last_plate_crop", None) is not None:
                det["has_plate_crop"] = True
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
        _attach_motion(det, motion_state)

        detections.append(det)
        annotated_boxes.append((x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details))
        vehicles.append({
            "xyxy": (x1, y1, x2, y2),
            "track_id": track_id,
            "class": name,
            "confidence": conf,
            "plate": plate,
            "plate_status": plate_status,
        })

    vehicle_count = len(vehicles)
    if VEHICLES_ONLY and vehicle_count == 0:
        detections = []
        annotated_boxes = [b for b in annotated_boxes if len(b) > 4 and b[4] in ALLOWED_VEHICLE_NAMES]
    return annotated_boxes, detections, vehicles, person_count, vehicle_count


def _unpack_box(box: tuple):
    """Support legacy 10/11-tuples and 12-tuple (motion_state + vehicle_details)."""
    vehicle_details = None
    motion_state = None
    if len(box) >= 12:
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details = box[:12]
    elif len(box) >= 11:
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state = box[:11]
    else:
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label = box[:10]
    return x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details


def _draw_label_block(annotated, x, y, lines, color, lite=False):
    """High-contrast stacked labels with dark backing for plate readability."""
    font = cv2.FONT_HERSHEY_SIMPLEX
    scale = 0.48 if lite else 0.58
    thick = 1 if lite else 2
    line_h = 18 if lite else 22
    pad_x, pad_y = 6, 4
    max_w = 0
    sizes = []
    for line in lines:
        (tw, th), _ = cv2.getTextSize(str(line), font, scale, thick)
        max_w = max(max_w, tw)
        sizes.append((tw, th))
    block_h = pad_y * 2 + line_h * len(lines)
    block_w = max_w + pad_x * 2
    top = max(0, y - block_h - 4)
    left = max(0, x)
    cv2.rectangle(annotated, (left, top), (left + block_w, top + block_h), (0, 0, 0), -1)
    cv2.rectangle(annotated, (left, top), (left + block_w, top + block_h), color, 1)
    ty = top + pad_y + (sizes[0][1] if sizes else 14)
    for i, line in enumerate(lines):
        cv2.putText(
            annotated,
            str(line),
            (left + pad_x, ty + i * line_h),
            font,
            scale,
            color,
            thick,
            cv2.LINE_AA,
        )


def _draw_box_labels(annotated, x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, lite=False, motion_state=None, vehicle_details=None):
    # Color by vehicle type (registered type when known, else YOLO class) — consistent across cameras.
    type_key = _overlay_type_key(name, vehicle_details)
    color = CLASS_COLORS.get(type_key, CLASS_COLORS.get(name, (40, 180, 40)))
    cv2.rectangle(annotated, (x1, y1), (x2, y2), color, 2 if not lite else 2)
    lines = []
    display_name = CLASS_DISPLAY_NAMES.get(type_key, CLASS_DISPLAY_NAMES.get(name, name or "?"))
    class_label = display_name if not lite else (display_name[0].upper() if display_name else "?")
    if lite:
        head = f"#{track_id} {class_label}" if track_id is not None else class_label
    else:
        head = f"#{track_id} {display_name} {conf * 100:.0f}%" if track_id is not None else f"{display_name} {conf * 100:.0f}%"
    lines.append(head)
    if motion_state == "moving":
        lines.append("MOVING")
    elif motion_state == "parked":
        lines.append("PARKED")
    if plate_status == "unreadable":
        lines.append("PLATE UNREADABLE")
    elif plate:
        lines.append(str(plate))
        if owner_label:
            lines.append(str(owner_label)[:32])
    elif track_id is not None:
        lines.append("Reading plate…")
    _draw_label_block(annotated, x1, y1, lines[:4], color, lite=lite)


def _motion_label(state: str | None) -> str | None:
    if state == "moving":
        return "Moving"
    if state == "parked":
        return "Parked"
    if state == "idle":
        return "Settling"
    return None


def _attach_motion(det: dict, motion_state: str | None) -> None:
    if not motion_state:
        return
    det["motion_state"] = motion_state
    label = _motion_label(motion_state)
    if label:
        det["motion_label"] = label


def draw_scene(frame, annotated_boxes, zones_data, occupied_slots, active_events, person_count, vehicle_count, use_poly):
    annotated = frame.copy()
    draw_zones(annotated, zones_data, occupied_slots)

    for box in annotated_boxes:
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details = _unpack_box(box)
        _draw_box_labels(
            annotated,
            x1,
            y1,
            x2,
            y2,
            name,
            conf,
            track_id,
            plate,
            plate_status,
            owner_label,
            lite=False,
            motion_state=motion_state,
            vehicle_details=vehicle_details,
        )

    mode = "slots" if use_poly else "count-fallback"
    summary = f"Vehicles: {vehicle_count} | Mode: {mode}" if VEHICLES_ONLY else f"People: {person_count} | Vehicles: {vehicle_count} | Mode: {mode}"
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
        x1, y1, x2, y2, name, conf, track_id, plate, plate_status, owner_label, motion_state, vehicle_details = _unpack_box(box)
        _draw_box_labels(
            annotated,
            x1,
            y1,
            x2,
            y2,
            name,
            conf,
            track_id,
            plate,
            plate_status,
            owner_label,
            lite=True,
            motion_state=motion_state,
            vehicle_details=vehicle_details,
        )
    n_occ = len(occupied_slots) if occupied_slots else 0
    summary = f"V:{vehicle_count} Occ:{n_occ}" if VEHICLES_ONLY else f"P:{person_count} V:{vehicle_count} Occ:{n_occ}"
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

    def _resolve_stream(self) -> tuple[str | None, bool]:
        path = self.path.split("?", 1)[0]
        if path in STREAM_PATH_INDEX:
            return STREAM_PATH_INDEX[path]
        if path == "/stream.mjpg":
            cam = next(iter(STREAM_STATES.keys()), None)
            return (cam, False) if cam else (None, False)
        parts = [p for p in path.split("/") if p]
        if len(parts) >= 3 and parts[-1] == "stream.mjpg" and parts[-2] == "ai":
            cam = parts[0]
            if cam in STREAM_STATES:
                return (cam, True)
        if len(parts) >= 2 and parts[-1] == "stream.mjpg":
            cam = parts[0]
            if cam in STREAM_STATES:
                return (cam, False)
        return (None, False)

    def _ai_authorized(self) -> bool:
        expected = (AI_API_TOKEN or "").strip()
        if not expected:
            return True
        got = (self.headers.get("X-AI-TOKEN") or "").strip()
        return got == expected

    def _send_json(self, code: int, payload: dict) -> None:
        body = json.dumps(payload).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json")
        self.send_header("Cache-Control", "no-store")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read_json_body(self, max_bytes: int = 32768):
        try:
            length = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            return None
        if length <= 0 or length > max_bytes:
            return None
        raw = self.rfile.read(length)
        try:
            data = json.loads(raw.decode("utf-8"))
        except Exception:
            return None
        return data if isinstance(data, dict) else None

    def _handle_plate_crop(self, path: str) -> bool:
        parts = [p for p in path.split("/") if p]
        if len(parts) < 3 or parts[1] not in ("plate-crop", "vehicle-crop"):
            return False
        kind = parts[1]
        camera_id = parts[0]
        track_raw = parts[2]
        if track_raw.lower().endswith(".jpg"):
            track_raw = track_raw[:-4]
        try:
            track_id = int(track_raw)
        except ValueError:
            self.send_error(404)
            return True
        state = STREAM_STATES.get(camera_id)
        if kind == "vehicle-crop":
            jpeg = state.get_vehicle_crop(track_id) if state else None
            if not jpeg and state:
                jpeg = state.get_plate_crop(track_id)
        else:
            jpeg = state.get_plate_crop(track_id) if state else None
        if not jpeg:
            self.send_error(404)
            return True
        self.send_response(200)
        self.send_header("Content-Type", "image/jpeg")
        self.send_header("Cache-Control", "no-store, private")
        self.send_header("Content-Length", str(len(jpeg)))
        self.end_headers()
        self.wfile.write(jpeg)
        return True

    def do_GET(self):
        path = self.path.split("?", 1)[0]
        if path == "/":
            self.send_response(200)
            self.send_header("Content-Type", "text/html")
            self.end_headers()
            links = []
            for p, (cid, ai) in sorted(STREAM_PATH_INDEX.items()):
                label = f"{cid} ({'AI' if ai else 'live'})"
                links.append(f'<li><a href="{p}">{label}</a></li>')
            link_html = "".join(links)
            self.wfile.write(
                f"<html><body><h3>AI Parking Streams</h3><ul>{link_html}</ul></body></html>".encode("utf-8")
            )
            return

        if path == "/health":
            self.send_response(200)
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            cameras = {}
            for cid, state in STREAM_STATES.items():
                cameras[cid] = {
                    "online": bool(getattr(state, "rtsp_online", False)),
                    "has_frame": state.get_jpeg(False) is not None,
                }
            any_online = any(row["online"] for row in cameras.values()) if cameras else False
            body = json.dumps({
                "ok": True,
                "cameras": list(cameras.keys()),
                "camera_status": cameras,
                "any_online": any_online,
            }).encode("utf-8")
            self.wfile.write(body)
            return

        # Single JPEG snapshot (never hangs like MJPEG).
        snap_ai = False
        snap_cam = None
        if path in ("/snapshot.jpg", "/frame.jpg"):
            snap_cam = next(iter(STREAM_STATES.keys()), None)
        elif path.endswith("/snapshot.jpg") or path.endswith("/frame.jpg"):
            parts = [p for p in path.split("/") if p]
            if len(parts) >= 2:
                snap_cam = parts[0]
                snap_ai = len(parts) >= 3 and parts[1] == "ai"
        if snap_cam:
            state = STREAM_STATES.get(snap_cam)
            jpeg = state.get_jpeg(ai=snap_ai) if state else None
            if not jpeg:
                self.send_error(503)
                return
            self.send_response(200)
            self.send_header("Content-Type", "image/jpeg")
            self.send_header("Cache-Control", "no-store, private")
            self.send_header("Content-Length", str(len(jpeg)))
            self.end_headers()
            self.wfile.write(jpeg)
            return

        if self._handle_plate_crop(path):
            return

        camera_id, ai_overlay = self._resolve_stream()
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
                jpeg = state.get_jpeg(ai=ai_overlay)
                if jpeg is None:
                    time.sleep(0.005)
                    continue
                if jpeg is last_sent:
                    time.sleep(0.002)
                    continue
                last_sent = jpeg
                self.wfile.write(b"--frame\r\n")
                self.wfile.write(b"Content-Type: image/jpeg\r\n")
                self.wfile.write(f"Content-Length: {len(jpeg)}\r\n\r\n".encode("ascii"))
                self.wfile.write(jpeg)
                self.wfile.write(b"\r\n")
        except (BrokenPipeError, ConnectionResetError, ConnectionAbortedError):
            return

    def do_POST(self):
        path = self.path.split("?", 1)[0]
        if path != "/test-scan":
            self.send_error(404)
            return
        if not self._ai_authorized():
            self._send_json(403, {"ok": False, "saved": False, "message": "Unauthorized."})
            return
        payload = self._read_json_body() or {}
        camera_id = str(payload.get("camera_id") or "").strip()
        view = payload.get("view") if isinstance(payload.get("view"), dict) else {}
        worker = CAMERA_WORKERS.get(camera_id)
        if worker is None:
            want = camera_id.upper()
            for cid, candidate in CAMERA_WORKERS.items():
                if str(cid).upper() == want:
                    worker = candidate
                    break
        if worker is None and len(CAMERA_WORKERS) == 1:
            worker = next(iter(CAMERA_WORKERS.values()))
        if worker is None:
            self._send_json(404, {"ok": False, "saved": False, "message": "Unknown camera."})
            return
        result = worker.test_scan_view(view)
        result["saved"] = False
        result["camera_id"] = worker.config.camera_id
        code = 200 if result.get("ok") else 503
        self._send_json(code, result)


def start_stream_server():
    server = ThreadingHTTPServer((STREAM_HOST, STREAM_PORT), MjpegHandler)
    thread = threading.Thread(target=server.serve_forever, daemon=True, name="mjpeg-http")
    thread.start()
    print(f"MJPEG base: http://{STREAM_HOST}:{STREAM_PORT}/")
    for path, (cam, ai) in sorted(STREAM_PATH_INDEX.items()):
        kind = "AI" if ai else "live"
        print(f"  {cam} ({kind}): http://127.0.0.1:{STREAM_PORT}{path}")
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
        self._ai_overlay_lock = threading.Lock()
        self._ai_overlay_jpeg: bytes | None = None
        zones_path = Path(config.zones_file)
        if not zones_path.is_file():
            zones_path = BASE_DIR / "zones.json"
        self.zones_holder = [load_zones(zones_path)]
        self.zones_path = zones_path
        self.preview_max_width = int(config.preview_max_width or PREVIEW_MAX_WIDTH)
        self.infer_max_width = int(config.infer_max_width) if getattr(config, "infer_max_width", 0) else INFER_MAX_WIDTH
        cam_ai_cap = int(getattr(config, "ai_stream_max_width", 0) or 0)
        self.ai_stream_max_width = cam_ai_cap if cam_ai_cap > 0 else AI_STREAM_MAX_WIDTH
        self.stream_fps = float(config.stream_fps or STREAM_TARGET_FPS)
        self.jpeg_quality = int(config.jpeg_quality or STREAM_JPEG_QUALITY)
        self.ai_jpeg_quality = max(int(AI_STREAM_JPEG_QUALITY or 0), self.jpeg_quality)
        self.lite_preview = bool(config.lite_preview)

    def start(self):
        STREAM_STATES[self.config.camera_id] = self.state
        CAMERA_WORKERS[self.config.camera_id] = self
        raw_path = self.config.stream_path or f"/{self.config.camera_id}/stream.mjpg"
        ai_path = f"/{self.config.camera_id}/ai/stream.mjpg"
        STREAM_PATH_INDEX[raw_path] = (self.config.camera_id, False)
        STREAM_PATH_INDEX[f"/{self.config.camera_id}/stream.mjpg"] = (self.config.camera_id, False)
        STREAM_PATH_INDEX[ai_path] = (self.config.camera_id, True)
        if raw_path.endswith("/stream.mjpg") and raw_path != ai_path:
            legacy_ai = raw_path[: -len("stream.mjpg")] + "ai/stream.mjpg"
            if legacy_ai != ai_path:
                STREAM_PATH_INDEX[legacy_ai] = (self.config.camera_id, True)
        if str(self.config.camera_id).upper() == "CAM-AI-1" or raw_path == "/stream.mjpg":
            STREAM_PATH_INDEX["/stream.mjpg"] = (self.config.camera_id, False)
            legacy_primary_ai = "/CAM-AI-1/ai/stream.mjpg"
            if legacy_primary_ai != ai_path:
                STREAM_PATH_INDEX[legacy_primary_ai] = (self.config.camera_id, True)
        # Keep old CAM-AI-2 bookmarks working after id rename to CAM-2.
        if str(self.config.camera_id).upper() == "CAM-2":
            STREAM_PATH_INDEX["/CAM-AI-2/stream.mjpg"] = (self.config.camera_id, False)
            STREAM_PATH_INDEX["/CAM-AI-2/ai/stream.mjpg"] = (self.config.camera_id, True)

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
            f"{self.stream_fps:.0f}fps@{self.preview_max_width}px infer≤{self.infer_max_width}px "
            f"ai≤{self.ai_stream_max_width}px q={self.jpeg_quality}"
            f"{' lite' if self.lite_preview else ''}"
        )

    def stop(self):
        self.running.clear()
        CAMERA_WORKERS.pop(self.config.camera_id, None)
        if self.preview_reader:
            self.preview_reader.stop()
        if self.infer_reader and not self._shared_reader:
            self.infer_reader.stop()

    def _preview_loop(self):
        interval = 1.0 / max(1.0, self.stream_fps)
        encode_params = [int(cv2.IMWRITE_JPEG_QUALITY), self.jpeg_quality]
        ai_encode_params = [int(cv2.IMWRITE_JPEG_QUALITY), int(self.ai_jpeg_quality)]
        last_seq = -1
        while self.running.is_set():
            started = time.perf_counter()
            zones_data = self.zones_holder[0]
            offline_reason = f"{self.config.camera_id} offline"
            if not self.config.has_credentials:
                offline_reason = f"{self.config.camera_id}: set USER/PASS in .env"
            elif self.preview_reader is not None and not getattr(self.preview_reader, "online", False):
                offline_reason = f"{self.config.camera_id}: RTSP reconnecting"

            live = bool(self.preview_reader and getattr(self.preview_reader, "online", False))

            if self.preview_reader is None:
                frame = blank_frame(offline_reason)
                src_shape = frame.shape
                is_new = True
            else:
                ret, frame, last_seq = self.preview_reader.read_if_newer(last_seq)
                if not ret:
                    if self.state.get_jpeg(False) is not None:
                        self.state.rtsp_online = False
                        time.sleep(0.05)
                        continue
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

            display_raw, _ = resize_for_infer(frame, self.preview_max_width)
            raw_jpeg = None
            ok_raw, buf_raw = cv2.imencode(".jpg", display_raw, encode_params)
            if ok_raw:
                raw_jpeg = buf_raw.tobytes()

            # Dual-stream cameras: YOLO boxes are in main-stream coords. Never paste them onto the
            # substream preview (FOV/aspect differ → boxes land on walls). AI MJPEG comes from infer.
            ai_jpeg = None
            vehicle_count = self.state.vehicle_count
            detections = self.state.detections
            if self._shared_reader:
                state = self.scene.snapshot()
                vehicle_count = state["vehicle_count"]
                detections = state["detections"]
                ai_jpeg = self._encode_ai_overlay(frame, state, zones_data, ai_encode_params)
            else:
                with self._ai_overlay_lock:
                    ai_jpeg = self._ai_overlay_jpeg

            if raw_jpeg or ai_jpeg:
                self.state.set_frame(
                    raw_jpeg,
                    ai_jpeg,
                    vehicle_count,
                    detections,
                    rtsp_online=live,
                )
            elapsed = time.perf_counter() - started
            time.sleep(max(0.0, interval - elapsed))

    def _encode_ai_overlay(self, frame, state: dict, zones_data, ai_encode_params) -> bytes | None:
        """Draw YOLO boxes onto the same frame they were detected on."""
        ai_cap = max(640, int(self.ai_stream_max_width or AI_STREAM_MAX_WIDTH))
        infer_w = int(self.infer_max_width) if int(self.infer_max_width or 0) > 0 else self.preview_max_width
        ai_width = min(max(self.preview_max_width, min(infer_w, ai_cap)), ai_cap)
        display_ai, _ = resize_for_infer(frame, ai_width)
        if display_ai is frame:
            display_ai = frame.copy()
        box_src = state.get("source_shape") or frame.shape
        boxes = scale_boxes_to_frame(state["annotated_boxes"], box_src, display_ai.shape)
        if self.lite_preview:
            annotated = draw_scene_lite(
                display_ai,
                boxes,
                state["occupied_slots"],
                state["person_count"],
                state["vehicle_count"],
            )
        else:
            annotated = draw_scene(
                display_ai,
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
        if MONITOR_SHARPEN:
            annotated = enhance_monitor_frame(annotated)
        ok_ai, buf_ai = cv2.imencode(".jpg", annotated, ai_encode_params)
        return buf_ai.tobytes() if ok_ai else None

    def _publish_ai_overlay(self, frame, state: dict | None = None) -> None:
        """Publish AI MJPEG from the infer frame so dual-stream FOV cannot misplace boxes."""
        zones_data = self.zones_holder[0]
        snap = state or self.scene.snapshot()
        ai_encode_params = [int(cv2.IMWRITE_JPEG_QUALITY), int(self.ai_jpeg_quality)]
        ai_jpeg = self._encode_ai_overlay(frame, snap, zones_data, ai_encode_params)
        if ai_jpeg is None:
            return
        with self._ai_overlay_lock:
            self._ai_overlay_jpeg = ai_jpeg
        # Keep counts/detections fresh even when preview is on a different RTSP path.
        self.state.set_frame(
            None,
            ai_jpeg,
            snap["vehicle_count"],
            snap["detections"],
            rtsp_online=True,
        )

    def _publish_plate_crops(self) -> None:
        plate_crops: dict[int, bytes] = {}
        vehicle_crops: dict[int, bytes] = {}
        for tid, mem in list(self.intelligence.tracks.items()):
            plate_raw = getattr(mem, "last_plate_crop", None)
            encoded_plate = encode_crop_jpeg_bytes(plate_raw, quality=90, max_side=360)
            if encoded_plate:
                plate_crops[int(tid)] = encoded_plate
            vehicle_raw = getattr(mem, "last_vehicle_crop", None)
            encoded_vehicle = encode_crop_jpeg_bytes(vehicle_raw, quality=85, max_side=420)
            if encoded_vehicle:
                vehicle_crops[int(tid)] = encoded_vehicle
            elif encoded_plate:
                vehicle_crops[int(tid)] = encoded_plate
        self.state.set_plate_crops(plate_crops)
        self.state.set_vehicle_crops(vehicle_crops)

    def test_scan_view(self, view: dict | None) -> dict:
        """OCR the zoomed monitor region. Does not post occupancy or write Laravel DB."""
        if self.infer_reader is None:
            return {"ok": False, "saved": False, "message": "Camera is not running.", "plate": None}
        ret, frame = self.infer_reader.read()
        if not ret or frame is None:
            return {"ok": False, "saved": False, "message": "No live frame.", "plate": None}
        ocr = self.plate_queue.ocr if self.plate_queue else None
        return scan_visible_region(frame, view, ocr, self.intelligence.tracks)

    def _try_sync_plate_ocr(self, frame, now: float) -> None:
        """Run one blocking OCR read per stalled track so plates appear without waiting on the async queue."""
        ocr = self.plate_queue.ocr if self.plate_queue else None
        if not ocr or not ocr.enabled:
            return
        from plate_owner_lookup import lookup_plate_async

        for tid, mem in list(self.intelligence.tracks.items()):
            if mem.plate_status == "ok" and mem.plate:
                continue
            if (now - mem.first_seen) < 0.15:
                continue
            if not getattr(mem, "last_ocr_xyxy", None):
                continue
            last_sync = float(getattr(mem, "last_sync_ocr_at", 0.0) or 0.0)
            if last_sync and (now - last_sync) < OCR_EVERY_SEC:
                continue
            mem.last_sync_ocr_at = now
            read = ocr.read_plate(frame, mem.last_ocr_xyxy, cls_id=getattr(mem, "cls_id", None))
            crop = PlateOCR.crop_plate_region(frame, mem.last_ocr_xyxy, cls_id=getattr(mem, "cls_id", None))
            if crop is not None:
                mem.last_plate_crop = crop
            mem.apply_ocr_vote(read.plate, read.status, read.confidence)
            if mem.needs_owner_lookup():
                lookup_plate_async(mem)

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
            use_half = bool(USE_HALF and self.device != "cpu")
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
                                half=use_half,
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
                                half=use_half,
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
                            half=use_half,
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
            if annotated_boxes and vehicle_count > 0:
                self._held_boxes = list(annotated_boxes)
                self._held_vehicles = list(vehicles)
                self._held_detections = list(detections)
                self._held_counts = (person_count, vehicle_count)
                self._held_until = now + BOX_HOLD_SEC
            elif vehicle_count == 0 and VEHICLES_ONLY:
                self._held_boxes = []
                self._held_vehicles = []
                self._held_detections = []
                self._held_until = 0.0
                annotated_boxes = []
                vehicles = []
                detections = []
                person_count = 0
            elif now < self._held_until and self._held_boxes:
                annotated_boxes = list(self._held_boxes)
                vehicles = list(self._held_vehicles)
                detections = list(self._held_detections)
                person_count, vehicle_count = self._held_counts

            self._try_sync_plate_ocr(frame, now)
            annotated_boxes = refresh_plates_from_tracks(
                detections, vehicles, annotated_boxes, self.intelligence
            )

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
            scene_snap = {
                "annotated_boxes": annotated_boxes,
                "person_count": person_count,
                "vehicle_count": vehicle_count,
                "occupied_slots": occupied_slots,
                "active_events": list(self.intelligence.active_events),
                "use_poly": use_poly,
                "detections": detections,
                "source_shape": frame.shape,
            }
            # Draw AI overlay on the infer RTSP frame (same FOV/coords as boxes), never the substream.
            self._publish_ai_overlay(frame, scene_snap)
            self._publish_plate_crops()
            last_infer = time.time()

            if now - last_post >= POST_EVERY_SEC:
                refresh_plates_from_tracks(
                    detections, vehicles, annotated_boxes, self.intelligence
                )
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
    model_name = resolve_model_name()
    model_path = ensure_model(model_name)

    cameras = load_cameras(BASE_DIR)
    if not cameras:
        raise SystemExit("No cameras configured. Set AI_CAMERA_1_IP (and optionally AI_CAMERA_2_IP / AI_CAMERA_3_IP).")

    print(f"Loading pretrained YOLOv9 ({model_name}) from {model_path}...")
    model = YOLO(str(model_path))
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
        f"tracker={'ultralytics' if USE_ULTRALYTICS_TRACK else 'iou'} | "
        f"detect={','.join(COCO_NAMES.get(i, str(i)) for i in DETECT_CLASS_IDS)} | "
        f"vehicles_only={'yes' if VEHICLES_ONLY else 'no'}"
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
