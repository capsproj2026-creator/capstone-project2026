<?php

namespace Tests\Feature;

use App\Models\GateLog;
use App\Models\User;
use App\Services\RfidAccessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RemedialRegistrationTest extends TestCase
{
    private const TOKEN = 'test-rfid-api-token';

    private string $uid = '';

    private ?User $admin = null;

    /** @var list<User> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.rfid.api_token', self::TOKEN);
        Config::set('services.registration.remedial_gate_enabled', true);
        Config::set('services.registration.remedial_hours', 5);
        Config::set('services.registration.remedial_one_entry', true);

        $this->uid = strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));

        try {
            $this->admin = User::query()->where('email', 'admin@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->admin) {
            $this->markTestSkipped('Admin user not seeded.');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ($this->created as $user) {
            try {
                GateLog::query()->where('user_id', $user->id)->delete();
                $user->delete();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    private function createPendingUser(): User
    {
        $user = User::query()->create([
            'id' => (int) (microtime(true) * 1000) % 2000000000,
            'fullname' => 'Remedial Test User',
            'email' => 'remedial.'.uniqid().'@example.com',
            'password' => Hash::make('Password1!xx'),
            'phone_number' => '09171234567',
            'user_role_id' => 3,
            'department_code' => 'CCS',
            'vehicle_id' => 1,
            'id_number' => 'REM'.substr((string) time(), -6),
            'plate_number' => 'REM'.random_int(100, 999),
            'status' => User::STATUS_PENDING,
            'registration_state' => User::REGISTRATION_PENDING,
            'Gate_access' => User::GATE_ACCESS_PENDING,
            'rfid_uid' => $this->uid,
            'email_verified_at' => now(),
            'strike_count' => 0,
            'created_at' => now(),
        ]);

        $this->created[] = $user;

        return $user;
    }

    private function declineRemedial(User $user): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.registrations.decline', ['id' => $user->id]), [
                'remarks' => 'License photo is unreadable',
                'decline_type' => 'remedial',
                'decline_category' => User::DECLINE_CATEGORY_DOCUMENTS_ILLEGIBLE,
                'allow_temp_gate' => '1',
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->isRemedialDeclined(), 'Expected remedial decline state');
        $this->assertTrue((bool) $user->remedial_gate_enabled);
    }

    private function logoutCurrentUser(): void
    {
        Auth::logout();
        $this->flushSession();
    }

    public function test_remedial_decline_allows_portal_login(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        $this->assertTrue($user->isRemedialDeclined());
        $this->assertTrue($user->canAccessRemedialPortal());
        $this->assertFalse($user->hasGateAccess());

        $this->logoutCurrentUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Password1!xx',
        ])->assertRedirect(route('user.dashboard'));

        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Registration needs document correction', false)
            ->assertSee('Fix Documents', false);
    }

    public function test_remedial_rfid_entry_before_expiry(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        $result = app(RfidAccessService::class)->process($this->uid, 'GATE-IN-1', 'Entry');

        $this->assertTrue($result['granted']);
        $this->assertTrue($result['user']['is_remedial'] ?? false);
        $this->assertStringContainsString('Remedial access', (string) $result['message']);
    }

    public function test_remedial_rfid_denied_after_expiry(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        Carbon::setTestNow(now()->addHours(6));

        $result = app(RfidAccessService::class)->process($this->uid, 'GATE-IN-1', 'Entry');

        $this->assertFalse($result['granted']);
        $this->assertStringContainsString('expired', strtolower((string) $result['message']));
    }

    public function test_remedial_one_entry_blocks_second_entry(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        $rfid = app(RfidAccessService::class);
        $this->assertTrue($rfid->process($this->uid, 'GATE-IN-1', 'Entry')['granted']);
        $this->assertTrue($rfid->process($this->uid, 'GATE-OUT-1', 'Exit')['granted']);

        $second = $rfid->process($this->uid, 'GATE-IN-1', 'Entry');
        $this->assertFalse($second['granted']);
        $this->assertStringContainsString('one-time', strtolower((string) $second['message']));
    }

    public function test_resubmit_returns_to_pending_and_disables_gate(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        $this->logoutCurrentUser();

        $this->actingAs($user->fresh())
            ->get(route('user.registration.fix'))
            ->assertOk();

        $this->actingAs($user->fresh())
            ->post(route('user.registration.resubmit'), [
                'driver_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
                'lto_or_photo' => UploadedFile::fake()->create('or.jpg', 100, 'image/jpeg'),
                'lto_cr_photo' => UploadedFile::fake()->create('cr.jpg', 100, 'image/jpeg'),
                'id_document' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            ])
            ->assertRedirect(route('user.dashboard'));

        $user->refresh();
        $this->assertSame(User::STATUS_PENDING, $user->status);
        $this->assertSame(User::REGISTRATION_PENDING, $user->registrationState());
        $this->assertFalse((bool) $user->remedial_gate_enabled);
        $this->assertTrue($user->hasPendingResubmission());

        $this->actingAs($user->fresh())
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('awaiting admin review', false);
    }

    public function test_final_decline_blocks_portal_login(): void
    {
        $user = $this->createPendingUser();

        $this->actingAs($this->admin)
            ->post(route('admin.registrations.decline', ['id' => $user->id]), [
                'remarks' => 'Fraudulent documents',
                'decline_type' => 'final',
                'decline_category' => User::DECLINE_CATEGORY_OTHER,
            ])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->isFinalDenied());
        $this->assertFalse($user->canAccessPortal());

        $this->logoutCurrentUser();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'Password1!xx',
        ])->assertRedirect(route('login'));
    }

    public function test_approve_after_resubmit_clears_remedial_fields(): void
    {
        $user = $this->createPendingUser();
        $this->declineRemedial($user);

        $user->update([
            'status' => User::STATUS_PENDING,
            'registration_state' => User::REGISTRATION_PENDING,
            'remedial_gate_enabled' => false,
            'resubmitted_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.registrations.approve', ['id' => $user->id]))
            ->assertRedirect();

        $user->refresh();
        $this->assertSame(User::STATUS_GRANTED, $user->status);
        $this->assertSame(User::REGISTRATION_GRANTED, $user->registrationState());
        $this->assertNull($user->remedial_expires_at);
        $this->assertFalse((bool) $user->remedial_gate_enabled);
    }
}
