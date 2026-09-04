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
            ->assertSee('Search by name, Student/Faculty, RFID, or gate...')
            ->assertSee('id="access-logs-filter-form"', false)
            ->assertSee('All Types')
            ->assertSee('All Directions')
            ->assertSee('All Results')
            ->assertSee('Access Records')
            ->assertSee('Recent Denied Access')
            ->assertSee('Timestamp')
            ->assertSee('Direction')
            ->assertSee('Gate')
            ->assertSee('Result')
            ->assertSee(".private('gate.scans')", false)
            ->assertSee('.GateScanProcessed', false)
            ->assertDontSee('RFID Tag')
            ->assertDontSee('>Reason</th>', false)
            ->assertDontSee('setInterval(fetchResults', false);
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

    public function test_admin_access_logs_can_search_by_user_name(): void
    {
        $userWithLog = \App\Models\GateLog::query()
            ->with('user')
            ->whereNotNull('user_id')
            ->orderByDesc('timestamp')
            ->first();

        $name = trim((string) ($userWithLog?->user?->displayName() ?? ''));
        if ($name === '' || strcasecmp($name, 'Unknown') === 0) {
            $this->markTestSkipped('No access log with a named user available.');
        }

        $needle = explode(' ', $name)[0];

        $this->actingAs($this->admin)
            ->get(route('admin.access-logs', ['q' => $needle]))
            ->assertOk()
            ->assertSee($name, false);
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
            ->assertSee('Access Logs')
            ->assertSee('id="access-logs-filter-form"', false)
            ->assertSee(route('guard.access-logs'), false);
    }

    public function test_guard_access_logs_search_query_reloads_filtered_page(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
            ?? User::query()->where('user_role_id', 2)->first();

        if (! $guard) {
            $this->markTestSkipped('No guard user found.');
        }

        $this->actingAs($guard)
            ->get(route('guard.access-logs', ['q' => 'GATE']))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('value="GATE"', false);
    }
}
