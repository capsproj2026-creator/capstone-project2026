<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\RfidAccessService;
use App\Services\TemporaryRfidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TemporaryRfidAccessTest extends TestCase
{
    private const TOKEN = 'test-rfid-api-token';

    private const UID = 'CAFEF00D01';

    private RfidAccessService $rfid;

    /** @var list<User> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.rfid.api_token', self::TOKEN);
        Config::set('services.rfid.temp_access_enabled', true);
        Config::set('services.rfid.temp_access_hours', 5);
        Config::set('services.rfid.temp_access_max', 3);

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
        Carbon::setTestNow();

        foreach ($this->created as $user) {
            try {
                GateLog::query()->where('user_id', $user->id)->delete();
                $user->delete();
            } catch (\Throwable) {
            }
        }

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

    public function test_unknown_uid_grants_temporary_entry(): void
    {
        $result = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');

        $this->assertTrue($result['granted']);
        $this->assertSame(RfidAccessService::STATUS_GRANTED, $result['status']);
        $this->assertTrue($result['user']['is_temporary'] ?? false);
        $this->assertSame('Student / Faculty', $result['user']['role'] ?? null);
        $this->assertStringContainsString('Unregistered student/faculty', $result['message']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $user = User::query()->where('rfid_uid', $uid)->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isTemporaryAccount());
        $this->assertTrue($user->isCampusVehicleOwner());
        $this->assertSame(3, (int) $user->user_role_id);
        $this->assertSame(TemporaryRfidService::PLACEHOLDER_NAME, $user->fullname);
        $this->assertNull($user->email);
        $this->assertSame($uid, $user->temp_rfid_uid);
        $this->assertSame(1, (int) $user->temporary_sequence);
        $this->created[] = $user;

        $log = GateLog::query()->where('user_id', $user->id)->latest('timestamp')->first();
        $this->assertNotNull($log);
        $this->assertSame(RfidAccessService::STATUS_GRANTED, $log->result);
        $this->assertStringContainsString('Unregistered student/faculty', (string) $log->reason);
    }

    public function test_expired_temporary_access_is_denied(): void
    {
        $first = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertTrue($first['granted']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $user = User::query()->where('rfid_uid', $uid)->first();
        $this->assertNotNull($user);
        $this->created[] = $user;

        Carbon::setTestNow(now()->addHours(5)->addMinute());

        $expired = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertFalse($expired['granted']);
        $this->assertStringContainsString('expired', strtolower($expired['message']));

        $user->refresh();
        $this->assertNull($user->rfid_uid);
        $this->assertSame($uid, $user->temp_rfid_uid);
        $this->assertSame(User::GATE_ACCESS_DENIED, $user->Gate_access);
    }

    public function test_fourth_temporary_account_is_blocked(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $grant = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
            $this->assertTrue($grant['granted'], "temp grant {$i} should succeed");

            $uid = $this->rfid->normalizeUid(self::UID);
        $user = User::query()->where('rfid_uid', $uid)->first();
            $this->assertNotNull($user);
            $this->assertSame($i, (int) $user->temporary_sequence);
            $this->created[] = $user;

            Carbon::setTestNow(now()->addHours(5)->addMinute());
            $deny = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
            $this->assertFalse($deny['granted']);
            $user->refresh();
            $this->assertNull($user->rfid_uid);
            Carbon::setTestNow(now()->addMinute());
        }

        $blocked = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertFalse($blocked['granted']);
        $this->assertSame(TemporaryRfidService::LIMIT_MESSAGE, $blocked['message']);
        $this->assertNull(User::query()->where('rfid_uid', self::UID)->first());
    }

    public function test_conversion_to_full_account_keeps_rfid(): void
    {
        $grant = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertTrue($grant['granted']);
        $temp = User::query()->where('rfid_uid', self::UID)->first();
        $this->assertNotNull($temp);
        $this->created[] = $temp;

        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $this->get(route('register', ['temp' => $temp->temp_conversion_token]))
            ->assertOk()
            ->assertSee('Complete Registration')
            ->assertSee(self::UID, false);

        $converted = app(TemporaryRfidService::class)->convertToFull($temp, [
            'fullname' => 'Converted Temp User',
            'email' => 'temp.convert.'.uniqid().'@my.cspc.edu.ph',
            'phone_number' => '09171234567',
            'address' => 'ZONE 4 NABUA CAMARINES SUR',
            'password' => Hash::make('Passw0rd!'),
            'user_role_id' => 3,
            'department_code' => 'CCS',
            'vehicle_id' => (int) $vehicle->id,
            'vehicle_model' => 'Honda Click 125',
            'vehicle_color' => 'White',
            'plate_number' => 'TMP-1234',
            'id_number' => 'CONV'.strtoupper(substr(uniqid(), -6)),
            'driver_license_number' => 'N01-12-345678',
        ]);

        $this->assertFalse($converted->isTemporaryAccount());
        $this->assertSame(TemporaryRfidService::ACCOUNT_FULL, $converted->account_type);
        $this->assertSame(self::UID, $converted->rfid_uid);
        $this->assertSame('TMP-1234', $converted->plate_number);
        $this->assertSame('White', $converted->vehicle_color);
        $this->assertSame(User::STATUS_PENDING, $converted->status);
        $this->assertSame(User::GATE_ACCESS_PENDING, $converted->Gate_access);
        $this->assertNull($converted->temp_conversion_token);
    }

    public function test_conversion_after_expiry_restores_uid_and_keeps_gate_logs(): void
    {
        $grant = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertTrue($grant['granted']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $temp = User::query()->where('rfid_uid', $uid)->first();
        $this->assertNotNull($temp);
        $this->created[] = $temp;

        $logCount = GateLog::query()->where('user_id', $temp->id)->count();
        $this->assertGreaterThan(0, $logCount);

        Carbon::setTestNow(now()->addHours(5)->addMinute());
        $expired = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertFalse($expired['granted']);

        $temp->refresh();
        $this->assertNull($temp->rfid_uid);
        $this->assertSame($uid, $temp->temp_rfid_uid);

        $this->get(route('register', ['temp' => $temp->temp_conversion_token]))
            ->assertOk()
            ->assertSee($uid, false);

        $vehicle = Vehicle::query()->orderBy('id')->first();
        if (! $vehicle) {
            $this->markTestSkipped('No vehicle types seeded.');
        }

        $converted = app(TemporaryRfidService::class)->convertToFull($temp, [
            'fullname' => 'Converted After Expiry',
            'email' => 'temp.expiry.'.uniqid().'@my.cspc.edu.ph',
            'phone_number' => '09171234567',
            'password' => Hash::make('Passw0rd!'),
            'user_role_id' => 3,
            'id_number' => 'CEX'.strtoupper(substr(uniqid(), -6)),
        ]);

        $this->assertSame($uid, $converted->rfid_uid);
        $this->assertSame($uid, $converted->temp_rfid_uid);
        $this->assertGreaterThanOrEqual($logCount, GateLog::query()->where('user_id', $converted->id)->count());
        $this->assertNotNull(
            GateLog::query()
                ->where('user_id', $converted->id)
                ->where('action', 'Entry')
                ->where('result', RfidAccessService::STATUS_GRANTED)
                ->first()
        );
        $this->assertFalse($converted->isTemporaryAccount());
    }

    public function test_converting_a_temp_account_frees_a_slot_for_the_same_card(): void
    {
        $grant = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertTrue($grant['granted']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $temp = User::query()->where('rfid_uid', $uid)->first();
        $this->assertNotNull($temp);
        $this->created[] = $temp;

        $converted = app(TemporaryRfidService::class)->convertToFull($temp, [
            'fullname' => 'Converted Slot Free',
            'email' => 'temp.slot.'.uniqid().'@my.cspc.edu.ph',
            'password' => Hash::make('Passw0rd!'),
            'user_role_id' => 3,
            'id_number' => 'CSF'.strtoupper(substr(uniqid(), -6)),
        ]);

        $this->assertSame(TemporaryRfidService::ACCOUNT_FULL, $converted->account_type);

        $temps = app(TemporaryRfidService::class);
        $this->assertSame(0, $temps->countForIdentity($temps->identityKeyForUid($uid)));
        $this->assertSame(3, $temps->remainingForIdentity($temps->identityKeyForUid($uid)));
    }

    public function test_unregistered_placeholder_is_student_faculty_and_cannot_be_approved(): void
    {
        $admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        if (! $admin) {
            $this->markTestSkipped('Admin user not seeded.');
        }
        if (! $admin->hasVerifiedEmail()) {
            $admin->update(['email_verified_at' => now()]);
        }

        $grant = $this->rfid->process(self::UID, 'GATE-IN-1', 'Entry');
        $this->assertTrue($grant['granted']);

        $uid = $this->rfid->normalizeUid(self::UID);
        $temp = User::query()->where('rfid_uid', $uid)->first();
        $this->assertNotNull($temp);
        $this->created[] = $temp;

        $this->actingAs($admin)
            ->get(route('admin.registrations', ['status' => User::STATUS_PENDING]))
            ->assertOk()
            ->assertSee('Not registered yet', false)
            ->assertSee(TemporaryRfidService::PLACEHOLDER_NAME, false)
            ->assertDontSee('@temp.smartcampus.invalid', false);

        $this->actingAs($admin)
            ->post(route('admin.registrations.approve', ['id' => $temp->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $temp->refresh();
        $this->assertTrue($temp->isTemporaryAccount());
        $this->assertSame(User::STATUS_PENDING, $temp->status);
        $this->assertSame(User::GATE_ACCESS_GRANTED, $temp->Gate_access);
    }
}
