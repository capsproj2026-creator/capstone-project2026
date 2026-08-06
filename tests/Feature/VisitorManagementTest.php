<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorRfidCard;
use App\Services\VisitorService;
use App\Support\PlateLookup;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VisitorManagementTest extends TestCase
{
    private const TOKEN = 'test-rfid-api-token';

    private const VISITOR_UID = 'AABB1122CC';

    private ?User $admin = null;

    private ?User $guardUser = null;

    private ?User $student = null;

    /** @var list<int> */
    private array $visitorIds = [];

    /** @var list<int> */
    private array $cardIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.rfid.api_token', self::TOKEN);

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
            $this->guardUser = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
            $this->student = User::query()
                ->where('user_role_id', 3)
                ->where('status', User::STATUS_GRANTED)
                ->first();

            VisitorRfidCard::query()->where('rfid_uid', self::VISITOR_UID)->delete();
            Visitor::query()->where('rfid_uid', self::VISITOR_UID)->delete();
            GateLog::query()->where('rfid_uid', self::VISITOR_UID)->delete();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin || ! $this->guardUser) {
            $this->markTestSkipped('Seeded admin/guard users required.');
        }

        foreach ([$this->admin, $this->guardUser, $this->student] as $user) {
            if ($user && ! $user->hasVerifiedEmail()) {
                $user->update(['email_verified_at' => now()]);
            }
            if ($user && $user->status !== User::STATUS_GRANTED) {
                $user->update(['status' => User::STATUS_GRANTED]);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->visitorIds as $id) {
            GateLog::query()->where('visitor_id', $id)->delete();
            Visitor::query()->where('id', $id)->delete();
        }
        foreach ($this->cardIds as $id) {
            VisitorRfidCard::query()->where('id', $id)->delete();
        }
        VisitorRfidCard::query()->where('rfid_uid', self::VISITOR_UID)->delete();
        GateLog::query()->where('rfid_uid', self::VISITOR_UID)->delete();

        parent::tearDown();
    }

    public function test_admin_and_guard_can_open_visitor_pages(): void
    {
        $this->flushSession();
        $this->actingAs($this->admin->fresh())
            ->get(route('admin.visitors.active'))
            ->assertOk()
            ->assertSee('Registered Visitors', false);
        $this->actingAs($this->admin->fresh())
            ->get(route('admin.visitors.history'))
            ->assertOk()
            ->assertSee('Visitor History', false);
        $this->actingAs($this->admin->fresh())
            ->get('/admin/visitors/register')
            ->assertNotFound();

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->get(route('guard.visitors.register'))
            ->assertOk()
            ->assertSee('Register Visitor', false);
        $this->actingAs($this->guardUser->fresh())
            ->get(route('guard.visitors.active'))
            ->assertOk()
            ->assertSee('Active Visitors', false);
        $this->actingAs($this->guardUser->fresh())
            ->get(route('guard.visitors.history'))
            ->assertOk()
            ->assertSee('Visitor History', false);
    }

    public function test_student_cannot_access_visitor_routes(): void
    {
        if (! $this->student) {
            $this->markTestSkipped('No granted student available.');
        }

        $this->actingAs($this->student)
            ->get(route('admin.visitors.active'))
            ->assertRedirect();
    }

    public function test_register_assign_entry_exit_and_reuse_rfid(): void
    {
        $exitAt = now()->addHours(4)->format('Y-m-d\TH:i');

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->post(route('guard.visitors.store'), [
                'first_name' => 'Test',
                'last_name' => 'Visitor',
                'middle_name' => '',
                'contact_number' => '09171234567',
                'email' => null,
                'purpose' => 'Meeting',
                'office_to_visit' => 'CCS Dean',
                'expected_exit_at' => $exitAt,
                'plate_number' => 'VIS-'.random_int(100, 999),
                'vehicle_id' => 1,
                'vehicle_color' => 'Black',
                'rfid_uid' => self::VISITOR_UID,
            ])
            ->assertRedirect(route('guard.visitors.active'));

        $visitor = Visitor::query()->where('rfid_uid', self::VISITOR_UID)->orderByDesc('id')->first();
        $this->assertNotNull($visitor);
        $this->visitorIds[] = (int) $visitor->id;
        $this->assertSame(Visitor::STATUS_WAITING, $visitor->status);

        $card = VisitorRfidCard::query()->where('rfid_uid', self::VISITOR_UID)->first();
        $this->assertNotNull($card);
        $this->cardIds[] = (int) $card->id;
        $this->assertSame(VisitorRfidCard::STATUS_ASSIGNED, $card->status);

        $entry = $this->postJson('/api/rfid/scan', [
            'uid' => self::VISITOR_UID,
            'gate_id' => 'GATE-IN-V',
            'direction' => 'Entry',
        ], ['X-RFID-TOKEN' => self::TOKEN]);

        $entry->assertOk()->assertJsonPath('code', 'access_granted');
        $visitor->refresh();
        $this->assertSame(Visitor::STATUS_INSIDE, $visitor->status);
        $this->assertNotNull(GateLog::query()->where('visitor_id', $visitor->id)->where('action', 'Entry')->first());

        $this->postJson('/api/rfid/scan', [
            'uid' => self::VISITOR_UID,
            'gate_id' => 'GATE-IN-V',
            'direction' => 'Entry',
        ], ['X-RFID-TOKEN' => self::TOKEN])
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_inside');

        $this->postJson('/api/rfid/scan', [
            'uid' => self::VISITOR_UID,
            'gate_id' => 'GATE-OUT-V',
            'direction' => 'Exit',
        ], ['X-RFID-TOKEN' => self::TOKEN])
            ->assertOk()
            ->assertJsonPath('code', 'access_granted');

        $visitor->refresh();
        $card->refresh();
        $this->assertSame(Visitor::STATUS_COMPLETED, $visitor->status);
        $this->assertSame(VisitorRfidCard::STATUS_AVAILABLE, $card->status);
        $this->assertSame(self::VISITOR_UID, $visitor->rfid_uid);

        // Reuse UID on a new visitor
        $visitor2 = app(VisitorService::class)->register([
            'first_name' => 'Second',
            'last_name' => 'Guest',
            'contact_number' => '09170000000',
            'purpose' => 'Delivery',
            'office_to_visit' => 'Supply',
            'expected_exit_at' => now()->addHours(2),
            'plate_number' => 'VIS-'.random_int(1000, 9999),
            'vehicle_id' => 1,
            'vehicle_color' => 'White',
            'rfid_uid' => self::VISITOR_UID,
        ], $this->admin);

        $this->visitorIds[] = (int) $visitor2->id;
        $this->assertSame(self::VISITOR_UID, $visitor2->rfid_uid);
        $card->refresh();
        $this->assertSame(VisitorRfidCard::STATUS_ASSIGNED, $card->status);
        $this->assertSame((int) $visitor2->id, (int) $card->visitor_id);
    }

    public function test_expired_visitor_is_denied_at_gate(): void
    {
        $visitor = app(VisitorService::class)->register([
            'first_name' => 'Late',
            'last_name' => 'Guest',
            'contact_number' => '09171112222',
            'purpose' => 'Errand',
            'office_to_visit' => 'Registrar',
            'expected_exit_at' => now()->addHour(),
            'plate_number' => 'EXP'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Red',
            'rfid_uid' => self::VISITOR_UID,
        ], $this->guardUser);

        $this->visitorIds[] = (int) $visitor->id;
        $card = VisitorRfidCard::query()->where('rfid_uid', self::VISITOR_UID)->first();
        $this->cardIds[] = (int) $card->id;

        $visitor->update(['expected_exit_at' => now()->subMinute()]);
        $card->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/rfid/scan', [
            'uid' => self::VISITOR_UID,
            'gate_id' => 'GATE-IN-V',
            'direction' => 'Entry',
        ], ['X-RFID-TOKEN' => self::TOKEN])
            ->assertForbidden()
            ->assertJsonPath('code', 'access_denied');

        $visitor->refresh();
        $this->assertSame(Visitor::STATUS_EXPIRED, $visitor->status);
    }

    public function test_plate_lookup_prefers_user_then_visitor(): void
    {
        $plate = 'PLK'.random_int(100, 999);

        if ($this->student) {
            $original = $this->student->plate_number;
            $this->student->update(['plate_number' => $plate]);
            PlateLookup::forgetIndex();

            $identity = PlateLookup::identity($plate);
            $this->assertTrue($identity['registered']);
            $this->assertFalse($identity['is_visitor']);
            $this->assertSame($this->student->id, $identity['user_id']);

            $this->student->update(['plate_number' => $original]);
            PlateLookup::forgetIndex();
        }

        $visitor = app(VisitorService::class)->register([
            'first_name' => 'Plate',
            'last_name' => 'Match',
            'contact_number' => '09173334444',
            'purpose' => 'Interview',
            'office_to_visit' => 'HR',
            'expected_exit_at' => now()->addHours(3),
            'plate_number' => $plate,
            'vehicle_id' => 1,
            'vehicle_color' => 'Blue',
        ], $this->admin);

        $this->visitorIds[] = (int) $visitor->id;

        $identity = PlateLookup::identity($plate);
        $this->assertTrue($identity['registered']);
        $this->assertTrue($identity['is_visitor']);
        $this->assertSame('Visitor', $identity['role']);
        $this->assertSame($visitor->id, $identity['visitor_id']);
    }

    public function test_assign_rejects_user_owned_uid(): void
    {
        $uid = 'USEROWNED1';
        $owner = User::query()->whereNotNull('rfid_uid')->where('rfid_uid', '!=', '')->first();
        if (! $owner) {
            $this->markTestSkipped('No user with RFID UID available.');
        }

        $visitor = app(VisitorService::class)->register([
            'first_name' => 'Conflict',
            'last_name' => 'Case',
            'contact_number' => '09175556666',
            'purpose' => 'Test',
            'office_to_visit' => 'IT',
            'expected_exit_at' => now()->addHours(1),
            'plate_number' => 'CNF'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Gray',
        ], $this->admin);
        $this->visitorIds[] = (int) $visitor->id;

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->post(route('guard.visitors.assign-rfid', $visitor->id), [
                'rfid_uid' => $owner->rfid_uid,
            ])
            ->assertSessionHasErrors('rfid_uid');
    }

    public function test_expire_command_marks_overdue_visitors(): void
    {
        $visitor = app(VisitorService::class)->register([
            'first_name' => 'Cron',
            'last_name' => 'Expire',
            'contact_number' => '09176667777',
            'purpose' => 'Overstay',
            'office_to_visit' => 'Library',
            'expected_exit_at' => now()->addHours(2),
            'plate_number' => 'CRN'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Green',
            'rfid_uid' => self::VISITOR_UID,
        ], $this->admin);
        $this->visitorIds[] = (int) $visitor->id;
        $card = VisitorRfidCard::query()->where('rfid_uid', self::VISITOR_UID)->first();
        $this->cardIds[] = (int) $card->id;

        $visitor->update(['expected_exit_at' => now()->subMinutes(5)]);

        $this->artisan('visitors:expire')->assertSuccessful();

        $visitor->refresh();
        $this->assertSame(Visitor::STATUS_EXPIRED, $visitor->status);
    }
}
