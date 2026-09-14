<?php

namespace Tests\Feature;

use App\Models\ParkingArea;
use App\Models\User;
use Tests\TestCase;

class SystemSecurityTest extends TestCase
{
    private ?User $admin = null;

    private ?User $guard = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
            $this->guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin || ! $this->guard) {
            $this->markTestSkipped('Run php artisan db:seed — test users not found.');
        }

        foreach ([$this->admin, $this->guard] as $user) {
            if ($user && ! $user->hasVerifiedEmail()) {
                $user->update(['email_verified_at' => now()]);
            }
        }
    }

    public function test_guest_cannot_access_protected_portals(): void
    {
        $protected = [
            route('admin.dashboard'),
            route('admin.reports.export'),
            route('admin.reports.export-pdf'),
            route('admin.reports.export-excel'),
            route('admin.parking.zone-access'),
            route('admin.parking.layout'),
            route('guard.dashboard'),
            route('guard.gate'),
            route('user.dashboard'),
            route('user.policy'),
            route('profile.edit'),
        ];

        foreach ($protected as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_cross_role_access_is_denied(): void
    {
        $this->actingAs($this->admin)
            ->get(route('guard.dashboard'))
            ->assertRedirect(route('admin.dashboard'));

        $this->flushSession();

        $this->actingAs($this->guard)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('guard.dashboard'));
    }

    public function test_guard_cannot_modify_zone_access(): void
    {
        $zone = ParkingArea::query()->first();
        if (! $zone) {
            $this->markTestSkipped('No parking zones seeded.');
        }

        $this->actingAs($this->guard)
            ->get(route('admin.parking.zone-access'))
            ->assertRedirect(route('guard.dashboard'));

        $this->actingAs($this->guard)
            ->post(route('admin.parking.areas.update'), [
                'visible' => [$zone->id => '1'],
                'roles' => [$zone->id => ['Student']],
            ])
            ->assertRedirect(route('guard.dashboard'));
    }

    public function test_zone_access_rejects_visible_zone_without_roles(): void
    {
        $zone = ParkingArea::query()->first();
        if (! $zone) {
            $this->markTestSkipped('No parking zones seeded.');
        }

        $this->actingAs($this->admin)
            ->from(route('admin.parking.zone-access'))
            ->post(route('admin.parking.areas.update'), [
                'visible' => [$zone->id => '1'],
                'roles' => [],
            ])
            ->assertRedirect(route('admin.parking.zone-access'))
            ->assertSessionHasErrors("zone_{$zone->id}");
    }

    public function test_zone_access_page_does_not_show_user_assignment(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.parking.zone-access'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Assign user to this zone', $html);
        $this->assertStringNotContainsString('assignments[', $html);
    }

    public function test_unverified_user_is_sent_to_verification_notice(): void
    {
        $this->guard->update(['email_verified_at' => null]);

        $this->post(route('login'), [
            'email' => 'guard@my.cspc.edu.ph',
            'password' => 'password123',
        ])
            ->assertRedirect(route('verification.notice'));

        $this->assertAuthenticatedAs($this->guard);

        $this->get(route('guard.dashboard'))
            ->assertRedirect(route('verification.notice'));

        $this->guard->update(['email_verified_at' => now()]);
    }

    public function test_pending_user_cannot_access_portal(): void
    {
        $originalStatus = $this->guard->status;
        $this->guard->update(['status' => User::STATUS_PENDING]);

        $this->actingAs($this->guard)
            ->get(route('guard.dashboard'))
            ->assertRedirect(route('login'));

        $this->guard->update(['status' => $originalStatus]);
    }

    public function test_admin_post_routes_require_authentication(): void
    {
        $this->post(route('admin.settings.general'), ['descriptions' => [1 => 'test']])
            ->assertRedirect(route('login'));

        $this->post(route('admin.parking.areas.update'), [])
            ->assertRedirect(route('login'));
    }

    public function test_login_rejects_empty_credentials(): void
    {
        $this->post(route('login'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_email_format(): void
    {
        $this->post(route('login'), [
            'email' => 'not-an-email',
            'password' => 'password123',
        ])->assertSessionHasErrors(['email']);
    }

    public function test_profile_update_cannot_escalate_role_or_gate_access(): void
    {
        $student = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->first();

        if (! $student) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        if (! $student->hasVerifiedEmail()) {
            $student->update(['email_verified_at' => now()]);
        }

        $originalRole = (int) $student->user_role_id;
        $originalStatus = (string) $student->status;
        $originalGate = (string) ($student->Gate_access ?? '');
        $originalRfid = (string) ($student->rfid_uid ?? '');

        $this->actingAs($student)
            ->post(route('profile.update'), [
                'update_profile' => '1',
                'fullname' => $student->fullname ?? $student->name ?? 'Test User',
                'phone_number' => $student->phone_number ?: '09171234567',
                'email' => $student->email,
                'address' => $student->address ?: 'TEST ADDRESS',
                'user_role_id' => 1,
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'rfid_uid' => 'DEADBEEF',
                'strike_count' => 0,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame($originalRole, (int) $student->user_role_id);
        $this->assertSame($originalStatus, (string) $student->status);
        $this->assertSame($originalGate, (string) ($student->Gate_access ?? ''));
        $this->assertSame($originalRfid, (string) ($student->rfid_uid ?? ''));
    }

    public function test_student_cannot_fetch_guard_violation_evidence_route(): void
    {
        $student = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->first();

        if (! $student) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        if (! $student->hasVerifiedEmail()) {
            $student->update(['email_verified_at' => now()]);
        }

        $log = \App\Models\ViolationLog::query()->orderByDesc('created_at')->first();
        if (! $log) {
            $this->markTestSkipped('No violation log found.');
        }

        $this->actingAs($student)
            ->get(route('guard.violations.evidence', ['id' => (string) $log->getKey(), 'index' => 0]))
            ->assertRedirect(route('user.dashboard'));
    }

    public function test_user_cannot_mark_another_users_notification_read(): void
    {
        $owner = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->where('Gate_access', User::GATE_ACCESS_GRANTED)
            ->where(function ($q) {
                $q->whereNull('strike_count')->orWhere('strike_count', '<', User::MAX_STRIKES);
            })
            ->orderBy('id')
            ->first();
        $other = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->where('Gate_access', User::GATE_ACCESS_GRANTED)
            ->where(function ($q) {
                $q->whereNull('strike_count')->orWhere('strike_count', '<', User::MAX_STRIKES);
            })
            ->where('id', '!=', $owner?->id)
            ->orderBy('id')
            ->first();

        if (! $owner || ! $other) {
            $this->markTestSkipped('Need two granted student/staff users.');
        }

        foreach ([$owner, $other] as $u) {
            if (! $u->hasVerifiedEmail()) {
                $u->update(['email_verified_at' => now()]);
            }
        }

        $owner = $owner->fresh();
        $other = $other->fresh();

        $notification = \App\Models\Notification::query()->create([
            'user_id' => (int) $owner->id,
            'title' => 'Security checkup notification',
            'message' => 'Owner-only notification',
            'type' => 'System',
            'is_read' => false,
            'created_at' => now(),
        ]);

        $this->assertNotNull($notification->id);

        // Foreign user scoped update must not touch the row.
        $foreign = \App\Models\Notification::query()
            ->where('user_id', (int) $other->id)
            ->where('id', (int) $notification->id)
            ->update(['is_read' => true]);
        $this->assertSame(0, $foreign);

        $this->flushSession();
        $this->actingAs($other)
            ->post(route('user.notifications.action', ['action' => 'mark_read']), [
                'id' => (int) $notification->id,
            ]);

        $notification->refresh();
        $this->assertFalse((bool) $notification->is_read, 'Foreign user must not mark another user notification read.');

        $this->flushSession();
        $this->actingAs($owner)
            ->post(route('user.notifications.action', ['action' => 'mark_read']), [
                'id' => (int) $notification->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.notifications'));

        $notification->refresh();
        $this->assertTrue((bool) $notification->is_read, 'Owner must be able to mark their own notification read.');

        \App\Models\Notification::query()->where('id', $notification->id)->delete();
    }
}
