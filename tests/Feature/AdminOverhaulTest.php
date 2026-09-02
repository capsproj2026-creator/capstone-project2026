<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Services\RfidAccessService;
use Tests\TestCase;

class AdminOverhaulTest extends TestCase
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

    public function test_zone_access_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parking.zone-access'))
            ->assertOk()
            ->assertSee('Zone Access')
            ->assertSee('zone-role-panel', false)
            ->assertSee('zone-access-savebar', false);
    }

    public function test_parking_layout_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parking.layout'))
            ->assertOk()
            ->assertSee('Zones & Spaces', false)
            ->assertSee('Add parking area', false);
    }

    public function test_parking_layout_shows_acad1_and_duran_snapshots(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parking.layout', ['zone_id' => 4]))
            ->assertOk()
            ->assertSee('images/parking/snapshot_acad1.jpg', false)
            ->assertSee('Calibrated', false);

        $this->actingAs($this->admin)
            ->get(route('admin.parking.layout', ['zone_id' => 3]))
            ->assertOk()
            ->assertSee('images/parking/snapshot_duran.jpg', false)
            ->assertSee('Calibrated', false);
    }

    public function test_parking_overview_shows_selected_zone_snapshot(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parking', ['zone_id' => 4]))
            ->assertOk()
            ->assertSee('images/parking/snapshot_acad1.jpg', false);
    }

    public function test_reports_export_returns_csv(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition') ?? '');
    }

    public function test_settings_parking_section_loads(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'parking']))
            ->assertOk()
            ->assertSee('Parking Access Rules');

        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'access']))
            ->assertOk()
            ->assertSee('Zone Access Settings');
    }

    public function test_settings_tabs_and_system_info_save(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'general']))
            ->assertOk()
            ->assertSee('settings-sticky-header', false)
            ->assertSee('settings-subnav__tab--active', false)
            ->assertSee('System Settings')
            ->assertSee('Configure system preferences and access rules');

        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'general']))
            ->post(route('admin.settings.system'), [
                'campus_name' => 'CSPC Smart Campus',
                'timezone' => 'Asia/Manila',
                'contact_email' => 'security@cspc.edu.ph',
                'contact_phone' => '+63 900 000 0000',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'general']))
            ->post(route('admin.settings.preferences'), [
                'auto_lock_on_3rd_violation' => '1',
                'send_violation_notifications' => '1',
                'enable_visitor_time_limits' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'general']))
            ->assertOk()
            ->assertSee('CSPC Smart Campus')
            ->assertSee('security@cspc.edu.ph');
    }

    public function test_settings_admin_users_tab_and_permissions(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'admins']))
            ->assertOk()
            ->assertSee('Admin User Management')
            ->assertSee('Create Admin')
            ->assertSee('Create Guard')
            ->assertDontSee('Role Permissions');

        $suffix = (string) random_int(1000, 9999);
        $email = "temp.admin{$suffix}@gmail.com";
        $response = $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'admins']))
            ->post(route('admin.settings.admins.store'), [
                'name' => 'Temp Admin '.$suffix,
                'email' => $email,
                'id_number' => 'ADM'.$suffix,
                'phone_number' => '09171234567',
                'job_title' => 'Security Head',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

        $response->assertRedirect(route('admin.settings', ['section' => 'admins']));
        $response->assertSessionHasNoErrors();

        $created = \App\Models\User::query()->where('email', $email)->first();
        $this->assertNotNull($created);
        $this->assertSame('Security Head', $created->job_title);

        $this->actingAs($this->admin)
            ->delete(route('admin.settings.staff.destroy', $created->id))
            ->assertRedirect(route('admin.settings', ['section' => 'admins']));

        $this->assertNull(\App\Models\User::query()->where('email', $email)->first());
    }

    public function test_settings_violations_tab_crud(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'violations']))
            ->assertOk()
            ->assertSee('Violation Types')
            ->assertSee('Add Type')
            ->assertSee('Description:')
            ->assertDontSee('Default Penalty')
            ->assertDontSee('Save Violation Types');

        $suffix = (string) random_int(1000, 9999);
        $name = 'Test Speeding '.$suffix;
        $description = 'Vehicle exceeded the campus speed limit';

        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'violations']))
            ->post(route('admin.settings.violations.store'), [
                'violation_name' => $name,
                'description' => $description,
            ])
            ->assertRedirect(route('admin.settings', ['section' => 'violations']))
            ->assertSessionHasNoErrors();

        $created = \App\Models\ViolationType::query()->where('violation_name', $name)->first();
        $this->assertNotNull($created);
        $this->assertSame($description, $created->description);
        $this->assertSame('Active', $created->status);

        $this->actingAs($this->admin)
            ->get(route('admin.settings', ['section' => 'violations']))
            ->assertOk()
            ->assertSee($name)
            ->assertSee($description);

        $updatedDescription = 'Vehicle parked in a restricted area';
        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'violations']))
            ->put(route('admin.settings.violations.update', $created->id), [
                'violation_name' => 'Illegal Parking '.$suffix,
                'description' => $updatedDescription,
            ])
            ->assertRedirect(route('admin.settings', ['section' => 'violations']))
            ->assertSessionHasNoErrors();

        $created->refresh();
        $this->assertSame('Illegal Parking '.$suffix, $created->violation_name);
        $this->assertSame($updatedDescription, $created->description);

        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'violations']))
            ->post(route('admin.settings.violations.toggle', $created->id))
            ->assertRedirect(route('admin.settings', ['section' => 'violations']));

        $created->refresh();
        $this->assertSame('Inactive', $created->status);

        $this->actingAs($this->admin)
            ->from(route('admin.settings', ['section' => 'violations']))
            ->post(route('admin.settings.violations.toggle', $created->id))
            ->assertRedirect(route('admin.settings', ['section' => 'violations']));

        $created->refresh();
        $this->assertSame('Active', $created->status);

        $this->actingAs($this->admin)
            ->delete(route('admin.settings.violations.destroy', $created->id))
            ->assertRedirect(route('admin.settings', ['section' => 'violations']));

        $this->assertNull(\App\Models\ViolationType::query()->where('id', $created->id)->first());
    }

    public function test_view_user_back_link_from_registrations(): void
    {
        $user = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', ['Student', 'Staff', 'Guard']))
            ->first();

        if (! $user) {
            $this->markTestSkipped('No non-admin user found.');
        }

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', ['id' => $user->id, 'from' => 'registrations']))
            ->assertOk()
            ->assertSee('Back to Registrations');
    }

    public function test_view_user_back_link_from_user_management(): void
    {
        $user = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('role_name', ['Student', 'Staff', 'Guard']))
            ->first();

        if (! $user) {
            $this->markTestSkipped('No non-admin user found.');
        }

        $this->actingAs($this->admin)
            ->get(route('admin.users.show', ['id' => $user->id, 'from' => 'users']))
            ->assertOk()
            ->assertSee('Back to User Management');
    }

    public function test_user_management_filters_accept_query_params(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users', [
                'search' => 'student',
                'type' => 'Student',
                'status' => 'Granted',
                'sort' => 'fullname',
                'direction' => 'asc',
            ]))
            ->assertOk();
    }

    public function test_admin_layout_hides_notification_bell(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('aria-label="Notifications"', $html);
    }

    public function test_grant_access_saves_rfid_uid_and_grants_user(): void
    {
        $uid = 'CAFE'.strtoupper(substr(uniqid(), -8));
        $user = User::query()->create([
            'fullname' => 'RFID Grant Test',
            'email' => 'rfid.grant.'.uniqid().'@my.cspc.edu.ph',
            'password' => bcrypt('password123'),
            'user_role_id' => 3,
            'department_code' => 'CCS',
            'vehicle_id' => 1,
            'id_number' => 'GRANT'.strtoupper(substr(uniqid(), -5)),
            'plate_number' => 'TST'.random_int(100, 999),
            'status' => User::STATUS_PENDING,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'strike_count' => 0,
            'email_verified_at' => now(),
            'created_at' => now(),
        ]);

        try {
            $this->actingAs($this->admin)
                ->post(route('admin.rfid.approve', ['id' => $user->id]), [
                    'rfid_uid' => 'UID: '.implode(' ', str_split(strtolower($uid), 2)),
                ])
                ->assertRedirect(route('admin.rfid', ['tab' => 'assigned']))
                ->assertSessionHas('success');

            $user->refresh();
            $this->assertSame($uid, $user->rfid_uid);
            $this->assertSame(User::GATE_ACCESS_GRANTED, $user->Gate_access);
            $this->assertSame(User::STATUS_GRANTED, $user->status);

            $this->actingAs($this->admin)
                ->get(route('admin.rfid', [
                    'tab' => 'assigned',
                    'search' => $user->id_number,
                ]))
                ->assertOk()
                ->assertSee($user->name)
                ->assertSee($uid)
                ->assertSee('data-has-rfid="1"', false);

            // Client-side filters keep all rows in the DOM.
            $this->actingAs($this->admin)
                ->get(route('admin.rfid', ['tab' => 'pending']))
                ->assertOk()
                ->assertSee($user->name)
                ->assertSee('data-has-rfid="1"', false);

            $scan = app(RfidAccessService::class)->process($uid, 'GATE-IN-TEST', 'Entry');
            $this->assertSame(RfidAccessService::STATUS_GRANTED, $scan['status']);
            $this->assertTrue($scan['granted']);
        } finally {
            GateLog::query()->where('user_id', $user->id)->delete();
            $user->delete();
        }
    }

    public function test_reports_export_pdf(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.export-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_reports_typed_pdf_and_excel_exports(): void
    {
        $types = ['all', 'overview', 'violations', 'parking', 'access'];

        foreach ($types as $type) {
            $slug = match ($type) {
                'all' => 'all_reports',
                default => $type.'_report',
            };

            $pdf = $this->actingAs($this->admin)
                ->get(route('admin.reports.export-pdf', ['type' => $type]));
            $pdf->assertOk();
            $pdf->assertHeader('content-type', 'application/pdf');
            $this->assertMatchesRegularExpression(
                '/'.$slug.'_\d{4}-\d{2}-\d{2}_\d{6}\.pdf/',
                $pdf->headers->get('content-disposition') ?? ''
            );

            $excel = $this->actingAs($this->admin)
                ->get(route('admin.reports.export-excel', ['type' => $type]));
            $excel->assertOk();
            $this->assertStringContainsString(
                'spreadsheetml.sheet',
                $excel->headers->get('content-type') ?? ''
            );
            $this->assertMatchesRegularExpression(
                '/'.$slug.'_\d{4}-\d{2}-\d{2}_\d{6}\.xlsx/',
                $excel->headers->get('content-disposition') ?? ''
            );
            $this->assertSame('PK', substr($excel->getContent(), 0, 2));
        }
    }

    public function test_zone_access_save_with_visibility(): void
    {
        $zone = \App\Models\ParkingArea::query()->first();
        if (! $zone) {
            $this->markTestSkipped('No parking zones seeded.');
        }

        $this->actingAs($this->admin)
            ->from(route('admin.parking.zone-access'))
            ->post(route('admin.parking.areas.update'), [
                'visible' => [$zone->id => '1'],
                'roles' => [$zone->id => ['Student']],
            ])
            ->assertRedirect(route('admin.parking.zone-access'))
            ->assertSessionHas('success');
    }

    public function test_admin_document_route_accepts_id_or_and_cr(): void
    {
        foreach (['license', 'orcr', 'or', 'cr', 'id'] as $doc) {
            $url = route('admin.users.document', ['id' => $this->admin->id, 'doc' => $doc]);
            $matched = app('router')->getRoutes()->match(
                \Illuminate\Http\Request::create($url, 'GET')
            );

            $this->assertSame('admin.users.document', $matched->getName());
            $this->assertSame($doc, $matched->parameter('doc'));
        }
    }

    public function test_admin_can_add_and_remove_parking_area_and_space(): void
    {
        $prefix = 'ZX'.random_int(10, 99);
        $name = 'Test Lot '.$prefix;
        $expectedId = app(\App\Services\ParkingLayoutService::class)->nextAreaId();

        $this->actingAs($this->admin)
            ->from(route('admin.parking.layout'))
            ->post(route('admin.parking.areas.store'), [
                'area_name' => $name,
                'slot_prefix' => $prefix,
                'slot_count' => 2,
                'designation_notes' => 'Students',
                'is_visible' => '1',
                'allowed_roles' => ['Student'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $area = \App\Models\ParkingArea::query()->where('area_name', $name)->first();
        $this->assertNotNull($area);
        $this->assertSame($expectedId, (int) $area->id);
        $slots = \App\Models\ParkingSlot::query()->where('area_id', $area->id)->orderBy('slot_number')->get();
        $this->assertCount(2, $slots);
        $this->assertSame(2, (int) $area->capacity);

        $this->actingAs($this->admin)
            ->from(route('admin.parking.layout', ['zone_id' => $area->id]))
            ->post(route('admin.parking.slots.store'), [
                'area_id' => $area->id,
                'slot_count' => 1,
            ])
            ->assertRedirect(route('admin.parking.layout', ['zone_id' => $area->id]));

        $this->assertSame(3, \App\Models\ParkingSlot::query()->where('area_id', $area->id)->count());

        $removable = \App\Models\ParkingSlot::query()->where('area_id', $area->id)->orderByDesc('id')->first();
        $this->actingAs($this->admin)
            ->post(route('admin.parking.slots.destroy', $removable->id))
            ->assertRedirect(route('admin.parking.layout', ['zone_id' => $area->id]));
        $this->assertNull(\App\Models\ParkingSlot::query()->find($removable->id));

        $occupied = \App\Models\ParkingSlot::query()->where('area_id', $area->id)->first();
        $occupied->update(['status' => 'Occupied']);
        $this->actingAs($this->admin)
            ->from(route('admin.parking.layout', ['zone_id' => $area->id]))
            ->post(route('admin.parking.areas.destroy', $area->id))
            ->assertRedirect()
            ->assertSessionHasErrors('area');
        $this->assertNotNull(\App\Models\ParkingArea::query()->find($area->id));

        $occupied->update(['status' => 'Available']);
        $this->actingAs($this->admin)
            ->post(route('admin.parking.areas.destroy', $area->id))
            ->assertRedirect(route('admin.parking.layout'));
        $this->assertNull(\App\Models\ParkingArea::query()->find($area->id));
        $this->assertSame(0, \App\Models\ParkingSlot::query()->where('area_id', $area->id)->count());
    }

    public function test_admin_cannot_delete_ai_monitored_parking_area(): void
    {
        $ids = app(\App\Services\AiCameraRegistry::class)->monitoredAreaIds();
        $aiId = $ids[0] ?? null;
        $area = $aiId ? \App\Models\ParkingArea::query()->find($aiId) : null;
        if (! $area) {
            $this->markTestSkipped('No AI-monitored parking area found.');
        }

        $this->actingAs($this->admin)
            ->from(route('admin.parking.layout'))
            ->post(route('admin.parking.areas.destroy', $aiId))
            ->assertRedirect()
            ->assertSessionHasErrors('area');

        $this->assertNotNull(\App\Models\ParkingArea::query()->find($aiId));
    }

    public function test_guard_cannot_add_parking_area(): void
    {
        $guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        if (! $guard) {
            $this->markTestSkipped('Guard user not seeded.');
        }
        if (! $guard->hasVerifiedEmail()) {
            $guard->update(['email_verified_at' => now()]);
        }
        if ($guard->status !== User::STATUS_GRANTED) {
            $guard->update(['status' => User::STATUS_GRANTED]);
        }

        $prefix = 'ZG'.random_int(10, 99);
        $this->actingAs($guard)
            ->post(route('admin.parking.areas.store'), [
                'area_name' => 'Guard Lot '.$prefix,
                'slot_prefix' => $prefix,
                'slot_count' => 1,
                'allowed_roles' => ['Student'],
            ])
            ->assertRedirect();

        $this->assertNull(\App\Models\ParkingArea::query()->where('slot_prefix', $prefix)->first());
    }
}
