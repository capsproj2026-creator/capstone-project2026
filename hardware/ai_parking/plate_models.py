"""Pretrained license-plate YOLO models for tighter OCR crops."""

from __future__ import annotations

import os
import urllib.request
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
MODELS_DIR = BASE_DIR / "models"
DEFAULT_VARIANT = "v1s"
DEFAULT_DEST_NAME = "plate.pt"

# Fine-tuned YOLOv11 plate detectors (MorseTech Lab / Roboflow dataset).
# Ultralytics-compatible .pt weights — used inside vehicle crops before EasyOCR.
PLATE_MODEL_VARIANTS: dict[str, dict[str, str]] = {
    "v1n": {
        "label": "YOLOv11-Nano plate",
        "description": "Fastest; good for CPU edge devices.",
        "filename": "license-plate-finetune-v1n.pt",
        "url": (
            "https://github.com/morsetechlab/yolov11-license-plate-detection/"
            "releases/download/v1.0.0/license-plate-finetune-v1n.pt"
        ),
    },
    "v1s": {
        "label": "YOLOv11-Small plate",
        "description": "Balanced speed and accuracy (recommended default).",
        "filename": "license-plate-finetune-v1s.pt",
        "url": (
            "https://github.com/morsetechlab/yolov11-license-plate-detection/"
            "releases/download/v1.0.0/license-plate-finetune-v1s.pt"
        ),
    },
    "v1m": {
        "label": "YOLOv11-Medium plate",
        "description": "Better on distant/small plates; slower.",
        "filename": "license-plate-finetune-v1m.pt",
        "url": (
            "https://github.com/morsetechlab/yolov11-license-plate-detection/"
            "releases/download/v1.0.0/license-plate-finetune-v1m.pt"
        ),
    },
}


def normalize_variant(name: str | None) -> str:
    raw = (name or DEFAULT_VARIANT).strip().lower()
    if raw.endswith(".pt"):
        raw = raw[:-3]
    if raw in {"plate", "default"}:
        return DEFAULT_VARIANT
    if raw not in PLATE_MODEL_VARIANTS:
        allowed = ", ".join(PLATE_MODEL_VARIANTS)
        raise ValueError(f"Unknown plate model {raw!r}. Choose one of: {allowed}")
    return raw


def resolve_variant() -> str:
    return normalize_variant(os.getenv("AI_PARKING_PLATE_MODEL_VARIANT", DEFAULT_VARIANT))


def default_plate_path() -> Path:
    custom = os.getenv("AI_PARKING_PLATE_MODEL", "").strip()
    if custom:
        path = Path(custom)
        if not path.is_absolute():
            path = BASE_DIR / path
        return path
    return MODELS_DIR / DEFAULT_DEST_NAME


def ensure_plate_model(variant: str | None = None, force: bool = False) -> Path:
    """Download pretrained plate YOLO weights to models/plate.pt (or AI_PARKING_PLATE_MODEL path)."""
    dest = default_plate_path()
    dest.parent.mkdir(parents=True, exist_ok=True)

    if dest.is_file() and not force:
        return dest

    key = normalize_variant(variant or resolve_variant())
    meta = PLATE_MODEL_VARIANTS[key]
    url = meta["url"]
    tmp = dest.with_suffix(".pt.download")

    print(f"Downloading {meta['label']} ({key})...")
    print(f"  Source: {url}")
    print(f"  Dest:   {dest}")

    def _report(block_num: int, block_size: int, total_size: int) -> None:
        if total_size <= 0:
            return
        done = block_num * block_size
        pct = min(100, int(done * 100 / total_size))
        mb = done / (1024 * 1024)
        total_mb = total_size / (1024 * 1024)
        print(f"\r  Progress: {pct}% ({mb:.1f}/{total_mb:.1f} MB)", end="", flush=True)

    try:
        urllib.request.urlretrieve(url, tmp, reporthook=_report)
        print()
    except Exception as exc:
        tmp.unlink(missing_ok=True)
        raise SystemExit(f"Plate model download failed: {exc}") from exc

    if not tmp.is_file() or tmp.stat().st_size < 1_000_000:
        tmp.unlink(missing_ok=True)
        raise SystemExit("Plate model download failed — file too small or missing.")

    tmp.replace(dest)
    size_mb = dest.stat().st_size // (1024 * 1024)
    print(f"Saved plate model to {dest} ({size_mb} MB)")
    return dest


def list_variants() -> str:
    lines = ["Available pretrained plate YOLO models:"]
    for key, meta in PLATE_MODEL_VARIANTS.items():
        marker = " (default)" if key == DEFAULT_VARIANT else ""
        lines.append(f"  {key}{marker} - {meta['label']}: {meta['description']}")
    lines.append("")
    lines.append(f"Saved as: {MODELS_DIR / DEFAULT_DEST_NAME}")
    lines.append("Override path with AI_PARKING_PLATE_MODEL in .env")
    return "\n".join(lines)
