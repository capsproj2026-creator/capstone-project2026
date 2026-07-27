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
            route('guard.dashboard'),
            route('guard.gate'),
            route('user.dashboard'),
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
}
