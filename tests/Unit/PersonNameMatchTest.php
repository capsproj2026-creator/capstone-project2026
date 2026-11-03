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
        ];
    }
}
