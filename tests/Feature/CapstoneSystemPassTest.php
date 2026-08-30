<?php

namespace Tests\Feature;

use App\Models\ParkingSlot;
use App\Models\User;
use App\Support\PasswordRules;
use App\Support\RegistrationCooldown;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CapstoneSystemPassTest extends TestCase
{
    private ?User $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin) {
            $this->markTestSkipped('Run php artisan db:seed — admin user not found.');
        }
    }

    public function test_register_page_shows_split_name_and_id_upload(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('Full Name')
            ->assertSee('School ID')
            ->assertSee('Profile Picture')
            ->assertSee('LTO Official Receipt (OR)')
            ->assertSee('Color')
            ->assertSee(PasswordRules::hint(), false);
    }

    public function test_declined_registration_sets_gate_denied_and_declined_at(): void
    {
        $pending = User::query()->create([
            'id' => (int) (microtime(true) * 1000) % 2000000000,
            'fullname' => 'Decline Test User',
            'email' => 'decline.test.'.uniqid().'@example.com',
            'password' => Hash::make('OldPass1!xx'),
            'user_role_id' => 3,
            'id_number' => 'DEC'.substr((string) time(), -6),
            'status' => User::STATUS_PENDING,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'strike_count' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.registrations.decline', ['id' => $pending->id]), [
                'remarks' => 'Incomplete documents',
                'decline_type' => 'final',
                'decline_category' => 'documents_illegible',
            ])
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(User::STATUS_DENIED, $pending->status);
        $this->assertSame(User::REGISTRATION_DENIED_FINAL, $pending->registrationState());
        $this->assertSame(User::GATE_ACCESS_DENIED, $pending->Gate_access);
        $this->assertNotNull($pending->declined_at);
        $this->assertSame('Incomplete documents', $pending->decline_remarks);
        $this->assertTrue(RegistrationCooldown::isWithinCooldown($pending));

        $pending->delete();
    }

    public function test_admin_can_approve_pending_registration_without_payment(): void
    {
        $pending = User::query()->create([
            'id' => (int) (microtime(true) * 1000) % 2000000000,
            'fullname' => 'Approve Unpaid User',
            'email' => 'approve.unpaid.'.uniqid().'@example.com',
            'password' => Hash::make('OldPass1!xx'),
            'user_role_id' => 3,
            'id_number' => 'APU'.substr((string) time(), -6),
            'status' => User::STATUS_PENDING,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'payment_status' => 'unpaid',
            'strike_count' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.registrations.approve', ['id' => $pending->id]))
            ->assertRedirect();

        $pending->refresh();
        $this->assertSame(User::STATUS_GRANTED, $pending->status);
        $this->assertSame(User::GATE_ACCESS_PENDING, $pending->Gate_access);

        $notice = \App\Models\Notification::query()
            ->where('user_id', $pending->id)
            ->where('title', 'Account Approved')
            ->orderByDesc('created_at')
            ->first();
        $this->assertNotNull($notice);
        $this->assertStringNotContainsString('You now have campus access.', (string) $notice->message);

        $pending->delete();
    }

    public function test_declined_registration_requires_remarks(): void
    {
        $pending = User::query()->create([
            'id' => (int) (microtime(true) * 1000) % 2000000000,
            'fullname' => 'Decline Empty Remarks',
            'email' => 'decline.empty.'.uniqid().'@example.com',
            'password' => Hash::make('OldPass1!xx'),
            'user_role_id' => 3,
            'id_number' => 'DEE'.substr((string) time(), -6),
            'status' => User::STATUS_PENDING,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'strike_count' => 0,
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.registrations'))
            ->post(route('admin.registrations.decline', ['id' => $pending->id]), [
                'remarks' => '  ',
                'decline_type' => 'final',
            ])
            ->assertRedirect(route('admin.registrations'))
            ->assertSessionHasErrors('remarks');

        $pending->refresh();
        $this->assertSame(User::STATUS_PENDING, $pending->status);
        $this->assertNull($pending->decline_remarks);

        $pending->delete();
    }

    public function test_registrations_page_uses_decline_modal(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.registrations'))
            ->assertOk()
            ->assertSee('decline-modal', false)
            ->assertSee('Confirm Decline', false)
            ->assertSee('Reason / remarks', false)
            ->assertSee('Decline type', false)
            ->assertSee('Remedial', false);
    }

    public function test_admin_can_update_slot_status(): void
    {
        $slot = ParkingSlot::query()->first();
        if (! $slot) {
            $this->markTestSkipped('No parking slots seeded.');
        }

        $original = $slot->status ?? 'Available';
        $target = $original === 'Maintenance' ? 'Available' : 'Maintenance';

        $this->actingAs($this->admin)
            ->postJson(route('admin.parking.slots.update'), [
                'slot_id' => $slot->id,
                'status' => $target,
            ])
            ->assertOk()
            ->assertJsonPath('slot.status', $target);

        $slot->refresh();
        $this->assertSame($target, $slot->status);

        $slot->update(['status' => $original]);
    }

    public function test_guard_ai_parking_monitor_loads(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first()
            ?? User::query()->where('user_role_id', 2)->first();
        if (! $guard) {
            $this->markTestSkipped('No guard user found.');
        }

        $this->actingAs($guard)
            ->get(route('guard.ai-parking'))
            ->assertOk()
            ->assertSee('AI Parking Monitor')
            ->assertDontSee('id="ai-stream"', false);
    }

    public function test_register_page_includes_cspc_branding_assets(): void
    {
        $response = $this->get(route('register'))->assertOk();

        if (is_file(public_path('images/cspc-logo.png'))) {
            $response->assertSee('images/cspc-logo.png', false);
        }

        if (is_file(public_path('images/cspc-campus-bg.png'))) {
            $response->assertSee('images/cspc-campus-bg.png', false);
        }
    }

    public function test_session_mismatch_redirects_to_login_with_friendly_message(): void
    {
        $request = \Illuminate\Http\Request::create('/login', 'POST', [
            'email' => 'admin@my.cspc.edu.ph',
            'password' => 'admin123',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new \Illuminate\Session\TokenMismatchException('CSRF token mismatch.'));

        $this->assertTrue($response->isRedirect(route('login')));
        $this->assertSame(
            'Session expired. Please sign in again.',
            $this->app['session.store']->get('error')
        );
    }

    public function test_hidden_zone_appears_as_maintenance_for_users(): void
    {
        $user = User::query()
            ->whereIn('user_role_id', [3, 4])
            ->where('status', User::STATUS_GRANTED)
            ->first();

        if (! $user) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        $user->update(['email_verified_at' => now()]);
        $roleName = $user->roleName();

        $area = \App\Models\ParkingArea::query()->orderBy('id')->get()
            ->first(fn ($a) => in_array($roleName, $a->getAllowedRoles(), true));

        if (! $area) {
            $this->markTestSkipped('No parking area allowed for user role.');
        }

        $original = $area->is_visible;
        $area->update(['is_visible' => false]);

        try {
            $html = $this->actingAs($user)
                ->get(route('user.parking'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString($area->area_name, $html);
            $this->assertStringContainsString('Maintenance', $html);

            $payload = $this->actingAs($user)
                ->getJson(route('user.parking.status'))
                ->assertOk()
                ->json();

            $zone = collect($payload['zones'] ?? [])->firstWhere('id', $area->id);
            $this->assertNotNull($zone);
            $this->assertSame(0, $zone['available'] ?? null);
            $this->assertTrue(collect($zone['slots'] ?? [])->every(fn ($s) => ($s['status'] ?? '') === 'Maintenance'));
        } finally {
            $area->update(['is_visible' => $original]);
        }
    }

    public function test_password_change_rejects_same_password(): void
    {
        $plain = 'TempPass1!xx';
        $user = User::query()->create([
            'id' => ((int) (microtime(true) * 1000) + 7) % 2000000000,
            'fullname' => 'Password Test User',
            'email' => 'pw.test.'.uniqid().'@example.com',
            'password' => Hash::make($plain),
            'user_role_id' => 3,
            'id_number' => 'PW'.substr((string) time(), -6),
            'status' => User::STATUS_GRANTED,
            'Gate_access' => User::GATE_ACCESS_GRANTED,
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('profile.update'), [
                'change_password' => '1',
                'current_password' => $plain,
                'new_password' => $plain,
                'new_password_confirmation' => $plain,
            ])
            ->assertSessionHasErrors('new_password');

        $user->delete();
    }
}
