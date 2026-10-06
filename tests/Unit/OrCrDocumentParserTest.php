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
}
