"""
Click-to-calibrate parking zone polygons on the LIVE YOLO frame.

CAM-1 (Prototype) — individual slots PT-1 … PT-5:

  cd hardware/ai_parking
  python calibrate_zones.py --zones zones_prototype.json --live --camera 1 --fresh

IMPORTANT:
  Uses the MAIN RTSP path (same FOV as YOLOv9 infer), NOT the preview substream.
  Click coordinates are mapped from the display window back to the full frame
  before save. Saved image_width/image_height match that frame.

Controls:
  Left-click  — add polygon point (yellow-line corners)
  U           — undo last point
  C           — commit current zone points
  N / P       — next / previous zone
  R           — reset current zone
  D           — delete / clear ALL zone points
  F           — refresh live frame (keep drawn polygons)
  S           — save (calibrated=true when any slot has 3+ points)
  Q / Esc     — quit without forcing save
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
DEFAULT_CAMERA = 1
# Max window width for clicking comfort; frame coords stay full-res.
DISPLAY_MAX_WIDTH = int(os.getenv("AI_PARKING_CALIBRATE_DISPLAY_MAX", "1280"))


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


def clear_zone_points(zones_data: dict) -> dict:
    for zone in zones_data.get("zones") or []:
        zone["points"] = []
    zones_data["calibrated"] = False
    return zones_data


def ensure_slot_template(zones_data: dict, prefix: str = "AC", count: int = 10) -> dict:
    """Guarantee individual slot stubs AC-1..AC-N exist (never one mega-row)."""
    zones = zones_data.setdefault("zones", [])
    by_id = {str(z.get("id")): z for z in zones}
    for i in range(1, count + 1):
        zid = f"{prefix}-{i}"
        if zid not in by_id:
            zones.append({"id": zid, "type": "slot", "label": zid, "points": []})
    for extra_id, ztype, label in (
        ("no-park-1", "no_parking", "No Parking"),
        ("aisle-1", "aisle", "Drive Lane / Aisle"),
    ):
        if extra_id not in by_id:
            zones.append({"id": extra_id, "type": ztype, "label": label, "points": []})
    zones_data["zones"] = zones
    return zones_data


class Calibrator:
    def __init__(self, frame, zones_data, zones_path: Path, camera: CameraConfig | None = None):
        self.camera = camera
        self.base = frame.copy()
        self.zones_data = zones_data
        self.zones_path = zones_path
        self.zones = zones_data.setdefault("zones", [])
        if not self.zones:
            raise SystemExit("zones file has no zones to calibrate.")
        self.idx = 0
        self.drawing: list[list[int]] = list(self.zones[0].get("points") or [])
        self.window = "Calibrate Zones - click yellow-line corners (display->frame mapped)"
        h, w = self.base.shape[:2]
        self.frame_w, self.frame_h = w, h
        self.disp_scale = 1.0
        if w > DISPLAY_MAX_WIDTH:
            self.disp_scale = DISPLAY_MAX_WIDTH / float(w)
        print(
            f"Calibration frame: {w}x{h} (YOLO/main FOV). "
            f"Display scale={self.disp_scale:.4f} "
            f"(clicks converted display->frame before save)."
        )

    def current(self):
        return self.zones[self.idx]

    def _to_display(self, pts: list[list[int]]) -> np.ndarray:
        if self.disp_scale == 1.0:
            return np.array(pts, dtype=np.int32)
        s = self.disp_scale
        return np.array([[int(round(p[0] * s)), int(round(p[1] * s))] for p in pts], dtype=np.int32)

    def _from_display(self, x: int, y: int) -> list[int]:
        if self.disp_scale == 1.0:
            return [int(x), int(y)]
        return [int(round(x / self.disp_scale)), int(round(y / self.disp_scale))]

    def redraw(self):
        img = self.base.copy()
        if self.disp_scale != 1.0:
            img = cv2.resize(
                img,
                (int(round(self.frame_w * self.disp_scale)), int(round(self.frame_h * self.disp_scale))),
                interpolation=cv2.INTER_AREA,
            )
        for i, z in enumerate(self.zones):
            pts = z.get("points") or []
            if i == self.idx:
                pts = self.drawing
            if len(pts) < 1:
                continue
            color = ZONE_COLORS.get(z.get("type", "slot"), (200, 200, 200))
            arr = self._to_display(pts)
            if len(pts) >= 2:
                cv2.polylines(img, [arr], len(pts) >= 3, color, 2)
            for p in arr:
                cv2.circle(img, tuple(p), 4, color, -1)
            cv2.putText(
                img,
                z.get("label") or z.get("id"),
                tuple(arr[0]),
                cv2.FONT_HERSHEY_SIMPLEX,
                0.55,
                color,
                2,
            )
        z = self.current()
        drawn = sum(1 for zz in self.zones if zz.get("type") == "slot" and len(zz.get("points") or []) >= 3)
        hud = (
            f"[{self.idx + 1}/{len(self.zones)}] {z.get('type')} {z.get('id')}  "
            f"pts={len(self.drawing)}  slots_drawn={drawn}  "
            f"frame={self.frame_w}x{self.frame_h}  "
            f"| click  U undo  C commit  N/P  R reset  D clear-all  F refresh  S save  Q quit"
        )
        cv2.rectangle(img, (0, 0), (img.shape[1], 40), (0, 0, 0), -1)
        cv2.putText(img, hud, (8, 26), cv2.FONT_HERSHEY_SIMPLEX, 0.48, (0, 255, 255), 1, cv2.LINE_AA)
        tip = "Draw EACH bay between yellow lines (AC-1, AC-2, ...). Not one big row polygon."
        cv2.putText(img, tip, (8, img.shape[0] - 12), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (180, 255, 180), 1, cv2.LINE_AA)
        return img

    def on_mouse(self, event, x, y, flags, param):
        if event == cv2.EVENT_LBUTTONDOWN:
            self.drawing.append(self._from_display(x, y))

    def commit_drawing(self):
        self.current()["points"] = [list(p) for p in self.drawing]

    def refresh_frame(self):
        if self.camera is None:
            print("No live camera - cannot refresh.")
            return
        frame = grab_main_rtsp_frame(self.camera)
        if frame is None:
            print("Refresh failed - keeping previous frame.")
            return
        self.base = frame.copy()
        h, w = self.base.shape[:2]
        self.frame_w, self.frame_h = w, h
        self.disp_scale = DISPLAY_MAX_WIDTH / float(w) if w > DISPLAY_MAX_WIDTH else 1.0
        print(f"Refreshed live frame {w}x{h}")

    def save(self):
        self.commit_drawing()
        self.zones_data["image_width"] = int(self.frame_w)
        self.zones_data["image_height"] = int(self.frame_h)
        self.zones_data["calibration_source"] = "live_main_rtsp"
        if self.camera is not None:
            self.zones_data["camera_id"] = self.camera.camera_id
        calibrated = any(
            z.get("type") == "slot" and len(z.get("points") or []) >= 3 for z in self.zones
        )
        self.zones_data["calibrated"] = calibrated
        save_zones(self.zones_path, self.zones_data)
        print(f"Saved {self.zones_path} calibrated={calibrated} size={self.frame_w}x{self.frame_h}")
        for z in self.zones:
            if z.get("type") == "slot" and len(z.get("points") or []) >= 3:
                print(f"  {z.get('id')}: {len(z['points'])} points")
        if self.zones_path.name != "zones.json":
            # Keep active zones.json in sync for tools that still read it.
            save_zones(DEFAULT_ZONES_PATH, self.zones_data)
            print(f"Also updated {DEFAULT_ZONES_PATH.name}")

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
            if key in (ord("d"), ord("D")):
                clear_zone_points(self.zones_data)
                self.drawing = []
                print("Cleared all zone polygons.")
            if key in (ord("c"), ord("C")):
                self.commit_drawing()
            if key in (ord("f"), ord("F")):
                self.commit_drawing()
                self.refresh_frame()
            if key in (ord("n"), ord("N")):
                self.commit_drawing()
                self.idx = (self.idx + 1) % len(self.zones)
                self.drawing = list(self.current().get("points") or [])
            if key in (ord("p"), ord("P")):
                self.commit_drawing()
                self.idx = (self.idx - 1) % len(self.zones)
                self.drawing = list(self.current().get("points") or [])
            if key in (ord("s"), ord("S")):
                self.save()
        cv2.destroyAllWindows()


def grab_main_rtsp_frame(camera: CameraConfig):
    """Grab one frame from the MAIN RTSP path (YOLO FOV), never the preview substream."""
    transport = (camera.rtsp_transport or "tcp").lower()
    if transport not in ("tcp", "udp"):
        transport = "tcp"

    u = quote(camera.user, safe="")
    p = quote(camera.password, safe="")
    # Prefer main/infer path - same FOV as YOLOv9 boxes after scale-up.
    path = camera.rtsp_path or camera.preview_path
    url = f"rtsp://{u}:{p}@{camera.ip}:{camera.port}{path}"

    # Try preferred transport first, then the other (Dahua often drops when too many TCP sessions).
    transports = [transport] + ([t for t in ("tcp", "udp") if t != transport])
    for attempt, tr in enumerate(transports, start=1):
        os.environ["OPENCV_FFMPEG_CAPTURE_OPTIONS"] = (
            f"rtsp_transport;{tr}|stimeout;20000000|max_delay;5000000"
        )
        print(f"Opening MAIN RTSP {camera.camera_id} {camera.ip}{path} ({tr}) attempt {attempt}...")
        cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        if not cap.isOpened():
            cap.release()
            print(f"  open failed ({tr})")
            continue
        frame = None
        deadline = time.monotonic() + 25.0
        # Flush a few buffered frames so we land on a fresh image.
        while time.monotonic() < deadline:
            ret, frame = cap.read()
            if ret and frame is not None:
                # Drain a couple more for freshness.
                for _ in range(3):
                    r2, f2 = cap.read()
                    if r2 and f2 is not None:
                        frame = f2
                break
            time.sleep(0.05)
        cap.release()
        if frame is not None:
            print(
                f"Live MAIN frame {frame.shape[1]}x{frame.shape[0]} "
                f"(this is the YOLO coordinate space)"
            )
            return frame
        print(f"  read timed out ({tr})")
    return None


def grab_frame(image_path: str | None, camera: CameraConfig | None):
    if image_path:
        frame = cv2.imread(image_path)
        if frame is None:
            raise SystemExit(f"Could not read image: {image_path}")
        print(f"WARNING: calibrating from still image {image_path} - prefer --live for production.")
        return frame

    if camera is None:
        raise SystemExit("No camera selected.")
    if not camera.has_credentials:
        raise SystemExit(f"{camera.camera_id}: missing USER/PASS in .env.")

    frame = grab_main_rtsp_frame(camera)
    if frame is None:
        raise SystemExit(
            f"Could not grab MAIN RTSP frame for {camera.camera_id}. "
            "Check IP/credentials and that the camera is online."
        )
    return frame


def main():
    parser = argparse.ArgumentParser(description="Calibrate AI parking zone polygons on live YOLO frame")
    parser.add_argument("--image", help="Still image (not recommended for production)")
    parser.add_argument("--live", action="store_true", help="Grab live MAIN RTSP frame")
    parser.add_argument("--fresh", action="store_true", help="Clear old polygons first")
    parser.add_argument("--zones", default="zones_prototype.json", help="Zone JSON (default: zones_prototype.json)")
    parser.add_argument("--camera", type=int, default=DEFAULT_CAMERA, help="Camera number (1=CAM-1)")
    parser.add_argument("--slots", type=int, default=5, help="Ensure this many slot stubs")
    args = parser.parse_args()

    zones_path = Path(args.zones)
    if not zones_path.is_absolute():
        zones_path = BASE_DIR / zones_path

    image_path = args.image
    if args.live:
        image_path = None
    elif not image_path:
        # Default: live when no --image (production path).
        args.live = True
        image_path = None

    camera = None if image_path else resolve_camera(args.camera)
    if image_path:
        print(f"Using snapshot {image_path}")
    else:
        print(f"Using LIVE MAIN stream camera {args.camera} ({camera.camera_id if camera else '?'})")

    frame = grab_frame(image_path, camera)
    zones = load_zones(zones_path)
    prefix = "AC"
    name = zones_path.name.lower()
    if "prototype" in name or "proto" in name:
        prefix = "PT"
    elif "duran" in name:
        prefix = "DU"
    elif "auditorium" in name:
        prefix = "AU"
    ensure_slot_template(zones, prefix=prefix, count=max(1, args.slots))
    if args.fresh:
        clear_zone_points(zones)
        print("Fresh mode: draw EACH parking bay on the yellow lines, then press S.")

    Calibrator(frame, zones, zones_path, camera=camera).run()


if __name__ == "__main__":
    main()
