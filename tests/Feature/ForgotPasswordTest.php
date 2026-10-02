<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\PasswordResetToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    private ?string $email = null;

    protected function tearDown(): void
    {
        if ($this->email) {
            try {
                User::query()->where('email', $this->email)->delete();
                PasswordResetToken::query()->where('email', $this->email)->delete();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Forgot password?', false)
            ->assertSee(route('password.request'), false);
    }

    public function test_unknown_email_does_not_send_a_reset_message(): void
    {
        Mail::fake();
        $this->email = 'missing.'.uniqid().'@my.cspc.edu.ph';

        try {
            User::query()->where('email', $this->email)->delete();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        $this->from(route('password.request'))
            ->withSession(['_token' => 'test-token'])
            ->post(route('password.email'), ['email' => $this->email, '_token' => 'test-token'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    public function test_registered_user_can_reset_password_from_emailed_link(): void
    {
        Mail::fake();
        $this->email = 'reset.'.uniqid().'@my.cspc.edu.ph';

        try {
            User::query()->where('email', $this->email)->delete();
            $user = User::query()->create([
                'fullname' => 'Reset Tester',
                'email' => $this->email,
                'password' => Hash::make('OldPass1!'),
                'status' => User::STATUS_GRANTED,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        $this->withSession(['_token' => 'test-token'])
            ->post(route('password.email'), ['email' => $this->email, '_token' => 'test-token'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$resetUrl) {
            $resetUrl = $mail->resetUrl;

            return $mail->hasTo($this->email);
        });

        $this->assertNotEmpty($resetUrl ?? null);
        $parts = parse_url($resetUrl);
        parse_str($parts['query'] ?? '', $query);
        $token = basename($parts['path'] ?? '');

        $this->get($resetUrl)->assertOk()->assertSee('Choose a new password', false);

        $this->withSession(['_token' => 'test-token'])
            ->post(route('password.update'), [
                '_token' => 'test-token',
                'token' => $token,
                'email' => $query['email'] ?? $this->email,
                'password' => 'NewPass2!',
                'password_confirmation' => 'NewPass2!',
            ])->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(password_verify('NewPass2!', $user->password));
        $this->assertFalse(password_verify('OldPass1!', $user->password));
        $this->assertSame(0, PasswordResetToken::query()->where('email', $this->email)->count());
    }
}
