import threading
import time
from pathlib import Path

import cv2
from urllib.parse import quote
from ultralytics import YOLO

from load_env import load_project_env
from yolo_models import ensure_model, resolve_model_path

load_project_env()

# ========= PATHS =========
BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = resolve_model_path()

# ========= IP BULLET CAMERA CONFIG =========
CAMERA_IP = "192.168.1.108"
CAMERA_USER = "admin"
CAMERA_PASS = "CSPCcapstone12345"
CAMERA_PORT = 554

# Main stream = sharper image = better class names (more bandwidth than sub)
RTSP_PATH = "/cam/realmonitor?channel=1&subtype=0"

# Stronger model + full size = more correct object names
MODEL_NAME = str(MODEL_PATH)  # hardware/ai_parking/models/yolov9c.pt
IMG_SIZE = 640
CONF = 0.45                 # ignore weak/wrong guesses
IOU = 0.50                  # NMS overlap threshold
MAX_DET = 50

# Detect persons + vehicles only
DETECT_CLASSES = [0, 2, 3, 5, 7]  # person, car, motorcycle, bus, truck

# Exact COCO class names (id -> name)
COCO_NAMES = {
    0: "person", 1: "bicycle", 2: "car", 3: "motorcycle", 4: "airplane",
    5: "bus", 6: "train", 7: "truck", 8: "boat", 9: "traffic light",
    10: "fire hydrant", 11: "stop sign", 12: "parking meter", 13: "bench",
    14: "bird", 15: "cat", 16: "dog", 17: "horse", 18: "sheep", 19: "cow",
    20: "elephant", 21: "bear", 22: "zebra", 23: "giraffe", 24: "backpack",
    25: "umbrella", 26: "handbag", 27: "tie", 28: "suitcase", 29: "frisbee",
    30: "skis", 31: "snowboard", 32: "sports ball", 33: "kite", 34: "baseball bat",
    35: "baseball glove", 36: "skateboard", 37: "surfboard", 38: "tennis racket",
    39: "bottle", 40: "wine glass", 41: "cup", 42: "fork", 43: "knife",
    44: "spoon", 45: "bowl", 46: "banana", 47: "apple", 48: "sandwich",
    49: "orange", 50: "broccoli", 51: "carrot", 52: "hot dog", 53: "pizza",
    54: "donut", 55: "cake", 56: "chair", 57: "couch", 58: "potted plant",
    59: "bed", 60: "dining table", 61: "toilet", 62: "tv", 63: "laptop",
    64: "mouse", 65: "remote", 66: "keyboard", 67: "cell phone", 68: "microwave",
    69: "oven", 70: "toaster", 71: "sink", 72: "refrigerator", 73: "book",
    74: "clock", 75: "vase", 76: "scissors", 77: "teddy bear", 78: "hair drier",
    79: "toothbrush",
}

USE_WEBCAM = False
FULLSCREEN = True

RTSP_CANDIDATES = [
    "/cam/realmonitor?channel=1&subtype=0",  # Dahua main (best quality)
    "/cam/realmonitor?channel=1&subtype=1",  # Dahua sub (faster)
    "/Streaming/Channels/101",
    "/Streaming/Channels/102",
    "/h264Preview_01_main",
    "/h264Preview_01_sub",
    "/stream1",
    "/live",
]
# ===========================================


class LatestFrameReader:
    """Always keeps only the newest RTSP frame so YOLO lag does not pile up."""

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


def open_rtsp(ip, user, password, port, path):
    u = quote(user, safe="")
    p = quote(password, safe="")
    # Prefer TCP — more stable / less stutter on many LAN cameras
    url = f"rtsp://{u}:{p}@{ip}:{port}{path}"
    print(f"Trying: rtsp://{user}:***@{ip}:{port}{path}")
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    if not cap.isOpened():
        cap.release()
        return None
    ret, frame = cap.read()
    if not ret or frame is None:
        cap.release()
        return None
    print(f"OK — connected. Frame size: {frame.shape[1]}x{frame.shape[0]}")
    return cap


def draw_detections(frame, result):
    """Draw boxes with exact object names + confidence %."""
    annotated = frame.copy()
    boxes = result.boxes
    if boxes is None or len(boxes) == 0:
        return annotated, []

    detected = []
    for box in boxes:
        x1, y1, x2, y2 = map(int, box.xyxy[0].tolist())
        cls_id = int(box.cls[0])
        conf = float(box.conf[0])
        name = COCO_NAMES.get(cls_id, result.names.get(cls_id, f"class_{cls_id}"))
        label = f"{name} {conf * 100:.0f}%"
        detected.append(name)

        color = (0, 220, 0)
        cv2.rectangle(annotated, (x1, y1), (x2, y2), color, 2)

        (tw, th), _ = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.7, 2)
        ty = max(y1 - 8, th + 8)
        cv2.rectangle(annotated, (x1, ty - th - 8), (x1 + tw + 10, ty + 4), color, -1)
        cv2.putText(
            annotated,
            label,
            (x1 + 5, ty - 2),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.7,
            (0, 0, 0),
            2,
            cv2.LINE_AA,
        )

    return annotated, detected


print(f"Loading model {MODEL_NAME} (imgsz={IMG_SIZE}, conf={CONF})...")
ensure_model()
model = YOLO(MODEL_NAME)

# Use GPU if available (much faster + same accuracy)
try:
    import torch
    DEVICE = 0 if torch.cuda.is_available() else "cpu"
except Exception:
    DEVICE = "cpu"
print(f"Using device: {DEVICE}")

if USE_WEBCAM:
    print("Opening laptop webcam...")
    cap = cv2.VideoCapture(0)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
else:
    paths = [RTSP_PATH] + [p for p in RTSP_CANDIDATES if p != RTSP_PATH]
    cap = None
    for path in paths:
        cap = open_rtsp(CAMERA_IP, CAMERA_USER, CAMERA_PASS, CAMERA_PORT, path)
        if cap is not None:
            break

if cap is None or not cap.isOpened():
    print("ERROR: Could not open any RTSP path.")
    raise SystemExit(1)

reader = LatestFrameReader(cap)

window_name = "YOLOv9 Live Detection - IP Camera"
cv2.namedWindow(window_name, cv2.WINDOW_NORMAL)
if FULLSCREEN:
    cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, cv2.WINDOW_FULLSCREEN)

print("Connected. Detecting all objects. Press 'q' quit | 'f' fullscreen")

while True:
    ret, frame = reader.read()
    if not ret:
        time.sleep(0.005)
        continue

    predict_kwargs = dict(
        imgsz=IMG_SIZE,
        conf=CONF,
        iou=IOU,
        max_det=MAX_DET,
        device=DEVICE,
        verbose=False,
    )
    if DETECT_CLASSES is not None:
        predict_kwargs["classes"] = DETECT_CLASSES

    results = model(frame, **predict_kwargs)
    annotated, detected = draw_detections(frame, results[0])

    if detected:
        counts = {}
        for name in detected:
            counts[name] = counts.get(name, 0) + 1
        summary = " | ".join(f"{k}:{v}" for k, v in sorted(counts.items()))
        cv2.rectangle(annotated, (8, 8), (min(8 + 11 * len(summary) + 24, annotated.shape[1] - 8), 42), (0, 0, 0), -1)
        cv2.putText(
            annotated,
            summary,
            (16, 34),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.65,
            (0, 255, 0),
            2,
            cv2.LINE_AA,
        )

    cv2.imshow(window_name, annotated)

    key = cv2.waitKey(1) & 0xFF
    if key == ord("q"):
        break
    if key == ord("f"):
        FULLSCREEN = not FULLSCREEN
        mode = cv2.WINDOW_FULLSCREEN if FULLSCREEN else cv2.WINDOW_NORMAL
        cv2.setWindowProperty(window_name, cv2.WND_PROP_FULLSCREEN, mode)

reader.stop()
cap.release()
cv2.destroyAllWindows()
