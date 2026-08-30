<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows fully granted users and declined-remedial users (limited portal).
 */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->canAccessPortal()) {
            $message = $user->loginBlockedReason() ?? 'You do not have access to this system.';
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerate(true);

            return redirect()->route('login')->with('error', $message);
        }

        return $next($request);
    }
}
