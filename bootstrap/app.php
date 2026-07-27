<?php

use App\Services\NavigationService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
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
            $request->session()->regenerate(true);

            return $request->routeIs('register') ? route('register') : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->routeIs('logout') || $request->is('logout') || $request->path() === 'logout') {
                if (Auth::check()) {
                    Auth::guard('web')->logout();
                }
                $request->session()->invalidate();
                $request->session()->regenerate(true);

                return redirect()
                    ->route('login')
                    ->with('error', 'Your session expired. You have been signed out — please sign in again.');
            }

            if ($request->isMethod('post') && Auth::check()) {
                return redirect()
                    ->back()
                    ->withInput($request->except('password', '_token', 'current_password', 'new_password', 'new_password_confirmation'))
                    ->with('error', 'Your session expired. Refresh the page, then try again — or use Sign out (session expired?) from the profile menu.');
            }

            if (Auth::check()) {
                Auth::guard('web')->logout();
            }
            $request->session()->invalidate();
            $request->session()->regenerate(true);

            $message = 'Your session expired. Please sign in again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            $redirect = $request->routeIs('register') ? route('register') : route('login');

            return redirect($redirect)
                ->withInput($request->except('password', '_token'))
                ->with('error', $message);
        });
    })->create();
