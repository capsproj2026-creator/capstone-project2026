<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class GateMonitorRealtimeTest extends TestCase
{
    public function test_live_gate_page_boots_echo_and_waiting_state(): void
    {
        try {
            $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
                ?? User::query()->where('user_role_id', 2)->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $guard) {
            $this->markTestSkipped('Run php artisan db:seed — guard user not found.');
        }

        $this->actingAs($guard)
            ->get(route('guard.gate'))
            ->assertOk()
            ->assertSee('Waiting for RFID...', false)
            ->assertSee('whenEchoReady', false)
            ->assertSee(".private('gate.scans')", false)
            ->assertSee('.GateScanProcessed', false)
            ->assertSee('IDLE_MS', false)
            ->assertSee('reverb-app-key', false)
            ->assertSee('Emergency gate open', false)
            ->assertSee('GATE-IN-1', false)
            ->assertSee('data-open-gate="GATE-IN-1"', false)
            ->assertDontSee('data-open-gate="GATE-OUT-1"', false)
            ->assertDontSee("fetch(eventsBase", false)
            ->assertDontSee('setInterval(poll', false);
    }

    public function test_guard_can_authorize_gate_scans_channel(): void
    {
        try {
            $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
                ?? User::query()->where('user_role_id', 2)->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $guard) {
            $this->markTestSkipped('Run php artisan db:seed — guard user not found.');
        }

        \Illuminate\Support\Facades\Config::set('broadcasting.default', 'reverb');
        \Illuminate\Support\Facades\Config::set('broadcasting.connections.reverb.key', 'testing-key');
        \Illuminate\Support\Facades\Config::set('broadcasting.connections.reverb.secret', 'testing-secret');
        \Illuminate\Support\Facades\Config::set('broadcasting.connections.reverb.app_id', 'testing-app');
        \Illuminate\Support\Facades\Config::set('broadcasting.connections.reverb.options', [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ]);

        // phpunit defaults BROADCAST_CONNECTION=null, so channels.php was bound to NullBroadcaster.
        \Illuminate\Support\Facades\Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $this->assertContains((int) $guard->user_role_id, [1, 2], 'Fixture user must be Admin or Guard');

        $response = $this->actingAs($guard)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-gate.scans',
            ]);

        $response->assertOk();
        $this->assertNotEmpty($response->getContent(), 'broadcasting/auth returned empty body');
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('auth', $json);
    }

    public function test_guard_can_queue_emergency_open_and_student_cannot(): void
    {
        \Illuminate\Support\Facades\Event::fake();
        \Illuminate\Support\Facades\Config::set('broadcasting.default', 'null');

        try {
            $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
                ?? User::query()->where('user_role_id', 2)->first();
            $student = User::query()
                ->where('user_role_id', 3)
                ->where('status', User::STATUS_GRANTED)
                ->where('email', '!=', 'guard@my.cspc.edu.ph')
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $guard) {
            $this->markTestSkipped('Run php artisan db:seed — guard user not found.');
        }

        $this->actingAs($guard)
            ->postJson(route('guard.gate.open'), [
                'gate_id' => 'GATE-IN-1',
                'reason' => 'Stuck vehicle at boom',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('gate_id', 'GATE-IN-1');

        $this->actingAs($guard)
            ->postJson(route('guard.gate.open'), [
                'gate_id' => 'GATE-OUT-1',
                'reason' => 'Exit should not have emergency open',
            ])
            ->assertStatus(422);

        if ($student && strtolower($student->roleName()) !== 'guard') {
            $denied = $this->actingAs($student)
                ->postJson(route('guard.gate.open'), [
                    'gate_id' => 'GATE-IN-1',
                    'reason' => 'Should be blocked',
                ]);

            $this->assertFalse($denied->isSuccessful(), 'Students must not open the boom gate.');
        }

        \App\Models\GateLog::query()
            ->where('rfid_uid', 'MANUAL-OVERRIDE')
            ->where('reason', 'Stuck vehicle at boom')
            ->delete();
    }

    public function test_access_logs_page_does_not_poll_events_endpoint(): void
    {
        try {
            $admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $admin) {
            $this->markTestSkipped('Run php artisan db:seed — admin user not found.');
        }

        $html = $this->actingAs($admin)
            ->get(route('admin.access-logs'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(".private('gate.scans')", $html);
        $this->assertStringContainsString('whenEchoReady', $html);
        $this->assertStringNotContainsString("setInterval(() => fetchResults()", $html);
        $this->assertStringNotContainsString('setInterval(fetchResults', $html);
    }
}
