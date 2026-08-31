"""Download pretrained license-plate YOLO weights into hardware/ai_parking/models/plate.pt."""

from __future__ import annotations

import argparse

from load_env import load_project_env
from plate_models import ensure_plate_model, list_variants, resolve_variant

load_project_env()


def main() -> None:
    parser = argparse.ArgumentParser(
        description="Download pretrained YOLO license-plate detector (saved as models/plate.pt)."
    )
    parser.add_argument(
        "--variant",
        "-v",
        default=None,
        help="Model variant: v1n (fast), v1s (default), v1m (accurate)",
    )
    parser.add_argument("--force", action="store_true", help="Re-download even if plate.pt exists.")
    parser.add_argument("--list", action="store_true", help="List supported plate models and exit.")
    args = parser.parse_args()

    if args.list:
        print(list_variants())
        return

    variant = args.variant or resolve_variant()
    path = ensure_plate_model(variant=variant, force=args.force)
    print(f"Ready: {path} ({path.stat().st_size // (1024 * 1024)} MB)")


if __name__ == "__main__":
    main()
