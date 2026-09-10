"""Parking intelligence: slot occupancy, rule events, tracking dwell, debounce."""

from __future__ import annotations

import os
import time
from collections import defaultdict
from dataclasses import dataclass, field
from typing import Any

from geometry import assign_zones_for_box, has_calibrated_slots, usable_zones_for_frame

OVERTIME_MINUTES = float(os.getenv("AI_PARKING_OVERTIME_MINUTES", "30"))
DEBOUNCE_MINUTES = float(os.getenv("AI_PARKING_VIOLATION_DEBOUNCE_MINUTES", "10"))
IOU_THRESHOLD = float(os.getenv("AI_PARKING_ZONE_IOU", "0.08"))
# Keep lost tracker aliases briefly (IoU tracker max age).
TRACK_HOLD_SEC = float(os.getenv("AI_PARKING_TRACK_HOLD_SEC", "20.0"))
# Keep recognition sessions alive after tracker miss so ID churn can reattach.
TRACK_LOST_GRACE_SEC = float(
    os.getenv("AI_PARKING_TRACK_LOST_GRACE_SEC", str(TRACK_HOLD_SEC))
)
# Require this many matching OCR reads before locking a plate on a track.
PLATE_VOTE_NEEDED = int(os.getenv("AI_PARKING_PLATE_VOTE_NEEDED", "3"))
# Exceptional single-frame lock (known PH format only).
OCR_HIGH_CONF_LOCK = float(os.getenv("AI_PARKING_OCR_HIGH_CONF_LOCK", "0.90"))
# Alias / explicit lock threshold (falls back to HIGH_CONF).
PLATE_LOCK_CONFIDENCE = float(
    os.getenv("AI_PARKING_PLATE_LOCK_CONFIDENCE", str(OCR_HIGH_CONF_LOCK))
)
PLATE_VOTE_CONSENSUS_RATIO = float(os.getenv("AI_PARKING_PLATE_VOTE_CONSENSUS_RATIO", "0.55"))
TRACK_MATCH_IOU = float(os.getenv("AI_PARKING_TRACK_MATCH_IOU", "0.25"))
# Center-distance fallback when IoU is weak (fraction of bbox diagonal).
TRACK_MATCH_CENTER_FRAC = float(os.getenv("AI_PARKING_TRACK_MATCH_CENTER_FRAC", "0.22"))
TRACK_MATCH_SIZE_RATIO = float(os.getenv("AI_PARKING_TRACK_MATCH_SIZE_RATIO", "0.45"))
# Cap OCR retries / pending "Reading plate…" time so tracks reach a terminal state.
OCR_MAX_ATTEMPTS = int(os.getenv("AI_PARKING_OCR_MAX_ATTEMPTS", "6"))
OCR_PENDING_TIMEOUT_SEC = float(os.getenv("AI_PARKING_OCR_PENDING_TIMEOUT_SEC", "12"))
# Prefer plate-YOLO/OpenCV crop only; skip EasyOCR on huge bumper bands when no plate ROI.
OCR_PLATE_ONLY = os.getenv("AI_PARKING_OCR_PLATE_ONLY", "1").strip().lower() in (
    "1",
    "true",
    "yes",
    "on",
)
# Normalized center movement (px/sec ÷ bbox diagonal). Below = parked, above = moving.
MOTION_SPEED_THRESH = float(os.getenv("AI_PARKING_MOTION_SPEED_THRESH", "0.12"))
MOTION_PARK_SEC = float(os.getenv("AI_PARKING_MOTION_PARK_SEC", "0.8"))
MOTION_SMOOTH_ALPHA = float(os.getenv("AI_PARKING_MOTION_SMOOTH_ALPHA", "0.35"))


def _iou_xyxy(a, b) -> float:
    ax1, ay1, ax2, ay2 = a
    bx1, by1, bx2, by2 = b
    ix1, iy1 = max(ax1, bx1), max(ay1, by1)
    ix2, iy2 = min(ax2, bx2), min(ay2, by2)
    iw, ih = max(0, ix2 - ix1), max(0, iy2 - iy1)
    inter = iw * ih
    if inter <= 0:
        return 0.0
    area_a = max(0, ax2 - ax1) * max(0, ay2 - ay1)
    area_b = max(0, bx2 - bx1) * max(0, by2 - by1)
    union = area_a + area_b - inter
    return inter / union if union > 0 else 0.0


def _center_xyxy(a) -> tuple[float, float]:
    return ((a[0] + a[2]) / 2.0, (a[1] + a[3]) / 2.0)


def _diag_xyxy(a) -> float:
    return max(1.0, ((a[2] - a[0]) ** 2 + (a[3] - a[1]) ** 2) ** 0.5)


def _area_xyxy(a) -> float:
    return max(0.0, float(a[2] - a[0]) * float(a[3] - a[1]))


def _size_ratio(a, b) -> float:
    aa, bb = _area_xyxy(a), _area_xyxy(b)
    if aa <= 0 or bb <= 0:
        return 0.0
    return min(aa, bb) / max(aa, bb)


def _mem_ref_boxes(mem: "TrackMemory") -> list[tuple[int, int, int, int]]:
    """Prefer infer-frame last_xyxy; also try OCR-frame box (scale may differ)."""
    out: list[tuple[int, int, int, int]] = []
    for prev in (getattr(mem, "last_xyxy", None), getattr(mem, "last_ocr_xyxy", None)):
        if prev is None:
            continue
        try:
            box = tuple(int(v) for v in prev[:4])
        except (TypeError, ValueError):
            continue
        if len(box) == 4 and box not in out:
            out.append(box)  # type: ignore[arg-type]
    return out


def _spatial_match_score(
    box: tuple[int, int, int, int],
    mem: "TrackMemory",
) -> tuple[float, float, float, bool]:
    """
    Returns (score, best_iou, best_center_dist_px, matched).
    score is higher for stronger matches; matched means reattach-worthy.
    """
    refs = _mem_ref_boxes(mem)
    if not refs:
        return (-1.0, 0.0, 1e9, False)
    best_iou = 0.0
    best_dist = 1e9
    best_size = 0.0
    cx, cy = _center_xyxy(box)
    for ref in refs:
        best_iou = max(best_iou, _iou_xyxy(box, ref))
        rx, ry = _center_xyxy(ref)
        dist = ((cx - rx) ** 2 + (cy - ry) ** 2) ** 0.5
        if dist < best_dist:
            best_dist = dist
            best_size = _size_ratio(box, ref)
    diag = _diag_xyxy(box)
    center_ok = best_dist <= max(12.0, TRACK_MATCH_CENTER_FRAC * diag)
    size_ok = best_size >= TRACK_MATCH_SIZE_RATIO
    iou_ok = best_iou >= TRACK_MATCH_IOU
    matched = iou_ok or (center_ok and size_ok)
    # Prefer high IoU, then close center, then locked plates handled by caller.
    score = best_iou * 10.0 - (best_dist / max(diag, 1.0)) + best_size
    return (score, best_iou, best_dist, matched)


class SimpleIoUTracker:
    """
    Per-camera IoU tracker. Avoids Ultralytics ByteTrack persist=True state
    being shared across cameras on one YOLO model instance.
    """

    def __init__(self, iou_thresh: float = TRACK_MATCH_IOU, max_age_sec: float = TRACK_HOLD_SEC):
        self.iou_thresh = iou_thresh
        self.max_age_sec = max_age_sec
        self._next_id = 1
        self._boxes: dict[int, tuple] = {}
        self._last_seen: dict[int, float] = {}

    def update(self, detections: list[dict[str, Any]], now: float | None = None) -> list[dict[str, Any]]:
        now = now if now is not None else time.time()
        # Drop stale
        stale = [tid for tid, ts in self._last_seen.items() if now - ts > self.max_age_sec]
        for tid in stale:
            self._boxes.pop(tid, None)
            self._last_seen.pop(tid, None)

        used: set[int] = set()
        for det in detections:
            xyxy = det["xyxy"]
            best_id = None
            best_iou = self.iou_thresh
            for tid, prev in self._boxes.items():
                if tid in used:
                    continue
                iou = _iou_xyxy(xyxy, prev)
                if iou >= best_iou:
                    best_iou = iou
                    best_id = tid
            if best_id is None:
                best_id = self._next_id
                self._next_id += 1
            used.add(best_id)
            self._boxes[best_id] = xyxy
            self._last_seen[best_id] = now
            det["track_id"] = best_id
        return detections


@dataclass
class TrackMemory:
    first_seen: float
    last_seen: float = 0.0
    recognition_session_id: int = 0
    current_tracker_id: int | None = None
    previous_tracker_ids: list[int] = field(default_factory=list)
    camera_id: str = ""
    vehicle_type: str | None = None
    slot_id: str | None = None
    slot_since: float | None = None
    plate: str | None = None
    # pending | ok | unreadable | not_read  (ok == plate_locked)
    plate_status: str = "pending"
    ocr_confidence: float = 0.0
    plate_votes: dict[str, int] = field(default_factory=dict)
    plate_vote_scores: dict[str, float] = field(default_factory=dict)
    plate_vote_counts: dict[str, int] = field(default_factory=dict)
    unreadable_votes: int = 0
    ocr_attempts: int = 0
    ocr_started_at: float = 0.0
    plate_locked_at: float = 0.0
    plate_lock_reason: str | None = None
    last_ocr_at: float = 0.0
    last_ocr_xyxy: tuple[int, int, int, int] | None = None
    last_plate_crop: Any = None
    last_vehicle_crop: Any = None
    thumb_jpeg_base64: str | None = None
    thumb_jpeg_at: float = 0.0
    cls_id: int | None = None
    sync_ocr_attempted: bool = False
    last_sync_ocr_at: float = 0.0
    hit_streak: int = 0
    last_zones: list[str] = field(default_factory=list)
    # Owner cache from Laravel plate-lookup (once per locked plate).
    owner_name: str | None = None
    owner_label: str | None = None
    user_id: int | str | None = None
    vehicle_details: str | None = None
    department: str | None = None
    owner_role: str | None = None
    registration_status: str | None = None
    registered: bool | None = None
    lookup_done_at: float = 0.0
    lookup_pending: bool = False
    lookup_plate: str | None = None
    violation_flag: bool = False
    last_xyxy: tuple[int, int, int, int] | None = None
    prev_center: tuple[float, float] | None = None
    smooth_center: tuple[float, float] | None = None
    last_motion_at: float = 0.0
    motion_speed: float = 0.0
    # idle | moving | parked
    motion_state: str = "idle"
    parked_since: float | None = None

    def note_seen(self, now: float) -> None:
        self.last_seen = now
        self.hit_streak += 1

    def session_label(self) -> str:
        sid = int(self.recognition_session_id or 0)
        return f"VehicleSession-{sid:04d}" if sid else "VehicleSession-????"

    def update_motion(self, xyxy: tuple[int, int, int, int], now: float) -> str:
        """Classify vehicle as moving vs parked from bbox center drift."""
        x1, y1, x2, y2 = xyxy
        cx = (x1 + x2) / 2.0
        cy = (y1 + y2) / 2.0
        bw = max(1.0, float(x2 - x1))
        bh = max(1.0, float(y2 - y1))
        diag = (bw * bw + bh * bh) ** 0.5

        alpha = max(0.05, min(0.95, MOTION_SMOOTH_ALPHA))
        if self.smooth_center is None:
            self.smooth_center = (cx, cy)
        else:
            scx, scy = self.smooth_center
            self.smooth_center = (scx * (1.0 - alpha) + cx * alpha, scy * (1.0 - alpha) + cy * alpha)
        sx, sy = self.smooth_center

        if self.prev_center is not None and self.last_motion_at > 0:
            dt = max(0.05, now - self.last_motion_at)
            px, py = self.prev_center
            dist = ((sx - px) ** 2 + (sy - py) ** 2) ** 0.5
            self.motion_speed = dist / dt / max(diag, 1.0)
            if self.motion_speed >= MOTION_SPEED_THRESH:
                self.motion_state = "moving"
                self.parked_since = None
            elif self.parked_since is None:
                self.parked_since = now
            elif (now - self.parked_since) >= MOTION_PARK_SEC:
                self.motion_state = "parked"
        else:
            self.motion_state = "idle"
            self.parked_since = now

        self.prev_center = (sx, sy)
        self.last_motion_at = now
        self.last_xyxy = xyxy
        return self.motion_state

    def clear_owner(self) -> None:
        self.owner_name = None
        self.owner_label = None
        self.user_id = None
        self.vehicle_details = None
        self.department = None
        self.owner_role = None
        self.registration_status = None
        self.registered = None
        self.lookup_done_at = 0.0
        self.lookup_pending = False
        self.lookup_plate = None

    def needs_owner_lookup(self) -> bool:
        if self.plate_status != "ok" or not self.plate:
            return False
        if self.lookup_pending:
            return False
        if self.lookup_plate == self.plate and self.lookup_done_at > 0:
            return False
        return True

    def apply_owner_lookup(self, data: dict | None) -> None:
        self.lookup_pending = False
        self.lookup_done_at = time.time()
        self.lookup_plate = self.plate
        if not data:
            self.owner_label = "Unknown"
            self.registered = False
            self.registration_status = "Plate Not Registered"
            return
        self.registered = bool(data.get("registered"))
        self.owner_name = data.get("owner_name")
        self.owner_label = data.get("owner_label") or (
            self.owner_name if self.registered else "Unknown"
        )
        self.user_id = data.get("user_id")
        self.vehicle_details = data.get("vehicle_details")
        self.department = data.get("department")
        self.owner_role = data.get("owner_role") or data.get("role")
        self.registration_status = data.get("registration_status")
        if not self.registered:
            self.owner_name = None
            self.owner_label = "Unknown"
            if not self.registration_status:
                self.registration_status = "Plate Not Registered"

    def overlay_owner_line(self) -> str | None:
        if self.plate_status == "unreadable":
            return "Plate Unreadable"
        if self.plate_status == "not_read":
            return "Plate Not Read"
        if self.plate_status != "ok" or not self.plate:
            return None
        if self.lookup_done_at > 0 and self.lookup_plate == self.plate:
            return self.owner_label or ("Unknown" if not self.registered else None)
        return None

    def is_plate_locked(self) -> bool:
        return self.plate_status == "ok" and bool(self.plate)

    def is_plate_terminal(self) -> bool:
        return self.plate_status in ("ok", "unreadable", "not_read")

    def mark_ocr_attempt(self, now: float | None = None) -> None:
        if self.is_plate_terminal():
            return
        now = now if now is not None else time.time()
        if self.ocr_started_at <= 0:
            self.ocr_started_at = now
        self.ocr_attempts += 1

    def tick_plate_deadline(self, now: float | None = None) -> bool:
        """
        Force pending → not_read when attempts/timeout are exhausted.
        Never clears a locked plate.
        """
        if self.is_plate_locked() or self.plate_status in ("unreadable", "not_read"):
            return False
        if self.plate_status != "pending":
            return False
        now = now if now is not None else time.time()
        timed_out = (
            self.ocr_started_at > 0
            and OCR_PENDING_TIMEOUT_SEC > 0
            and (now - self.ocr_started_at) >= OCR_PENDING_TIMEOUT_SEC
        )
        attempts_exhausted = OCR_MAX_ATTEMPTS > 0 and self.ocr_attempts >= OCR_MAX_ATTEMPTS
        if not timed_out and not attempts_exhausted:
            return False
        self.plate = None
        self.plate_status = "not_read"
        self.plate_locked_at = 0.0
        self.plate_lock_reason = "timeout_or_max_attempts"
        self.clear_owner()
        print(
            f"[OCR] PLATE NOT READ attempts={self.ocr_attempts}/{OCR_MAX_ATTEMPTS} "
            f"timeout={OCR_PENDING_TIMEOUT_SEC}s"
        )
        return True

    def lock_plate(self, plate: str, confidence: float, reason: str) -> None:
        """Hard-lock plate; subsequent OCR must be ignored."""
        manual = str(reason or "").startswith("manual")
        if self.is_plate_locked() and self.plate == plate and not manual:
            self.ocr_confidence = max(self.ocr_confidence, float(confidence or 0.0))
            return
        if self.plate != plate:
            self.clear_owner()
        self.plate = plate
        self.plate_status = "ok"
        self.ocr_confidence = max(self.ocr_confidence, float(confidence or 0.0))
        self.plate_locked_at = time.time()
        self.plate_lock_reason = reason
        self.unreadable_votes = 0
        # Manual guard overrides must stick even if OCR later votes differently.
        if manual:
            self.plate_votes = {plate: max(99, int(self.plate_votes.get(plate, 0) or 0))}
            self.plate_vote_scores = {plate: max(99.0, float(self.plate_vote_scores.get(plate, 0.0) or 0.0))}
        print(
            f"[OCR] PLATE LOCKED: {plate} conf={confidence:.2f} reason={reason} "
            f"attempts={self.ocr_attempts}"
        )

    def absorb_plate_state(self, donor: "TrackMemory") -> bool:
        """Copy locked / in-progress OCR state from a nearby track (ID churn)."""
        if donor is self:
            return False
        if self.is_plate_locked():
            return False
        if not (donor.is_plate_locked() or donor.plate_votes or donor.ocr_attempts > 0):
            return False

        self.plate = donor.plate
        self.plate_status = donor.plate_status
        self.ocr_confidence = donor.ocr_confidence
        self.plate_votes = dict(donor.plate_votes)
        self.plate_vote_scores = dict(donor.plate_vote_scores)
        self.plate_vote_counts = dict(getattr(donor, "plate_vote_counts", {}) or {})
        self.unreadable_votes = donor.unreadable_votes
        self.ocr_attempts = max(self.ocr_attempts, donor.ocr_attempts)
        self.ocr_started_at = donor.ocr_started_at or self.ocr_started_at
        self.plate_locked_at = donor.plate_locked_at
        self.plate_lock_reason = donor.plate_lock_reason
        if donor.last_plate_crop is not None:
            self.last_plate_crop = donor.last_plate_crop
        if donor.last_vehicle_crop is not None:
            self.last_vehicle_crop = donor.last_vehicle_crop
        # Preserve owner so UI does not flicker to Unknown.
        if donor.lookup_done_at > 0 and donor.lookup_plate == donor.plate:
            self.owner_name = donor.owner_name
            self.owner_label = donor.owner_label
            self.user_id = donor.user_id
            self.vehicle_details = donor.vehicle_details
            self.department = donor.department
            self.owner_role = donor.owner_role
            self.registration_status = donor.registration_status
            self.registered = donor.registered
            self.lookup_done_at = donor.lookup_done_at
            self.lookup_plate = donor.lookup_plate
            self.lookup_pending = False
        print(
            f"[OCR] REATTACH absorbed status={self.plate_status} plate={self.plate!r} "
            f"from prior track state"
        )
        return True

    def apply_ocr_vote(self, plate: str | None, status: str, confidence: float) -> None:
        """Stabilize plate text across frames; never overwrite a locked plate."""
        from plate_text import (
            is_known_ph_format,
            looks_like_plate_text,
            prefer_stable_car_plate,
            reconcile_partial_plates,
        )

        # HARD LOCK — ignore everything after lock (including failures / outliers).
        if self.is_plate_locked():
            print(f"[OCR] OCR skipped: plate already locked ({self.plate})")
            return
        if self.plate_status in ("unreadable", "not_read"):
            return

        conf = float(confidence or 0.0)
        self.ocr_confidence = max(self.ocr_confidence, conf)

        if status == "ok" and plate:
            known = is_known_ph_format(plate)
            loose = looks_like_plate_text(plate)
            if not known and not loose:
                self.unreadable_votes += 1
                self.tick_plate_deadline()
                return

            # Demote overlong outliers (EBD8147) when a stable shorter plate is supported.
            plate = prefer_stable_car_plate(plate, list(self.plate_votes.keys()) + [plate]) or plate
            known = is_known_ph_format(plate)
            loose = looks_like_plate_text(plate)

            weight = max(1, int(round(conf * 4)))
            if known:
                weight += 2
            if conf >= PLATE_LOCK_CONFIDENCE:
                weight += 2
            self.plate_votes[plate] = self.plate_votes.get(plate, 0) + weight
            self.plate_vote_scores[plate] = self.plate_vote_scores.get(plate, 0.0) + conf
            self.plate_vote_counts[plate] = self.plate_vote_counts.get(plate, 0) + 1

            # Cross-frame bull-bar merge (EBD81 + EBD84 → EBD814).
            merged = reconcile_partial_plates(list(self.plate_votes.keys()) + [plate])
            if merged:
                merged = prefer_stable_car_plate(merged, list(self.plate_votes.keys()) + [merged, plate]) or merged
            if merged and is_known_ph_format(merged):
                if merged not in self.plate_votes:
                    self.plate_votes[merged] = self.plate_votes.get(merged, 0) + max(3, weight)
                    self.plate_vote_scores[merged] = self.plate_vote_scores.get(merged, 0.0) + conf
                    self.plate_vote_counts[merged] = self.plate_vote_counts.get(merged, 0) + 1
                plate = merged
                known = True

            # Pick leader by weighted votes, then score; never prefer overlong extension.
            leader = prefer_stable_car_plate(
                max(self.plate_votes.items(), key=lambda kv: (kv[1], self.plate_vote_scores.get(kv[0], 0.0)))[0],
                list(self.plate_votes.keys()),
            ) or plate
            vote_weight = self.plate_votes.get(leader, 0)
            total_weight = sum(self.plate_votes.values())
            consensus = vote_weight / max(total_weight, 1)
            # Mean OCR confidence (NOT weight — dividing by weight blocked all locks).
            hit_count = max(1, int(self.plate_vote_counts.get(leader, 0) or 0))
            leader_conf = self.plate_vote_scores.get(leader, 0.0) / float(hit_count)
            leader_known = is_known_ph_format(leader)

            print(
                f"[OCR] attempt {self.ocr_attempts}/{OCR_MAX_ATTEMPTS} "
                f"raw={plate!r} conf={conf:.2f} leader={leader!r} "
                f"hits={hit_count} weight={vote_weight}/{total_weight} "
                f"({consensus:.0%}) mean_conf={leader_conf:.2f}"
            )

            # A) Exceptional single strong known-PH read
            high_conf_lock = (
                leader_known
                and conf >= PLATE_LOCK_CONFIDENCE
                and plate == leader
                and known
            )
            # B) Multi-frame consensus on known PH (use hit counts, not weights)
            consensus_lock = (
                hit_count >= PLATE_VOTE_NEEDED
                and consensus >= PLATE_VOTE_CONSENSUS_RATIO
                and leader_known
                and leader_conf >= max(0.35, PLATE_LOCK_CONFIDENCE - 0.40)
            )
            # C) Two matching known-PH reads with solid mean confidence
            dual_strong = (
                leader_known
                and hit_count >= 2
                and leader_conf >= max(0.40, PLATE_LOCK_CONFIDENCE - 0.45)
                and consensus >= 0.5
            )
            # D) One clear known-PH read (typical EasyOCR 0.50–0.80 on good crops)
            single_solid = (
                leader_known
                and known
                and plate == leader
                and hit_count >= 1
                and conf >= max(0.50, min(0.70, PLATE_LOCK_CONFIDENCE - 0.35))
            )

            if high_conf_lock:
                self.lock_plate(leader, conf, f"high_conf>={PLATE_LOCK_CONFIDENCE:.2f}")
                return
            if consensus_lock:
                self.lock_plate(
                    leader,
                    leader_conf,
                    f"{hit_count} matching hits ({consensus:.0%} consensus)",
                )
                return
            if dual_strong:
                self.lock_plate(leader, leader_conf, "strong matching results")
                return
            if single_solid:
                self.lock_plate(leader, conf, f"known_ph_solid conf>={conf:.2f}")
                return

            self.tick_plate_deadline()
            return

        if status == "unreadable":
            # Failures never unlock; only advance toward terminal while still pending.
            self.unreadable_votes += 1
            if self.unreadable_votes >= PLATE_VOTE_NEEDED + 2 and not self.plate_votes:
                self.plate = None
                self.plate_status = "unreadable"
                self.plate_lock_reason = "unreadable"
                self.clear_owner()
            else:
                self.tick_plate_deadline()
            return

        self.tick_plate_deadline()


class ParkingIntelligence:
    def __init__(self, camera_id: str = ""):
        # tracker_id -> recognition session (multiple IDs may alias the same object)
        self.tracks: dict[int, TrackMemory] = {}
        # Stable recognition sessions (physical vehicle identity)
        self.sessions: dict[int, TrackMemory] = {}
        self._next_session_id = 1
        self.camera_id = str(camera_id or "")
        self._debounce: dict[tuple, float] = {}
        self.active_events: list[dict[str, Any]] = []

    def _unique_sessions(self) -> list[TrackMemory]:
        seen: set[int] = set()
        out: list[TrackMemory] = []
        for mem in self.sessions.values():
            mid = id(mem)
            if mid in seen:
                continue
            seen.add(mid)
            out.append(mem)
        for mem in self.tracks.values():
            mid = id(mem)
            if mid in seen:
                continue
            seen.add(mid)
            out.append(mem)
        return out

    def _bind_tracker(self, track_id: int, mem: TrackMemory) -> None:
        tid = int(track_id)
        prev = mem.current_tracker_id
        if prev is not None and int(prev) != tid:
            if int(prev) not in mem.previous_tracker_ids:
                mem.previous_tracker_ids.append(int(prev))
            # Drop superseded alias so OCR/UI resolve through the live tracker id.
            old = self.tracks.get(int(prev))
            if old is mem:
                del self.tracks[int(prev)]
        mem.current_tracker_id = tid
        self.tracks[tid] = mem
        if mem.recognition_session_id:
            self.sessions[int(mem.recognition_session_id)] = mem

    def find_session_near_xyxy(
        self,
        xyxy: tuple[int, int, int, int] | list[int] | None,
        *,
        exclude_tracker_id: int | None = None,
        cls_id: int | None = None,
        vehicle_type: str | None = None,
        within_grace: bool = True,
        now: float | None = None,
        prefer_locked: bool = True,
    ) -> tuple[TrackMemory | None, dict[str, Any]]:
        """Spatially match a bbox to an existing recognition session."""
        meta: dict[str, Any] = {"iou": 0.0, "center_distance": 0.0, "matched": False}
        if xyxy is None:
            return None, meta
        try:
            box = tuple(int(v) for v in xyxy[:4])
        except (TypeError, ValueError):
            return None, meta
        if len(box) != 4:
            return None, meta
        now = now if now is not None else time.time()
        best_mem: TrackMemory | None = None
        best_key = (-1.0, -1.0, -1.0)  # locked_bonus, score, iou
        best_iou = 0.0
        best_dist = 0.0

        for mem in self._unique_sessions():
            if within_grace and TRACK_LOST_GRACE_SEC > 0:
                age = now - float(mem.last_seen or mem.first_seen or now)
                if age > TRACK_LOST_GRACE_SEC:
                    continue
            if exclude_tracker_id is not None and mem.current_tracker_id is not None:
                if int(mem.current_tracker_id) == int(exclude_tracker_id):
                    # Still allow matching this session when the exclude id is a stale alias
                    # that already points here — handled by caller creating a new id.
                    pass
            if exclude_tracker_id is not None and self.tracks.get(int(exclude_tracker_id)) is mem:
                continue
            if cls_id is not None and mem.cls_id is not None and int(mem.cls_id) != int(cls_id):
                # Soft preference only — do not hard-reject (class flicker truck/car).
                pass
            if vehicle_type and mem.vehicle_type and vehicle_type != mem.vehicle_type:
                pass

            score, iou, dist, matched = _spatial_match_score(box, mem)
            if not matched:
                continue
            locked_bonus = 1.0 if (prefer_locked and mem.is_plate_locked()) else 0.0
            # Prefer sessions with any OCR progress over empty ones.
            progress = 1.0 if (mem.is_plate_locked() or mem.plate_votes or mem.ocr_attempts > 0) else 0.0
            key = (locked_bonus, progress, score, iou)
            if key > best_key:
                best_key = key
                best_mem = mem
                best_iou = iou
                best_dist = dist

        if best_mem is None:
            return None, meta
        meta = {
            "iou": round(best_iou, 3),
            "center_distance": round(best_dist, 1),
            "matched": True,
            "session_id": best_mem.recognition_session_id,
        }
        return best_mem, meta

    def touch_track(
        self,
        track_id: int,
        now: float | None = None,
        xyxy: tuple[int, int, int, int] | None = None,
        *,
        vehicle_type: str | None = None,
        cls_id: int | None = None,
        camera_id: str | None = None,
    ) -> TrackMemory:
        now = now if now is not None else time.time()
        tid = int(track_id)
        cam = str(camera_id if camera_id is not None else self.camera_id or "")

        mem = self.tracks.get(tid)
        if mem is not None:
            mem.note_seen(now)
            mem.current_tracker_id = tid
            if cam:
                mem.camera_id = cam
            if vehicle_type:
                mem.vehicle_type = vehicle_type
            if cls_id is not None:
                mem.cls_id = int(cls_id)
            if xyxy is not None:
                mem.last_xyxy = tuple(int(v) for v in xyxy[:4])
            if not mem.is_plate_locked():
                mem.tick_plate_deadline(now)
            return mem

        # New tracker ID - try to reattach to an existing recognition session.
        if xyxy is not None:
            donor, meta = self.find_session_near_xyxy(
                xyxy,
                exclude_tracker_id=tid,
                cls_id=cls_id,
                vehicle_type=vehicle_type,
                within_grace=True,
                now=now,
                prefer_locked=True,
            )
            if donor is not None:
                old_tid = donor.current_tracker_id
                self._bind_tracker(tid, donor)
                donor.note_seen(now)
                if cam:
                    donor.camera_id = cam
                if vehicle_type:
                    donor.vehicle_type = vehicle_type
                if cls_id is not None:
                    donor.cls_id = int(cls_id)
                donor.last_xyxy = tuple(int(v) for v in xyxy[:4])
                cam_tag = cam or donor.camera_id or "CAM"
                print(
                    f"[{cam_tag}] Track ID changed: #{old_tid} -> #{tid}\n"
                    f"[{cam_tag}] Session #{donor.recognition_session_id} REATTACHED\n"
                    f"[{cam_tag}] Reason: IoU={meta.get('iou')}, "
                    f"center_distance={meta.get('center_distance')}px"
                )
                if donor.is_plate_locked():
                    print(
                        f"[{cam_tag}] Track #{tid}\n"
                        f"[{cam_tag}] Session #{donor.recognition_session_id}\n"
                        f"[{cam_tag}] Plate={donor.plate}\n"
                        f"[{cam_tag}] OCR=SKIPPED\n"
                        f"[{cam_tag}] Reason=PLATE_ALREADY_LOCKED"
                    )
                elif not donor.is_plate_locked():
                    donor.tick_plate_deadline(now)
                return donor

        # Genuinely new physical vehicle / no spatial match.
        sid = self._next_session_id
        self._next_session_id += 1
        mem = TrackMemory(
            first_seen=now,
            last_seen=now,
            hit_streak=1,
            recognition_session_id=sid,
            current_tracker_id=tid,
            camera_id=cam,
            vehicle_type=vehicle_type,
            cls_id=int(cls_id) if cls_id is not None else None,
        )
        if xyxy is not None:
            mem.last_xyxy = tuple(int(v) for v in xyxy[:4])
        self.sessions[sid] = mem
        self.tracks[tid] = mem
        cam_tag = cam or "CAM"
        print(
            f"[{cam_tag}] Track #{tid}\n"
            f"[{cam_tag}] No matching session\n"
            f"[{cam_tag}] New recognition session created #{sid} ({mem.session_label()})"
        )
        return mem

    def find_track_near_xyxy(
        self,
        xyxy: tuple[int, int, int, int] | list[int] | None,
        pending_only: bool = True,
        min_iou: float | None = None,
        exclude_id: int | None = None,
        prefer_locked: bool = False,
    ) -> TrackMemory | None:
        """
        Reattach an async OCR result when the tracker ID changed mid-OCR.
        Prefer pending tracks with highest IoU against the submit-time bbox.
        When prefer_locked=True, favor tracks that already have a locked plate.
        """
        if xyxy is None:
            return None
        try:
            box = tuple(int(v) for v in xyxy[:4])
        except (TypeError, ValueError):
            return None
        if len(box) != 4:
            return None
        thresh = float(min_iou) if min_iou is not None else TRACK_MATCH_IOU
        best_mem: TrackMemory | None = None
        best_key = (-1.0, -1.0)  # (locked_bonus, iou)
        for mem in self._unique_sessions():
            if exclude_id is not None and mem.current_tracker_id is not None:
                if int(mem.current_tracker_id) == int(exclude_id):
                    continue
            if exclude_id is not None and self.tracks.get(int(exclude_id)) is mem:
                continue
            if pending_only and mem.plate_status != "pending":
                continue
            if mem.is_plate_terminal() and pending_only:
                continue
            score, iou, _dist, matched = _spatial_match_score(box, mem)
            if min_iou is not None:
                if iou < thresh:
                    continue
            elif not matched and iou < thresh:
                continue
            locked_bonus = 1.0 if (prefer_locked and mem.is_plate_locked()) else 0.0
            key = (locked_bonus, iou, score)
            if key > best_key:
                best_key = key
                best_mem = mem
        return best_mem

    def prune_stale_sessions(self, seen_tracks: set[int], now: float | None = None) -> None:
        """Drop tracker aliases not seen this frame; expire sessions after grace."""
        now = now if now is not None else time.time()
        grace = max(0.5, float(TRACK_LOST_GRACE_SEC))

        # Remove superseded / unseen tracker aliases (session may remain).
        for tid in list(self.tracks.keys()):
            if tid in seen_tracks:
                continue
            mem = self.tracks.get(tid)
            if mem is None:
                continue
            if mem.current_tracker_id is not None and int(mem.current_tracker_id) != int(tid):
                del self.tracks[tid]
                continue
            # Keep alias briefly so in-flight OCR keyed by old id still resolves.
            age = now - float(mem.last_seen or mem.first_seen or now)
            if age > min(2.0, grace):
                del self.tracks[tid]

        # Expire recognition sessions that have not been seen within grace.
        for sid, mem in list(self.sessions.items()):
            age = now - float(mem.last_seen or mem.first_seen or now)
            if age <= grace:
                continue
            for tid, m in list(self.tracks.items()):
                if m is mem:
                    del self.tracks[tid]
            del self.sessions[sid]
            cam_tag = mem.camera_id or "CAM"
            print(
                f"[{cam_tag}] Session #{sid} expired "
                f"(grace={grace:.1f}s, last_plate={mem.plate!r})"
            )

    def tick_all_plate_deadlines(self, now: float | None = None) -> None:
        now = now if now is not None else time.time()
        for mem in self._unique_sessions():
            mem.tick_plate_deadline(now)

    def _should_emit(self, key: tuple) -> bool:
        now = time.time()
        last = self._debounce.get(key, 0.0)
        if now - last < DEBOUNCE_MINUTES * 60:
            return False
        self._debounce[key] = now
        return True

    def _emit(
        self,
        event_type: str,
        zone_id: str,
        track_id: int | None,
        plate: str | None = None,
        extra: dict | None = None,
    ) -> dict | None:
        key = (track_id, event_type, zone_id)
        if not self._should_emit(key):
            return None
        evt = {
            "type": event_type,
            "zone_id": zone_id,
            "track_id": track_id,
            "plate": plate,
            "confidence": 0.8,
            "ts": time.time(),
        }
        if extra:
            evt.update(extra)
        return evt

    def analyze(
        self,
        vehicles: list[dict[str, Any]],
        zones_data: dict[str, Any],
        frame_shape: tuple[int, int],
    ) -> tuple[list[dict[str, Any]], list[dict[str, Any]], set[str], bool]:
        """
        vehicles: [{xyxy, track_id, class, confidence, plate?}]
        Returns: (slot_statuses, new_events, occupied_slot_ids, used_polygons)
        """
        zones = usable_zones_for_frame(zones_data, frame_shape)
        use_poly = has_calibrated_slots(zones_data)
        now = time.time()
        events: list[dict[str, Any]] = []
        occupied: set[str] = set()
        slot_vehicle_counts: dict[str, int] = defaultdict(int)
        seen_tracks: set[int] = set()

        slot_zones = [z for z in zones if z.get("type") == "slot"]
        rule_zones = [z for z in zones if z.get("type") in ("no_parking", "aisle")]

        for v in vehicles:
            xyxy = v["xyxy"]
            tid = v.get("track_id")
            plate = v.get("plate")
            plate_status = v.get("plate_status") or "pending"
            if tid is not None:
                seen_tracks.add(int(tid))
                mem = self.touch_track(
                    int(tid),
                    now,
                    xyxy=tuple(int(x) for x in xyxy),
                    vehicle_type=v.get("class") or v.get("vehicle_type"),
                    cls_id=v.get("cls_id"),
                )
                # Never let a stale vehicle payload downgrade a locked plate.
                if mem.is_plate_locked():
                    pass
                elif plate and plate_status == "ok":
                    mem.lock_plate(str(plate), float(v.get("ocr_confidence") or 0.5), "analyze_payload")
                elif plate_status == "unreadable" and mem.plate_status != "ok":
                    mem.plate_status = "unreadable"
                    mem.plate = None
                elif plate_status == "not_read" and mem.plate_status == "pending":
                    mem.plate_status = "not_read"
                    mem.plate = None
                mem.tick_plate_deadline(now)

            matched_slots = []
            matched_rules = []
            if use_poly:
                matched_slots = assign_zones_for_box(xyxy, slot_zones, frame_shape, IOU_THRESHOLD)
                matched_rules = assign_zones_for_box(xyxy, rule_zones, frame_shape, IOU_THRESHOLD)

            # --- slot occupancy ---
            primary_slot = None
            if matched_slots:
                matched_slots.sort(key=lambda z: z.get("_iou", 0), reverse=True)
                primary_slot = matched_slots[0]
                for ms in matched_slots:
                    sid = str(ms.get("id"))
                    occupied.add(sid)
                    slot_vehicle_counts[sid] += 1

            if tid is not None:
                mem = self.tracks[int(tid)]
                sid = str(primary_slot["id"]) if primary_slot else None
                if sid != mem.slot_id:
                    mem.slot_id = sid
                    mem.slot_since = now if sid else None
                elif sid and mem.slot_since is None:
                    mem.slot_since = now

                # overtime
                if (
                    sid
                    and mem.slot_since is not None
                    and (now - mem.slot_since) >= OVERTIME_MINUTES * 60
                ):
                    evt = self._emit(
                        "overtime",
                        sid,
                        int(tid),
                        mem.plate,
                        {"dwell_minutes": round((now - mem.slot_since) / 60, 1)},
                    )
                    if evt:
                        events.append(evt)

                # double park: one vehicle spanning 2+ slots
                if len(matched_slots) >= 2:
                    zone_key = "+".join(sorted(str(z["id"]) for z in matched_slots[:3]))
                    evt = self._emit(
                        "double_park",
                        zone_key,
                        int(tid),
                        mem.plate,
                        {"slots": [str(z["id"]) for z in matched_slots]},
                    )
                    if evt:
                        events.append(evt)

                # no parking / aisle
                for rz in matched_rules:
                    et = "no_parking" if rz.get("type") == "no_parking" else "aisle_blocked"
                    evt = self._emit(et, str(rz.get("id")), int(tid), mem.plate, {"label": rz.get("label")})
                    if evt:
                        events.append(evt)

        # double park: 2+ vehicles in same slot
        if use_poly:
            for sid, count in slot_vehicle_counts.items():
                if count >= 2:
                    evt = self._emit("double_park", sid, None, None, {"vehicles_in_slot": count})
                    if evt:
                        events.append(evt)

        # Soft-prune: keep sessions through grace; do not treat tracker ID as identity.
        self.prune_stale_sessions(seen_tracks, now)

        slot_statuses: list[dict[str, Any]] = []
        if use_poly:
            for z in slot_zones:
                sid = str(z.get("id"))
                slot_statuses.append({
                    "slot_number": sid,
                    "occupied": sid in occupied,
                })

        # keep recent events for overlay
        self.active_events = (events + self.active_events)[:30]

        return slot_statuses, events, occupied, use_poly
