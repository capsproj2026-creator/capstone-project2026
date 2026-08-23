<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Visitor;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VisitorGoogleFormWebhookTest extends TestCase
{
    private const TOKEN = 'test-visitor-pre-register-webhook-token';

    private ?User $guardUser = null;

    /** @var list<int> */
    private array $visitorIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.visitor_pre_register.webhook_token', self::TOKEN);

        try {
            $this->guardUser = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->guardUser) {
            $this->markTestSkipped('Seeded guard user required.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->visitorIds as $id) {
            Visitor::query()->where('id', $id)->delete();
        }

        parent::tearDown();
    }

    public function test_webhook_requires_valid_token(): void
    {
        $this->postJson(route('api.visitor.pre-register.google'), [])
            ->assertUnauthorized();

        $this->postJson(route('api.visitor.pre-register.google'), [], [
            'X-VISITOR-PRE-REGISTER-TOKEN' => 'wrong-token',
        ])->assertUnauthorized();
    }

    public function test_webhook_creates_visitor_and_returns_signed_success_url(): void
    {
        $exitAt = now()->addHours(3)->format('Y-m-d\TH:i:s');
        $plate = 'GFR'.random_int(1000, 9999);

        $response = $this->postJson(route('api.visitor.pre-register.google'), [
            'first_name' => 'Google',
            'last_name' => 'Form',
            'contact_number' => '09179998888',
            'email' => 'google-form@example.com',
            'purpose' => 'Delivery',
            'office_to_visit' => 'Supply',
            'expected_exit_at' => $exitAt,
            'plate_number' => $plate,
            'vehicle_name' => 'Automobiles',
            'vehicle_color' => 'Blue',
        ], [
            'X-VISITOR-PRE-REGISTER-TOKEN' => self::TOKEN,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['confirmation_code', 'success_url', 'visitor_id']);

        $visitor = Visitor::query()->where('plate_number', strtoupper($plate))->orderByDesc('id')->first();
        $this->assertNotNull($visitor);
        $this->visitorIds[] = (int) $visitor->id;

        $this->assertSame(Visitor::STATUS_WAITING, $visitor->status);
        $this->assertSame(Visitor::SOURCE_SELF, $visitor->registration_source);
        $this->assertSame($response->json('confirmation_code'), $visitor->confirmation_code);

        $this->get($response->json('success_url'))
            ->assertOk()
            ->assertSee($visitor->confirmation_code, false);
    }

    public function test_webhook_rejects_past_expected_exit_at(): void
    {
        $this->postJson(route('api.visitor.pre-register.google'), [
            'first_name' => 'Late',
            'last_name' => 'Webhook',
            'contact_number' => '09171112233',
            'purpose' => 'Test',
            'office_to_visit' => 'Office',
            'expected_exit_at' => now()->subHour()->format('Y-m-d\TH:i:s'),
            'plate_number' => 'LWH'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Black',
        ], [
            'X-VISITOR-PRE-REGISTER-TOKEN' => self::TOKEN,
        ])->assertUnprocessable();
    }

    public function test_qr_uses_google_form_url_when_configured(): void
    {
        $googleUrl = 'https://docs.google.com/forms/d/e/test123/viewform';
        Config::set('services.visitor_pre_register.google_form_url', $googleUrl);

        if (! $this->guardUser->hasVerifiedEmail()) {
            $this->guardUser->update(['email_verified_at' => now()]);
        }

        $this->flushSession();
        $this->actingAs($this->guardUser->fresh())
            ->get(route('visitor.pre-register.qr'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_pre_register_redirects_to_google_form_when_configured(): void
    {
        $googleUrl = 'https://docs.google.com/forms/d/e/test456/viewform';
        Config::set('services.visitor_pre_register.google_form_url', $googleUrl);

        $this->get(route('visitor.pre-register'))
            ->assertRedirect($googleUrl);
    }

    public function test_signed_success_url_expires(): void
    {
        $response = $this->postJson(route('api.visitor.pre-register.google'), [
            'first_name' => 'Expire',
            'last_name' => 'Link',
            'contact_number' => '09170001111',
            'purpose' => 'Test',
            'office_to_visit' => 'Office',
            'expected_exit_at' => now()->addHours(2)->format('Y-m-d\TH:i:s'),
            'plate_number' => 'EXP'.random_int(100, 999),
            'vehicle_id' => 1,
            'vehicle_color' => 'Gray',
        ], [
            'X-VISITOR-PRE-REGISTER-TOKEN' => self::TOKEN,
        ]);

        $response->assertOk();
        $visitorId = (int) $response->json('visitor_id');
        $this->visitorIds[] = $visitorId;

        $expiredUrl = URL::temporarySignedRoute(
            'visitor.pre-register.success',
            now()->subMinute(),
            ['visitor' => $visitorId]
        );

        $this->get($expiredUrl)->assertRedirect(route('visitor.pre-register'));
    }
}
