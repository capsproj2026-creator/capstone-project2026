"""Unit tests for zone scaling. Run: python test_geometry.py"""

from __future__ import annotations

import unittest

from geometry import (
    box_ground_point,
    primary_slot_for_box,
    scale_points_to_frame,
    usable_zones_for_frame,
)


class GeometryScaleTest(unittest.TestCase):
    def test_same_size_keeps_points(self):
        pts = [[10, 20], [30, 40]]
        self.assertEqual(scale_points_to_frame(pts, (767, 1024), (767, 1024)), [[10, 20], [30, 40]])

    def test_scales_near_aspect_with_stretch(self):
        pts = [[0, 0], [1000, 1000]]
        out = scale_points_to_frame(pts, (1000, 1000), (2000, 2000))
        self.assertEqual(out[0], [0, 0])
        self.assertEqual(out[1], [2000, 2000])

    def test_cover_scale_when_aspect_differs(self):
        # Portrait calibration → landscape live frame uses cover+center.
        pts = [[0, 0], [767, 1024]]
        out = scale_points_to_frame(pts, (767, 1024), (1280, 720))
        self.assertEqual(out[0][0], 0)
        self.assertLess(out[0][1], 0)  # vertically centered crop offset
        self.assertEqual(out[1][0], 1280)
        self.assertGreater(out[1][1], 720)

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


class PrimarySlotTest(unittest.TestCase):
    """Occupancy = bottom-center inside polygon; never nearest-neighbor."""

    def setUp(self):
        self.zones = [
            {
                "id": "AC-1",
                "type": "slot",
                "points": [[0, 0], [100, 0], [100, 100], [0, 100]],
            },
            {
                "id": "AC-2",
                "type": "slot",
                "points": [[110, 0], [210, 0], [210, 100], [110, 100]],
            },
            {
                "id": "AC-3",
                "type": "slot",
                "points": [[220, 0], [320, 0], [320, 100], [220, 100]],
            },
        ]

    def test_ground_point_is_bottom_center(self):
        self.assertEqual(box_ground_point((10, 20, 50, 80)), (30.0, 80.0))

    def test_inside_ac3_only(self):
        # Box sits fully in AC-3; ground point at bottom-center.
        box = (230, 10, 300, 90)
        hit = primary_slot_for_box(box, self.zones)
        self.assertIsNotNone(hit)
        self.assertEqual(hit["id"], "AC-3")

    def test_between_slots_is_none(self):
        # Ground point in the gap between AC-1 and AC-2.
        box = (95, 10, 115, 50)
        self.assertIsNone(primary_slot_for_box(box, self.zones))

    def test_bbox_overlap_without_ground_point_is_none(self):
        # Wide bbox overlaps AC-1 and AC-2 but ground point is in the aisle gap.
        box = (50, 10, 160, 50)
        gx, gy = box_ground_point(box)
        self.assertEqual((gx, gy), (105.0, 50.0))
        self.assertIsNone(primary_slot_for_box(box, self.zones))

    def test_tire_line_inside_one_bay_when_center_misses(self):
        # Bottom-center sits in the gap just past AC-1; most of the tire line is still in AC-1.
        box = (80, 20, 122, 95)
        self.assertEqual(box_ground_point(box), (101.0, 95.0))
        hit = primary_slot_for_box(box, self.zones)
        self.assertIsNotNone(hit)
        self.assertEqual(hit["id"], "AC-1")

    def test_overlapping_polys_pick_smallest(self):
        nested = [
            {"id": "BIG", "type": "slot", "points": [[0, 0], [200, 0], [200, 200], [0, 200]]},
            {"id": "SMALL", "type": "slot", "points": [[40, 40], [80, 40], [80, 80], [40, 80]]},
        ]
        box = (45, 45, 75, 70)
        hit = primary_slot_for_box(box, nested)
        self.assertEqual(hit["id"], "SMALL")


if __name__ == "__main__":
    unittest.main()
