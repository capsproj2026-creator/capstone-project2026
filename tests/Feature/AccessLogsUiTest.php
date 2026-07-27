<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AccessLogsUiTest extends TestCase
{
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin) {
            $this->markTestSkipped('Run php artisan db:seed — admin user not found.');
        }
    }

    public function test_admin_access_logs_matches_reference_sections(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.access-logs'));

        $response->assertOk()
            ->assertSee('Access Logs')
            ->assertSee('Monitor all entry and exit activities via RFID access')
            ->assertSee('Total Logs')
            ->assertSee('Entries Granted')
            ->assertSee('Exits Granted')
            ->assertSee('Access Denied')
            ->assertSee('Search by name, RFID, or gate...')
            ->assertSee('All Types')
            ->assertSee('All Directions')
            ->assertSee('All Results')
            ->assertSee('Access Records')
            ->assertSee('Recent Denied Access')
            ->assertSee('Timestamp')
            ->assertSee('Direction')
            ->assertSee('Gate')
            ->assertSee('Result')
            ->assertDontSee('RFID Tag')
            ->assertDontSee('>Reason</th>', false);
    }

    public function test_admin_access_logs_filters_by_direction_and_result(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.access-logs', [
                'direction' => 'Entry',
                'result' => 'Granted',
                'type' => 'all',
            ]))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.access-logs', [
                'q' => 'GATE',
                'result' => 'Denied',
            ]))
            ->assertOk()
            ->assertSee('Recent Denied Access');
    }

    public function test_guard_access_logs_still_loads(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
            ?? User::query()->where('user_role_id', 2)->first();

        if (! $guard) {
            $this->markTestSkipped('No guard user found.');
        }

        $this->actingAs($guard)
            ->get(route('guard.access-logs'))
            ->assertOk()
            ->assertSee('Access Logs');
    }
}
