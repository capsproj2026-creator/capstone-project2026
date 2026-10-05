<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleSignInDomainTest extends TestCase
{
    /** @var list<string> */
    private array $emails = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.allowed_domain' => 'my.cspc.edu.ph,cspc.edu.ph,gmail.com',
        ]);
    }

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

    private function fakeGoogle(string $email): void
    {
        $googleUser = (new SocialiteUser)->map([
            'id' => 'g-'.uniqid(),
            'email' => $email,
            'name' => 'Google Tester',
        ]);

        $provider = \Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function registeredUser(string $domain): User
    {
        $email = 'google.'.uniqid().'@'.$domain;
        $this->emails[] = $email;

        try {
            return User::query()->create([
                'fullname' => 'Google Tester',
                'email' => $email,
                'password' => Hash::make('Secret12!'),
                'status' => User::STATUS_GRANTED,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }
    }

    public function test_domain_list_matches_exact_domains_only(): void
    {
        $this->assertTrue(GoogleAuthController::emailDomainAllowed('a@my.cspc.edu.ph'));
        $this->assertTrue(GoogleAuthController::emailDomainAllowed('a@cspc.edu.ph'));
        $this->assertTrue(GoogleAuthController::emailDomainAllowed('A@GMAIL.COM'));
        $this->assertFalse(GoogleAuthController::emailDomainAllowed('a@yahoo.com'));
        $this->assertFalse(GoogleAuthController::emailDomainAllowed('a@evilcspc.edu.ph'));
        $this->assertFalse(GoogleAuthController::emailDomainAllowed('a@gmail.com.evil.io'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedDomainProvider(): array
    {
        return [
            'gmail' => ['gmail.com'],
            'cspc staff' => ['cspc.edu.ph'],
            'cspc student' => ['my.cspc.edu.ph'],
        ];
    }

    #[DataProvider('allowedDomainProvider')]
    public function test_registered_accounts_on_allowed_domains_can_sign_in(string $domain): void
    {
        $user = $this->registeredUser($domain);
        $this->fakeGoogle($user->email);

        $this->get(route('auth.google.callback'))
            ->assertRedirect()
            ->assertSessionMissing('error');
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_is_asked_to_require_a_fresh_password_sign_in(): void
    {
        $location = $this->get(route('auth.google'))->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertSame('0', $query['max_age'] ?? null);
        $this->assertSame('select_account', $query['prompt'] ?? null);
    }

    public function test_other_domains_are_rejected(): void
    {
        $this->fakeGoogle('someone.'.uniqid().'@yahoo.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', fn (string $msg) => str_contains($msg, '@gmail.com'));
        $this->assertGuest();
    }
}
