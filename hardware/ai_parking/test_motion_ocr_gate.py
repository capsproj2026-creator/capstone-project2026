"""Motion state + OCR attempt-budget tests."""

from __future__ import annotations

import time
import unittest
from unittest.mock import MagicMock, patch

from parking_rules import (
    MOVEMENT_CONFIRMATION_FRAMES,
    OCR_MAX_ATTEMPTS,
    STATIONARY_CONFIRMATION_FRAMES,
    TrackMemory,
    ocr_allowed_for_motion,
)
from plate_ocr import AsyncPlateQueue


def _box(cx: float, cy: float, w: float = 200.0, h: float = 120.0):
    return (cx - w / 2, cy - h / 2, cx + w / 2, cy + h / 2)


class MotionStateTests(unittest.TestCase):
    def test_same_position_becomes_stationary(self):
        mem = TrackMemory(first_seen=time.time())
        t0 = time.time()
        box = _box(400, 300)
        self.assertEqual(mem.update_motion(box, t0), "idle")
        for i in range(STATIONARY_CONFIRMATION_FRAMES + 2):
            state = mem.update_motion(box, t0 + 0.2 * (i + 1))
        self.assertIn(state, ("parked", "idle"))
        self.assertNotEqual(state, "moving")
        # OCR is attempt-budgeted, not movement-gated by default.
        self.assertTrue(mem.allows_ocr())

    def test_large_center_drift_becomes_moving(self):
        mem = TrackMemory(first_seen=time.time())
        t0 = time.time()
        mem.update_motion(_box(100, 100), t0)
        state = "idle"
        for i in range(MOVEMENT_CONFIRMATION_FRAMES + 2):
            state = mem.update_motion(_box(100 + 80 * (i + 1), 100 + 60 * (i + 1)), t0 + 0.15 * (i + 1))
        self.assertEqual(state, "moving")
        self.assertTrue(mem.allows_ocr())

    def test_small_jitter_does_not_trigger_moving(self):
        mem = TrackMemory(first_seen=time.time())
        t0 = time.time()
        cx, cy = 500.0, 400.0
        mem.update_motion(_box(cx, cy), t0)
        for i in range(10):
            jitter = 0.4 if i % 2 == 0 else -0.4
            state = mem.update_motion(_box(cx + jitter, cy + jitter), t0 + 0.2 * (i + 1))
        self.assertNotEqual(state, "moving")
        self.assertTrue(mem.allows_ocr())


class AttemptBudgetOcrTests(unittest.TestCase):
    def _queue(self):
        ocr = MagicMock()
        ocr.enabled = True
        return AsyncPlateQueue(ocr, maxsize=4)

    def test_parked_vehicle_allows_ocr_submit(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        intel = MagicMock()
        intel.tracks = {7: mem}
        q = self._queue()
        frame = MagicMock()
        crop = MagicMock()
        with patch("plate_ocr.PlateOCR.crop_plate_region", return_value=crop):
            with patch.object(q, "_q") as mock_q:
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
                mock_q.put_nowait.assert_called_once()

    def test_moving_vehicle_allows_ocr_submit(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "moving"
        mem.plate_status = "pending"
        intel = MagicMock()
        intel.tracks = {7: mem}
        q = self._queue()
        frame = MagicMock()
        crop = MagicMock()
        with patch("plate_ocr.PlateOCR.crop_plate_region", return_value=crop):
            with patch.object(q, "_q") as mock_q:
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
                mock_q.put_nowait.assert_called_once()

    def test_parked_still_queues_ocr(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        mem.last_ocr_at = 0.0
        intel = MagicMock()
        intel.tracks = {7: mem}
        q = self._queue()
        frame = MagicMock()
        crop = MagicMock()
        with patch("plate_ocr.PlateOCR.crop_plate_region", return_value=crop):
            with patch.object(q, "_q") as mock_q:
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
                mock_q.put_nowait.assert_called_once()

    def test_queued_ocr_runs_while_parked(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        mem.current_tracker_id = 15
        intel = MagicMock()
        intel.tracks = {15: mem}
        q = self._queue()
        ocr = q.ocr
        ocr.read_crop = MagicMock(return_value=MagicMock(plate="EBD814", status="ok", confidence=0.9))

        key = ("CAM-2", 15)
        with q._lock:
            q._inflight.add(key)
        q._q.put(
            (key, MagicMock(), intel, 15, 2, (10, 10, 200, 120), "CAM-2")
        )
        time.sleep(0.35)
        ocr.read_crop.assert_called()
        self.assertTrue(mem.is_plate_locked() or mem.ocr_attempts >= 1)

    def test_mark_attempt_counts_while_parked(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.ocr_attempts = 2
        mem.mark_ocr_attempt()
        self.assertEqual(mem.ocr_attempts, 3)

    def test_six_failures_mark_not_read_and_stop(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        mem.ocr_started_at = time.time()
        for _ in range(OCR_MAX_ATTEMPTS):
            mem.mark_ocr_attempt()
        self.assertEqual(mem.ocr_attempts, OCR_MAX_ATTEMPTS)
        mem.tick_plate_deadline()
        self.assertEqual(mem.plate_status, "not_read")
        self.assertFalse(mem.allows_ocr())

    def test_successful_lock_stops_ocr(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        mem.lock_plate("ABC1234", 0.95, "test")
        self.assertTrue(mem.is_plate_locked())
        self.assertFalse(mem.allows_ocr())


if __name__ == "__main__":
    unittest.main()
