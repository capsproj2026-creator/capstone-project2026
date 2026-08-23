<?php

namespace App\Providers;

use App\Models\GateLog;
use App\Models\Notification;
use App\Observers\GateLogObserver;
use App\Services\NavigationService;
use App\Services\SystemSettingService;
use App\Support\AppDateTime;
use App\Support\MongoConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureApplicationTimezone();

        Event::listen(DiagnosingHealth::class, function (): void {
            if (config('database.default') !== 'mongodb') {
                return;
            }

            MongoConnection::ping();
        });

        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key)->response(function () {
                return back()
                    ->withInput(request()->only('email'))
                    ->with('error', 'Too many login attempts. Please try again in a minute.');
            });
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip())->response(function () {
                return back()
                    ->withInput(request()->except('password', '_token'))
                    ->with('error', 'Too many registration attempts. Please try again later.');
            });
        });

        RateLimiter::for('register-scan-id', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip())->response(function () {
                if (request()->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Too many ID scan attempts. Please wait about a minute, then try again.',
                        'id_number' => null,
                        'first_name' => null,
                        'last_name' => null,
                        'middle_name' => null,
                        'warnings' => [],
                    ], 429);
                }

                return response('Too Many Attempts.', 429);
            });
        });

        RateLimiter::for('visitor-pre-register', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip())->response(function () {
                return back()
                    ->withInput(request()->except('_token', 'website'))
                    ->withErrors(['form' => 'Too many pre-registration attempts. Please try again in a minute.']);
            });
        });

        GateLog::observe(GateLogObserver::class);

        View::share('appTimezone', AppDateTime::timezone());

        View::composer('layouts.app', function ($view): void {
            $user = Auth::user();

            if (! $user) {
                $view->with([
                    'notificationCount' => 0,
                    'notificationsUrl' => null,
                ]);

                return;
            }

            $roleId = (int) ($user->user_role_id ?? 0);
            $notificationsRoute = NavigationService::notificationsRouteFor($user);

            if ($notificationsRoute === null) {
                $view->with([
                    'notificationCount' => 0,
                    'notificationsUrl' => null,
                ]);

                return;
            }

            if ($roleId === NavigationService::ROLE_GUARD) {
                $notificationCount = Notification::query()
                    ->unread()
                    ->where('user_id', $user->id)
                    ->whereIn('type', ['System', 'General', 'Parking', 'Update'])
                    ->count();
            } else {
                $notificationCount = Notification::query()
                    ->unread()
                    ->where('user_id', $user->id)
                    ->count();
            }

            $view->with([
                'notificationCount' => $notificationCount,
                'notificationsUrl' => route($notificationsRoute),
            ]);
        });
    }

    private function configureApplicationTimezone(): void
    {
        $timezone = AppDateTime::DEFAULT_TIMEZONE;

        try {
            $configured = app(SystemSettingService::class)->get('timezone', AppDateTime::DEFAULT_TIMEZONE);
            if (is_string($configured) && $configured !== '' && in_array($configured, timezone_identifiers_list(), true)) {
                $timezone = $configured;
            }
        } catch (Throwable) {
            // Keep Asia/Manila when settings/DB are unavailable during boot.
        }

        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
