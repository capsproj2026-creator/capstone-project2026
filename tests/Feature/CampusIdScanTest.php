<?php

namespace Tests\Feature;

use App\Services\CampusId\CampusIdOcrService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class CampusIdScanTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_page_exposes_scan_status_element(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('id_scan_status', false)
            ->assertSee('auto-fill your name and SN', false);
    }

    public function test_scan_endpoint_returns_parsed_fields(): void
    {
        $mock = Mockery::mock(CampusIdOcrService::class);
        $mock->shouldReceive('scan')
            ->once()
            ->andReturn([
                'ok' => true,
                'id_number' => '231002254',
                'full_name' => 'John Michael Moral Toldanes',
                'name_complete' => true,
                'warnings' => [],
            ]);

        $this->app->instance(CampusIdOcrService::class, $mock);

        $file = UploadedFile::fake()->create('campus-id.jpg', 20, 'image/jpeg');

        $this->postJson(route('register.scan-id'), [
            'id_document' => $file,
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'id_number' => '231002254',
                'full_name' => 'John Michael Moral Toldanes',
                'name_complete' => true,
            ]);
    }

    public function test_scan_endpoint_rejects_pdf_for_auto_scan(): void
    {
        $file = UploadedFile::fake()->create('campus-id.pdf', 100, 'application/pdf');

        $this->postJson(route('register.scan-id'), [
            'id_document' => $file,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_document']);
    }
}
