"""Quick RTSP probe for one camera (Tapo/Dahua). Does not start YOLO.

Usage:
  cd hardware/ai_parking
  python test_rtsp.py --ip 192.168.1.104 --user YOUR_USER --password YOUR_PASS
  python test_rtsp.py --from-env 2
"""

from __future__ import annotations

import argparse
import os
import sys

os.environ.setdefault("OPENCV_FFMPEG_CAPTURE_OPTIONS", "rtsp_transport;tcp")

import cv2

from camera_registry import load_cameras
from load_env import load_project_env


def try_url(url: str, label: str) -> bool:
    safe = url
    if "@" in url:
        # hide password in print
        pre, post = url.split("@", 1)
        safe = pre.rsplit(":", 1)[0] + ":***@" + post
    print(f"Trying {label}: {safe}")
    cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
    cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    if not cap.isOpened():
        print("  FAIL: open")
        cap.release()
        return False
    ret, frame = cap.read()
    cap.release()
    if not ret or frame is None:
        print("  FAIL: no frame")
        return False
    print(f"  OK: {frame.shape[1]}x{frame.shape[0]}")
    return True


def main() -> int:
    load_project_env()
    parser = argparse.ArgumentParser()
    parser.add_argument("--ip")
    parser.add_argument("--user", default="")
    parser.add_argument("--password", default="")
    parser.add_argument("--port", type=int, default=554)
    parser.add_argument("--path", default="/stream1")
    parser.add_argument("--from-env", type=int, help="Load AI_CAMERA_N from .env (1/2/3)")
    args = parser.parse_args()

    if args.from_env:
        cams = {i + 1: c for i, c in enumerate(load_cameras())}
        # load_cameras returns list not indexed by N — find by id suffix
        all_cams = load_cameras()
        target = None
        for c in all_cams:
            if c.camera_id.endswith(str(args.from_env)) or (
                args.from_env == 1 and c.camera_id == "CAM-AI-1"
            ):
                target = c
                break
        if not target and args.from_env <= len(all_cams):
            target = all_cams[args.from_env - 1]
        if not target:
            print(f"Camera {args.from_env} not loaded (check IP/ENABLED in .env)")
            return 1
        ip, user, password, port, path = target.ip, target.user, target.password, target.port, target.rtsp_path
        print(f"From env: {target.camera_id} {ip} user={user!r} pass_len={len(password)}")
    else:
        if not args.ip:
            print("--ip or --from-env required")
            return 1
        ip, user, password, port, path = args.ip, args.user, args.password, args.port, args.path

    if not user or not password:
        print("ERROR: username/password required. Tapo returns 401 without Camera Account.")
        print("Create one in Tapo app → Settings → Advanced → Camera Account, then set .env.")
        return 2

    from urllib.parse import quote

    u, p = quote(user, safe=""), quote(password, safe="")
    ok = False
    for try_path in (path, "/stream1", "/stream2"):
        url = f"rtsp://{u}:{p}@{ip}:{port}{try_path}"
        if try_url(url, try_path):
            ok = True
            break
    return 0 if ok else 3


if __name__ == "__main__":
    sys.exit(main())
