<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

/**
 * Focused QA for Admin / Guard portal flows.
 * Requires MongoDB with CapstoneSeeder data (admin@my.cspc.edu.ph, guard@my.cspc.edu.ph).
 */
class PortalFlowQaTest extends TestCase
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

    public function test_public_pages_load(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'admin@my.cspc.edu.ph',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_logout_clears_session_via_get_or_post(): void
    {
        $this->actingAs($this->admin)
            ->get(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();

        $this->actingAs($this->admin)
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
    }

    public function test_logout_with_invalid_csrf_never_shows_419(): void
    {
        $this->actingAs($this->admin);

        $request = \Illuminate\Http\Request::create('/logout', 'POST');
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $this->admin);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

        $this->assertTrue($response->isRedirect(route('login')));
        $this->assertSame(
            'Session expired. Please sign in again.',
            $this->app['session.store']->get('error')
        );
        $this->assertGuest();
    }

    public function test_admin_login_and_dashboard(): void
    {
        $this->post(route('login'), [
            'email' => 'admin@my.cspc.edu.ph',
            'password' => 'admin123',
        ])->assertRedirect(route('admin.dashboard'));

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_guard_login_and_dashboard(): void
    {
        $this->post(route('login'), [
            'email' => 'guard@my.cspc.edu.ph',
            'password' => 'password123',
        ])->assertRedirect(route('guard.dashboard'));

        $this->get(route('guard.dashboard'))->assertOk();
    }

    public function test_role_isolation_admin_cannot_access_guard_routes(): void
    {
        $this->actingAs($this->admin)
            ->get(route('guard.dashboard'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_role_isolation_guard_cannot_access_admin_routes(): void
    {
        $this->actingAs($this->guard)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('guard.dashboard'));
    }

    public function test_admin_portal_pages_load(): void
    {
        $admin = $this->admin;

        $this->actingAs($admin)->get(route('admin.registrations'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $this->actingAs($admin)->get(route('admin.rfid'))->assertOk();
        $this->actingAs($admin)->get(route('admin.parking'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.parking.zone-access'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reports.export'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.reports.export-pdf'))->assertOk();
        $this->actingAs($admin)->get(route('admin.violations'))->assertOk();
        $this->actingAs($admin)->get(route('admin.access-logs'))->assertOk();
        $this->actingAs($admin)->get(route('admin.reports'))->assertOk();
        $this->actingAs($admin)->get(route('admin.settings'))->assertOk();
        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    }

    public function test_guard_portal_pages_load(): void
    {
        $guard = $this->guard;

        $this->actingAs($guard)->get(route('guard.gate'))->assertOk();
        $this->actingAs($guard)->get(route('guard.monitor'))->assertOk();
        $this->actingAs($guard)->get(route('guard.violations'))->assertOk();
        $this->actingAs($guard)->get(route('guard.notifications'))->assertOk();
        $this->actingAs($guard)->get(route('guard.access-logs'))->assertOk();
        $this->actingAs($guard)->get(route('guard.parking'))->assertOk();
    }

    public function test_notification_action_rejects_unknown_action(): void
    {
        $this->actingAs($this->guard)
            ->post(route('guard.notifications.action', ['action' => 'hack']))
            ->assertNotFound();
    }

    public function test_guard_can_mark_all_notifications_read(): void
    {
        $this->actingAs($this->guard)
            ->post(route('guard.notifications.action', 'mark_all_read'))
            ->assertRedirect(route('guard.notifications'));
    }

    public function test_public_register_rejects_admin_role(): void
    {
        $this->post(route('register'), [
            'reg_category' => 'personnel',
            'system_role' => 'Admin',
            'fullname' => 'Fake Admin',
            'email' => 'fake-admin-qa@cspc.edu',
            'phone_number' => '09001112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'id_number' => 'FAKE-ADMIN-QA',
        ])->assertSessionHasErrors();
    }

    public function test_rfid_cannot_revive_denied_registration(): void
    {
        $uid = 'DEAD'.strtoupper(substr(uniqid(), -8));
        $user = \App\Models\User::query()->create([
            'fullname' => 'Denied RFID Test',
            'email' => 'denied.rfid.'.uniqid().'@my.cspc.edu.ph',
            'password' => bcrypt('password123'),
            'user_role_id' => 3,
            'department_code' => 'CCS',
            'vehicle_id' => 1,
            'id_number' => 'DEN'.strtoupper(substr(uniqid(), -5)),
            'plate_number' => 'DEN'.random_int(100, 999),
            'status' => \App\Models\User::STATUS_DENIED,
            'Gate_access' => \App\Models\User::GATE_ACCESS_DENIED,
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        try {
            $this->actingAs($this->admin)
                ->post(route('admin.rfid.approve', ['id' => $user->id]), [
                    'rfid_uid' => $uid,
                ])
                ->assertRedirect()
                ->assertSessionHas('error');

            $user->refresh();
            $this->assertSame(\App\Models\User::STATUS_DENIED, $user->status);
            $this->assertSame(\App\Models\User::GATE_ACCESS_DENIED, $user->Gate_access);
            $this->assertNull($user->rfid_uid);
        } finally {
            $user->delete();
        }
    }
}
