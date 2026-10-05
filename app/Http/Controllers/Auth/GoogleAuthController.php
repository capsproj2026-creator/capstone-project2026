<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\ViolationEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    public static function isConfigured(): bool
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $clientSecret = trim((string) config('services.google.client_secret', ''));

        return $clientId !== '' && $clientSecret !== '';
    }

    /**
     * Google sign-in domains from GOOGLE_ALLOWED_DOMAIN (comma-separated). Empty = any domain.
     *
     * @return list<string>
     */
    public static function allowedDomains(): array
    {
        $raw = (string) config('services.google.allowed_domain', '');

        return array_values(array_unique(array_filter(array_map(
            fn (string $d) => ltrim(strtolower(trim($d)), '@'),
            explode(',', $raw)
        ))));
    }

    public static function emailDomainAllowed(string $email): bool
    {
        $domains = self::allowedDomains();
        if ($domains === []) {
            return true;
        }

        // Exact domain after "@": cspc.edu.ph must not also admit evil-cspc.edu.ph.
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));

        return in_array($domain, $domains, true);
    }

    public function redirect(): RedirectResponse|SymfonyRedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()
                ->route('login')
                ->with('error', 'Google sign-in is not configured yet. Ask an admin to set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET.');
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        if (! self::isConfigured()) {
            return redirect()
                ->route('login')
                ->with('error', 'Google sign-in is not configured.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::warning('Google OAuth callback failed', ['message' => $e->getMessage()]);

            return redirect()
                ->route('login')
                ->with('error', 'Google sign-in was cancelled or failed. Please try again.');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            return redirect()
                ->route('login')
                ->with('error', 'Google did not return an email address for that account.');
        }

        $allowedDomains = self::allowedDomains();
        if ($allowedDomains !== [] && ! self::emailDomainAllowed($email)) {
            $list = implode(', ', array_map(fn (string $d) => '@'.$d, $allowedDomains));

            return redirect()
                ->route('login')
                ->with('error', "Only {$list} Google accounts can sign in.");
        }

        try {
            $user = User::query()
                ->with('role')
                ->where('email', $email)
                ->first();

            if (! $user && filled($googleUser->getId())) {
                $user = User::query()
                    ->with('role')
                    ->where('google_id', (string) $googleUser->getId())
                    ->first();
            }
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->with('error', 'Database connection is not available. Please try again once the database is configured.');
        }

        if (! $user) {
            return redirect()
                ->route('register')
                ->with('error', 'No campus account exists for '.$email.'. Register first, then use Continue with Google.')
                ->withInput(['email' => $email]);
        }

        $updates = [];
        if (filled($googleUser->getId()) && (string) ($user->google_id ?? '') !== (string) $googleUser->getId()) {
            $updates['google_id'] = (string) $googleUser->getId();
        }
        if (! $user->hasVerifiedEmail()) {
            $updates['email_verified_at'] = now();
            $updates['email_verification_token'] = null;
            $updates['email_verification_expires_at'] = null;
        }
        if ($updates !== []) {
            $user->fill($updates);
            $user->save();
        }

        try {
            app(ViolationEnforcementService::class)->reconcileFromViolationHistory($user);
            $user->refresh();
        } catch (Throwable $e) {
            report($e);
        }

        if ($blockedReason = $user->loginBlockedReason()) {
            return redirect()->route('login')->with('error', $blockedReason);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->to(NavigationService::dashboardUrlFor($user));
    }
}
