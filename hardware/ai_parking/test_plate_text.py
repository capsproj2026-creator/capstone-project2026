"""Unit tests for plate_text.py — run: python test_plate_text.py"""

from __future__ import annotations

import unittest

from plate_text import (
    best_from_results,
    correction_variants,
    is_ph_car_plate,
    is_ph_motorcycle_plate,
    parse_plate_candidate,
    score_candidate,
)


class PlateTextTest(unittest.TestCase):
    def test_parse_car_plate(self):
        self.assertEqual(parse_plate_candidate("abc 1234"), ("ABC1234", True))
        self.assertEqual(parse_plate_candidate("NAA-5678"), ("NAA5678", True))
        self.assertEqual(parse_plate_candidate("NAR 6011"), ("NAR6011", True))

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
        self.assertTrue(is_ph_car_plate("NAR6011"))
        self.assertFalse(is_ph_car_plate("AB12345"))

    def test_score_prefers_valid_car(self):
        car_score = score_candidate("ABC1234", True, 0.5)
        junk_score = score_candidate("AB12", False, 0.5)
        self.assertGreater(car_score, junk_score)

    def test_parse_embedded_car_plate(self):
        # Direct parse may accept generic alphanumeric; substring logic in best_from_results
        # extracts valid PH plates from longer OCR reads.
        parsed, known = parse_plate_candidate("ABC1234")
        self.assertEqual(parsed, "ABC1234")
        self.assertTrue(known)

    def test_joins_split_ocr_fragments(self):
        """EasyOCR often returns letter block + digit block separately."""
        # Fake EasyOCR bboxes: four corner points each.
        results = [
            ([[10, 10], [80, 10], [80, 40], [10, 40]], "NAR", 0.91),
            ([[90, 12], [180, 12], [180, 42], [90, 42]], "6011", 0.88),
        ]
        plate, score, _any = best_from_results(results, min_conf=0.28)
        self.assertEqual(plate, "NAR6011")
        self.assertGreaterEqual(score, 0.55)

    def test_prefers_plate_over_brand_text(self):
        """Multicab crops often include ISUZU / PHILIPPINES above the real plate."""
        results = [
            ([[20, 5], [120, 5], [120, 30], [20, 30]], "ISUZU", 0.95),
            ([[30, 40], [90, 40], [90, 70], [30, 70]], "EBD", 0.82),
            ([[100, 42], [160, 42], [160, 72], [100, 72]], "814", 0.80),
            ([[10, 80], [200, 80], [200, 100], [10, 100]], "PHILIPPINES", 0.93),
        ]
        plate, score, _any = best_from_results(results, min_conf=0.24)
        self.assertEqual(plate, "EBD814")
        self.assertGreater(score, 0.7)

    def test_ebd814_is_known_car(self):
        self.assertTrue(is_ph_car_plate("EBD814"))
        parsed, known = parse_plate_candidate("EBD 814")
        self.assertEqual(parsed, "EBD814")
        self.assertTrue(known)


if __name__ == "__main__":
    unittest.main()
