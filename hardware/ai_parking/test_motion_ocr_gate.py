"""Movement confirmation + movement-gated OCR tests (mandatory)."""

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
        # First frame seeds centers (idle / UNKNOWN).
        self.assertEqual(mem.update_motion(box, t0), "idle")
        # Identical position across frames → stationary/parked (not MOVING).
        for i in range(STATIONARY_CONFIRMATION_FRAMES + 2):
            state = mem.update_motion(box, t0 + 0.2 * (i + 1))
        self.assertIn(state, ("parked", "idle"))
        self.assertNotEqual(state, "moving")
        self.assertFalse(mem.allows_ocr())
        self.assertFalse(ocr_allowed_for_motion(state))

    def test_large_center_drift_becomes_moving(self):
        mem = TrackMemory(first_seen=time.time())
        t0 = time.time()
        mem.update_motion(_box(100, 100), t0)
        state = "idle"
        for i in range(MOVEMENT_CONFIRMATION_FRAMES + 2):
            # Large jump each frame relative to bbox diagonal.
            state = mem.update_motion(_box(100 + 80 * (i + 1), 100 + 60 * (i + 1)), t0 + 0.15 * (i + 1))
        self.assertEqual(state, "moving")
        self.assertTrue(mem.allows_ocr())

    def test_small_jitter_does_not_trigger_moving(self):
        mem = TrackMemory(first_seen=time.time())
        t0 = time.time()
        cx, cy = 500.0, 400.0
        mem.update_motion(_box(cx, cy), t0)
        for i in range(10):
            # Sub-pixel / tiny wobble well below MOTION_SPEED_THRESH after normalization.
            jitter = 0.4 if i % 2 == 0 else -0.4
            state = mem.update_motion(_box(cx + jitter, cy + jitter), t0 + 0.2 * (i + 1))
        self.assertNotEqual(state, "moving")
        self.assertFalse(mem.allows_ocr())


class MovementGatedOcrTests(unittest.TestCase):
    def _queue(self):
        ocr = MagicMock()
        ocr.enabled = True
        return AsyncPlateQueue(ocr, maxsize=4)

    def test_stationary_vehicle_ocr_submit_zero_times(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.plate_status = "pending"
        intel = MagicMock()
        intel.tracks = {7: mem}
        q = self._queue()
        frame = MagicMock()
        with patch.object(q, "_q") as mock_q:
            for _ in range(5):
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
            mock_q.put_nowait.assert_not_called()
        self.assertEqual(mem.ocr_attempts, 0)

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
        self.assertEqual(mem.ocr_attempts, 1)

    def test_becomes_stationary_stops_further_ocr(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "moving"
        mem.plate_status = "pending"
        intel = MagicMock()
        intel.tracks = {7: mem}
        q = self._queue()
        frame = MagicMock()
        crop = MagicMock()
        with patch("plate_ocr.PlateOCR.crop_plate_region", return_value=crop):
            with patch.object(q, "_q"):
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
        self.assertEqual(mem.ocr_attempts, 1)

        mem.motion_state = "parked"
        mem.last_ocr_at = 0.0
        with patch("plate_ocr.PlateOCR.crop_plate_region", return_value=crop) as crop_fn:
            with patch.object(q, "_q") as mock_q:
                q.submit("CAM-1", 7, frame, (10, 10, 200, 120), intel, every_sec=0.0)
                mock_q.put_nowait.assert_not_called()
                crop_fn.assert_not_called()
        self.assertEqual(mem.ocr_attempts, 1)

    def test_stationary_frames_do_not_increment_attempts(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "parked"
        mem.ocr_attempts = 2
        mem.mark_ocr_attempt()
        self.assertEqual(mem.ocr_attempts, 2)

    def test_six_failures_mark_not_read_and_stop(self):
        mem = TrackMemory(first_seen=time.time())
        mem.motion_state = "moving"
        mem.plate_status = "pending"
        mem.ocr_started_at = time.time()
        for _ in range(OCR_MAX_ATTEMPTS):
            mem.mark_ocr_attempt()
        self.assertEqual(mem.ocr_attempts, OCR_MAX_ATTEMPTS)
        self.assertTrue(mem.tick_plate_deadline())
        self.assertEqual(mem.plate_status, "not_read")
        self.assertTrue(mem.is_plate_terminal())
        # Further attempts ignored while terminal.
        before = mem.ocr_attempts
        mem.mark_ocr_attempt()
        self.assertEqual(mem.ocr_attempts, before)

    def test_guard_manual_overrides_unknown_and_blocks_ocr(self):
        mem = TrackMemory(first_seen=time.time())
        mem.plate_status = "not_read"
        mem.plate = None
        mem.lock_plate("ABC1234", 1.0, "manual_guard")
        self.assertEqual(mem.plate, "ABC1234")
        self.assertEqual(mem.plate_source, "GUARD")
        self.assertEqual(mem.plate_status, "ok")
        # OCR vote must not overwrite guard plate.
        mem.apply_ocr_vote("XYZ9999", "ok", 0.99)
        self.assertEqual(mem.plate, "ABC1234")
        self.assertEqual(mem.plate_source, "GUARD")


class OcrGateHelperTests(unittest.TestCase):
    def test_ocr_allowed_only_when_moving(self):
        self.assertTrue(ocr_allowed_for_motion("moving"))
        self.assertFalse(ocr_allowed_for_motion("parked"))
        self.assertFalse(ocr_allowed_for_motion("idle"))
        self.assertFalse(ocr_allowed_for_motion(None))
        self.assertFalse(ocr_allowed_for_motion(""))


if __name__ == "__main__":
    unittest.main()
