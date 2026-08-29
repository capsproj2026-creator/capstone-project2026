<?php

namespace Tests\Feature;

use App\Services\CampusId\LicenseOcrService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class LicenseScanTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_page_includes_gsu18_form_and_scanner(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Vehicle Registration', false)
            ->assertSee('license-preview', false)
            ->assertSee('Driver’s License Photo', false)
            ->assertDontSee('license-scanner-video', false)
            ->assertSee('Driver’s License Number', false)
            ->assertSee('LTO Official Receipt (OR)', false)
            ->assertSee('LTO Certificate of Registration (CR)', false)
            ->assertSee('Color', false)
            ->assertDontSee('Date of Application', false)
            ->assertDontSee('Classification', false)
            ->assertSee('School ID', false)
            ->assertSee('Profile Picture', false)
            ->assertDontSee('Scan like this', false)
            ->assertDontSee('ocr-guides', false)
            ->assertDontSee('id_scan_status', false);
    }

    public function test_scan_license_endpoint_returns_parsed_fields(): void
    {
        $mock = Mockery::mock(LicenseOcrService::class);
        $mock->shouldReceive('scan')
            ->once()
            ->andReturn([
                'ok' => true,
                'full_name' => 'Juan Santos Dela Cruz',
                'address' => 'Nabua, Camarines Sur',
                'phone_number' => '09171234567',
                'driver_license_number' => 'N01-12-345678',
                'plate_number' => 'ABC 1234',
                'warnings' => [],
            ]);

        $this->app->instance(LicenseOcrService::class, $mock);

        $file = UploadedFile::fake()->create('license.jpg', 20, 'image/jpeg');

        $this->postJson(route('register.scan-license'), [
            'driver_license' => $file,
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'full_name' => 'Juan Santos Dela Cruz',
                'driver_license_number' => 'N01-12-345678',
                'phone_number' => '09171234567',
            ]);
    }

    public function test_scan_license_endpoint_rejects_pdf(): void
    {
        $file = UploadedFile::fake()->create('license.pdf', 100, 'application/pdf');

        $this->postJson(route('register.scan-license'), [
            'driver_license' => $file,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['driver_license']);
    }

    public function test_scan_orcr_endpoint_returns_warnings_without_blocking(): void
    {
        $mock = Mockery::mock(\App\Services\CampusId\OrCrOcrService::class);
        $mock->shouldReceive('scan')
            ->once()
            ->andReturn([
                'ok' => true,
                'kind' => 'or',
                'plate_number' => 'ABC 1234',
                'warnings' => ['Could not find “OFFICIAL RECEIPT” on this file. Confirm it is the LTO OR.'],
                'message' => 'Please review this document.',
            ]);

        $this->app->instance(\App\Services\CampusId\OrCrOcrService::class, $mock);

        $file = UploadedFile::fake()->create('or.jpg', 20, 'image/jpeg');

        $this->postJson(route('register.scan-orcr'), [
            'document' => $file,
            'kind' => 'or',
            'plate_number' => 'XYZ-9999',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('kind', 'or')
            ->assertJsonFragment(['Could not find “OFFICIAL RECEIPT” on this file. Confirm it is the LTO OR.']);
    }
}
