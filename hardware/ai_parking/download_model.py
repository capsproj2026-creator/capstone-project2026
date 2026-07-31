"""Download YOLOv9c weights into hardware/ai_parking/models/."""

from __future__ import annotations

import shutil
from pathlib import Path

from ultralytics import YOLO

BASE_DIR = Path(__file__).resolve().parent
MODEL_PATH = BASE_DIR / "models" / "yolov9c.pt"


def main() -> None:
    MODEL_PATH.parent.mkdir(parents=True, exist_ok=True)
    if MODEL_PATH.is_file():
        print(f"Model already exists: {MODEL_PATH}")
        return

    print("Downloading yolov9c.pt (first run may take a minute)...")
    model = YOLO("yolov9c.pt")
    source = Path(getattr(model, "ckpt_path", None) or "yolov9c.pt")
    if not source.is_file():
        source = BASE_DIR / "yolov9c.pt"

    if source.is_file() and source.resolve() != MODEL_PATH.resolve():
        shutil.copy2(source, MODEL_PATH)
        if source.parent == BASE_DIR and source.name == "yolov9c.pt":
            source.unlink(missing_ok=True)

    if MODEL_PATH.is_file():
        print(f"Saved model to {MODEL_PATH}")
    else:
        raise SystemExit("Download failed — check your internet connection and try again.")


if __name__ == "__main__":
    main()
