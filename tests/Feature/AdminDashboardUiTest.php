<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminDashboardUiTest extends TestCase
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

    public function test_admin_dashboard_matches_reference_sections(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Smart Campus Vehicle Management System Overview')
            ->assertSee('Total Users')
            ->assertSee('Active Violations')
            ->assertSee("Today's Activity", false)
            ->assertSee('Parking')
            ->assertSee('Weekly Entry/Exit Trends')
            ->assertSee('Violation Types Distribution')
            ->assertSee('Recent Violations')
            ->assertSee('View All')
            ->assertSee('Quick Actions')
            ->assertSee('Registrations')
            ->assertSee('RFID Assignment')
            ->assertSee('User Management')
            ->assertDontSee('Add New User')
            ->assertDontSee('Log Violation')
            ->assertSee('Generate Report')
            ->assertSee('View Parking Map')
            ->assertSee('3-Strike System');
    }
}
