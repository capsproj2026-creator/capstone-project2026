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
}
