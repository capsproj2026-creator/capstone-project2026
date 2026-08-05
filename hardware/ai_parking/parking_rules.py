"""Parking intelligence: slot occupancy, rule events, tracking dwell, debounce."""

from __future__ import annotations

import os
import time
from collections import defaultdict
from dataclasses import dataclass, field
from typing import Any

from geometry import assign_zones_for_box, has_calibrated_slots, usable_zones

OVERTIME_MINUTES = float(os.getenv("AI_PARKING_OVERTIME_MINUTES", "30"))
DEBOUNCE_MINUTES = float(os.getenv("AI_PARKING_VIOLATION_DEBOUNCE_MINUTES", "10"))
IOU_THRESHOLD = float(os.getenv("AI_PARKING_ZONE_IOU", "0.12"))
# Keep lost tracks briefly so ByteTrack ID flicker does not wipe plate memory / re-OCR.
TRACK_HOLD_SEC = float(os.getenv("AI_PARKING_TRACK_HOLD_SEC", "3.0"))
# Require this many matching OCR reads before locking a plate on a track.
PLATE_VOTE_NEEDED = int(os.getenv("AI_PARKING_PLATE_VOTE_NEEDED", "1"))
TRACK_MATCH_IOU = float(os.getenv("AI_PARKING_TRACK_MATCH_IOU", "0.25"))


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
    slot_id: str | None = None
    slot_since: float | None = None
    plate: str | None = None
    # pending | ok | unreadable
    plate_status: str = "pending"
    ocr_confidence: float = 0.0
    plate_votes: dict[str, int] = field(default_factory=dict)
    unreadable_votes: int = 0
    last_ocr_at: float = 0.0
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

    def note_seen(self, now: float) -> None:
        self.last_seen = now
        self.hit_streak += 1

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
            self.owner_label = "Unknown Vehicle"
            self.registered = False
            self.registration_status = "Plate Not Registered"
            return
        self.registered = bool(data.get("registered"))
        self.owner_name = data.get("owner_name")
        self.owner_label = data.get("owner_label") or (
            self.owner_name if self.registered else "Unknown Vehicle"
        )
        self.user_id = data.get("user_id")
        self.vehicle_details = data.get("vehicle_details")
        self.department = data.get("department")
        self.owner_role = data.get("owner_role") or data.get("role")
        self.registration_status = data.get("registration_status")
        if not self.registered:
            self.owner_name = None
            self.owner_label = "Unknown Vehicle"
            if not self.registration_status:
                self.registration_status = "Plate Not Registered"

    def overlay_owner_line(self) -> str | None:
        if self.plate_status == "unreadable":
            return "Plate Unreadable"
        if self.plate_status != "ok" or not self.plate:
            return None
        if self.lookup_done_at > 0 and self.lookup_plate == self.plate:
            return self.owner_label or ("Unknown Vehicle" if not self.registered else None)
        return None

    def apply_ocr_vote(self, plate: str | None, status: str, confidence: float) -> None:
        """Stabilize plate text across frames; avoid locking on a single bad read."""
        self.ocr_confidence = max(self.ocr_confidence, float(confidence or 0.0))
        if status == "ok" and plate:
            self.plate_votes[plate] = self.plate_votes.get(plate, 0) + 1
            votes = self.plate_votes[plate]
            if votes >= PLATE_VOTE_NEEDED or (votes >= 1 and confidence >= 0.75):
                if self.plate != plate:
                    self.clear_owner()
                self.plate = plate
                self.plate_status = "ok"
                self.unreadable_votes = 0
            return

        if status == "unreadable":
            self.unreadable_votes += 1
            # Only mark unreadable once we have tried enough and never locked a plate.
            if self.plate_status != "ok" and self.unreadable_votes >= PLATE_VOTE_NEEDED:
                self.plate = None
                self.plate_status = "unreadable"
                self.clear_owner()


class ParkingIntelligence:
    def __init__(self):
        self.tracks: dict[int, TrackMemory] = {}
        self._debounce: dict[tuple, float] = {}
        self.active_events: list[dict[str, Any]] = []

    def touch_track(self, track_id: int, now: float | None = None) -> TrackMemory:
        now = now if now is not None else time.time()
        mem = self.tracks.get(track_id)
        if mem is None:
            mem = TrackMemory(first_seen=now, last_seen=now, hit_streak=1)
            self.tracks[track_id] = mem
        else:
            mem.note_seen(now)
        return mem

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
        zones = usable_zones(zones_data)
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
                mem = self.touch_track(int(tid), now)
                if plate and plate_status == "ok":
                    mem.plate = plate
                    mem.plate_status = "ok"
                elif plate_status == "unreadable" and mem.plate_status != "ok":
                    mem.plate_status = "unreadable"
                    mem.plate = None

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

        # Soft-prune stale tracks (hold briefly to survive tracker flicker / brief occlusion).
        stale = [
            t for t, mem in self.tracks.items()
            if t not in seen_tracks and (now - (mem.last_seen or mem.first_seen)) > TRACK_HOLD_SEC
        ]
        for t in stale:
            del self.tracks[t]

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
