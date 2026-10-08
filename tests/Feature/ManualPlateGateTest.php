<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Services\GateLogService;
use App\Services\RfidAccessService;
use InvalidArgumentException;
use Tests\TestCase;

class ManualPlateGateTest extends TestCase
{
    private ?User $owner = null;

    private ?User $guard = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
                ?? User::query()->where('user_role_id', 2)->first();

            $this->owner = User::query()->create([
                'fullname' => 'Manual Plate Owner',
                'email' => 'manual.plate.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'MPL'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'MPL'.random_int(1000, 9999),
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'rfid_uid' => 'MP'.strtoupper(bin2hex(random_bytes(4))),
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

        parent::tearDown();
    }

    public function test_manual_plate_entry_is_saved_on_the_access_log(): void
    {
        $result = app(GateLogService::class)->recordByPlate($this->owner->plate_number, 'Entry');

        $this->assertSame('Entry', $result['action']);
        $this->assertSame('GATE-IN-1', $result['log']->gate_id);
        $this->assertSame(RfidAccessService::STATUS_GRANTED, $result['log']->fresh()->result);
        $this->assertSame('Manual plate Entry — RFID unavailable', $result['log']->fresh()->reason);
        $this->assertSame($this->owner->id, $result['log']->user_id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already inside');
        app(GateLogService::class)->recordByPlate($this->owner->plate_number, 'Entry');
    }

    public function test_unknown_plate_is_rejected_without_a_granted_log(): void
    {
        $before = GateLog::query()->where('rfid_uid', 'MANUAL-PLATE')->count();

        try {
            app(GateLogService::class)->recordByPlate('ZZZ0000', 'Exit');
            $this->fail('Unknown plate should be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('No registered vehicle', $e->getMessage());
        }

        $this->assertSame($before, GateLog::query()->where('rfid_uid', 'MANUAL-PLATE')->count());
    }

    public function test_guard_gate_page_shows_manual_plate_form(): void
    {
        if (! $this->guard) {
            $this->markTestSkipped('Guard user not found.');
        }

        $this->actingAs($this->guard)
            ->get(route('guard.gate'))
            ->assertOk()
            ->assertSee('Entry / exit when RFID is unavailable', false)
            ->assertSee('Record entry', false)
            ->assertSee('Record exit', false);
    }
}
