<?php

namespace Tests\Feature;

use App\Events\AiParkingRealtime;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AiParkingRealtimeTest extends TestCase
{
    public function test_event_serializes_consistent_payload(): void
    {
        $event = new AiParkingRealtime(
            AiParkingRealtime::EVENT_VIOLATION_CREATED,
            [
                'vehicleEventId' => 'CAM-1:session:42',
                'trackingId' => 17,
                'plateNumber' => 'ABC1234',
                'violationType' => 'Wrong Parking',
                'detectionSource' => 'AI',
            ],
            '2026-09-18T04:10:39Z',
        );

        $this->assertSame('AiParkingRealtime', $event->broadcastAs());
        $this->assertSame('private-ai.parking', $event->broadcastOn()[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame(AiParkingRealtime::EVENT_VIOLATION_CREATED, $payload['event']);
        $this->assertSame('2026-09-18T04:10:39Z', $payload['timestamp']);
        $this->assertSame('ABC1234', $payload['data']['plateNumber']);
        $this->assertSame(17, $payload['data']['trackingId']);
    }

    public function test_emit_dispatches_broadcast_event(): void
    {
        Event::fake([AiParkingRealtime::class]);

        AiParkingRealtime::emit(AiParkingRealtime::EVENT_PLATE_NOT_READ, [
            'plateNumber' => 'UNKNOWN',
            'trackingId' => 9,
        ]);

        Event::assertDispatched(AiParkingRealtime::class, function (AiParkingRealtime $event) {
            return $event->eventName === AiParkingRealtime::EVENT_PLATE_NOT_READ
                && ($event->data['plateNumber'] ?? null) === 'UNKNOWN'
                && $event->broadcastAs() === 'AiParkingRealtime';
        });
    }

    public function test_guard_can_authorize_ai_parking_channel(): void
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

        \Illuminate\Support\Facades\Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $response = $this->actingAs($guard)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-ai.parking',
            ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('auth', $json);
    }

    public function test_student_cannot_authorize_ai_parking_channel(): void
    {
        try {
            $student = User::query()
                ->where('user_role_id', 3)
                ->where('status', User::STATUS_GRANTED)
                ->where('email', '!=', 'guard@my.cspc.edu.ph')
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $student || strtolower($student->roleName()) === 'guard') {
            $this->markTestSkipped('No student fixture available.');
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

        \Illuminate\Support\Facades\Broadcast::forgetDrivers();
        require base_path('routes/channels.php');

        $response = $this->actingAs($student)
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-ai.parking',
            ]);

        $this->assertFalse($response->isSuccessful(), 'Students must not subscribe to ai.parking');
    }

    public function test_ai_parking_monitor_boots_echo_channel(): void
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
            ->get(route('guard.ai-parking'))
            ->assertOk()
            ->assertSee('whenEchoReady', false)
            ->assertSee(".private('ai.parking')", false)
            ->assertSee('.AiParkingRealtime', false);
    }
}
