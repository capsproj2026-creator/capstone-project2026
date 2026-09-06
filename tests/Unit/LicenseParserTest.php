<?php

namespace Tests\Unit;

use App\Services\CampusId\LicenseParser;
use Tests\TestCase;

class LicenseParserTest extends TestCase
{
    private LicenseParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new LicenseParser;
    }

    public function test_parses_philippine_drivers_license_fields(): void
    {
        $result = $this->parser->parse([
            ['text' => 'REPUBLIC OF THE PHILIPPINES'],
            ['text' => 'LAND TRANSPORTATION OFFICE'],
            ['text' => "DRIVER'S LICENSE"],
            ['text' => 'DELA CRUZ, JUAN SANTOS', 'confidence' => 0.95],
            ['text' => '123 ZONE 4, BRGY CRISTO REY, NABUA, CAMARINES SUR', 'confidence' => 0.9],
            ['text' => 'License No. N01-12-345678', 'confidence' => 0.97],
            ['text' => '09171234567', 'confidence' => 0.8],
        ]);

        $this->assertSame('Juan Santos Dela Cruz', $result['full_name']);
        $this->assertSame('N01-12-345678', $result['driver_license_number']);
        $this->assertSame('09171234567', $result['phone_number']);
        $this->assertNotNull($result['address']);
        $this->assertStringContainsString('NABUA', $result['address']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_extracts_optional_plate_when_printed_on_license(): void
    {
        $result = $this->parser->parse([
            ['text' => "DRIVER'S LICENSE"],
            ['text' => 'DELA CRUZ, JUAN SANTOS'],
            ['text' => 'N01-12-345678'],
            ['text' => 'Plate No. ABC 1234'],
        ]);

        $this->assertSame('ABC 1234', $result['plate_number']);
        $this->assertSame('N01-12-345678', $result['driver_license_number']);
    }

    public function test_reads_multiline_address_below_the_name(): void
    {
        $result = $this->parser->parse([
            ['text' => "DRIVER'S LICENSE"],
            ['text' => 'DELA CRUZ, JUAN SANTOS'],
            ['text' => 'Address: 0095 ZONE 4'],
            ['text' => 'CRISTO REY'],
            ['text' => 'BATO, CAMARINES SUR'],
            ['text' => 'N01-12-345678'],
            ['text' => '01/15/1998'],
        ]);

        $this->assertNotNull($result['address']);
        $this->assertStringContainsString('0095 ZONE 4', $result['address']);
        $this->assertStringContainsString('CRISTO REY', $result['address']);
        $this->assertStringContainsString('CAMARINES SUR', $result['address']);
    }

    public function test_reads_address_when_label_is_on_its_own_line(): void
    {
        $result = $this->parser->parse([
            ['text' => 'DELA CRUZ, JUAN SANTOS'],
            ['text' => 'ADDRESS'],
            ['text' => 'PUROK 2 SAN ISIDRO'],
            ['text' => 'NABUA, CAMARINES SUR'],
            ['text' => 'N04-18-002345'],
        ]);

        $this->assertNotNull($result['address']);
        $this->assertStringContainsString('PUROK 2 SAN ISIDRO', $result['address']);
        $this->assertStringContainsString('NABUA', $result['address']);
        $this->assertStringContainsString('CAMARINES SUR', $result['address']);
    }

    public function test_warns_when_contact_and_license_are_missing(): void
    {
        $result = $this->parser->parse([
            ['text' => 'JUAN DELA CRUZ', 'confidence' => 0.9],
        ]);

        $this->assertSame('Juan Dela Cruz', $result['full_name']);
        $this->assertNull($result['driver_license_number']);
        $this->assertNull($result['phone_number']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_parses_sample_non_professional_front_layout(): void
    {
        $result = $this->parser->parse([
            ['text' => 'REPUBLIC OF THE PHILIPPINES'],
            ['text' => 'DEPARTMENT OF TRANSPORTATION'],
            ['text' => 'LAND TRANSPORTATION OFFICE'],
            ['text' => "NON-PROFESSIONAL DRIVER'S LICENSE"],
            ['text' => 'Last Name, First Name, Middle Name'],
            ['text' => 'DELA CRUZ, JUAN PEDRO GARCIA'],
            ['text' => 'Nationality'],
            ['text' => 'PHL'],
            ['text' => 'Sex'],
            ['text' => 'M'],
            ['text' => 'Date of Birth'],
            ['text' => '1987/10/04'],
            ['text' => 'Weight (kg)'],
            ['text' => '70'],
            ['text' => 'Height (m)'],
            ['text' => '1.55'],
            ['text' => 'Address'],
            ['text' => 'UNIT/HOUSE NO. BUILDING, STREET NAME,'],
            ['text' => 'BARANGAY, CITY/MUNICIPALITY'],
            ['text' => 'License No.'],
            ['text' => 'N03-12-123456'],
            ['text' => 'Expiration Date'],
            ['text' => '2022/10/04'],
            ['text' => 'Agency Code'],
            ['text' => 'N32'],
            ['text' => 'Blood Type'],
            ['text' => 'O+'],
            ['text' => 'Eyes Color'],
            ['text' => 'BLACK'],
            ['text' => 'Restrictions'],
            ['text' => '1, 2'],
            ['text' => 'Conditions'],
            ['text' => 'NONE'],
        ]);

        $this->assertSame('Juan Pedro Garcia Dela Cruz', $result['full_name']);
        $this->assertSame('N03-12-123456', $result['driver_license_number']);
        $this->assertNotNull($result['address']);
        $this->assertStringContainsString('BARANGAY', $result['address']);
        $this->assertStringContainsString('STREET NAME', $result['address']);
    }

    public function test_dedupes_overlapping_ocr_address_fragments(): void
    {
        $result = $this->parser->parse([
            ['text' => 'DELA CRUZ, JUAN PEDRO GARCIA'],
            ['text' => 'Address'],
            ['text' => '144.MAGNOLIA,LOURDESYOUNG.NABUA'],
            ['text' => '144.MAGNOLIA.LOURDESYOUN'],
            ['text' => 'CAMARINESSUR,4434'],
            ['text' => 'CAMARINES SUR,4434'],
            ['text' => 'N03-12-123456'],
        ]);

        $this->assertSame('Juan Pedro Garcia Dela Cruz', $result['full_name']);
        $this->assertSame('N03-12-123456', $result['driver_license_number']);
        $this->assertSame('144 MAGNOLIA, LOURDES YOUNG, NABUA, CAMARINES SUR, 4434', $result['address']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_reads_license_number_when_ocr_glues_it_to_expiration(): void
    {
        $result = $this->parser->parse([
            ['text' => 'License No'],
            ['text' => 'N03-12-123456N2022/10/04'],
            ['text' => 'N32'],
        ]);

        $this->assertSame('N03-12-123456', $result['driver_license_number']);
    }

    public function test_reads_licenseno_label_on_its_own_line(): void
    {
        $result = $this->parser->parse([
            ['text' => 'LICENSENO'],
            ['text' => 'N01-12-345678'],
        ]);

        $this->assertSame('N01-12-345678', $result['driver_license_number']);
    }

    public function test_reads_licenseno_same_line_with_spaces_instead_of_hyphens(): void
    {
        $result = $this->parser->parse([
            ['text' => 'LICENSENO N01 12 345678'],
        ]);

        $this->assertSame('N01-12-345678', $result['driver_license_number']);
    }

    public function test_reads_compact_license_number_without_separators(): void
    {
        $result = $this->parser->parse([
            ['text' => 'LICENSENO N0112345678'],
        ]);

        $this->assertSame('N01-12-345678', $result['driver_license_number']);
    }

    public function test_normalizes_ocr_letter_digit_confusion_in_license_number(): void
    {
        $result = $this->parser->parse([
            ['text' => 'LICENSENO'],
            ['text' => 'NO1-I2-345678'],
        ]);

        $this->assertSame('N01-12-345678', $result['driver_license_number']);
    }

    public function test_reads_license_number_from_sample_card_ocr_dump(): void
    {
        $result = $this->parser->parse([
            ['text' => 'NON-PROFESSIONALDRIVER\'SLICENSI'],
            ['text' => 'DELA CRUZJUAN PEDRO GARCIA'],
            ['text' => 'Address'],
            ['text' => 'UNIT/HOUSENO.BUILDING,STREETNAME'],
            ['text' => 'License No'],
            ['text' => 'Expiration Date'],
            ['text' => 'N03-12-123456N2022/10/04'],
            ['text' => 'N32'],
            ['text' => 'NOS-12-124SEOELACRUZJRANPCDRDGARCIA'],
        ]);

        $this->assertSame('N03-12-123456', $result['driver_license_number']);
    }
}
