<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\Notification;
use App\Models\OvernightParkingRequest;
use App\Models\SanctionEndorsement;
use App\Models\StalledVehicleReport;
use App\Models\User;
use App\Models\UserSuspension;
use App\Models\ViolationLog;
use App\Services\GateLogService;
use App\Services\OvernightParkingService;
use App\Services\RfidAccessService;
use App\Services\ViolationEnforcementService;
use App\Support\OffenseStatus;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class ParkingPolicyWorkflowTest extends TestCase
{
    private ?User $owner = null;

    private ?User $admin = null;

    private ?User $guard = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->admin = User::query()->where('user_role_id', 1)->first();
            $this->guard = User::query()->where('user_role_id', 2)->first();

            $this->owner = User::query()->create([
                'fullname' => 'Policy Workflow Owner',
                'email' => 'policy.flow.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => 4,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'PWF'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'PWF'.random_int(1000, 9999),
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'rfid_uid' => 'AB'.strtoupper(bin2hex(random_bytes(4))),
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postForm(User $as, string $url, array $data = []): \Illuminate\Testing\TestResponse
    {
        $this->flushSession();

        return $this->actingAs($as)
            ->withSession(['_token' => 'test-token'])
            ->post($url, $data + ['_token' => 'test-token']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        if ($this->owner) {
            $id = $this->owner->id;
            ViolationLog::query()->where('user_id', $id)->delete();
            SanctionEndorsement::query()->where('user_id', $id)->delete();
            UserSuspension::query()->where('user_id', $id)->delete();
            OvernightParkingRequest::query()->where('user_id', $id)->delete();
            StalledVehicleReport::query()->where('plate_number', $this->owner->plate_number)->delete();
            GateLog::query()->where('user_id', $id)->delete();
            Notification::query()->where('user_id', $id)->delete();
            $this->owner->delete();
        }

        parent::tearDown();
    }

    private function cite(int $times): int
    {
        $service = app(ViolationEnforcementService::class);
        $strikes = 0;
        for ($i = 0; $i < $times; $i++) {
            $log = ViolationLog::query()->create([
                'user_id' => $this->owner->id,
                'violator_name' => $this->owner->fullname,
                'plate_number' => $this->owner->plate_number,
                'violation_type' => 'Illegal Parking',
                'violation_types' => ['Illegal Parking'],
                'guard_id' => '2',
                'status' => 'Active',
                'created_at' => now(),
            ]);
            $strikes = $service->syncStrikesFromLogs($this->owner, $log, '2');
        }
        $this->owner->refresh();
        $this->owner->forgetParkingSanction();

        return $strikes;
    }

    private function endorsement(int $level): ?SanctionEndorsement
    {
        return SanctionEndorsement::query()
            ->where('user_id', $this->owner->id)
            ->where('offense_level', $level)
            ->orderByDesc('created_at')
            ->first();
    }

    public function test_first_offense_is_warning_only(): void
    {
        $this->assertSame(1, $this->cite(1));

        $this->assertNull($this->endorsement(2));
        $this->assertNull($this->owner->parkingSanctionReason());
        $this->assertFalse($this->owner->isLocked());
        $this->assertSame('1st Offense — warning ticket', OffenseStatus::for($this->owner)['label']);
    }

    public function test_second_offense_waits_for_gsu_then_suspends_six_months(): void
    {
        $this->cite(2);

        $endorsement = $this->endorsement(2);
        $this->assertNotNull($endorsement);
        $this->assertSame(SanctionEndorsement::STATUS_PENDING_GSU, $endorsement->status);
        $this->assertNull($this->owner->parkingSanctionReason(), 'Nothing changes until the GSU approves.');

        app(ViolationEnforcementService::class)->gsuDecideSecondOffense($endorsement, true, (int) ($this->admin?->id ?? 1), 'Verified');

        $this->owner->forgetParkingSanction();
        $sanction = $this->owner->activeParkingSanction();
        $this->assertNotNull($sanction);
        $this->assertSame(UserSuspension::KIND_SUSPENSION, $sanction->kind);
        $this->assertEqualsWithDelta(now()->addMonthsNoOverflow(6)->timestamp, $sanction->suspended_until->timestamp, 120);
        $this->assertStringContainsString('suspended until', (string) $this->owner->parkingSanctionReason());
        $this->assertFalse($this->owner->isLocked(), 'Portal login stays open; only the vehicle is blocked.');

        $this->expectException(InvalidArgumentException::class);
        app(ViolationEnforcementService::class)->gsuDecideSecondOffense($endorsement->fresh(), true, 1, null);
    }

    public function test_suspended_vehicle_is_denied_entry_but_can_exit(): void
    {
        $this->cite(2);
        app(ViolationEnforcementService::class)->gsuDecideSecondOffense($this->endorsement(2), true, 1, null);

        $entry = app(RfidAccessService::class)->process($this->owner->rfid_uid, 'GATE-IN-1', 'Entry');
        $this->assertFalse($entry['granted']);
        $this->assertStringContainsString('suspended', strtolower((string) ($entry['message'] ?? '')));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('suspended');
        app(GateLogService::class)->recordByPlate($this->owner->plate_number, 'Entry');
    }

    public function test_third_offense_goes_gsu_then_vpaf_then_revokes(): void
    {
        $this->cite(3);
        $service = app(ViolationEnforcementService::class);

        $third = $this->endorsement(3);
        $this->assertNotNull($third);
        $this->assertSame(SanctionEndorsement::STATUS_PENDING_GSU, $third->status);
        $this->assertFalse($this->owner->isLocked(), 'No automatic lock on the 3rd offense.');

        $service->gsuDecideThirdOffense($third, true, 1, 'Verified');
        $third->refresh();
        $this->assertSame(SanctionEndorsement::STATUS_PENDING_VPAF, $third->status);
        $this->owner->forgetParkingSanction();
        $this->assertNull($this->owner->activeParkingSanction());

        $service->vpafDecideThirdOffense($third, true, 1, 'VPAF memo 12');
        $this->owner->forgetParkingSanction();
        $this->assertTrue($this->owner->activeParkingSanction()?->isRevocation());
        $this->assertSame('Parking privileges revoked', OffenseStatus::for($this->owner)['label']);
    }

    public function test_removing_citations_withdraws_endorsements_and_lifts_sanction(): void
    {
        $this->cite(2);
        app(ViolationEnforcementService::class)->gsuDecideSecondOffense($this->endorsement(2), true, 1, null);

        ViolationLog::query()->where('user_id', $this->owner->id)->delete();
        app(ViolationEnforcementService::class)->syncStrikesFromLogs($this->owner);
        $this->owner->forgetParkingSanction();

        $this->assertSame(SanctionEndorsement::STATUS_WITHDRAWN, $this->endorsement(2)->status);
        $this->assertNull($this->owner->activeParkingSanction());
    }

    public function test_admin_endorsement_page_and_decision(): void
    {
        if (! $this->admin) {
            $this->markTestSkipped('Admin user not found.');
        }

        $this->cite(2);
        $endorsement = $this->endorsement(2);

        $this->actingAs($this->admin)
            ->get(route('admin.endorsements'))
            ->assertOk()
            ->assertSee('Policy Workflow Owner')
            ->assertSee('Approve 6-month suspension');

        $this->postForm($this->admin, route('admin.endorsements.decide', $endorsement->id), ['decision' => 'reject', 'remarks' => 'First time at GSU'])
            ->assertRedirect();

        $this->assertSame(SanctionEndorsement::STATUS_REJECTED, $endorsement->fresh()->status);
        $this->owner->forgetParkingSanction();
        $this->assertNull($this->owner->activeParkingSanction());
    }

    public function test_overnight_night_boundaries(): void
    {
        $service = app(OvernightParkingService::class);

        $this->assertSame('2026-10-09', $service->currentNight(Carbon::parse('2026-10-10 03:00', 'Asia/Manila')));
        $this->assertSame('2026-10-10', $service->currentNight(Carbon::parse('2026-10-10 05:00', 'Asia/Manila')));
        $this->assertTrue($service->isOvernightWindow(Carbon::parse('2026-10-10 22:00', 'Asia/Manila')));
        $this->assertFalse($service->isOvernightWindow(Carbon::parse('2026-10-10 21:59', 'Asia/Manila')));
    }

    public function test_staff_overnight_request_and_overnight_check(): void
    {
        $service = app(OvernightParkingService::class);
        $tonight = $service->currentNight();

        $this->postForm($this->owner, route('user.overnight-parking.store'), [
            'first_night' => $tonight,
            'reason' => 'Official travel to Manila',
        ])->assertRedirect(route('user.overnight-parking'));

        $request = OvernightParkingRequest::query()->where('user_id', $this->owner->id)->first();
        $this->assertSame(OvernightParkingRequest::STATUS_PENDING, $request->status);

        GateLog::query()->create([
            'user_id' => $this->owner->id,
            'action' => 'Entry',
            'gate_id' => 'GATE-IN-1',
            'result' => RfidAccessService::STATUS_GRANTED,
            'timestamp' => now(),
            'log_date' => now()->toDateString(),
        ]);

        $check = $service->overnightCheck();
        $this->assertTrue($check['unapproved']->contains(fn ($row) => (int) $row['user']->id === (int) $this->owner->id));

        if ($this->admin) {
            $this->postForm($this->admin, route('admin.overnight-parking.decide', $request->id), ['decision' => 'approve'])
                ->assertRedirect();
        } else {
            $request->update(['status' => OvernightParkingRequest::STATUS_APPROVED]);
        }
        $this->assertSame(OvernightParkingRequest::STATUS_APPROVED, $request->fresh()->status);

        $check = $service->overnightCheck();
        $this->assertTrue($check['approved']->contains(fn ($row) => (int) $row['user']->id === (int) $this->owner->id));
        $this->assertFalse($check['unapproved']->contains(fn ($row) => (int) $row['user']->id === (int) $this->owner->id));
    }

    public function test_students_cannot_request_overnight_parking(): void
    {
        $this->owner->update(['user_role_id' => 3]);

        $this->actingAs($this->owner->fresh())
            ->get(route('user.overnight-parking'))
            ->assertForbidden();
    }

    public function test_new_and_updated_pages_render(): void
    {
        $this->cite(2);

        if ($this->admin) {
            $this->actingAs($this->admin)->get(route('admin.violations'))->assertOk()->assertSee('Offense Ladder Overview');
            $this->actingAs($this->admin)->get(route('admin.overnight-parking'))->assertOk()->assertSee('Employee requests');
            $this->actingAs($this->admin)->get(route('admin.stalled-vehicles'))->assertOk()->assertSee('Report a stalled vehicle');
            $this->actingAs($this->admin)->get(route('admin.endorsements', ['tab' => 'decided']))->assertOk();
            $this->actingAs($this->admin)->get(route('admin.settings', ['section' => 'general']))->assertOk()->assertSee('Endorse 2nd and 3rd Offenses to the GSU');
            $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk();
        }

        if ($this->guard) {
            $this->flushSession();
            $this->actingAs($this->guard)->get(route('guard.overnight-check'))->assertOk()->assertSee('Overnight check')->assertDontSee('Employee requests');
            $this->actingAs($this->guard)->get(route('guard.violations'))->assertOk();
        }

        $this->flushSession();
        $this->actingAs($this->owner)->get(route('user.violations'))->assertOk()->assertSee('2nd Offense');
        $this->actingAs($this->owner)->get(route('user.overnight-parking'))->assertOk()->assertSee('Send to GSU');
        $this->actingAs($this->owner)->get(route('user.dashboard'))->assertOk()->assertSee('Overnight Parking');
    }

    public function test_stalled_vehicle_stages_and_guard_flow(): void
    {
        $report = new StalledVehicleReport(['status' => StalledVehicleReport::STATUS_ACTIVE]);
        $report->reported_at = Carbon::parse('2026-10-10 08:00');

        $this->assertSame('grace', $report->stage(Carbon::parse('2026-10-10 19:59')));
        $this->assertSame('tow', $report->stage(Carbon::parse('2026-10-10 20:00')));
        $this->assertSame('tow', $report->stage(Carbon::parse('2026-10-10 22:59')));
        $this->assertSame('overdue', $report->stage(Carbon::parse('2026-10-10 23:00')));

        if (! $this->guard) {
            $this->markTestSkipped('Guard user not found.');
        }

        $this->postForm($this->guard, route('guard.stalled-vehicles.store'), [
            'plate_number' => $this->owner->plate_number,
            'location' => 'Duran Hall parking, slot 3',
            'cause' => StalledVehicleReport::CAUSE_KEY,
        ])->assertRedirect(route('guard.stalled-vehicles'));

        $saved = StalledVehicleReport::query()->where('plate_number', $this->owner->plate_number)->first();
        $this->assertNotNull($saved);
        $this->assertSame((int) $this->owner->id, (int) $saved->user_id);
        $this->assertSame('Lost or broken key', $saved->causeLabel());
        $this->assertTrue(Notification::query()->where('user_id', $this->owner->id)->where('title', 'Stalled Vehicle Reported')->exists());

        $this->flushSession();
        $this->actingAs($this->guard)
            ->get(route('guard.stalled-vehicles'))
            ->assertOk()
            ->assertSee($this->owner->plate_number)
            ->assertSee('Grace period');

        $this->postForm($this->guard, route('guard.stalled-vehicles.removed', $saved->id), ['removal_notes' => 'Spare key delivered'])
            ->assertRedirect();
        $this->assertSame(StalledVehicleReport::STATUS_REMOVED, $saved->fresh()->status);
    }
}
