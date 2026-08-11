"""Download pretrained YOLOv9 weights into hardware/ai_parking/models/."""

from __future__ import annotations

import argparse

from load_env import load_project_env
from yolo_models import ensure_model, list_variants, resolve_model_name

load_project_env()


def main() -> None:
    parser = argparse.ArgumentParser(description="Download Ultralytics pretrained YOLOv9 weights.")
    parser.add_argument(
        "--model",
        "-m",
        default=None,
        help="Model name: yolov9t, yolov9s, yolov9m, yolov9c (default), yolov9e",
    )
    parser.add_argument("--force", action="store_true", help="Re-download even if file exists.")
    parser.add_argument("--list", action="store_true", help="List supported models and exit.")
    args = parser.parse_args()

    if args.list:
        print(list_variants())
        return

    name = args.model or resolve_model_name()
    path = ensure_model(name, force=args.force)
    print(f"Ready: {path} ({path.stat().st_size // (1024 * 1024)} MB)")


if __name__ == "__main__":
    main()
