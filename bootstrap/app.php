<?php

use App\Services\NavigationService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->authenticateSessions();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureRole::class,
            'granted' => \App\Http\Middleware\EnsureGranted::class,
            'no.cache' => \App\Http\Middleware\PreventPageCache::class,
            'permission' => \App\Http\Middleware\EnsurePermission::class,
        ]);

        // Default "guest" middleware sends authenticated users to route('home').
        // That caused Login / Register links to bounce back to the welcome page.
        $middleware->redirectUsersTo(function (Request $request) {
            $user = Auth::user();

            if (! $user) {
                return route('login');
            }

            if (! $user->hasVerifiedEmail()) {
                return route('verification.notice');
            }

            if ($user->canAccessPortal()) {
                return NavigationService::dashboardUrlFor($user);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->routeIs('register') ? route('register') : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $sessionExpiredMessage = 'Session expired. Please sign in again.';

        $handleExpiredSession = function (Request $request) use ($sessionExpiredMessage) {
            if (Auth::check()) {
                Auth::guard('web')->logout();
            }

            try {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            } catch (\Throwable) {
                // Session may already be unusable when the CSRF token is stale.
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => $sessionExpiredMessage], 419);
            }

            $redirect = ($request->routeIs('register') || $request->is('register'))
                ? route('register')
                : route('login');

            return redirect($redirect)
                ->withInput($request->except('password', '_token', 'current_password', 'new_password', 'new_password_confirmation', 'password_confirmation'))
                ->with('error', $sessionExpiredMessage);
        };

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($handleExpiredSession) {
            return $handleExpiredSession($request);
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($handleExpiredSession) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return $handleExpiredSession($request);
        });
    })->create();
