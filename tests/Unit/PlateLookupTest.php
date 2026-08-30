<?php

namespace Tests\Unit;

use App\Support\PlateLookup;
use PHPUnit\Framework\TestCase;

class PlateLookupTest extends TestCase
{
    public function test_normalize_strips_hyphens_and_spaces(): void
    {
        $this->assertSame('ABC1234', PlateLookup::normalize('abc-1234'));
        $this->assertSame('ABC1234', PlateLookup::normalize(' ABC 1234 '));
        $this->assertSame('', PlateLookup::normalize(''));
    }

    public function test_candidates_include_common_ph_formats(): void
    {
        $candidates = PlateLookup::candidates('abc1234');

        $this->assertContains('ABC1234', $candidates);
        $this->assertContains('ABC-1234', $candidates);
        $this->assertContains('ABC 1234', $candidates);
    }

    public function test_candidates_include_lto_motorcycle_formats(): void
    {
        $candidates = PlateLookup::candidates('0501-0401328');

        $this->assertContains('05010401328', $candidates);
        $this->assertContains('0501-0401328', $candidates);
        $this->assertContains('0501 0401328', $candidates);
    }

    public function test_ocr_correction_variants_fix_common_mistakes(): void
    {
        $variants = PlateLookup::ocrCorrectionVariants('AB01234');
        $this->assertContains('AB01234', $variants);
        $this->assertContains('ABO1234', $variants);
    }

    public function test_search_keys_deduplicates(): void
    {
        $keys = PlateLookup::searchKeys('abc-1234');
        $this->assertContains('ABC1234', $keys);
        $this->assertSame($keys, array_values(array_unique($keys)));
    }

    public function test_motorcycle_ocr_correction(): void
    {
        $variants = PlateLookup::ocrCorrectionVariants('O5010401328');
        $this->assertContains('05010401328', $variants);
    }
}
