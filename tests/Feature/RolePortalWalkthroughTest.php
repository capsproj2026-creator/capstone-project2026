<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NavigationService;
use Tests\TestCase;

/**
 * HTTP walkthrough of every primary portal GET page for Admin, Guard, and User.
 * Asserts shell chrome (sidebar toggle, CSPC branding) and no server errors.
 */
class RolePortalWalkthroughTest extends TestCase
{
    private ?User $admin = null;

    private ?User $guard = null;

    private ?User $endUser = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
            $this->guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
            $this->endUser = User::query()
                ->whereIn('user_role_id', [NavigationService::ROLE_STUDENT, NavigationService::ROLE_STAFF])
                ->where('status', User::STATUS_GRANTED)
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin || ! $this->guard) {
            $this->markTestSkipped('Run php artisan db:seed — admin/guard test users not found.');
        }

        foreach ([$this->admin, $this->guard, $this->endUser] as $user) {
            if ($user && ! $user->hasVerifiedEmail()) {
                $user->update(['email_verified_at' => now()]);
            }
        }
    }

    /**
     * @return list<array{0: string, 1?: array<string, mixed>}>
     */
    private function adminPages(): array
    {
        return [
            ['admin.dashboard'],
            ['admin.registrations'],
            ['admin.users'],
            ['admin.rfid'],
            ['admin.visitors.active'],
            ['admin.visitors.history'],
            ['admin.parking'],
            ['admin.parking.zone-access'],
            ['admin.violations'],
            ['admin.access-logs'],
            ['admin.reports'],
            ['admin.settings'],
            ['admin.live-cameras'],
            ['admin.guards.create'],
            ['profile.edit'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: array<string, mixed>}>
     */
    private function guardPages(): array
    {
        return [
            ['guard.dashboard'],
            ['guard.gate'],
            ['guard.monitor'],
            ['guard.visitors.register'],
            ['guard.visitors.active'],
            ['guard.visitors.history'],
            ['guard.violations'],
            ['guard.notifications'],
            ['guard.access-logs'],
            ['guard.parking'],
            ['guard.ai-parking'],
            ['guard.plate-lookup'],
            ['guard.live-cameras'],
            ['profile.edit'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: array<string, mixed>}>
     */
    private function userPages(): array
    {
        return [
            ['user.dashboard'],
            ['user.notifications'],
            ['user.violations'],
            ['user.entry-exit'],
            ['user.parking'],
            ['profile.edit'],
        ];
    }

    private function assertPortalPageOk(User $user, string $routeName, array $params = []): void
    {
        $response = $this->actingAs($user)->get(route($routeName, $params));
        $status = $response->getStatusCode();

        $this->assertContains(
            $status,
            [200, 302],
            "Unexpected HTTP {$status} for {$routeName}"
        );

        if ($status === 302) {
            // JSON status endpoints may redirect if Accept is wrong; treat as soft pass only for status routes.
            $this->assertTrue(
                str_ends_with($routeName, '.status'),
                "Unexpected redirect for HTML page {$routeName} → ".$response->headers->get('Location')
            );

            return;
        }

        $html = $response->getContent();
        $this->assertStringNotContainsString('Whoops', $html);
        $this->assertStringNotContainsString('Illuminate\\', $html);
        $this->assertStringContainsString('id="portal-sidebar"', $html);
        $this->assertStringContainsString('id="portal-menu-btn"', $html);
        $this->assertTrue(
            str_contains($html, 'CSPC') || str_contains($html, 'cspc-logo'),
            "Missing CSPC branding on {$routeName}"
        );
    }

    public function test_admin_walkthrough_all_primary_pages(): void
    {
        foreach ($this->adminPages() as $page) {
            $this->assertPortalPageOk($this->admin, $page[0], $page[1] ?? []);
        }

        $this->actingAs($this->admin)
            ->getJson(route('admin.parking.status'))
            ->assertOk();
    }

    public function test_guard_walkthrough_all_primary_pages(): void
    {
        foreach ($this->guardPages() as $page) {
            $this->assertPortalPageOk($this->guard, $page[0], $page[1] ?? []);
        }

        $this->actingAs($this->guard)
            ->getJson(route('guard.parking.status'))
            ->assertOk();
    }

    public function test_user_walkthrough_all_primary_pages(): void
    {
        if (! $this->endUser) {
            $this->markTestSkipped('No granted student/staff user found.');
        }

        foreach ($this->userPages() as $page) {
            $this->assertPortalPageOk($this->endUser, $page[0], $page[1] ?? []);
        }
    }

    public function test_navigation_service_routes_match_walkthrough_coverage(): void
    {
        $adminRoutes = collect(NavigationService::routesForRole('Admin'))->pluck('route');
        $guardRoutes = collect(NavigationService::routesForRole('Guard'))->pluck('route');
        $userRoutes = collect(NavigationService::routesForRole('Student'))->pluck('route');

        $walkedAdmin = collect($this->adminPages())->pluck(0);
        $walkedGuard = collect($this->guardPages())->pluck(0);
        $walkedUser = collect($this->userPages())->pluck(0);

        foreach ($adminRoutes as $route) {
            $this->assertTrue(
                $walkedAdmin->contains($route),
                "Admin nav route {$route} missing from walkthrough"
            );
        }
        foreach ($guardRoutes as $route) {
            $this->assertTrue(
                $walkedGuard->contains($route),
                "Guard nav route {$route} missing from walkthrough"
            );
        }
        foreach ($userRoutes as $route) {
            $this->assertTrue(
                $walkedUser->contains($route),
                "User nav route {$route} missing from walkthrough"
            );
        }
    }
}
