"""
Click-to-calibrate parking zone polygons.

Run (camera or image):
  cd hardware/ai_parking
  python calibrate_zones.py --zones zones_acad1.json
  python calibrate_zones.py --live --camera 2
  python calibrate_zones.py --image snapshot_acad1.jpg

Uses snapshot_acad1.jpg for ACAD 1 when that file exists. Pass --live to grab CAM-2.

Controls:
  Left-click  — add polygon point
  U           — undo last point
  C           — close/finish current zone points
  N           — next zone
  P           — previous zone
  S           — save zones.json (sets calibrated=true if any slot has 3+ points)
  R           — reset current zone points
  Q / Esc     — quit without saving
"""

from __future__ import annotations

import argparse
import os
import time
from pathlib import Path
from urllib.parse import quote

from load_env import load_project_env

load_project_env()
os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

import cv2
import numpy as np

from camera_registry import CameraConfig, load_cameras
from geometry import ZONE_COLORS, load_zones, save_zones

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_ZONES_PATH = BASE_DIR / "zones.json"
DEFAULT_CAMERA = 2


def resolve_camera(index: int) -> CameraConfig:
    cameras = load_cameras()
    for cam in cameras:
        if cam.camera_id.endswith(str(index)) or (
            index == 1 and cam.camera_id.upper() in {"CAM-1", "CAM-AI-1"}
        ):
            return cam
    if 1 <= index <= len(cameras):
        return cameras[index - 1]
    raise SystemExit(
        f"Camera {index} is not in .env (check AI_CAMERA_{index}_IP and ENABLED)."
    )


def companion_snapshot(zones_path: Path) -> str | None:
    """zones_acad1.json → snapshot_acad1.jpg when that file exists."""
    data = load_zones(zones_path)
    named = str(data.get("snapshot") or "").strip()
    if named:
        candidate = Path(named)
        if not candidate.is_absolute():
            candidate = BASE_DIR / candidate
        if candidate.is_file():
            return str(candidate)
    stem = zones_path.stem
    if stem.startswith("zones_"):
        candidate = BASE_DIR / f"snapshot_{stem[6:]}.jpg"
        if candidate.is_file():
            return str(candidate)
    return None


class Calibrator:
    def __init__(self, frame, zones_data, zones_path: Path):
        self.base = frame.copy()
        self.zones_data = zones_data
        self.zones_path = zones_path
        self.zones = zones_data.setdefault("zones", [])
        if not self.zones:
            raise SystemExit("zones.json has no zones to calibrate.")
        self.idx = 0
        self.drawing: list[list[int]] = list(self.zones[0].get("points") or [])
        self.window = "Calibrate Zones — click points"

    def current(self):
        return self.zones[self.idx]

    def redraw(self):
        img = self.base.copy()
        for i, z in enumerate(self.zones):
            pts = z.get("points") or []
            if i == self.idx:
                pts = self.drawing
            if len(pts) >= 1:
                color = ZONE_COLORS.get(z.get("type", "slot"), (200, 200, 200))
                arr = np.array(pts, dtype=np.int32)
                if len(pts) >= 2:
                    cv2.polylines(img, [arr], len(pts) >= 3, color, 2)
                for p in pts:
                    cv2.circle(img, tuple(p), 4, color, -1)
                if pts:
                    cv2.putText(
                        img,
                        z.get("label") or z.get("id"),
                        tuple(pts[0]),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        0.6,
                        color,
                        2,
                    )
        z = self.current()
        hud = f"[{self.idx + 1}/{len(self.zones)}] {z.get('type')} {z.get('id')}  |  click=add  U=undo  R=reset  N/P=next/prev  S=save  Q=quit"
        cv2.rectangle(img, (0, 0), (img.shape[1], 36), (0, 0, 0), -1)
        cv2.putText(img, hud, (10, 24), cv2.FONT_HERSHEY_SIMPLEX, 0.55, (0, 255, 255), 1, cv2.LINE_AA)
        return img

    def on_mouse(self, event, x, y, flags, param):
        if event == cv2.EVENT_LBUTTONDOWN:
            self.drawing.append([int(x), int(y)])

    def commit_drawing(self):
        self.current()["points"] = [list(p) for p in self.drawing]

    def run(self):
        cv2.namedWindow(self.window, cv2.WINDOW_NORMAL)
        cv2.setMouseCallback(self.window, self.on_mouse)
        while True:
            cv2.imshow(self.window, self.redraw())
            key = cv2.waitKey(20) & 0xFF
            if key in (27, ord("q"), ord("Q")):
                break
            if key in (ord("u"), ord("U")):
                if self.drawing:
                    self.drawing.pop()
            if key in (ord("r"), ord("R")):
                self.drawing = []
            if key in (ord("c"), ord("C")):
                self.commit_drawing()
            if key in (ord("n"), ord("N")):
                self.commit_drawing()
                self.idx = (self.idx + 1) % len(self.zones)
                self.drawing = list(self.current().get("points") or [])
            if key in (ord("p"), ord("P")):
                self.commit_drawing()
                self.idx = (self.idx - 1) % len(self.zones)
                self.drawing = list(self.current().get("points") or [])
            if key in (ord("s"), ord("S")):
                self.commit_drawing()
                h, w = self.base.shape[:2]
                self.zones_data["image_width"] = w
                self.zones_data["image_height"] = h
                calibrated = any(
                    z.get("type") == "slot" and len(z.get("points") or []) >= 3 for z in self.zones
                )
                self.zones_data["calibrated"] = calibrated
                save_zones(self.zones_path, self.zones_data)
                print(f"Saved {self.zones_path} (calibrated={calibrated})")
                if self.zones_path.name != "zones.json" and DEFAULT_ZONES_PATH.is_file():
                    active = load_zones(DEFAULT_ZONES_PATH)
                    if active.get("snapshot") and active.get("snapshot") == self.zones_data.get("snapshot"):
                        save_zones(DEFAULT_ZONES_PATH, self.zones_data)
                        print(f"Also updated {DEFAULT_ZONES_PATH.name} (same snapshot)")
        cv2.destroyAllWindows()


def grab_mjpeg_frame(camera: CameraConfig):
    """Grab one JPEG from the local AI MJPEG proxy (no extra RTSP client)."""
    import urllib.request

    base = (os.getenv("AI_PARKING_STREAM_BASE") or "http://127.0.0.1:8090").rstrip("/")
    paths = []
    for path in (camera.stream_path, f"/{camera.camera_id}/stream.mjpg"):
        if path and path not in paths:
            paths.append(path if path.startswith("/") else f"/{path}")

    soi, eoi = b"\xff\xd8", b"\xff\xd9"
    for path in paths:
        url = base + path
        try:
            with urllib.request.urlopen(url, timeout=8) as response:
                buf = b""
                while eoi not in buf and len(buf) < 4_000_000:
                    chunk = response.read(32768)
                    if not chunk:
                        break
                    buf += chunk
            start, end = buf.find(soi), buf.find(eoi)
            if start < 0 or end <= start:
                continue
            data = np.frombuffer(buf[start : end + 2], dtype=np.uint8)
            frame = cv2.imdecode(data, cv2.IMREAD_COLOR)
            if frame is not None:
                print(f"Frame {frame.shape[1]}x{frame.shape[0]} from {url}")
                return frame
        except Exception:
            continue
    return None


def grab_frame(image_path: str | None, camera: CameraConfig | None):
    if image_path:
        frame = cv2.imread(image_path)
        if frame is None:
            raise SystemExit(f"Could not read image: {image_path}")
        return frame

    if camera is None:
        raise SystemExit("No camera selected.")
    if not camera.has_credentials:
        raise SystemExit(
            f"{camera.camera_id}: missing USER/PASS in .env. "
            "Set AI_CAMERA_2_USER and AI_CAMERA_2_PASS."
        )

    mjpeg = grab_mjpeg_frame(camera)
    if mjpeg is not None and mjpeg.shape[1] >= 960:
        return mjpeg

    transport = (camera.rtsp_transport or "tcp").lower()
    if transport not in ("tcp", "udp"):
        transport = "tcp"
    os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = f"rtsp_transport;{transport}|stimeout;8000000"

    u = quote(camera.user, safe="")
    p = quote(camera.password, safe="")
    path = camera.preview_path or camera.rtsp_path
    url = f"rtsp://{u}:{p}@{camera.ip}:{camera.port}{path}"
    print(f"Opening {camera.camera_id} at {camera.ip}{path} ({transport})...")
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    if not cap.isOpened():
        cap.release()
        raise SystemExit(
            f"Camera open failed for {camera.camera_id} ({camera.ip}). "
            "Pass --image snapshot.jpg, or use --camera 1 if the Dahua is on this Wi-Fi."
        )
    frame = None
    deadline = time.monotonic() + 12.0
    while time.monotonic() < deadline:
        ret, frame = cap.read()
        if ret and frame is not None:
            break
        time.sleep(0.05)
    cap.release()
    if frame is None:
        raise SystemExit("Could not grab camera frame.")
    print(f"Frame {frame.shape[1]}x{frame.shape[0]}")
    return frame


def main():
    parser = argparse.ArgumentParser(description="Calibrate AI parking zone polygons")
    parser.add_argument("--image", help="Use a still image instead of live camera")
    parser.add_argument(
        "--live",
        action="store_true",
        help="Grab a live frame from --camera instead of the lot snapshot",
    )
    parser.add_argument(
        "--zones",
        default="zones.json",
        help="Zone file to edit (default: zones.json). Example: zones_acad1.json",
    )
    parser.add_argument(
        "--camera",
        type=int,
        default=DEFAULT_CAMERA,
        help="Camera number from .env (default: 2 = Tapo). Use 1 for Dahua.",
    )
    args = parser.parse_args()

    zones_path = Path(args.zones)
    if not zones_path.is_absolute():
        zones_path = BASE_DIR / zones_path

    image_path = args.image
    if not image_path and not args.live:
        image_path = companion_snapshot(zones_path)

    camera = None if image_path else resolve_camera(args.camera)
    if image_path:
        print(f"Using snapshot {image_path}")
    frame = grab_frame(image_path, camera)
    zones = load_zones(zones_path)
    Calibrator(frame, zones, zones_path).run()


if __name__ == "__main__":
    main()
