<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NavigationService;
use Tests\TestCase;

/**
 * Verifies navbar/sidebar shell + role navigation remain intact after UI redesign.
 */
class PortalShellNavTest extends TestCase
{
    private function seedUserOrSkip(string $email): User
    {
        try {
            $user = User::query()->where('email', $email)->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped("Seeded user missing: {$email}");
        }

        return $user;
    }

    public function test_admin_shell_shows_cspc_branding_and_nav_routes(): void
    {
        $admin = $this->seedUserOrSkip('admin@my.cspc.edu.ph');

        $html = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('portal-sidebar', false)
            ->assertSee('portal-menu-btn', false)
            ->assertSee('Smart Campus VMS')
            ->assertSee('CSPC')
            ->assertSee('Camarines Sur Polytechnic Colleges')
            ->getContent();

        $this->assertStringContainsString('id="portal-sidebar"', $html);
        $this->assertStringContainsString('id="portal-sidebar-nav"', $html);
        $this->assertStringContainsString('id="portal-menu-btn"', $html);
        $this->assertStringNotContainsString('id="portal-sidebar-edge-toggle"', $html);
        $this->assertStringContainsString('id="portal-overlay"', $html);
        $this->assertStringContainsString('id="portal-main"', $html);
        $this->assertStringContainsString('portal-sidebar-closed', $html);
        $this->assertStringContainsString('Show or hide sidebar', $html);
        $this->assertStringContainsString(route('admin.registrations'), $html);
        $this->assertStringContainsString(route('admin.violations'), $html);
        $this->assertStringContainsString(route('admin.settings'), $html);
        $this->assertStringContainsString('aria-current="page"', $html);

        foreach (NavigationService::routesForRole('Admin') as $item) {
            $this->assertStringContainsString($item['label'], $html);
            $this->assertStringContainsString(route($item['route']), $html);
        }
    }

    public function test_guard_shell_shows_nav_routes(): void
    {
        $guard = $this->seedUserOrSkip('guard@my.cspc.edu.ph');

        $html = $this->actingAs($guard)
            ->get(route('guard.dashboard'))
            ->assertOk()
            ->assertSee('Smart Campus VMS')
            ->assertSee('Access Control and Monitoring')
            ->assertDontSee('id="portal-sidebar-edge-toggle"', false)
            ->getContent();

        foreach (NavigationService::routesForRole('Guard') as $item) {
            $this->assertStringContainsString($item['label'], $html);
            $this->assertStringContainsString(route($item['route']), $html);
        }
    }

    public function test_user_shell_shows_nav_routes(): void
    {
        try {
            $user = User::query()
                ->whereIn('user_role_id', [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF])
                ->where('status', User::STATUS_GRANTED)
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        $user->update(['email_verified_at' => now()]);

        $html = $this->actingAs($user)
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Smart Campus VMS')
            ->assertSee('Vehicle and Parking Management')
            ->assertDontSee('id="portal-sidebar-edge-toggle"', false)
            ->getContent();

        $roleKey = strtolower($user->roleName());
        foreach (NavigationService::routesForRole($roleKey) as $item) {
            $this->assertStringContainsString($item['label'], $html);
            $this->assertStringContainsString(route($item['route']), $html);
        }
    }
}
