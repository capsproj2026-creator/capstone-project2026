<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationResendTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    protected function tearDown(): void
    {
        foreach ($this->emails as $email) {
            try {
                User::query()->where('email', $email)->delete();
            } catch (\Throwable) {
            }
        }

        parent::tearDown();
    }

    private function makeUser(bool $verified): User
    {
        $email = 'verify.'.uniqid().'@my.cspc.edu.ph';
        $this->emails[] = $email;

        try {
            return User::query()->create([
                'fullname' => 'Verify Tester',
                'email' => $email,
                'password' => Hash::make('Secret12!'),
                'status' => User::STATUS_PENDING,
                'email_verified_at' => $verified ? now() : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    private function postResend(string $email)
    {
        return $this->from(route('verification.resend'))
            ->withSession(['_token' => 'test-token'])
            ->post(route('verification.resend.send'), ['email' => $email, '_token' => 'test-token']);
    }

    public function test_login_page_links_to_resend_page(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('verification.resend'), false)
            ->assertSee('Resend verification link', false);

        $this->get(route('verification.resend'))
            ->assertOk()
            ->assertSee('Verify your email', false);
    }

    public function test_unknown_email_sends_nothing_but_answers_the_same(): void
    {
        Notification::fake();

        $this->postResend('missing.'.uniqid().'@my.cspc.edu.ph')
            ->assertRedirect(route('verification.resend'))
            ->assertSessionHas('success');

        Notification::assertNothingSent();
    }

    public function test_unverified_user_gets_a_new_link(): void
    {
        Notification::fake();
        $user = $this->makeUser(verified: false);

        $this->postResend($user->email)
            ->assertRedirect(route('verification.resend'))
            ->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_is_not_emailed_again(): void
    {
        Notification::fake();
        $user = $this->makeUser(verified: true);

        $this->postResend($user->email)->assertSessionHas('success');

        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_emailed_link_verifies_after_signing_in(): void
    {
        Notification::fake();
        $user = $this->makeUser(verified: false);

        $this->postResend($user->email);

        $link = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user, &$link) {
            $link = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertNotEmpty($link);

        // Opening the link signed-out sends them to sign in first...
        $this->get($link)->assertRedirect(route('login'));

        // ...and signing in returns them to the link instead of the generic notice.
        $this->withSession(['_token' => 'test-token', 'url.intended' => $link])
            ->post(route('login'), ['email' => $user->email, 'password' => 'Secret12!', '_token' => 'test-token'])
            ->assertRedirect($link);

        $this->get($link)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
