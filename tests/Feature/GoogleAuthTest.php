<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_login_page_hides_google_button_when_not_configured(): void
    {
        Config::set('services.google.client_id', '');
        Config::set('services.google.client_secret', '');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Continue with Google', false);
    }

    public function test_login_page_shows_google_button_when_configured(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google', false)
            ->assertSee(route('auth.google'), false);
    }

    public function test_google_redirect_requires_configuration(): void
    {
        Config::set('services.google.client_id', '');
        Config::set('services.google.client_secret', '');

        $this->get(route('auth.google'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_google_callback_rejects_non_campus_domain(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Config::set('services.google.allowed_domain', 'my.cspc.edu.ph');

        $this->mockGoogleUser('outsider@gmail.com', 'google-uid-1');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');
    }

    public function test_google_callback_sends_unknown_email_to_register(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Config::set('services.google.allowed_domain', 'my.cspc.edu.ph');

        $email = 'nouser.'.uniqid().'@my.cspc.edu.ph';
        $this->mockGoogleUser($email, 'google-uid-missing');

        try {
            User::query()->where('email', $email)->delete();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('error');
    }

    public function test_google_callback_logs_in_existing_granted_user(): void
    {
        Config::set('services.google.client_id', 'test-client-id');
        Config::set('services.google.client_secret', 'test-client-secret');
        Config::set('services.google.allowed_domain', 'my.cspc.edu.ph');
        Config::set('broadcasting.default', 'null');

        $email = 'google.user.'.uniqid().'@my.cspc.edu.ph';
        $user = null;

        try {
            User::query()->where('email', $email)->delete();
            $user = User::query()->create([
                'fullname' => 'Google Test User',
                'email' => $email,
                'password' => bcrypt('password123'),
                'user_role_id' => 3,
                'department_code' => 'CCS',
                'vehicle_id' => 1,
                'id_number' => 'GGL'.strtoupper(substr(uniqid(), -6)),
                'plate_number' => 'GGL'.random_int(100, 999),
                'status' => User::STATUS_GRANTED,
                'Gate_access' => User::GATE_ACCESS_GRANTED,
                'strike_count' => 0,
                'email_verified_at' => now(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB unavailable: '.$e->getMessage());
        }

        $this->mockGoogleUser($email, 'google-uid-ok');

        try {
            $this->get(route('auth.google.callback'))
                ->assertRedirect();

            $this->assertAuthenticatedAs($user);
            $user->refresh();
            $this->assertSame('google-uid-ok', (string) $user->google_id);
        } finally {
            if ($user) {
                $user->delete();
            }
        }
    }

    private function mockGoogleUser(string $email, string $id): void
    {
        $abstract = Mockery::mock(SocialiteUser::class);
        $abstract->shouldReceive('getId')->andReturn($id);
        $abstract->shouldReceive('getEmail')->andReturn($email);
        $abstract->shouldReceive('getName')->andReturn('Google User');
        $abstract->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->andReturn($abstract);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
