<?php

namespace Tests\Unit;

use App\Support\PrivateEvidence;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PrivateEvidenceTest extends TestCase
{
    #[DataProvider('pathProvider')]
    public function test_safe_path_allowlist(string $path, bool $expected): void
    {
        $this->assertSame($expected, PrivateEvidence::isSafePath($path));
    }

    public static function pathProvider(): array
    {
        return [
            ['violation-evidence/ai-abc.jpg', true],
            ['violation-evidence/foo/bar.png', true],
            ['uploads/documents/license/x.jpg', false],
            ['../violation-evidence/x.jpg', false],
            ['violation-evidence/../secrets.env', false],
            ['', false],
        ];
    }
}
