<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NavigationService
{
    /** Role IDs from user_roles collection (see CapstoneSeeder). */
    public const ROLE_ADMIN = 1;

    public const ROLE_GUARD = 2;

    public const ROLE_STUDENT = 3;

    public const ROLE_STAFF = 4;

    public const ROLE_VISITOR = 5;

    public static function routesForRole(?string $roleName): array
    {
        $role = strtolower($roleName ?? 'user');

        $routes = [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'access' => ['admin']],
            ['label' => 'Dashboard', 'route' => 'guard.dashboard', 'icon' => 'layout-dashboard', 'access' => ['guard']],
            ['label' => 'Dashboard', 'route' => 'user.dashboard', 'icon' => 'layout-dashboard', 'access' => ['student', 'staff']],

            ['label' => 'Registrations', 'route' => 'admin.registrations', 'icon' => 'user-plus', 'access' => ['admin']],
            ['label' => 'RFID Assignment', 'route' => 'admin.rfid', 'icon' => 'hash', 'access' => ['admin']],
            ['label' => 'User Management', 'route' => 'admin.users', 'icon' => 'users', 'access' => ['admin']],
            ['label' => 'Registered Visitors', 'route' => 'admin.visitors.active', 'icon' => 'user-round-check', 'access' => ['admin']],
            ['label' => 'Visitor History', 'route' => 'admin.visitors.history', 'icon' => 'history', 'access' => ['admin']],
            ['label' => 'Violations', 'route' => 'admin.violations', 'icon' => 'triangle-alert', 'access' => ['admin']],
            ['label' => 'Access Logs', 'route' => 'admin.access-logs', 'icon' => 'file-text', 'access' => ['admin']],
            ['label' => 'Parking', 'route' => 'admin.parking', 'icon' => 'parking-square', 'access' => ['admin']],
            ['label' => 'Live Cameras', 'route' => 'admin.live-cameras', 'icon' => 'camera', 'access' => ['admin']],
            ['label' => 'Reports', 'route' => 'admin.reports', 'icon' => 'bar-chart-3', 'access' => ['admin']],
            ['label' => 'Settings', 'route' => 'admin.settings', 'icon' => 'settings', 'access' => ['admin']],

            ['label' => 'Live Gate Monitor', 'route' => 'guard.gate', 'icon' => 'activity', 'access' => ['guard']],
            ['label' => 'User Monitor', 'route' => 'guard.monitor', 'icon' => 'users', 'access' => ['guard']],
            ['label' => 'Register Visitor', 'route' => 'guard.visitors.register', 'icon' => 'clipboard-plus', 'access' => ['guard']],
            ['label' => 'Active Visitors', 'route' => 'guard.visitors.active', 'icon' => 'user-round-check', 'access' => ['guard']],
            ['label' => 'Visitor History', 'route' => 'guard.visitors.history', 'icon' => 'history', 'access' => ['guard']],
            ['label' => 'Violations', 'route' => 'guard.violations', 'icon' => 'triangle-alert', 'access' => ['guard']],
            ['label' => 'Updates', 'route' => 'guard.notifications', 'icon' => 'bell', 'access' => ['guard']],
            ['label' => 'Access Logs', 'route' => 'guard.access-logs', 'icon' => 'file-text', 'access' => ['guard']],
            ['label' => 'Parking', 'route' => 'guard.parking', 'icon' => 'parking-square', 'access' => ['guard']],
            ['label' => 'AI Parking Monitor', 'route' => 'guard.ai-parking', 'icon' => 'scan', 'access' => ['guard']],
            ['label' => 'Live Cameras', 'route' => 'guard.live-cameras', 'icon' => 'camera', 'access' => ['guard']],

            ['label' => 'Notifications', 'route' => 'user.notifications', 'icon' => 'bell', 'access' => ['student', 'staff']],
            ['label' => 'My Violations', 'route' => 'user.violations', 'icon' => 'triangle-alert', 'access' => ['student', 'staff']],
            ['label' => 'Entry/Exit History', 'route' => 'user.entry-exit', 'icon' => 'history', 'access' => ['student', 'staff']],
            ['label' => 'Parking', 'route' => 'user.parking', 'icon' => 'parking-square', 'access' => ['student', 'staff']],
        ];

        return array_values(array_filter($routes, fn (array $item) => in_array($role, $item['access'], true)));
    }

    /**
     * Named route for the signed-in user's portal dashboard.
     */
    public static function dashboardRouteFor(?User $user = null): string
    {
        $user ??= Auth::user();

        if (! $user) {
            return 'login';
        }

        $user->loadMissing('role');

        $roleId = (int) ($user->user_role_id ?? 0);

        return match ($roleId) {
            self::ROLE_ADMIN => 'admin.dashboard',
            self::ROLE_GUARD => 'guard.dashboard',
            self::ROLE_STUDENT, self::ROLE_STAFF => 'user.dashboard',
            default => match (strtolower($user->roleName())) {
                'admin' => 'admin.dashboard',
                'guard' => 'guard.dashboard',
                'student', 'staff' => 'user.dashboard',
                default => 'home',
            },
        };
    }

    /**
     * Full URL path for the user's dashboard (/admin, /guard, or /user).
     */
    public static function dashboardUrlFor(?User $user = null): string
    {
        return route(self::dashboardRouteFor($user));
    }

    /** @deprecated Use dashboardRouteFor() */
    public static function dashboardRouteForUser(): string
    {
        return self::dashboardRouteFor();
    }

    public static function notificationsRouteFor(?User $user = null): ?string
    {
        $user ??= Auth::user();

        if (! $user) {
            return null;
        }

        $roleId = (int) ($user->user_role_id ?? 0);

        if (in_array($roleId, [self::ROLE_STUDENT, self::ROLE_STAFF], true)) {
            return 'user.notifications';
        }

        if ($roleId === self::ROLE_GUARD) {
            return 'guard.notifications';
        }

        return null;
    }

    public static function navActiveClassForRole(?string $roleName): string
    {
        // Shared CSPC navy active state; role portals keep identity via profile accent.
        return 'portal-nav-item--active bg-[var(--cspc-navy-soft)] text-[var(--cspc-navy)] shadow-sm';
    }
}
