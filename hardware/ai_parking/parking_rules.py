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


@dataclass
class TrackMemory:
    first_seen: float
    slot_id: str | None = None
    slot_since: float | None = None
    plate: str | None = None
    last_ocr_at: float = 0.0
    last_zones: list[str] = field(default_factory=list)


class ParkingIntelligence:
    def __init__(self):
        self.tracks: dict[int, TrackMemory] = {}
        self._debounce: dict[tuple, float] = {}
        self.active_events: list[dict[str, Any]] = []

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
            if tid is not None:
                seen_tracks.add(int(tid))
                mem = self.tracks.get(int(tid))
                if mem is None:
                    mem = TrackMemory(first_seen=now)
                    self.tracks[int(tid)] = mem
                if plate:
                    mem.plate = plate

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

        # prune stale tracks
        stale = [t for t in self.tracks if t not in seen_tracks]
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
