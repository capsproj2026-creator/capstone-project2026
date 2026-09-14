<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Services\RfidAccessService;
use App\Services\TemporaryRfidService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TemporaryRfidAccessTest extends TestCase
{
    private const TOKEN = 'test-rfid-api-token';

    private const UID = 'CAFEF00D01';

    private RfidAccessService $rfid;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.rfid.api_token', self::TOKEN);
        Config::set('services.rfid.temp_access_enabled', false);

        $this->rfid = app(RfidAccessService::class);
        $uid = $this->rfid->normalizeUid(self::UID);

        try {
            User::query()->where('rfid_uid', $uid)->delete();
            User::query()->where('temp_identity_key', app(TemporaryRfidService::class)->identityKeyForUid($uid))->delete();
            GateLog::query()->where('rfid_uid', $uid)->delete();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        try {
            $uid = $this->rfid->normalizeUid(self::UID);
            $key = app(TemporaryRfidService::class)->identityKeyForUid($uid);
            User::query()->where('temp_identity_key', $key)->get()->each(function (User $user) {
                GateLog::query()->where('user_id', $user->id)->delete();
                $user->delete();
            });
            GateLog::query()->where('rfid_uid', $uid)->delete();
        } catch (\Throwable) {
        }

        parent::tearDown();
    }

    public function test_unknown_uid_logs_without_creating_user(): void
    {
        $result = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');

        $this->assertFalse($result['granted']);
        $this->assertSame(RfidAccessService::STATUS_CARD_NOT_REGISTERED, $result['status']);
        $this->assertNull($result['user']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $this->assertNull(User::query()->where('rfid_uid', $uid)->first());
        $this->assertNull(User::query()->where('temp_rfid_uid', $uid)->first());

        $log = GateLog::query()->where('rfid_uid', $uid)->latest('timestamp')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertNull($log->visitor_id);
        $this->assertSame(RfidAccessService::STATUS_CARD_NOT_REGISTERED, $log->result);
        $this->assertSame('Entry', $log->action);
        $this->assertSame('GATE-IN-1', $log->gate_id);
    }

    public function test_unknown_exit_also_logs_null_user(): void
    {
        $result = $this->rfid->process(self::UID, 'GATE-OUT-1', 'Exit');

        $this->assertFalse($result['granted']);
        $uid = $this->rfid->normalizeUid(self::UID);
        $log = GateLog::query()->where('rfid_uid', $uid)->latest('timestamp')->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertSame('Exit', $log->action);
    }
}
