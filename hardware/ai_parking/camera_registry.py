"""Load multi-camera RTSP configs from environment / Laravel .env."""

from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path
from typing import List, Optional


@dataclass(frozen=True)
class CameraConfig:
    camera_id: str
    name: str
    location: str
    area_id: int
    ip: str
    user: str
    password: str
    port: int
    rtsp_path: str
    stream_path: str
    zones_file: str
    enabled: bool = True
    # Low-latency / per-camera streaming knobs (wired Dahua benefits most)
    preview_rtsp_path: Optional[str] = None
    rtsp_transport: str = "tcp"
    preview_max_width: int = 960
    stream_fps: float = 20.0
    jpeg_quality: int = 88
    flush_frames: int = 1
    lite_preview: bool = False
    # 0 = use global AI_PARKING_INFER_MAX_WIDTH
    infer_max_width: int = 0
    # 0 = use global AI_PARKING_AI_STREAM_MAX_WIDTH (AI overlay MJPEG cap)
    ai_stream_max_width: int = 0

    @property
    def slug(self) -> str:
        return self.camera_id.replace(" ", "_")

    @property
    def has_credentials(self) -> bool:
        return bool(self.user.strip() and self.password.strip())

    @property
    def preview_path(self) -> str:
        return self.preview_rtsp_path or self.rtsp_path


def _env(key: str, default: str = "") -> str:
    return (os.getenv(key) or default).strip()


def _bool(key: str, default: bool = True) -> bool:
    raw = os.getenv(key)
    if raw is None:
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


def _int(key: str, default: int) -> int:
    raw = _env(key)
    if not raw:
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def _float(key: str, default: float) -> float:
    raw = _env(key)
    if not raw:
        return default
    try:
        return float(raw)
    except ValueError:
        return default


def load_cameras(base_dir: Path | None = None) -> List[CameraConfig]:
    """
    Build camera list from AI_CAMERA_{N}_* env vars.
    Camera 1 also accepts legacy AI_CAMERA_IP / AI_CAMERA_USER / …
    Cameras without an IP are skipped (n>1).
    """
    base_dir = base_dir or Path(__file__).resolve().parent
    cameras: List[CameraConfig] = []

    defaults = {
        1: {
            "id": "CAM-AI-1",
            "name": "Duran Hall Front",
            "location": "Duran Hall Front",
            "area_id": "4",
            "ip": _env("AI_CAMERA_IP", "192.168.1.108"),
            "user": _env("AI_CAMERA_USER", "admin"),
            "password": _env("AI_CAMERA_PASS", ""),
            "port": _env("AI_CAMERA_PORT", "554"),
            # Main stream for YOLO; substream for live preview (low latency)
            "rtsp_path": _env("AI_CAMERA_RTSP_PATH", "/cam/realmonitor?channel=1&subtype=0"),
            "preview_rtsp_path": "/cam/realmonitor?channel=1&subtype=1",
            "stream_path": "/stream.mjpg",
            "zones": "zones.json",
            "rtsp_transport": "udp",
            "preview_max_width": "960",
            "infer_max_width": "0",
            "stream_fps": "20",
            "jpeg_quality": "88",
            "flush_frames": "4",
            "lite_preview": "1",
        },
        2: {
            "id": "CAM-AI-2",
            "name": "Duran Hall Front",
            "location": "Duran Hall Front",
            "area_id": "3",
            "ip": "",
            "user": "",
            "password": "",
            "port": "554",
            "rtsp_path": "/stream1",
            "preview_rtsp_path": "",
            "stream_path": "/CAM-AI-2/stream.mjpg",
            "zones": "zones_duran.json",
            "rtsp_transport": "tcp",
            # Tapo C310 native max width (~2304×1296)
            "preview_max_width": "2304",
            "infer_max_width": "2304",
            "stream_fps": "15",
            "jpeg_quality": "75",
            "flush_frames": "1",
            "lite_preview": "0",
        },
        3: {
            "id": "CAM-AI-3",
            "name": "Talipapa",
            "location": "Talipapa",
            "area_id": "9",
            "ip": "",
            "user": "",
            "password": "",
            "port": "554",
            "rtsp_path": "/stream1",
            "preview_rtsp_path": "",
            "stream_path": "/CAM-AI-3/stream.mjpg",
            "zones": "zones_CAM-AI-3.json",
            "rtsp_transport": "tcp",
            "preview_max_width": "2304",
            "infer_max_width": "2304",
            "stream_fps": "15",
            "jpeg_quality": "75",
            "flush_frames": "1",
            "lite_preview": "0",
        },
    }

    max_index = 8
    for index in range(1, max_index + 1):
        prefix = f"AI_CAMERA_{index}_"
        base = defaults.get(index, {
            "id": f"CAM-AI-{index}",
            "name": f"AI Camera {index}",
            "location": "Campus",
            "area_id": str(18 + index),
            "ip": "",
            "user": "",
            "password": "",
            "port": "554",
            "rtsp_path": "/stream1",
            "preview_rtsp_path": "",
            "stream_path": f"/CAM-AI-{index}/stream.mjpg",
            "zones": f"zones_CAM-AI-{index}.json",
            "rtsp_transport": "tcp",
            "preview_max_width": "960",
            "infer_max_width": "0",
            "stream_fps": "20",
            "jpeg_quality": "65",
            "flush_frames": "1",
            "lite_preview": "0",
        })

        ip = _env(f"{prefix}IP")
        if not ip and index == 1:
            ip = base["ip"]

        enabled = _bool(f"{prefix}ENABLED", True if index < 3 else False)
        if not enabled:
            continue
        if index > 1 and not ip:
            continue
        if not ip and index == 1:
            continue

        if index == 1:
            user = _env(f"{prefix}USER") or _env("AI_CAMERA_USER", base["user"])
            password = _env(f"{prefix}PASS") or _env("AI_CAMERA_PASS", base["password"])
            port = _env(f"{prefix}PORT") or _env("AI_CAMERA_PORT", base["port"])
            rtsp_path = _env(f"{prefix}RTSP_PATH") or _env("AI_CAMERA_RTSP_PATH", base["rtsp_path"])
            area_default = _env("AI_PARKING_AREA_ID", base["area_id"])
        else:
            user = _env(f"{prefix}USER", base["user"])
            password = _env(f"{prefix}PASS", base["password"])
            port = _env(f"{prefix}PORT", base["port"])
            rtsp_path = _env(f"{prefix}RTSP_PATH", base["rtsp_path"])
            area_default = base["area_id"]

        camera_id = _env(f"{prefix}ID", _env("AI_CAMERA_ID", base["id"]) if index == 1 else base["id"])
        stream_path = _env(f"{prefix}STREAM_PATH", base["stream_path"])
        if not stream_path.startswith("/"):
            stream_path = "/" + stream_path

        preview_rtsp = _env(f"{prefix}PREVIEW_RTSP_PATH", base.get("preview_rtsp_path", ""))
        if preview_rtsp == "":
            preview_rtsp = None

        zones_name = _env(f"{prefix}ZONES", base["zones"])
        zones_path = str(base_dir / zones_name)
        if not (base_dir / zones_name).is_file() and (base_dir / "zones.json").is_file():
            zones_path = str(base_dir / "zones.json")

        transport = (_env(f"{prefix}RTSP_TRANSPORT", base.get("rtsp_transport", "tcp")) or "tcp").lower()
        if transport not in ("tcp", "udp"):
            transport = "tcp"

        cfg = CameraConfig(
            camera_id=camera_id,
            name=_env(f"{prefix}NAME", base["name"]).strip('"'),
            location=_env(f"{prefix}LOCATION", base["location"]).strip('"'),
            area_id=int(_env(f"{prefix}AREA_ID", area_default)),
            ip=ip,
            user=user,
            password=password,
            port=int(port or "554"),
            rtsp_path=rtsp_path,
            stream_path=stream_path,
            zones_file=zones_path,
            enabled=True,
            preview_rtsp_path=preview_rtsp,
            rtsp_transport=transport,
            preview_max_width=_int(f"{prefix}PREVIEW_MAX_WIDTH", int(base.get("preview_max_width", "960"))),
            stream_fps=_float(f"{prefix}STREAM_FPS", float(base.get("stream_fps", "20"))),
            jpeg_quality=_int(f"{prefix}JPEG_QUALITY", int(base.get("jpeg_quality", "88"))),
            flush_frames=max(1, _int(f"{prefix}FLUSH_FRAMES", int(base.get("flush_frames", "1")))),
            lite_preview=_bool(f"{prefix}LITE_PREVIEW", base.get("lite_preview", "0") in ("1", "true", "yes")),
            infer_max_width=_int(f"{prefix}INFER_MAX_WIDTH", int(base.get("infer_max_width", "0"))),
            ai_stream_max_width=_int(f"{prefix}AI_STREAM_MAX_WIDTH", int(base.get("ai_stream_max_width", "0"))),
        )

        if not cfg.has_credentials:
            print(
                f"[{cfg.camera_id}] WARNING: missing USER/PASS — RTSP will return 401. "
                f"Set {prefix}USER and {prefix}PASS in .env "
                "(Tapo: app → Advanced Settings → Camera Account)."
            )

        cameras.append(cfg)

    return cameras
