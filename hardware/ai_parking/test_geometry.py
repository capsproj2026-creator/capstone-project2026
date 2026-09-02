"""Unit tests for zone scaling. Run: python test_geometry.py"""

from __future__ import annotations

import unittest

from geometry import (
    scale_points_to_frame,
    usable_zones_for_frame,
)


class GeometryScaleTest(unittest.TestCase):
    def test_same_size_keeps_points(self):
        pts = [[10, 20], [30, 40]]
        self.assertEqual(scale_points_to_frame(pts, (767, 1024), (767, 1024)), [[10, 20], [30, 40]])

    def test_scales_to_live_frame_without_crop(self):
        pts = [[0, 0], [767, 1024]]
        out = scale_points_to_frame(pts, (767, 1024), (1280, 720))
        self.assertEqual(out[0], [0, 0])
        self.assertEqual(out[1], [1280, 720])

    def test_usable_zones_for_frame_copies_scaled_points(self):
        data = {
            "image_width": 100,
            "image_height": 200,
            "zones": [
                {"id": "AC-1", "type": "slot", "points": [[0, 0], [100, 0], [100, 200], [0, 200]]},
            ],
        }
        zones = usable_zones_for_frame(data, (400, 200))
        self.assertEqual(zones[0]["id"], "AC-1")
        self.assertEqual(zones[0]["points"], [[0, 0], [200, 0], [200, 400], [0, 400]])
        # original dict is unchanged
        self.assertEqual(data["zones"][0]["points"][2], [100, 200])


if __name__ == "__main__":
    unittest.main()
