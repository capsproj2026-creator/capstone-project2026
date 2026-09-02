"""Unit tests for monitor_scan view mapping. Run: python test_monitor_scan.py"""

from __future__ import annotations

import unittest

from monitor_scan import boxes_intersect, clamp01, view_to_xyxy


class MonitorScanTest(unittest.TestCase):
    def test_clamp01(self):
        self.assertEqual(clamp01(-1), 0.0)
        self.assertEqual(clamp01(2), 1.0)
        self.assertEqual(clamp01(0.25), 0.25)

    def test_full_view_covers_frame(self):
        x1, y1, x2, y2 = view_to_xyxy((720, 1280, 3), {"x": 0, "y": 0, "w": 1, "h": 1}, pad=0)
        self.assertEqual((x1, y1, x2, y2), (0, 0, 1280, 720))

    def test_zoomed_center_maps_to_middle(self):
        x1, y1, x2, y2 = view_to_xyxy(
            (1000, 1000, 3),
            {"x": 0.4, "y": 0.4, "w": 0.2, "h": 0.2},
            pad=0,
        )
        self.assertEqual((x1, y1, x2, y2), (400, 400, 600, 600))

    def test_boxes_intersect(self):
        self.assertTrue(boxes_intersect((0, 0, 50, 50), (40, 40, 80, 80)))
        self.assertFalse(boxes_intersect((0, 0, 10, 10), (20, 20, 30, 30)))


if __name__ == "__main__":
    unittest.main()
