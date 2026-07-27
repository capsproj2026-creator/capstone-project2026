<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NavigationService;
use App\Services\ViolationEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user) {
            if (! $user->hasVerifiedEmail()) {
                return redirect()->route('verification.notice');
            }

            if ($user->canAccessPortal()) {
                return redirect()->to(NavigationService::dashboardUrlFor($user));
            }

            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerate(true);
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        try {
            $user = User::query()
                ->with('role')
                ->where('email', $credentials['email'])
                ->first();
        } catch (\Throwable $e) {
            report($e);

            return back()
                ->with('error', 'Database connection is not available. Please try again once the database is configured.')
                ->onlyInput('email');
        }

        if (! $user || ! password_verify($credentials['password'], $user->password)) {
            return back()->with('error', 'Invalid email or password.')->onlyInput('email');
        }

        try {
            app(ViolationEnforcementService::class)->reconcileFromViolationHistory($user);
            $user->refresh();
        } catch (\Throwable $e) {
            report($e);
        }

        // Allow unverified users to authenticate so they can reach the verification page.
        if (! $user->hasVerifiedEmail()) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()
                ->route('verification.notice')
                ->with('error', 'You must verify your email address before accessing the portal. Please check your inbox for the verification link.');
        }

        if ($blockedReason = $user->loginBlockedReason()) {
            return redirect()->route('login')->with('error', $blockedReason)->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->to(NavigationService::dashboardUrlFor($user));
    }

    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerate(true);

        return redirect()
            ->route('login')
            ->with('success', 'You have been signed out. You can sign in again below.')
            ->withHeaders(self::noCacheHeaders());
    }

    /**
     * @return array<string, string>
     */
    public static function noCacheHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ];
    }
}
