"""Pretrained YOLOv9 model registry for AI parking (Ultralytics COCO weights)."""

from __future__ import annotations

import os
import shutil
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
MODELS_DIR = BASE_DIR / "models"
DEFAULT_MODEL = "yolov9c"

# COCO pretrained — detects car, motorcycle, bus, truck (and person if enabled).
YOLOV9_VARIANTS: dict[str, dict[str, str]] = {
    "yolov9t": {
        "label": "YOLOv9-Tiny",
        "description": "Fastest; weak PCs, close range only.",
    },
    "yolov9s": {
        "label": "YOLOv9-Small",
        "description": "Fast; good for small parking lots.",
    },
    "yolov9m": {
        "label": "YOLOv9-Medium",
        "description": "Better distant/small vehicles; slower on CPU.",
    },
    "yolov9c": {
        "label": "YOLOv9-C",
        "description": "Balanced accuracy and speed (recommended default).",
    },
    "yolov9e": {
        "label": "YOLOv9-Extra",
        "description": "Highest accuracy; best with NVIDIA GPU.",
    },
}


def normalize_model_name(name: str | None) -> str:
    raw = (name or DEFAULT_MODEL).strip().lower()
    if raw.endswith(".pt"):
        raw = raw[:-3]
    if raw not in YOLOV9_VARIANTS:
        allowed = ", ".join(YOLOV9_VARIANTS)
        raise ValueError(f"Unknown YOLO model {raw!r}. Choose one of: {allowed}")
    return raw


def resolve_model_name() -> str:
    return normalize_model_name(os.getenv("AI_PARKING_YOLO_MODEL", DEFAULT_MODEL))


def model_path(name: str | None = None) -> Path:
    n = normalize_model_name(name or os.getenv("AI_PARKING_YOLO_MODEL", DEFAULT_MODEL))
    return MODELS_DIR / f"{n}.pt"


def resolve_model_path() -> Path:
    return model_path(resolve_model_name())


def ensure_model(name: str | None = None, force: bool = False) -> Path:
    """Download pretrained weights if missing."""
    dest = model_path(name)
    dest.parent.mkdir(parents=True, exist_ok=True)
    n = normalize_model_name(name or os.getenv("AI_PARKING_YOLO_MODEL", DEFAULT_MODEL))

    if dest.is_file() and not force:
        return dest

    from ultralytics import YOLO

    print(f"Downloading pretrained {n}.pt (Ultralytics — first run may take a minute)...")
    model = YOLO(f"{n}.pt")
    source = Path(getattr(model, "ckpt_path", None) or f"{n}.pt")
    if not source.is_file():
        source = BASE_DIR / f"{n}.pt"

    if source.is_file() and source.resolve() != dest.resolve():
        shutil.copy2(source, dest)
        if source.parent == BASE_DIR and source.name == f"{n}.pt":
            source.unlink(missing_ok=True)

    if not dest.is_file():
        raise SystemExit(f"Download failed for {n}.pt — check your internet connection.")

    print(f"Saved pretrained model to {dest}")
    return dest


def list_variants() -> str:
    lines = ["Available pretrained YOLOv9 models (COCO):"]
    for key, meta in YOLOV9_VARIANTS.items():
        marker = " (default)" if key == DEFAULT_MODEL else ""
        lines.append(f"  {key}{marker} - {meta['label']}: {meta['description']}")
    return "\n".join(lines)
