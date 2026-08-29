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
