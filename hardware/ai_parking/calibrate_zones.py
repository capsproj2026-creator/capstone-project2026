"""
Click-to-calibrate parking zone polygons.

Run (camera or image):
  cd hardware/ai_parking
  python calibrate_zones.py
  python calibrate_zones.py --image snapshot.jpg

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

import cv2
import numpy as np

from geometry import ZONE_COLORS, load_zones, save_zones

BASE_DIR = Path(__file__).resolve().parent
ZONES_PATH = BASE_DIR / "zones.json"

CAMERA_IP = os.getenv("AI_CAMERA_IP", "192.168.1.108")
CAMERA_USER = os.getenv("AI_CAMERA_USER", "admin")
CAMERA_PASS = os.getenv("AI_CAMERA_PASS", "CSPCcapstone12345")
CAMERA_PORT = int(os.getenv("AI_CAMERA_PORT", "554"))
RTSP_PATH = os.getenv("AI_CAMERA_RTSP_PATH", "/cam/realmonitor?channel=1&subtype=0")


class Calibrator:
    def __init__(self, frame, zones_data):
        self.base = frame.copy()
        self.zones_data = zones_data
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
                save_zones(ZONES_PATH, self.zones_data)
                print(f"Saved {ZONES_PATH} (calibrated={calibrated})")
        cv2.destroyAllWindows()


def grab_frame(image_path: str | None):
    if image_path:
        frame = cv2.imread(image_path)
        if frame is None:
            raise SystemExit(f"Could not read image: {image_path}")
        return frame

    u = quote(CAMERA_USER, safe="")
    p = quote(CAMERA_PASS, safe="")
    url = f"rtsp://{u}:{p}@{CAMERA_IP}:{CAMERA_PORT}{RTSP_PATH}"
    print(f"Opening camera {CAMERA_IP}...")
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    if not cap.isOpened():
        raise SystemExit("Camera open failed. Pass --image snapshot.jpg instead.")
    # warm up
    frame = None
    for _ in range(30):
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
    args = parser.parse_args()

    frame = grab_frame(args.image)
    zones = load_zones(ZONES_PATH)
    Calibrator(frame, zones).run()


if __name__ == "__main__":
    main()
