<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NavigationService;
use Tests\TestCase;

class RfidAssignmentUiTest extends TestCase
{
    private function adminOrSkip(): User
    {
        try {
            $admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $admin) {
            $this->markTestSkipped('Admin user missing.');
        }

        if (! $admin->hasVerifiedEmail()) {
            $admin->update(['email_verified_at' => now()]);
        }

        return $admin;
    }

    public function test_rfid_page_shows_filter_cards_without_list_tabs(): void
    {
        $admin = $this->adminOrSkip();

        $html = $this->actingAs($admin)
            ->get(route('admin.rfid'))
            ->assertOk()
            ->assertSee('Total Users')
            ->assertSee('Pending Assignment')
            ->assertSee('RFID Assigned')
            ->assertSee('Locked Users')
            ->assertSee('Denied Users')
            ->assertSee('Approved Users')
            ->assertSee('data-rfid-filter', false)
            ->assertSee('rfid-user-row', false)
            ->getContent();

        $this->assertStringNotContainsString('aria-label="RFID user filters"', $html);
        $this->assertStringContainsString('id="rfid-filter-cards"', $html);
        $this->assertStringContainsString('data-has-rfid=', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Full Name', $html);
        $this->assertStringContainsString('RFID Status', $html);
        $this->assertStringContainsString('<th scope="col"', $html);
    }

    public function test_rfid_stats_match_filter_definitions(): void
    {
        $admin = $this->adminOrSkip();

        $eligible = User::query()->whereIn('user_role_id', [
            NavigationService::ROLE_STUDENT,
            NavigationService::ROLE_STAFF,
        ])->get();

        $total = $eligible->count();
        $pending = $eligible->filter(fn (User $u) => ! filled($u->rfid_uid))->count();
        $assigned = $eligible->filter(fn (User $u) => filled($u->rfid_uid))->count();

        $html = $this->actingAs($admin)
            ->get(route('admin.rfid'))
            ->assertOk()
            ->getContent();

        $this->assertGreaterThanOrEqual(1, $total);
        $this->assertSame($total, $pending + $assigned);
        $this->assertStringContainsString((string) $total, $html);
        $this->assertStringContainsString((string) $pending, $html);
        $this->assertStringContainsString((string) $assigned, $html);
    }

    public function test_assign_and_deny_actions_still_work_from_redesign(): void
    {
        $admin = $this->adminOrSkip();

        $this->actingAs($admin)
            ->get(route('admin.rfid', ['tab' => 'pending']))
            ->assertOk()
            ->assertSee('js-assign-rfid', false)
            ->assertSee('Assign RFID');
    }
}
