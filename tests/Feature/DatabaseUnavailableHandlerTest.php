<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class DatabaseUnavailableHandlerTest extends TestCase
{
    public function test_web_request_shows_friendly_database_unavailable_page(): void
    {
        Route::get('/__test/db-down', function () {
            throw new RuntimeException(
                'No suitable servers found (`serverSelectionTryOnce` set): [connection refused calling hello on 127.0.0.1:27017]'
            );
        });

        $this->get('/__test/db-down')
            ->assertStatus(503)
            ->assertSee('Database unavailable', false)
            ->assertSee('Database is temporarily unavailable', false);
    }

    public function test_api_request_returns_json_database_unavailable(): void
    {
        Route::get('/api/__test/db-down', function () {
            throw new RuntimeException('Server selection timeout: no suitable servers');
        });

        $this->getJson('/api/__test/db-down')
            ->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'database_unavailable');
    }

    public function test_rfid_api_returns_denied_shaped_payload_when_database_down(): void
    {
        Route::post('/api/rfid/__test/db-down', function () {
            throw new RuntimeException('Failed to connect: connection refused');
        });

        $this->postJson('/api/rfid/__test/db-down', ['gate_id' => 'ENTRY-1'])
            ->assertStatus(503)
            ->assertJsonPath('granted', false)
            ->assertJsonPath('code', 'database_unavailable')
            ->assertJsonPath('gate_id', 'ENTRY-1');
    }
}
