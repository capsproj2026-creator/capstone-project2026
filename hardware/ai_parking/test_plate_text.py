"""Unit tests for plate_text.py — run: python test_plate_text.py"""

from __future__ import annotations

import unittest

from plate_text import (
    correction_variants,
    is_ph_car_plate,
    is_ph_motorcycle_plate,
    parse_plate_candidate,
)


class PlateTextTest(unittest.TestCase):
    def test_parse_car_plate(self):
        self.assertEqual(parse_plate_candidate("abc 1234"), ("ABC1234", True))
        self.assertEqual(parse_plate_candidate("NAA-5678"), ("NAA5678", True))

    def test_parse_motorcycle_plate(self):
        self.assertEqual(parse_plate_candidate("0501-0401328"), ("05010401328", True))
        self.assertTrue(is_ph_motorcycle_plate("05010401328"))

    def test_ocr_corrections(self):
        variants = correction_variants("AB81234")
        self.assertIn("AB81234", variants)
        self.assertTrue(any(v.startswith("AB") and v.endswith("1234") for v in variants))

    def test_position_correct(self):
        parsed, known = parse_plate_candidate("AB01234")
        self.assertEqual(parsed, "AB01234")
        corrected = correction_variants("AB01234")
        self.assertTrue(any(is_ph_car_plate(v) for v in corrected))

    def test_car_format(self):
        self.assertTrue(is_ph_car_plate("ABC1234"))
        self.assertFalse(is_ph_car_plate("AB12345"))


if __name__ == "__main__":
    unittest.main()
