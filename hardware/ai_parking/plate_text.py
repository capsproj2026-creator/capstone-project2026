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


def looks_like_plate_text(text: str) -> bool:
    """Loose gate: alphanumeric plate-like string (OCR success ≠ PH validation)."""
    cleaned = re.sub(r"[^A-Z0-9]", "", _clean_raw(text or ""))
    if len(cleaned) < 5 or len(cleaned) > 12:
        return False
    # Brand junk checked after _BRAND_OR_HEADER is defined — inline common ones here.
    if cleaned in {
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
    }:
        return False
    if cleaned.isalpha() and len(cleaned) >= 5:
        return False
    has_letter = any(ch.isalpha() for ch in cleaned)
    has_digit = any(ch.isdigit() for ch in cleaned)
    if has_letter and has_digit:
        return True
    return is_ph_motorcycle_plate(cleaned)


def reconcile_partial_plates(candidates: Iterable[str]) -> Optional[str]:
    """
    Recover full PH car plates from truncated OCR pairs.

    Bull-bar cameras often yield EBD81 and EBD84 for the same plate EBD814.
    """
    expanded: list[str] = []
    for raw in candidates:
        text = str(raw or "")
        if not text:
            continue
        expanded.append(text)
        parsed, _known = parse_plate_candidate(text)
        if parsed:
            expanded.append(parsed)
            expanded.extend(correction_variants(parsed, max_variants=6))

    dig_groups: dict[str, set[str]] = {}
    complete: list[str] = []
    for raw in expanded:
        parsed, known = parse_plate_candidate(str(raw))
        if not parsed:
            continue
        if known and is_ph_car_plate(parsed):
            complete.append(parsed)
            continue
        m = re.fullmatch(r"([A-Z]{2,3})(\d{2,4})", parsed)
        if not m:
            # Try position-corrected form for digit/letter swaps (EBDB1 → EBD81).
            corrected = _position_correct(parsed)
            m = re.fullmatch(r"([A-Z]{2,3})(\d{2,4})", corrected)
            if not m:
                continue
            parsed = corrected
            if is_ph_car_plate(parsed):
                complete.append(parsed)
                continue
        letters, digits = m.group(1), m.group(2)
        dig_groups.setdefault(letters, set()).add(digits)

    if complete:
        # Prefer longest / most common complete plate.
        complete.sort(key=lambda p: (len(p), complete.count(p), p), reverse=True)
        return complete[0]

    for letters, digs in dig_groups.items():
        # Already have a 3–4 digit reading.
        for d in sorted(digs, key=len, reverse=True):
            cand = letters + d
            if is_ph_car_plate(cand):
                return cand
        two = [d for d in digs if len(d) == 2]
        if len(two) < 2:
            # Single 2-digit partial with high letter confidence — cannot invent last digit.
            continue
        # Same leading digit, different trailing → insert both trail digits (81+84→814).
        by_lead: dict[str, set[str]] = {}
        for d in two:
            by_lead.setdefault(d[0], set()).add(d[1])
        for lead, trails in by_lead.items():
            if len(trails) < 2:
                continue
            merged_digits = lead + "".join(sorted(trails))
            cand = letters + merged_digits
            if is_ph_car_plate(cand):
                return cand
            merged_digits = lead + "".join(trails)
            cand = letters + merged_digits
            if is_ph_car_plate(cand):
                return cand
    return None


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

    # Global translate only when the string is not already a clean PH plate
    # (global B→8 would corrupt real plates like EBD814).
    if not is_ph_car_plate(parsed) and not is_ph_motorcycle_plate(parsed):
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


def prefer_stable_car_plate(candidate: str | None, pool: Iterable[str]) -> Optional[str]:
    """
    Prefer a reliable shorter PH plate over an overlong extension.

    Example: pool has EBD814 and EBD8147 → keep EBD814 (extra trailing digit
    often comes from bull-bar OCR noise, not a real character).

    Do NOT invent a shorter plate from a lone 4-digit reading (NAR6011 must stay).
    """
    if not candidate:
        return None
    parsed, _ = parse_plate_candidate(candidate)
    if not parsed:
        return candidate

    raw_forms: set[str] = set()
    known_pool: list[str] = []
    for raw in pool:
        cleaned = re.sub(r"[^A-Z0-9]", "", _clean_raw(str(raw)))
        if cleaned:
            raw_forms.add(cleaned)
        p, known = parse_plate_candidate(str(raw))
        if p and known and is_ph_car_plate(p):
            known_pool.append(p)
    if parsed not in known_pool and is_ph_car_plate(parsed):
        known_pool.append(parsed)

    if not known_pool:
        return parsed

    # Demote 3+4 only when a true 3+3 reading also appeared as its own OCR string.
    m_long = re.fullmatch(r"([A-Z]{2,3})(\d{3,4})", parsed)
    if m_long and len(m_long.group(2)) == 4:
        letters, digits = m_long.group(1), m_long.group(2)
        shorter = letters + digits[:3]
        if (
            is_ph_car_plate(shorter)
            and shorter in raw_forms
            and shorter != parsed
        ):
            return shorter

    best = parsed
    best_key = (
        0 if is_ph_car_plate(parsed) else -1,
        -len(parsed),
        sum(1 for ch in parsed if ch.isalpha()),
    )
    for p in known_pool:
        if p not in raw_forms and p != parsed:
            continue
        key = (
            1 if is_ph_car_plate(p) else 0,
            -len(p),
            sum(1 for ch in p if ch.isalpha()),
        )
        if p == parsed or parsed.startswith(p) or p.startswith(parsed[: max(5, len(p) - 1)]):
            if key > best_key:
                best_key = key
                best = p
    return best


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
        # Prefer 3-letter series (EBD814) over truncated 2-letter false positives (EB248).
        if letters == 3:
            score += 0.12
        elif letters == 2:
            score -= 0.08
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

    # If the full string is already a known 3+4 plate, do not emit its 3+3
    # prefix as a competing candidate (would steal NAR6011 → NAR601).
    if is_ph_car_plate(compact) and re.fullmatch(r"[A-Z]{2,3}\d{4}", compact):
        return out[:16]

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
    Reject tiny low-confidence scraps (often bull-bar holes → fake trailing digits).
    """
    parts: list[tuple[float, float, str, float, float]] = []
    for bbox, text, conf in results:
        cleaned = re.sub(r"[^A-Z0-9]", "", _clean_raw(str(text)))
        if not cleaned:
            continue
        conf_f = float(conf)
        # Ignore single-character scraps unless very confident — they create EBD814+"7".
        if len(cleaned) == 1 and conf_f < 0.85:
            continue
        width = 0.0
        try:
            xs = [float(p[0]) for p in bbox]
            width = max(xs) - min(xs)
        except Exception:
            width = 0.0
        parts.append((_bbox_center_x(bbox), _bbox_center_y(bbox), cleaned, conf_f, width))

    if not parts:
        return []

    parts.sort(key=lambda p: (round(p[1] / 12.0), p[0]))  # row then left→right

    out: list[tuple[str, float]] = []
    seen: set[str] = set()

    def add(text: str, conf: float) -> None:
        text = re.sub(r"[^A-Z0-9]", "", text.upper())
        if len(text) < 5 or text in seen:
            return
        # Prefer stripping a trailing singleton digit when a known 3+3 plate is prefix.
        if len(text) >= 7:
            head = text[:6]
            if is_ph_car_plate(head) and len(text) == 7 and text[-1].isdigit():
                # Keep both; scoring will prefer the stable shorter form when present.
                pass
        seen.add(text)
        out.append((text, conf))

    # Do not full-join every scrap — only fragments that look plate-sized.
    core = [p for p in parts if len(p[2]) >= 2 or p[3] >= 0.85]
    if len(core) >= 2:
        add("".join(p[2] for p in core), min(p[3] for p in core))

    n = len(parts)
    for width in range(2, min(5, n + 1)):
        for start in range(0, n - width + 1):
            chunk = parts[start : start + width]
            # Skip joins that append a lone character with large horizontal gap.
            if any(len(p[2]) == 1 for p in chunk):
                xs = [p[0] for p in chunk]
                if max(xs) - min(xs) > 120:
                    continue
                if min(p[3] for p in chunk if len(p[2]) == 1) < 0.85:
                    continue
            add("".join(p[2] for p in chunk), min(p[3] for p in chunk))

    row: list[tuple[float, float, str, float, float]] = []
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

    # Bull-bar truncation: merge EBD81 + EBD84 → EBD814 within the same OCR pass.
    pool = [c for c, _ in candidates] + ([best] if best else [])
    merged = reconcile_partial_plates(pool)
    if merged and is_known_ph_format(merged):
        if best is None or not is_known_ph_format(best):
            best = merged
            best_score = max(best_score, 0.55)
        else:
            stable = prefer_stable_car_plate(best, [merged, best] + pool)
            if stable:
                best = stable
                best_score = max(best_score, 0.55)

    if best:
        best = prefer_stable_car_plate(best, pool + [best]) or best

    return best, best_score, best_any
