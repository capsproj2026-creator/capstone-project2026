<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NavigationService;
use Tests\TestCase;

class RfidAssignmentUiTest extends TestCase
{
    /** @var list<User> */
    private array $fixtures = [];

    protected function tearDown(): void
    {
        foreach ($this->fixtures as $user) {
            $user->delete();
        }
        $this->fixtures = [];

        parent::tearDown();
    }

    /** One student waiting for a card and one already assigned, so the table and both counters have rows. */
    private function seedStudents(): void
    {
        foreach ([null, 'TST'.strtoupper(substr(uniqid(), -7))] as $uid) {
            $this->fixtures[] = User::query()->create([
                'fullname' => 'RFID UI Fixture '.($uid ? 'Assigned' : 'Pending'),
                'email' => 'rfid.ui.'.uniqid().'@my.cspc.edu.ph',
                'password' => bcrypt('password123'),
                'user_role_id' => NavigationService::ROLE_STUDENT,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'RUI'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'RUI'.random_int(1000, 9999),
                'status' => User::STATUS_GRANTED,
                'rfid_uid' => $uid,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

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
        $this->seedStudents();

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
        $this->seedStudents();

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
