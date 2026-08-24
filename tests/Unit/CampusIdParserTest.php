<?php

namespace Tests\Unit;

use App\Services\CampusId\CampusIdParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CampusIdParserTest extends TestCase
{
    private CampusIdParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CampusIdParser;
    }

    public function test_parses_cspc_id_full_name_and_sn_from_ocr_lines(): void
    {
        $result = $this->parser->parse([
            ['text' => 'SN: 231002254', 'confidence' => 0.92, 'height' => 18, 'center_y' => 0.35],
            ['text' => 'JOHN MICHAEL MORAL', 'confidence' => 0.88, 'height' => 27, 'center_y' => 0.65],
            ['text' => 'TOLDANES', 'confidence' => 0.99, 'height' => 22, 'center_y' => 0.69],
            ['text' => '0095, ZONE 4, CRISTO REY, BATO, CAMARINES SUR', 'confidence' => 0.4, 'height' => 24, 'center_y' => 0.73],
        ]);

        $this->assertSame('231002254', $result['id_number']);
        $this->assertSame('John Michael Moral Toldanes', $result['full_name']);
        $this->assertTrue($result['name_complete']);
        $this->assertTrue($result['name_complete']);
        $this->assertSame([], $result['warnings']);
    }

    public function test_ignores_address_line_below_name(): void
    {
        $result = $this->parser->parse([
            ['text' => 'JOHN MICHAEL MORAL', 'confidence' => 0.96, 'height' => 27, 'center_y' => 0.65],
            ['text' => 'TOLDANES', 'confidence' => 0.99, 'height' => 22, 'center_y' => 0.69],
            ['text' => '0095,ZONE4,CRISTOREY,BATO', 'confidence' => 0.87, 'height' => 24, 'center_y' => 0.73],
            ['text' => 'CAMARINESSUR', 'confidence' => 0.99, 'height' => 21, 'center_y' => 0.75],
        ]);

        $this->assertSame('John Michael Moral Toldanes', $result['full_name']);
        $this->assertTrue($result['name_complete']);
    }

    public function test_reads_long_name_from_same_size_lines(): void
    {
        $result = $this->parser->parse([
            ['text' => 'MARIA CLARA ANGELICA', 'confidence' => 0.95, 'height' => 26, 'center_y' => 0.63],
            ['text' => 'SANTOS DELA', 'confidence' => 0.94, 'height' => 25, 'center_y' => 0.66],
            ['text' => 'CRUZ', 'confidence' => 0.98, 'height' => 24, 'center_y' => 0.69],
            ['text' => '0095,ZONE4,CRISTOREY,BATO', 'confidence' => 0.87, 'height' => 24, 'center_y' => 0.73],
        ]);

        $this->assertSame('Maria Clara Angelica Santos Dela Cruz', $result['full_name']);
        $this->assertTrue($result['name_complete']);
    }

    #[DataProvider('digitOnlySnProvider')]
    public function test_picks_digit_only_sn_candidate(string $text, string $expected): void
    {
        $result = $this->parser->parse([
            ['text' => $text, 'confidence' => 0.45],
            ['text' => 'JOHN MICHAEL MORAL', 'confidence' => 0.88],
            ['text' => 'TOLDANES', 'confidence' => 0.99],
        ]);

        $this->assertSame($expected, $result['id_number']);
    }

    public static function digitOnlySnProvider(): array
    {
        return [
            ['21002154', '21002154'],
            ['231002254', '231002254'],
        ];
    }

    public function test_warns_when_name_or_sn_missing(): void
    {
        $result = $this->parser->parse([
            ['text' => 'Camarines Sur Polytechnic Colleges', 'confidence' => 0.2],
        ]);

        $this->assertNull($result['id_number']);
        $this->assertNull($result['full_name']);
        $this->assertFalse($result['name_complete']);
        $this->assertCount(2, $result['warnings']);
    }

    public function test_rejects_partial_name_missing_surname_line(): void
    {
        $result = $this->parser->parse([
            ['text' => 'SN: 231002254', 'confidence' => 0.92, 'height' => 18, 'center_y' => 0.35],
            ['text' => 'JOHN MICHAEL', 'confidence' => 0.88, 'height' => 27, 'center_y' => 0.65],
        ]);

        $this->assertSame('231002254', $result['id_number']);
        $this->assertNull($result['full_name']);
        $this->assertFalse($result['name_complete']);
        $this->assertCount(1, $result['warnings']);
        $this->assertStringContainsString('full name', strtolower($result['warnings'][0]));
    }

    public function test_accepts_short_three_word_name(): void
    {
        $result = $this->parser->parse([
            ['text' => 'SN: 231002254', 'confidence' => 0.92, 'height' => 18, 'center_y' => 0.35],
            ['text' => 'JUAN DELA CRUZ', 'confidence' => 0.91, 'height' => 27, 'center_y' => 0.65],
        ]);

        $this->assertSame('Juan Dela Cruz', $result['full_name']);
        $this->assertTrue($result['name_complete']);
    }

    public function test_accepts_multi_word_surname_across_lines(): void
    {
        $result = $this->parser->parse([
            ['text' => 'SN: 210021540', 'confidence' => 0.9, 'height' => 16, 'center_y' => 0.32],
            ['text' => 'ANA MARIA', 'confidence' => 0.93, 'height' => 25, 'center_y' => 0.58],
            ['text' => 'LOPEZ DE', 'confidence' => 0.92, 'height' => 24, 'center_y' => 0.64],
            ['text' => 'LA CRUZ', 'confidence' => 0.94, 'height' => 24, 'center_y' => 0.70],
            ['text' => 'ZONE 4 BATO', 'confidence' => 0.5, 'height' => 14, 'center_y' => 0.84],
        ]);

        $this->assertSame('Ana Maria Lopez De La Cruz', $result['full_name']);
        $this->assertTrue($result['name_complete']);
    }

    public function test_accepts_single_line_name_with_four_or_more_words(): void
    {
        $result = $this->parser->parse([
            ['text' => 'JOHN MICHAEL MORAL TOLDANES', 'confidence' => 0.91, 'height' => 27, 'center_y' => 0.65],
        ]);

        $this->assertSame('John Michael Moral Toldanes', $result['full_name']);
        $this->assertTrue($result['name_complete']);
    }
}
