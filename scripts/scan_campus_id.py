#!/usr/bin/env python3
"""OCR a CSPC campus ID photo and return detected text lines as JSON."""

from __future__ import annotations

import json
import os
import sys
from pathlib import Path

if sys.platform == "win32":
    system_root = os.environ.get("SYSTEMROOT") or os.environ.get("SystemRoot") or r"C:\Windows"
    os.environ.setdefault("SYSTEMROOT", system_root)
    os.environ.setdefault("SystemRoot", system_root)
    os.environ.setdefault("WINDIR", system_root)
    system32 = os.path.join(system_root, "System32")
    path = os.environ.get("PATH", "")
    if system32.lower() not in path.lower():
        os.environ["PATH"] = system32 + os.pathsep + path

try:
    import cv2
    from rapidocr_onnxruntime import RapidOCR
except ImportError as exc:  # pragma: no cover - runtime dependency check
    print(
        json.dumps(
            {
                "ok": False,
                "message": f"Missing OCR dependency: {exc}. Run scripts/setup-campus-id-ocr.ps1",
            }
        )
    )
    sys.exit(1)


def _box_metrics(box, image_height: float) -> tuple[float, float]:
    ys = [float(point[1]) for point in box]
    height = max(ys) - min(ys)
    center_y = ((min(ys) + max(ys)) / 2) / max(image_height, 1.0)

    return height, center_y


def _rapidocr_lines(engine: RapidOCR, image, image_height: float | None = None) -> list[dict]:
    if image is None or getattr(image, "size", 0) == 0:
        return []

    ih = float(image_height or image.shape[0])
    result, _elapsed = engine(image)
    rows = []
    for item in result or []:
        if len(item) < 3:
            continue
        text = " ".join(str(item[1]).split())
        if not text:
            continue
        height, center_y = _box_metrics(item[0], ih)
        rows.append(
            {
                "text": text,
                "confidence": round(float(item[2]), 4),
                "height": round(height, 2),
                "center_y": round(center_y, 4),
            }
        )

    rows.sort(key=lambda row: (row["center_y"], -row["height"]))
    return rows


def _merge_lines(*groups):
    merged = []
    seen = set()
    for group in groups:
        for item in group:
            key = item["text"].strip().lower()
            if not key or key in seen:
                continue
            seen.add(key)
            merged.append(item)

    merged.sort(key=lambda row: (row.get("center_y", 0.0), -row.get("height", 0.0)))
    return merged


def scan_image(image_path: Path):
    image = cv2.imread(str(image_path))
    if image is None:
        return {"ok": False, "message": "Unable to read the uploaded image."}

    height, width = image.shape[:2]
    scale = max(1.0, 1200 / max(width, 1))
    if scale > 1.0:
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        height, width = image.shape[:2]

    engine = RapidOCR()

    full_lines = _rapidocr_lines(engine, image, height)

    sn_crop = image[int(height * 0.12) : int(height * 0.34), int(width * 0.05) : int(width * 0.55)]
    sn_lines = _rapidocr_lines(engine, sn_crop, height) if sn_crop.size > 0 else []

    # Name band — include all large printed name lines, stop before address.
    name_crop = image[int(height * 0.56) : int(height * 0.715), int(width * 0.05) : int(width * 0.82)]
    name_lines = _rapidocr_lines(engine, name_crop, height) if name_crop.size > 0 else []

    lines = _merge_lines(name_lines, full_lines, sn_lines)

    if not lines:
        return {"ok": False, "message": "No text detected on the ID photo."}

    return {"ok": True, "lines": lines}


def main(argv: list[str]) -> int:
    if len(argv) != 2:
        print(json.dumps({"ok": False, "message": "Usage: scan_campus_id.py <image-path>"}))
        return 1

    try:
        result = scan_image(Path(argv[1]))
    except Exception as exc:  # pragma: no cover - runtime safety net
        print(json.dumps({"ok": False, "message": f"Campus ID scan failed: {exc}"}))
        return 1

    print(json.dumps(result))
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
