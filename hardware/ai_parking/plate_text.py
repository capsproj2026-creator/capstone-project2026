"""Philippine plate parsing and OCR ambiguity correction."""

from __future__ import annotations

import re
from typing import Iterable, Optional

# Private car: ABC1234, AB1234, NAA1234 (newer series)
_PH_CAR_RE = re.compile(r"^[A-Z]{2,3}\d{3,4}$")
# LTO motorcycle: 05010401328 (4 + 7 digits)
_PH_MC_RE = re.compile(r"^\d{11}$")
# Government / diplomatic / broader alphanumeric fallback
_PLATE_RE = re.compile(r"^[A-Z0-9]{5,12}$")

_LETTERS = set("ABCDEFGHIJKLMNOPQRSTUVWXYZ")
_DIGITS = set("0123456789")

# Common EasyOCR confusions (letter ↔ digit).
# Do NOT map D→0 (kills real plates like EBD814).
_TO_DIGIT = str.maketrans(
    {
        "O": "0",
        "Q": "0",
        "I": "1",
        "L": "1",
        "Z": "2",
        "S": "5",
        "B": "8",
        "G": "6",
    }
)
_TO_LETTER = str.maketrans(
    {
        "0": "O",
        "1": "I",
        "2": "Z",
        "5": "S",
        "6": "G",
        "8": "B",
    }
)


def is_ph_car_plate(text: str) -> bool:
    return bool(_PH_CAR_RE.fullmatch(text))


def is_ph_motorcycle_plate(text: str) -> bool:
    return bool(_PH_MC_RE.fullmatch(text))


def is_known_ph_format(text: str) -> bool:
    return is_ph_car_plate(text) or is_ph_motorcycle_plate(text)


def _clean_raw(text: str) -> str:
    raw = str(text).upper().strip()
    return re.sub(r"\s+", "", raw)


def parse_plate_candidate(text: str) -> tuple[Optional[str], bool]:
    """Return (normalized_plate, is_known_ph_format)."""
    compact = _clean_raw(text)
    mc_hyphen = re.match(r"^(\d{4})-(\d{7})$", compact)
    if mc_hyphen:
        normalized = mc_hyphen.group(1) + mc_hyphen.group(2)
        return normalized, is_ph_motorcycle_plate(normalized)

    cleaned = re.sub(r"[^A-Z0-9]", "", compact)
    if len(cleaned) < 4 or len(cleaned) > 12:
        return None, False

    if is_ph_motorcycle_plate(cleaned):
        return cleaned, True
    if is_ph_car_plate(cleaned):
        return cleaned, True
    if _PLATE_RE.fullmatch(cleaned):
        return cleaned, False
    return None, False


def _position_correct(text: str) -> str:
    """Apply letter/digit slot corrections for PH car and MC plates."""
    if not text:
        return text

    if text.isdigit() and len(text) == 11:
        return text.translate(_TO_DIGIT)

    # Try car layout: leading letters then digits.
    letter_end = 0
    for ch in text:
        if ch in _LETTERS or ch in "OILZSB":
            letter_end += 1
        else:
            break

    if letter_end == 0:
        return text.translate(_TO_DIGIT)

    letters = text[:letter_end].translate(_TO_LETTER)
    digits = text[letter_end:].translate(_TO_DIGIT)
    return letters + digits


def correction_variants(text: str, max_variants: int = 12) -> list[str]:
    """Generate corrected spellings for fuzzy DB matching."""
    parsed, _known = parse_plate_candidate(text)
    if parsed is None:
        parsed = re.sub(r"[^A-Z0-9]", "", _clean_raw(text))
    if not parsed:
        return []

    out: list[str] = []
    seen: set[str] = set()

    def add(value: str) -> None:
        value = re.sub(r"[^A-Z0-9]", "", value.upper())
        if not value or value in seen:
            return
        seen.add(value)
        out.append(value)

    add(parsed)
    add(_position_correct(parsed))

    # Single-character flip at each position (OCR often off by one).
    alphabet = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ"
    confusable = {
        "0": "OQD",
        "O": "0QD",
        "1": "IL",
        "I": "1L",
        "8": "B",
        "B": "8",
        "5": "S",
        "S": "5",
        "2": "Z",
        "Z": "2",
        "6": "G",
        "G": "6",
    }
    for idx, ch in enumerate(parsed):
        for alt in confusable.get(ch, ""):
            candidate = parsed[:idx] + alt + parsed[idx + 1 :]
            add(_position_correct(candidate))
            if len(out) >= max_variants:
                return out[:max_variants]

    # Global translate fallbacks.
    add(parsed.translate(_TO_DIGIT))
    add(parsed.translate(_TO_LETTER))
    return out[:max_variants]


_BRAND_OR_HEADER = {
    "ISUZU",
    "TOYOTA",
    "HONDA",
    "MITSUBISHI",
    "NISSAN",
    "SUZUKI",
    "HYUNDAI",
    "FORD",
    "CHEVROLET",
    "KIA",
    "MAZDA",
    "PHILIPPINES",
    "PILIPINAS",
    "REPUBLIC",
}


def score_candidate(parsed: str, known_format: bool, conf: float) -> float:
    score = float(conf)
    if parsed in _BRAND_OR_HEADER or (parsed.isalpha() and len(parsed) >= 5):
        # Brand / header text often outscores real plates — crush it.
        return score * 0.25
    if is_ph_motorcycle_plate(parsed):
        score += 0.45
        if parsed[:4].isdigit() and int(parsed[:4]) > 0:
            score += 0.06
    elif is_ph_car_plate(parsed):
        score += 0.42
        letters = sum(1 for ch in parsed if ch.isalpha())
        digits = sum(1 for ch in parsed if ch.isdigit())
        if 2 <= letters <= 3 and 3 <= digits <= 4:
            score += 0.08
    elif known_format:
        score += 0.08
    else:
        # Generic alphanumeric (not PH layout) — keep weak so real plates win.
        score *= 0.45
        if parsed.isdigit() and len(parsed) < 11:
            score *= 0.85
    return score


def _substring_candidates(text: str) -> list[str]:
    """Try sliding windows for OCR that merges extra characters."""
    compact = re.sub(r"[^A-Z0-9]", "", _clean_raw(text))
    if len(compact) < 6:
        return []

    out: list[str] = []
    seen: set[str] = set()

    def add(value: str) -> None:
        if value and value not in seen:
            seen.add(value)
            out.append(value)

    add(compact)
    if len(compact) == 11 and compact.isdigit():
        add(compact)

    # Car plates embedded in longer reads (e.g. ABC1234X).
    for length in (7, 6):
        if len(compact) < length:
            continue
        for start in range(0, len(compact) - length + 1):
            add(compact[start : start + length])

    # Motorcycle 11-digit windows.
    if len(compact) >= 11:
        for start in range(0, len(compact) - 10):
            add(compact[start : start + 11])

    return out[:16]


def _bbox_center_x(bbox) -> float:
    try:
        xs = [float(p[0]) for p in bbox]
        return sum(xs) / max(len(xs), 1)
    except Exception:
        return 0.0


def _bbox_center_y(bbox) -> float:
    try:
        ys = [float(p[1]) for p in bbox]
        return sum(ys) / max(len(ys), 1)
    except Exception:
        return 0.0


def _joined_ocr_candidates(results: list[tuple]) -> list[tuple[str, float]]:
    """
    EasyOCR often splits one plate into pieces (e.g. 'NAR' + '6011').
    Build left-to-right joins so we can recover the full plate.
    """
    parts: list[tuple[float, float, str, float]] = []
    for bbox, text, conf in results:
        cleaned = re.sub(r"[^A-Z0-9]", "", _clean_raw(str(text)))
        if not cleaned:
            continue
        parts.append((_bbox_center_x(bbox), _bbox_center_y(bbox), cleaned, float(conf)))

    if not parts:
        return []

    parts.sort(key=lambda p: (round(p[1] / 12.0), p[0]))  # row then left→right

    out: list[tuple[str, float]] = []
    seen: set[str] = set()

    def add(text: str, conf: float) -> None:
        text = re.sub(r"[^A-Z0-9]", "", text.upper())
        if len(text) < 5 or text in seen:
            return
        seen.add(text)
        out.append((text, conf))

    # Full join across all fragments.
    add("".join(p[2] for p in parts), min(p[3] for p in parts))

    # Sliding joins of 2–4 neighboring fragments (covers NAR+6011).
    n = len(parts)
    for width in range(2, min(5, n + 1)):
        for start in range(0, n - width + 1):
            chunk = parts[start : start + width]
            add("".join(p[2] for p in chunk), min(p[3] for p in chunk))

    # Same-row joins only (fragments that share a similar Y).
    row: list[tuple[float, float, str, float]] = []
    row_y = None
    for part in parts:
        if row_y is None or abs(part[1] - row_y) <= 18:
            row.append(part)
            row_y = part[1] if row_y is None else (row_y * 0.6 + part[1] * 0.4)
        else:
            if len(row) >= 2:
                add("".join(p[2] for p in row), min(p[3] for p in row))
            row = [part]
            row_y = part[1]
    if len(row) >= 2:
        add("".join(p[2] for p in row), min(p[3] for p in row))

    return out


def best_from_results(
    results: Iterable[tuple],
    min_conf: float,
) -> tuple[Optional[str], float, float]:
    """Pick best plate from EasyOCR readtext output tuples."""
    result_list = list(results)
    best: Optional[str] = None
    best_score = 0.0
    best_any = 0.0

    candidates: list[tuple[str, float]] = []
    for _bbox, text, conf in result_list:
        conf_f = float(conf)
        best_any = max(best_any, conf_f)
        candidates.append((str(text), conf_f))
        for sub in _substring_candidates(text):
            candidates.append((sub, conf_f))

    for joined, conf_f in _joined_ocr_candidates(result_list):
        best_any = max(best_any, conf_f)
        candidates.append((joined, conf_f))
        for sub in _substring_candidates(joined):
            candidates.append((sub, conf_f))

    for candidate_text, conf_f in candidates:
        parsed, known = parse_plate_candidate(candidate_text)
        if parsed is None:
            for variant in correction_variants(candidate_text, max_variants=4):
                parsed_v, known_v = parse_plate_candidate(variant)
                if parsed_v is None:
                    continue
                score_v = score_candidate(parsed_v, known_v, conf_f * 0.95)
                if conf_f < min_conf and not known_v:
                    continue
                if score_v > best_score:
                    best_score = score_v
                    best = parsed_v
            continue

        score = score_candidate(parsed, known, conf_f)
        if conf_f < min_conf and not known:
            continue
        # Prefer known PH formats when scores are close (brand text often has higher raw conf).
        if best is not None and known and not is_known_ph_format(best) and score >= best_score * 0.85:
            best_score = score
            best = parsed
            continue
        if score > best_score:
            best_score = score
            best = parsed

    return best, best_score, best_any
