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
_TO_DIGIT = str.maketrans(
    {
        "O": "0",
        "Q": "0",
        "D": "0",
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


def score_candidate(parsed: str, known_format: bool, conf: float) -> float:
    score = float(conf)
    if is_ph_motorcycle_plate(parsed):
        score += 0.40
        # LTO motorcycle plates start with a 4-digit region code.
        if parsed[:4].isdigit() and int(parsed[:4]) > 0:
            score += 0.06
    elif is_ph_car_plate(parsed):
        score += 0.18
        letters = sum(1 for ch in parsed if ch.isalpha())
        digits = sum(1 for ch in parsed if ch.isdigit())
        if 2 <= letters <= 3 and 3 <= digits <= 4:
            score += 0.05
    elif known_format:
        score += 0.08
    elif parsed.isdigit() and len(parsed) < 11:
        score *= 0.82
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


def best_from_results(
    results: Iterable[tuple],
    min_conf: float,
) -> tuple[Optional[str], float, float]:
    """Pick best plate from EasyOCR readtext output tuples."""
    best: Optional[str] = None
    best_score = 0.0
    best_any = 0.0

    for _bbox, text, conf in results:
        conf_f = float(conf)
        best_any = max(best_any, conf_f)

        candidates = [text]
        candidates.extend(_substring_candidates(text))

        for candidate_text in candidates:
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
            if score > best_score:
                best_score = score
                best = parsed

    return best, best_score, best_any
