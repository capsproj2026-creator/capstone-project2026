<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    private ?User $guard = null;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            $this->guard = User::query()->where('email', 'guard@my.cspc.edu.ph')->first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        if (! $this->guard) {
            $this->markTestSkipped('Run php artisan db:seed — guard user not found.');
        }
    }

    public function test_verification_notice_requires_authentication(): void
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    public function test_unverified_user_can_view_notice_and_resend(): void
    {
        Notification::fake();

        $this->guard->update(['email_verified_at' => null]);

        $this->actingAs($this->guard)
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Registration Successful!')
            ->assertSee('Resend Verification Email');

        $this->actingAs($this->guard)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($this->guard, VerifyEmail::class);

        $this->guard->update(['email_verified_at' => now()]);
    }

    public function test_signed_verification_link_marks_email_verified(): void
    {
        $this->guard->update(['email_verified_at' => null]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $this->guard->getKey(),
                'hash' => sha1($this->guard->getEmailForVerification()),
            ]
        );

        $this->actingAs($this->guard)
            ->get($url)
            ->assertRedirect();

        $this->guard->refresh();
        $this->assertNotNull($this->guard->email_verified_at);
    }

    public function test_registration_email_validation_rejects_invalid_format(): void
    {
        $this->post(route('register'), [
            'reg_category' => 'vehicle',
            'fullname' => 'Test User',
            'email' => 'not-an-email',
            'phone_number' => '09001112222',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'id_number' => 'VERIFYQA1',
            'user_type' => 'Student',
            'plate_number' => 'ABC123',
            'department_code' => 'CCS',
            'vehicle_id' => '1',
        ])->assertSessionHasErrors('email');
    }
}
