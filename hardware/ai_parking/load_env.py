"""Load Laravel .env into os.environ (does not override existing vars)."""

from __future__ import annotations

import os
from pathlib import Path


def _strip_quotes(value: str) -> str:
    value = value.strip()
    if len(value) >= 2 and value[0] == value[-1] and value[0] in ('"', "'"):
        return value[1:-1]
    return value


def load_project_env() -> Path | None:
    """Read repo-root .env and map keys used by the AI parking service."""
    root = Path(__file__).resolve().parents[2]
    env_path = root / ".env"
    if not env_path.is_file():
        return None

    mapping = {
        "AI_CAMERA_IP": "AI_CAMERA_IP",
        "AI_CAMERA_USER": "AI_CAMERA_USER",
        "AI_CAMERA_PASS": "AI_CAMERA_PASS",
        "AI_CAMERA_PORT": "AI_CAMERA_PORT",
        "AI_CAMERA_RTSP_PATH": "AI_CAMERA_RTSP_PATH",
        "AI_CAMERA_ID": "AI_CAMERA_ID",
        "AI_PARKING_API_TOKEN": "AI_PARKING_API_TOKEN",
        "AI_PARKING_AREA_ID": "AI_PARKING_AREA_ID",
        "AI_PARKING_OVERTIME_MINUTES": "AI_PARKING_OVERTIME_MINUTES",
        "AI_PARKING_VIOLATION_DEBOUNCE_MINUTES": "AI_PARKING_VIOLATION_DEBOUNCE_MINUTES",
        "AI_PARKING_OCR_ENABLED": "AI_PARKING_OCR_ENABLED",
        "AI_PARKING_OCR_EVERY_SEC": "AI_PARKING_OCR_EVERY_SEC",
        "AI_STREAM_HOST": "AI_STREAM_HOST",
        "AI_STREAM_PORT": "AI_STREAM_PORT",
        "AI_USE_WEBCAM": "AI_USE_WEBCAM",
        "APP_URL": "AI_LARAVEL_API_BASE",
    }

    for line in env_path.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, raw = line.partition("=")
        key = key.strip()
        if key not in mapping:
            continue
        target = mapping[key]
        if os.getenv(target):
            continue
        os.environ[target] = _strip_quotes(raw)

    return env_path
