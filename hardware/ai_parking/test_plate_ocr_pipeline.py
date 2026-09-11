"""Unit tests for plate OCR timeout, track reattach, and plate-only gates."""

from __future__ import annotations

import time
import unittest

from parking_rules import (
    OCR_MAX_ATTEMPTS,
    OCR_MAX_REOPENS,
    OCR_PENDING_TIMEOUT_SEC,
    OCR_RETRY_COOLDOWN_SEC,
    ParkingIntelligence,
    TrackMemory,
)
from plate_ocr import PlateOCR
from plate_text import is_known_ph_format


class TrackReattachTests(unittest.TestCase):
    def test_find_track_near_xyxy_recovers_pending(self):
        intel = ParkingIntelligence()
        mem = intel.touch_track(17)
        mem.last_ocr_xyxy = (100, 200, 400, 500)
        mem.last_xyxy = (100, 200, 400, 500)
        mem.plate_status = "pending"

        # Tracker ID changed; OCR still has old bbox.
        found = intel.find_track_near_xyxy((110, 210, 390, 490), pending_only=True)
        self.assertIs(found, mem)

    def test_find_track_near_xyxy_skips_locked(self):
        intel = ParkingIntelligence()
        mem = intel.touch_track(3)
        mem.last_ocr_xyxy = (10, 10, 100, 100)
        mem.plate_status = "ok"
        mem.plate = "ABC1234"
        found = intel.find_track_near_xyxy((12, 12, 98, 98), pending_only=True)
        self.assertIsNone(found)


class PlateDeadlineTests(unittest.TestCase):
    def test_timeout_marks_not_read(self):
        mem = TrackMemory(first_seen=time.time())
        mem.ocr_started_at = time.time() - (OCR_PENDING_TIMEOUT_SEC + 1.0)
        mem.ocr_attempts = 1
        changed = mem.tick_plate_deadline()
        self.assertTrue(changed)
        self.assertEqual(mem.plate_status, "not_read")

    def test_max_attempts_marks_not_read(self):
        mem = TrackMemory(first_seen=time.time())
        mem.ocr_started_at = time.time()
        mem.ocr_attempts = max(OCR_MAX_ATTEMPTS, 1)
        changed = mem.tick_plate_deadline()
        self.assertTrue(changed)
        self.assertEqual(mem.plate_status, "not_read")

    def test_not_read_reopens_for_retry_after_cooldown(self):
        """A vehicle stuck on PLATE NOT READ must get another OCR pass instead of
        freezing forever, as long as it's still in frame and under the reopen cap."""
        mem = TrackMemory(first_seen=time.time())
        mem.ocr_started_at = time.time() - (OCR_PENDING_TIMEOUT_SEC + 1.0)
        mem.ocr_attempts = 1
        self.assertTrue(mem.tick_plate_deadline())
        self.assertEqual(mem.plate_status, "not_read")

        # Too soon — cooldown not elapsed yet.
        self.assertFalse(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "not_read")

        # Cooldown elapsed -> reopen for another attempt.
        mem.not_read_at = time.time() - (OCR_RETRY_COOLDOWN_SEC + 1.0)
        self.assertTrue(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "pending")
        self.assertEqual(mem.ocr_attempts, 0)
        self.assertEqual(mem.reopen_count, 1)

    def test_not_read_reopen_cap_is_bounded(self):
        """A genuinely unreadable plate must not retry forever."""
        mem = TrackMemory(first_seen=time.time())
        mem.plate_status = "not_read"
        mem.reopen_count = OCR_MAX_REOPENS
        mem.not_read_at = time.time() - (OCR_RETRY_COOLDOWN_SEC + 1.0)
        self.assertFalse(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "not_read")

    def test_guard_manual_entry_wins_after_retries_exhausted_else_stays_not_read(self):
        """End-to-end: once OCR retries are exhausted, the vehicle must stay
        'Plate Not Read' unless/until a guard manually enters a plate — at which
        point the guard's plate wins immediately and permanently."""
        mem = TrackMemory(first_seen=time.time())
        mem.plate_status = "not_read"
        mem.reopen_count = OCR_MAX_REOPENS  # retry budget fully used up
        mem.not_read_at = time.time() - (OCR_RETRY_COOLDOWN_SEC + 1.0)

        # No guard input yet -> must stay exactly as "Plate Not Read".
        self.assertFalse(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "not_read")
        self.assertIsNone(mem.plate)

        # Guard types in the plate manually (mirrors AiParkingService.correct_plate).
        mem.lock_plate("ABC1234", 1.0, "manual_guard")
        self.assertEqual(mem.plate_status, "ok")
        self.assertEqual(mem.plate, "ABC1234")

        # Even if the retry check runs again later, it must never undo the guard's plate.
        self.assertFalse(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "ok")
        self.assertEqual(mem.plate, "ABC1234")

    def test_maybe_retry_never_reopens_a_locked_plate(self):
        mem = TrackMemory(first_seen=time.time())
        mem.lock_plate("EBD814", 0.9, "high_conf")
        self.assertFalse(mem.maybe_retry_not_read())
        self.assertEqual(mem.plate_status, "ok")
        self.assertEqual(mem.plate, "EBD814")

    def test_known_ph_locks_with_realistic_easyocr_confidence(self):
        """Regression: leader_conf must use hit counts, not vote weights (was blocking all locks)."""
        mem = TrackMemory(first_seen=time.time())
        mem.apply_ocr_vote("EBD814", "ok", 0.55)
        self.assertEqual(mem.plate_status, "ok", "0.55 known-PH should lock via single_solid")
        self.assertEqual(mem.plate, "EBD814")

    def test_apply_ocr_vote_ignores_after_ok(self):
        mem = TrackMemory(first_seen=time.time())
        mem.lock_plate("EBD814", 0.95, "test")
        mem.apply_ocr_vote("ZZZ9999", "ok", 0.99)
        mem.apply_ocr_vote(None, "unreadable", 0.1)
        mem.tick_plate_deadline()
        self.assertEqual(mem.plate, "EBD814")
        self.assertEqual(mem.plate_status, "ok")

    def test_locked_plate_survives_failures_and_outliers(self):
        mem = TrackMemory(first_seen=time.time())
        mem.apply_ocr_vote("EBD814", "ok", 0.91)
        mem.apply_ocr_vote("EBD814", "ok", 0.94)
        mem.apply_ocr_vote("EBD814", "ok", 0.93)
        self.assertEqual(mem.plate_status, "ok")
        self.assertEqual(mem.plate, "EBD814")
        # Later noise must not unlock or replace.
        mem.apply_ocr_vote(None, "unreadable", 0.2)
        mem.apply_ocr_vote("EBD8147", "ok", 0.52)
        mem.ocr_attempts = 99
        mem.tick_plate_deadline()
        self.assertEqual(mem.plate, "EBD814")
        self.assertEqual(mem.plate_status, "ok")

    def test_outlier_does_not_beat_consensus(self):
        mem = TrackMemory(first_seen=time.time())
        mem.apply_ocr_vote("EBD814", "ok", 0.91)
        mem.apply_ocr_vote("EBD8147", "ok", 0.52)
        mem.apply_ocr_vote("EBD814", "ok", 0.94)
        mem.apply_ocr_vote("EBD81", "ok", 0.61)
        mem.apply_ocr_vote("EBD814", "ok", 0.93)
        self.assertEqual(mem.plate_status, "ok")
        self.assertEqual(mem.plate, "EBD814")

    def test_is_plate_terminal(self):
        mem = TrackMemory(first_seen=time.time())
        self.assertFalse(mem.is_plate_terminal())
        mem.plate_status = "not_read"
        self.assertTrue(mem.is_plate_terminal())

    def test_reattach_absorbs_locked_plate(self):
        intel = ParkingIntelligence(camera_id="CAM-2")
        old = intel.touch_track(15, xyxy=(100, 100, 300, 300), vehicle_type="truck")
        old.lock_plate("EBD814", 0.92, "test")
        old.owner_name = "Joshua Fuertes Sabater"
        old.owner_label = "Joshua Fuertes Sabater"
        old.registered = True
        old.lookup_done_at = time.time()
        old.lookup_plate = "EBD814"
        sid = old.recognition_session_id
        # Tracker ID changes to 16 for same box — same recognition session object.
        new = intel.touch_track(16, xyxy=(110, 110, 290, 290), vehicle_type="truck")
        self.assertIs(new, old)
        self.assertEqual(new.recognition_session_id, sid)
        self.assertEqual(new.plate_status, "ok")
        self.assertEqual(new.plate, "EBD814")
        self.assertEqual(new.owner_name, "Joshua Fuertes Sabater")
        # Submit must no-op on locked session.
        from plate_ocr import AsyncPlateQueue

        class FakeOCR:
            enabled = True

        q = AsyncPlateQueue(FakeOCR())  # type: ignore[arg-type]
        import numpy as np

        frame = np.zeros((200, 300, 3), dtype=np.uint8)
        before = new.ocr_attempts
        q.submit("CAM-2", 16, frame, (110, 110, 290, 290), intel, every_sec=0.0)
        self.assertEqual(new.ocr_attempts, before)

    def test_track_id_churn_keeps_locked_session(self):
        """Acceptance: #9 → #15 → #2 must keep plate LOCKED without restarting OCR."""
        intel = ParkingIntelligence(camera_id="CAM-2")
        now = time.time()
        a = intel.touch_track(9, now, xyxy=(270, 100, 1025, 790), vehicle_type="truck")
        a.lock_plate("EBD814", 0.95, "test")
        a.owner_label = "Joshua Fuertes Sabater"
        a.lookup_done_at = now
        a.lookup_plate = "EBD814"
        a.registered = True
        sid = a.recognition_session_id

        # Simulate miss: prune aliases but keep session in grace.
        intel.prune_stale_sessions(seen_tracks=set(), now=now + 0.5)
        self.assertIn(sid, intel.sessions)

        b = intel.touch_track(15, now + 0.6, xyxy=(273, 102, 1024, 791), vehicle_type="truck")
        self.assertIs(b, a)
        self.assertEqual(b.plate, "EBD814")
        self.assertEqual(b.plate_status, "ok")
        self.assertEqual(b.current_tracker_id, 15)

        intel.prune_stale_sessions(seen_tracks=set(), now=now + 1.0)
        c = intel.touch_track(2, now + 1.1, xyxy=(268, 98, 1020, 788), vehicle_type="truck")
        self.assertIs(c, a)
        self.assertEqual(c.recognition_session_id, sid)
        self.assertEqual(c.plate, "EBD814")
        self.assertEqual(c.owner_label, "Joshua Fuertes Sabater")
        # OCR deadline / not_read must never clear a lock after churn.
        c.ocr_attempts = 99
        c.tick_plate_deadline(now + 1.2)
        self.assertEqual(c.plate_status, "ok")
        self.assertEqual(c.plate, "EBD814")

    def test_new_vehicle_after_session_expire(self):
        from parking_rules import TRACK_LOST_GRACE_SEC

        intel = ParkingIntelligence(camera_id="CAM-2")
        now = time.time()
        old = intel.touch_track(9, now, xyxy=(270, 100, 1025, 790), vehicle_type="truck")
        old.lock_plate("EBD814", 0.95, "test")
        sid = old.recognition_session_id
        # Expire session.
        intel.prune_stale_sessions(seen_tracks=set(), now=now + TRACK_LOST_GRACE_SEC + 1.0)
        self.assertNotIn(sid, intel.sessions)
        # Different physical vehicle (far box) gets a new session — not old plate.
        neu = intel.touch_track(
            3, now + TRACK_LOST_GRACE_SEC + 1.5, xyxy=(50, 50, 180, 160), vehicle_type="car"
        )
        self.assertNotEqual(neu.recognition_session_id, sid)
        self.assertNotEqual(neu.plate, "EBD814")
        self.assertEqual(neu.plate_status, "pending")

    def test_reattach_survives_ocr_scale_mismatch(self):
        """last_ocr_xyxy may be 2x infer coords; reattach must still use last_xyxy."""
        intel = ParkingIntelligence(camera_id="CAM-2")
        old = intel.touch_track(9, xyxy=(100, 100, 300, 300), vehicle_type="truck")
        old.lock_plate("EBD814", 0.93, "test")
        old.last_ocr_xyxy = (200, 200, 600, 600)  # OCR-frame scale
        new = intel.touch_track(15, xyxy=(105, 105, 295, 295), vehicle_type="truck")
        self.assertIs(new, old)
        self.assertEqual(new.plate, "EBD814")


class PlateTextTests(unittest.TestCase):
    def test_known_ph_format_examples(self):
        self.assertTrue(is_known_ph_format("ABC1234") or is_known_ph_format("ABC123"))
        # Brand junk must not look like a plate.
        self.assertFalse(is_known_ph_format("ISUZU"))
        self.assertFalse(is_known_ph_format("PHILIPPINES"))


class PlateOnlySubCropTests(unittest.TestCase):
    def test_plate_only_returns_targeted_rois_without_detector(self):
        # Synthetic bumper-sized crop with no plate detector hit → targeted bands (not empty, not full bumper).
        import numpy as np

        crop = np.zeros((80, 160, 3), dtype=np.uint8)
        subs = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=True)
        self.assertGreaterEqual(len(subs), 1)
        for sub in subs:
            self.assertLess(sub.shape[0] * sub.shape[1], crop.shape[0] * crop.shape[1])

    def test_plate_only_path_uses_targeted_not_full_bumper(self):
        """Offline: plate-only miss still yields bounded ROIs (not empty full skip forever)."""
        import numpy as np

        crop = np.zeros((120, 240, 3), dtype=np.uint8)
        only = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=True)
        bumper = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=False)
        self.assertGreaterEqual(len(only), 1)
        self.assertGreaterEqual(len(bumper), 1)
        # Targeted ROIs are smaller than the parent bumper crop.
        self.assertTrue(all(s.shape[0] <= crop.shape[0] and s.shape[1] <= crop.shape[1] for s in only))
        print(f"[timing] plate-only subs={len(only)} bumper_path_subs={len(bumper)}")


class AsyncSubmitGateTests(unittest.TestCase):
    def test_submit_skips_terminal_statuses(self):
        from plate_ocr import AsyncPlateQueue, PlateOCR as PO

        class FakeOCR:
            enabled = True

        q = AsyncPlateQueue(FakeOCR())  # type: ignore[arg-type]
        intel = ParkingIntelligence()
        mem = intel.touch_track(42)
        mem.plate_status = "not_read"
        import numpy as np

        frame = np.zeros((200, 300, 3), dtype=np.uint8)
        before = mem.ocr_attempts
        q.submit("CAM-2", 42, frame, (10, 10, 100, 100), intel, every_sec=0.0)
        self.assertEqual(mem.ocr_attempts, before)
        self.assertEqual(mem.plate_status, "not_read")


if __name__ == "__main__":
    unittest.main()
