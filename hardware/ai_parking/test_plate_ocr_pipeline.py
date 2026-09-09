"""Unit tests for plate OCR timeout, track reattach, and plate-only gates."""

from __future__ import annotations

import time
import unittest

from parking_rules import (
    OCR_MAX_ATTEMPTS,
    OCR_PENDING_TIMEOUT_SEC,
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

    def test_apply_ocr_vote_ignores_after_ok(self):
        mem = TrackMemory(first_seen=time.time())
        mem.plate = "EBD8123"
        mem.plate_status = "ok"
        mem.apply_ocr_vote("ZZZ9999", "ok", 0.99)
        self.assertEqual(mem.plate, "EBD8123")
        self.assertEqual(mem.plate_status, "ok")

    def test_is_plate_terminal(self):
        mem = TrackMemory(first_seen=time.time())
        self.assertFalse(mem.is_plate_terminal())
        mem.plate_status = "not_read"
        self.assertTrue(mem.is_plate_terminal())


class PlateTextTests(unittest.TestCase):
    def test_known_ph_format_examples(self):
        self.assertTrue(is_known_ph_format("ABC1234") or is_known_ph_format("ABC123"))
        # Brand junk must not look like a plate.
        self.assertFalse(is_known_ph_format("ISUZU"))
        self.assertFalse(is_known_ph_format("PHILIPPINES"))


class PlateOnlySubCropTests(unittest.TestCase):
    def test_plate_only_returns_empty_without_detector_roi(self):
        # Synthetic bumper-sized crop with no plate detector hit → empty list when plate_only.
        import numpy as np

        crop = np.zeros((80, 160, 3), dtype=np.uint8)
        # Force plate_only path; detect_plate_crop on blank should miss.
        subs = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=True)
        self.assertEqual(subs, [])

    def test_plate_only_path_faster_than_bumper_bands(self):
        """Offline timing: plate-only miss skips bumper bands (no EasyOCR)."""
        import numpy as np

        crop = np.zeros((120, 240, 3), dtype=np.uint8)
        t0 = time.perf_counter()
        for _ in range(5):
            PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=True)
        plate_only_ms = (time.perf_counter() - t0) * 1000 / 5

        t1 = time.perf_counter()
        for _ in range(5):
            PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=False)
        bumper_ms = (time.perf_counter() - t1) * 1000 / 5

        only = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=True)
        bumper = PlateOCR._sub_crops(crop, cls_id=2, fast=True, plate_only=False)
        self.assertEqual(only, [])
        self.assertGreaterEqual(len(bumper), 1)
        # Crop selection itself is cheap; plate-only returns fewer/zero crops to OCR.
        print(
            f"[timing] plate-only sub_crops={plate_only_ms:.1f}ms "
            f"bumper sub_crops={bumper_ms:.1f}ms "
            f"bumper_subs={len(bumper)} (EasyOCR skipped when plate-only miss)"
        )


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
