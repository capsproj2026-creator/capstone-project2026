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


def _preprocess_for_ocr(image):
    """Light contrast enhancement for uneven lighting without destroying glyphs."""
    if image is None or getattr(image, "size", 0) == 0:
        return image

    lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB)
    luminance, a_channel, b_channel = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    luminance = clahe.apply(luminance)
    enhanced = cv2.merge((luminance, a_channel, b_channel))
    return cv2.cvtColor(enhanced, cv2.COLOR_LAB2BGR)


def _high_contrast(image):
    if image is None or getattr(image, "size", 0) == 0:
        return image

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(clipLimit=3.4, tileGridSize=(8, 8))
    gray = clahe.apply(gray)
    blur = cv2.GaussianBlur(gray, (0, 0), 1.1)
    sharp = cv2.addWeighted(gray, 1.55, blur, -0.55, 0)
    return cv2.cvtColor(sharp, cv2.COLOR_GRAY2BGR)


def _ocr_band(engine, image, y0: float, y1: float, x0: float, x1: float, min_width: int = 780) -> list[dict]:
    if image is None or getattr(image, "size", 0) == 0:
        return []

    height, width = image.shape[:2]
    top = max(0, min(height, int(height * y0)))
    bottom = max(top + 1, min(height, int(height * y1)))
    left = max(0, min(width, int(width * x0)))
    right = max(left + 1, min(width, int(width * x1)))
    crop = image[top:bottom, left:right]
    if crop.size == 0:
        return []

    crop_h, crop_w = crop.shape[:2]
    scale = max(1.0, min_width / max(crop_w, 1))
    if scale > 1.0:
        crop = cv2.resize(crop, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        crop_h = float(crop.shape[0])
    else:
        crop_h = float(crop_h)

    rows = _rapidocr_lines(engine, crop, crop_h)
    for row in rows:
        local_y = float(row.get("center_y") or 0.0)
        row["center_y"] = round((top + local_y * crop_h / max(scale, 1.0)) / max(height, 1.0), 4)

    return rows


def scan_image(image_path: Path, full_card: bool = False):
    image = cv2.imread(str(image_path))
    if image is None:
        return {"ok": False, "message": "Unable to read the uploaded image."}

    height, width = image.shape[:2]
    scale = max(1.0, 1400 / max(width, 1))
    if scale > 1.0:
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_CUBIC)
        height, width = image.shape[:2]

    enhanced = _preprocess_for_ocr(image)
    engine = RapidOCR()

    if full_card:
        lines = _rapidocr_lines(engine, enhanced, height)

        # Extra pass over the PH DL address band (below name, left/center, smaller type).
        addr_lines = _ocr_band(engine, enhanced, 0.26, 0.70, 0.0, 0.78, min_width=900)

        # License No / LICENSENO sits under the address, often glued to Expiration Date.
        # Crop the left-center of that band and upscale so the number is read alone.
        license_src = _high_contrast(enhanced)
        license_lines = _merge_lines(
            _ocr_band(engine, license_src, 0.56, 0.80, 0.16, 0.58, min_width=820),
            _ocr_band(engine, license_src, 0.56, 0.80, 0.28, 0.72, min_width=820),
        )

        lines = _merge_lines(lines, addr_lines, license_lines)
        if not lines:
            return {"ok": False, "message": "No text detected on the document photo."}

        return {
            "ok": True,
            "lines": lines,
            "name_lines": [],
            "sn_lines": [],
        }

    # SN region (left of photo, mid-upper card).
    sn_crop = enhanced[int(height * 0.12) : int(height * 0.36), int(width * 0.03) : int(width * 0.58)]
    sn_lines = _rapidocr_lines(engine, sn_crop, height) if sn_crop.size > 0 else []

    # Name region ONLY — below photo/DOB, above address (circled area on CSPC IDs).
    # Do not OCR school header, address, or footer into the name path.
    name_top = int(height * 0.54)
    name_bottom = int(height * 0.72)
    name_left = int(width * 0.05)
    name_right = int(width * 0.95)
    name_crop = enhanced[name_top:name_bottom, name_left:name_right]
    name_lines = []
    if name_crop.size > 0:
        crop_h = float(max(1, name_crop.shape[0]))
        name_lines = _rapidocr_lines(engine, name_crop, crop_h)
        for row in name_lines:
            local_y = float(row.get("center_y") or 0.0)
            # Map crop-relative center into full-card y for PHP NAME_BAND filtering.
            row["center_y"] = round((name_top + local_y * crop_h) / max(height, 1.0), 4)

    lines = _merge_lines(name_lines, sn_lines)

    if not lines:
        return {"ok": False, "message": "No text detected on the ID photo."}

    return {
        "ok": True,
        "lines": lines,
        "name_lines": name_lines,
        "sn_lines": sn_lines,
    }


def main(argv: list[str]) -> int:
    if len(argv) < 2:
        print(json.dumps({"ok": False, "message": "Usage: scan_campus_id.py <image-path> [--full]"}))
        return 1

    try:
        result = scan_image(Path(argv[1]), full_card="--full" in argv[2:])
    except Exception as exc:  # pragma: no cover - runtime safety net
        print(json.dumps({"ok": False, "message": f"Campus ID scan failed: {exc}"}))
        return 1

    print(json.dumps(result))
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
