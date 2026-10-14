<?php

namespace Tests\Unit;

use App\Services\CampusId\OrCrDocumentParser;
use Tests\TestCase;

class OrCrDocumentParserTest extends TestCase
{
    public function test_accepts_official_receipt_keywords_and_matching_plate(): void
    {
        $parser = new OrCrDocumentParser;
        $result = $parser->parse([
            ['text' => 'LAND TRANSPORTATION OFFICE'],
            ['text' => 'OFFICIAL RECEIPT'],
            ['text' => 'LTO'],
            ['text' => 'Plate No. ABC 1234'],
        ], 'or', 'ABC-1234');

        $this->assertTrue($result['has_lto']);
        $this->assertTrue($result['has_document_keyword']);
        $this->assertSame('ABC 1234', $result['plate_number']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_accepts_keywords_when_ocr_drops_the_spaces(): void
    {
        $parser = new OrCrDocumentParser;

        $or = $parser->parse([
            ['text' => 'REPUBLIC OF THEPHILIPPINES'],
            ['text' => 'LANDTRANSPORTATION OFFICE'],
            ['text' => 'OFFICIALRECEIPT'],
            ['text' => 'PlateNo.ABC 1234'],
        ], 'or', 'ABC-1234');

        $this->assertTrue($or['has_lto']);
        $this->assertTrue($or['has_document_keyword']);
        $this->assertSame([], $or['warnings']);

        $cr = $parser->parse([
            ['text' => 'LANDTRANSPORTATION OFFICE'],
            ['text' => 'CERTIFICATEOFREGISTRATION'],
        ], 'cr');

        $this->assertTrue($cr['has_lto']);
        $this->assertTrue($cr['has_document_keyword']);
    }

    public function test_flags_missing_cr_keywords_and_plate_mismatch(): void
    {
        $parser = new OrCrDocumentParser;
        $result = $parser->parse([
            ['text' => 'Some random photo'],
            ['text' => 'Plate XYZ 9999'],
        ], 'cr', 'ABC 1234');

        $this->assertFalse($result['has_document_keyword']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertTrue(collect($result['warnings'])->contains(fn ($w) => str_contains($w, 'CERTIFICATE OF REGISTRATION')));
        $this->assertTrue(collect($result['warnings'])->contains(fn ($w) => str_contains($w, 'does not match')));
    }

    public function test_cr_model_is_series_plus_body_type_not_make_or_year(): void
    {
        $parser = new OrCrDocumentParser;
        $result = $parser->parse([
            ['text' => 'LAND TRANSPORTATION OFFICE'],
            ['text' => 'CERTIFICATE OF REGISTRATION'],
            ['text' => 'MV FILE NO. 1301-000001234567'],
            ['text' => 'PLATE NO. NAB 4821'],
            ['text' => 'MAKE TOYOTA'],
            ['text' => 'SERIES VIOS'],
            ['text' => 'BODY TYPE SEDAN'],
            ['text' => 'YEAR MODEL 2020'],
            ['text' => 'COLOR PEARL WHITE'],
        ], 'cr', 'NAB-4821');

        $this->assertSame('VIOS SEDAN', $result['vehicle_model']);
        $this->assertSame('PEARL WHITE', $result['vehicle_color']);
        $this->assertSame('NAB 4821', $result['plate_number']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_cr_reads_glued_ocr_and_motorcycle_plate(): void
    {
        $parser = new OrCrDocumentParser;
        $result = $parser->parse([
            ['text' => 'CERTIFICATE OF REGISTRATION'],
            ['text' => 'LTO'],
            ['text' => 'MAKEHONDA SERIESCLICK 125I BODYTYPEMOTORCYCLE YEARMODEL2022 COLORRED PLATENO1234AB'],
        ], 'cr');

        $this->assertSame('CLICK 125I MOTORCYCLE', $result['vehicle_model']);
        $this->assertSame('RED', $result['vehicle_color']);
        $this->assertSame('1234 AB', $result['plate_number']);
    }
}
