<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visitor;
use App\Services\VisitorService;
use Tests\TestCase;

class VisitorPreRegistrationTest extends TestCase
{
    private ?User $guardUser = null;

    /** @var list<int> */
    private array $visitorIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->guardUser = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->guardUser) {
            $this->markTestSkipped('Seeded guard user required.');
        }

        if (! $this->guardUser->hasVerifiedEmail()) {
            $this->guardUser->update(['email_verified_at' => now()]);
        }
        if ($this->guardUser->status !== User::STATUS_GRANTED) {
            $this->guardUser->update(['status' => User::STATUS_GRANTED]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->visitorIds as $id) {
            Visitor::query()->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    public function test_public_pre_register_form_loads_without_auth(): void
    {
        $this->get(route('visitor.pre-register'))
            ->assertOk()
            ->assertSee('Visitor Pre-Registration', false)
            ->assertSee('Submit Pre-Registration', false);
    }

    public function test_post_creates_waiting_visitor_with_confirmation_code(): void
    {
        $exitAt = now()->addHours(4)->format('Y-m-d\TH:i');
        $plate = 'PRE'.random_int(1000, 9999);

        $response = $this->post(route('visitor.pre-register.store'), [
            'first_name' => 'Online',
            'last_name' => 'Guest',
            'middle_name' => '',
            'contact_number' => '09171234567',
            'email' => 'guest@example.com',
            'purpose' => 'Campus tour',
            'office_to_visit' => 'Registrar',
            'expected_exit_at' => $exitAt,
            'plate_number' => $plate,
            'vehicle_id' => 1,
            'vehicle_color' => 'Silver',
        ]);

        $response->assertRedirect(route('visitor.pre-register.success'));

        $visitor = Visitor::query()->where('plate_number', strtoupper($plate))->orderByDesc('id')->first();
        $this->assertNotNull($visitor);
        $this->visitorIds[] = (int) $visitor->id;

        $this->assertSame(Visitor::STATUS_WAITING, $visitor->status);
        $this->assertSame(Visitor::SOURCE_SELF, $visitor->registration_source);
        $this->assertNull($visitor->registered_by);
        $this->assertNotNull($visitor->confirmation_code);
        $this->assertMatchesRegularExpression('/^V-\d{8}-[A-F0-9]{4}$/', $visitor->confirmation_code);
        $this->assertNull($visitor->rfid_uid);

        $this->followRedirects($response)
            ->assertOk()
            ->assertSee($visitor->confirmation_code, false);
    }

    public function test_post_rejects_past_expected_exit_at(): void
    {
        $this->post(route('visitor.pre-register.store'), [
            'first_name' => 'Late',
            'last_name' => 'Submit',
            'contact_number' => '09170000001',
            'purpose' => 'Test',
            'office_to_visit' => 'Office',
            'expected_exit_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'plate_number' => 'PST'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Black',
        ])->assertSessionHasErrors('expected_exit_at');
    }

    public function test_confirmation_code_appears_in_guard_active_search(): void
    {
        $visitor = app(VisitorService::class)->preRegister([
            'first_name' => 'Search',
            'last_name' => 'Target',
            'contact_number' => '09178889999',
            'purpose' => 'Meeting',
            'office_to_visit' => 'Dean',
            'expected_exit_at' => now()->addHours(2),
            'plate_number' => 'SRC'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'White',
        ]);
        $this->visitorIds[] = (int) $visitor->id;

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->get(route('guard.visitors.active', ['search' => $visitor->confirmation_code]))
            ->assertOk()
            ->assertSee($visitor->confirmation_code, false)
            ->assertSee('Pre-registered online', false);
    }

    public function test_qr_route_requires_guard_or_admin_auth(): void
    {
        $this->get(route('visitor.pre-register.qr'))
            ->assertRedirect(route('login'));

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->get(route('visitor.pre-register.qr'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }
}
