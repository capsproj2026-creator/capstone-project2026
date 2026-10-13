#!/usr/bin/env python3
"""Compare faces on registration photos and fingerprint the files."""

from __future__ import annotations

import hashlib
import json
import sys
from pathlib import Path

import cv2
import numpy as np


def sha256_file(path: str) -> str:
    digest = hashlib.sha256()
    with open(path, "rb") as handle:
        for chunk in iter(lambda: handle.read(65536), b""):
            digest.update(chunk)
    return digest.hexdigest()


def ahash(image: np.ndarray, size: int = 8) -> str:
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY) if image.ndim == 3 else image
    small = cv2.resize(gray, (size, size), interpolation=cv2.INTER_AREA)
    bits = (small > float(small.mean())).flatten()
    value = 0
    for bit in bits:
        value = (value << 1) | int(bool(bit))
    return f"{value:016x}"


def detect_face(image: np.ndarray):
    gray = cv2.equalizeHist(cv2.cvtColor(image, cv2.COLOR_BGR2GRAY))
    cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
    faces = cascade.detectMultiScale(gray, 1.1, 4, minSize=(48, 48))
    if len(faces):
        x, y, w, h = max(faces, key=lambda row: row[2] * row[3])
        return int(x), int(y), int(w), int(h)

    height, width = gray.shape
    regions = [
        (int(width * 0.45), 0, width, height),
        (0, 0, int(width * 0.45), height),
    ]
    for x0, y0, x1, y1 in regions:
        crop = gray[y0:y1, x0:x1]
        found = cascade.detectMultiScale(crop, 1.08, 3, minSize=(32, 32))
        if len(found) == 0:
            continue
        fx, fy, fw, fh = max(found, key=lambda row: row[2] * row[3])
        return int(x0 + fx), int(y0 + fy), int(fw), int(fh)
    return None


def face_vector(image: np.ndarray, box):
    x, y, w, h = box
    pad = int(0.15 * max(w, h))
    x1 = max(0, x - pad)
    y1 = max(0, y - pad)
    x2 = min(image.shape[1], x + w + pad)
    y2 = min(image.shape[0], y + h + pad)
    crop = cv2.cvtColor(image[y1:y2, x1:x2], cv2.COLOR_BGR2GRAY)
    crop = cv2.resize(crop, (96, 96))
    crop = cv2.equalizeHist(crop)
    hist = cv2.calcHist([crop], [0], None, [32], [0, 256])
    cv2.normalize(hist, hist)
    return crop, hist.flatten()


def compare(left, right) -> float:
    crop_a, hist_a = left
    crop_b, hist_b = right
    corr = float(cv2.compareHist(hist_a.reshape(-1, 1), hist_b.reshape(-1, 1), cv2.HISTCMP_CORREL))
    distance = cv2.norm(crop_a.astype("float32"), crop_b.astype("float32"), cv2.NORM_L2) / (96 * 96)
    return (corr + max(0.0, 1.0 - distance / 40.0)) / 2.0


def main() -> int:
    payload = json.loads(sys.stdin.read() or "{}")
    paths = payload.get("images") or {}
    out = {
        "ok": True,
        "faces": {},
        "matches": {},
        "same_person": False,
        "hashes": {},
        "message": None,
    }
    faces = {}
    for key, path in paths.items():
        image = cv2.imread(str(path))
        if image is None:
            out["ok"] = False
            out["message"] = f"Could not read the {key} photo."
            print(json.dumps(out))
            return 1
        out["hashes"][key] = {"sha256": sha256_file(path), "ahash": ahash(image)}
        box = detect_face(image)
        out["faces"][key] = 1 if box else 0
        if box:
            faces[key] = face_vector(image, box)

    needed = [key for key in ("profile", "id", "license") if key in paths]
    missing = [key for key in needed if key not in faces]
    if missing:
        labels = {"profile": "profile picture", "id": "school ID", "license": "driver's license"}
        pretty = ", ".join(labels.get(key, key) for key in missing)
        out["ok"] = False
        out["message"] = f"Could not find a face on the {pretty}. Use a clear front-facing photo."
        print(json.dumps(out))
        return 0

    scores = []
    for left, right in (("profile", "id"), ("profile", "license"), ("id", "license")):
        if left in faces and right in faces:
            score = compare(faces[left], faces[right])
            out["matches"][f"{left}_{right}"] = round(score, 3)
            scores.append(score)

    out["same_person"] = bool(scores) and min(scores) >= 0.42 and (sum(scores) / len(scores)) >= 0.50
    if not out["same_person"]:
        out["ok"] = False
        out["message"] = "The face on the profile picture, school ID, and driver's license must belong to the same person."
    print(json.dumps(out))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
