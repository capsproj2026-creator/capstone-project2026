<?php

namespace Tests\Feature;

use App\Events\GateScanProcessed;
use App\Models\GateLog;
use App\Models\User;
use App\Services\RfidAccessService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RfidGateApiTest extends TestCase
{
    private const TOKEN = 'test-rfid-api-token';

    private const UID = 'AABBCCDDEE';

    private ?User $owner = null;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.rfid.api_token', self::TOKEN);

        try {
            User::query()->where('rfid_uid', self::UID)->delete();
            GateLog::query()->whereIn('rfid_uid', [self::UID, 'DEADBEEF99'])->delete();

            $this->owner = User::query()->create([
                'fullname' => 'RFID Test Owner',
                'email' => 'rfid.owner.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'RFID'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'RFID'.random_int(100, 999),
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'rfid_uid' => self::UID,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->owner) {
            GateLog::query()->where('user_id', $this->owner->id)->delete();
            $this->owner->delete();
        }
        GateLog::query()->where('rfid_uid', 'DEADBEEF99')->delete();

        parent::tearDown();
    }

    public function test_rejects_missing_api_token(): void
    {
        $this->postJson('/api/rfid/scan', [
            'uid' => self::UID,
            'gate_id' => 'GATE-IN-1',
            'direction' => 'Entry',
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'unauthorized');
    }

    public function test_rejects_token_in_request_body(): void
    {
        $this->postJson('/api/rfid/scan', [
            'uid' => self::UID,
            'gate_id' => 'GATE-IN-1',
            'direction' => 'Entry',
            'api_token' => self::TOKEN,
        ])->assertUnauthorized()
            ->assertJsonPath('code', 'unauthorized');
    }

    public function test_card_not_registered(): void
    {
        Event::fake([GateScanProcessed::class]);

        $response = $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => 'DEADBEEF99',
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertNotFound()
            ->assertJsonPath('status', RfidAccessService::STATUS_CARD_NOT_REGISTERED)
            ->assertJsonPath('granted', false)
            ->assertJsonStructure(['log_id']);

        $this->assertNotEmpty($response->json('log_id'));

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            return $event->scan['granted'] === false
                && $event->scan['result'] === RfidAccessService::STATUS_CARD_NOT_REGISTERED
                && $event->scan['action'] === 'Entry'
                && $event->scan['gate_id'] === 'GATE-IN-1'
                && ! empty($event->scan['id']);
        });
    }

    public function test_access_granted_on_entry_then_already_inside(): void
    {
        Event::fake([GateScanProcessed::class]);

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertOk()
            ->assertJsonPath('status', RfidAccessService::STATUS_GRANTED)
            ->assertJsonPath('granted', true)
            ->assertJsonPath('action', 'Entry')
            ->assertJsonStructure(['log_id']);

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            return $event->scan['granted'] === true
                && $event->scan['result'] === RfidAccessService::STATUS_GRANTED
                && $event->scan['name'] === 'RFID Test Owner'
                && $event->scan['action'] === 'Entry'
                && $event->scan['gate_id'] === 'GATE-IN-1';
        });

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', RfidAccessService::STATUS_ALREADY_INSIDE)
            ->assertJsonPath('granted', false)
            ->assertJsonStructure(['log_id']);

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            return $event->scan['granted'] === false
                && $event->scan['result'] === RfidAccessService::STATUS_ALREADY_INSIDE;
        });

        $grantedLog = GateLog::query()
            ->where('rfid_uid', self::UID)
            ->where('result', RfidAccessService::STATUS_GRANTED)
            ->first();

        $this->assertNotNull($grantedLog);
        $this->assertSame('GATE-IN-1', $grantedLog->gate_id);
        $this->assertSame('Entry', $grantedLog->action);
    }

    public function test_rfid_scan_payload_matches_live_gate_presenter(): void
    {
        Event::fake([GateScanProcessed::class]);

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertOk();

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            return $event->scan['name'] === 'RFID Test Owner'
                && $event->scan['action'] === 'Entry'
                && $event->scan['result'] === RfidAccessService::STATUS_GRANTED
                && $event->scan['gate_id'] === 'GATE-IN-1'
                && $event->scan['rfid_uid'] === '••••DDEE';
        });
    }

    public function test_exit_and_already_outside(): void
    {
        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertOk();

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-OUT-1',
                'direction' => 'Exit',
            ])
            ->assertOk()
            ->assertJsonPath('status', RfidAccessService::STATUS_GRANTED)
            ->assertJsonPath('action', 'Exit');

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-OUT-1',
                'direction' => 'Exit',
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', RfidAccessService::STATUS_ALREADY_OUTSIDE);
    }

    public function test_access_denied_when_gate_not_granted(): void
    {
        Event::fake([GateScanProcessed::class]);

        $this->owner->update(['Gate_access' => User::GATE_ACCESS_DENIED]);

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertForbidden()
            ->assertJsonPath('status', RfidAccessService::STATUS_DENIED)
            ->assertJsonPath('granted', false)
            ->assertJsonStructure(['log_id']);

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            return $event->scan['granted'] === false
                && $event->scan['result'] === RfidAccessService::STATUS_DENIED
                && str_contains((string) ($event->scan['reason'] ?? ''), 'Gate');
        });
    }

    public function test_gate_scan_processed_broadcasts_on_private_channel(): void
    {
        Event::fake([GateScanProcessed::class]);

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertOk();

        Event::assertDispatched(GateScanProcessed::class, function (GateScanProcessed $event) {
            $channels = collect($event->broadcastOn())->map(fn ($c) => (string) $c)->all();

            return $event->broadcastAs() === 'GateScanProcessed'
                && in_array('private-gate.scans', $channels, true)
                && isset($event->broadcastWith()['id'], $event->broadcastWith()['granted']);
        });
    }

    public function test_access_denied_when_account_locked(): void
    {
        $this->owner->update([
            'status' => User::STATUS_LOCKED,
            'strike_count' => User::MAX_STRIKES,
        ]);

        $this->withTokenHeader()
            ->postJson('/api/rfid/scan', [
                'uid' => self::UID,
                'gate_id' => 'GATE-IN-1',
                'direction' => 'Entry',
            ])
            ->assertForbidden()
            ->assertJsonPath('status', RfidAccessService::STATUS_DENIED);
    }

    private function withTokenHeader()
    {
        return $this->withHeaders(['X-RFID-TOKEN' => self::TOKEN]);
    }
}
