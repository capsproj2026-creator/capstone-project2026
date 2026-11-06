<?php

namespace Tests\Unit;

use App\Services\CampusId\CampusIdOcrService;
use App\Services\CampusId\LicenseOcrService;
use App\Services\CampusId\OrCrOcrService;
use App\Services\CampusId\RegistrationCredentialVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PersonNameMatchTest extends TestCase
{
    private RegistrationCredentialVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->verifier = new RegistrationCredentialVerifier(
            $this->createMock(CampusIdOcrService::class),
            $this->createMock(LicenseOcrService::class),
            $this->createMock(OrCrOcrService::class),
        );
    }

    #[DataProvider('samePersonNames')]
    public function test_accepts_same_name_in_different_orders(string $left, string $right): void
    {
        $this->assertTrue(
            $this->verifier->namesMatch($left, $right),
            "Expected match: {$left} ↔ {$right}"
        );
        $this->assertTrue(
            $this->verifier->namesMatch($right, $left),
            "Expected match (reversed): {$right} ↔ {$left}"
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function samePersonNames(): array
    {
        return [
            ['Joshua Fuertes Sabater', 'Sabater Joshua Fuertes'],
            ['Joshua Fuertes Sabater', 'Sabater, Joshua Fuertes'],
            ['Joshua Fuertes Sabater', 'SABATER, JOSHUA FUERTES'],
            ['JOSHUA FUERTES SABATER', 'SABATER JOSHUA FUERTES'],
            ['Maria Clara Santos', 'Santos Maria Clara'],
            ['Maria Clara Santos', 'Santos, Maria Clara'],
            // OCR sometimes keeps an SN label near the name.
            ['Joshua Fuertes Sabater', 'SN SABATER JOSHUA FUERTES'],
            // License field order: Last, First, Middle.
            ['Joshua Fuertes Sabater', 'SABATER, JOSHUA, FUERTES'],
            ['Juan Santos Dela Cruz', 'DELA CRUZ, JUAN, SANTOS'],
            ['Juan Santos Dela Cruz', 'DELA CRUZ, JUAN SANTOS'],
            // Middle initial, omitted middle name, suffix, and accents.
            ['Joshua Fuertes Sabater', 'Joshua F. Sabater'],
            ['Joshua Fuertes Sabater', 'Sabater, Joshua F.'],
            ['Joshua Fuertes Sabater', 'Joshua Sabater'],
            ['Joshua Fuertes Sabater', 'Joshua Sabater Jr.'],
            ['Jose Garcia', 'José García'],
            // Particles written together or apart.
            ['Juan Dela Cruz', 'Delacruz, Juan'],
            ['Juan Dela Cruz', 'De La Cruz, Juan'],
            ['Maria Clara Santos', 'Ma. Clara Santos'],
            ['Jon Reyes', 'John Reyes'],
            // Given names reordered, or OCR glued into one word.
            ['Maria Clara Santos', 'Clara Maria Santos'],
            ['Joshua Fuertes Sabater', 'JOSHUAFUERTESSABATER'],
        ];
    }

    #[DataProvider('differentPeople')]
    public function test_rejects_different_people(string $left, string $right): void
    {
        $this->assertFalse(
            $this->verifier->namesMatch($left, $right),
            "Expected no match: {$left} ↔ {$right}"
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function differentPeople(): array
    {
        return [
            ['Joshua Fuertes Sabater', 'Juan Dela Cruz'],
            ['Joshua Fuertes Sabater', 'Sabater'],
            ['Anna Reyes', 'Anna Santos'],
            // Same given names, different surname or middle name.
            ['John Michael Santos', 'John Michael Reyes'],
            ['Joshua Fuertes Sabater', 'Joshua Santos Sabater'],
            ['Juan Cruz', 'Juan Cruzado'],
            ['Maria Santos', 'Mario Santos'],
        ];
    }
}
