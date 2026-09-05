<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NavigationService;
use App\Support\CampusParkingPolicy;
use Tests\TestCase;

class UserPolicyPageTest extends TestCase
{
    private function grantedStudentOrSkip(): User
    {
        try {
            $user = User::query()
                ->where('user_role_id', NavigationService::ROLE_STUDENT)
                ->where('status', User::STATUS_GRANTED)
                ->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $user) {
            $this->markTestSkipped('No granted student user found.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->update(['email_verified_at' => now()]);
        }

        return $user;
    }

    public function test_user_sidebar_lists_policy_under_dashboard(): void
    {
        $routes = NavigationService::routesForRole('student');
        $labels = array_column($routes, 'label');

        $this->assertSame('Dashboard', $labels[0] ?? null);
        $this->assertSame('Policy', $labels[1] ?? null);
        $this->assertSame('user.policy', $routes[1]['route'] ?? null);
    }

    public function test_policy_page_shows_official_titles_without_section_prefix(): void
    {
        $user = $this->grantedStudentOrSkip();

        $this->actingAs($user)
            ->get(route('user.policy'))
            ->assertOk()
            ->assertSee('Policy')
            ->assertSee(CampusParkingPolicy::TITLE)
            ->assertSee('Rationale')
            ->assertSee('General Information')
            ->assertSee('Stalled Vehicles')
            ->assertSee('Parking and Traffic Violation')
            ->assertSee('Other Provisions/Conditions')
            ->assertSee('Separability Clause')
            ->assertSee('Repealing Clause')
            ->assertSee('Effectivity')
            ->assertDontSee('Section 9')
            ->assertDontSee('Section 10')
            ->assertDontSee('Section 11')
            ->assertDontSee('Section 12')
            ->assertDontSee('Section 13')
            ->assertDontSee('Section 14')
            ->assertDontSee('Section 15');
    }

    public function test_admin_and_guard_cannot_open_user_policy(): void
    {
        try {
            $admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
            $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $admin || ! $guard) {
            $this->markTestSkipped('Seeded admin/guard missing.');
        }

        foreach ([$admin, $guard] as $staff) {
            if (! $staff->hasVerifiedEmail()) {
                $staff->update(['email_verified_at' => now()]);
            }
        }

        $this->actingAs($admin)
            ->get(route('user.policy'))
            ->assertRedirect(route('admin.dashboard'));

        $this->flushSession();

        $this->actingAs($guard)
            ->get(route('user.policy'))
            ->assertRedirect(route('guard.dashboard'));
    }
}
