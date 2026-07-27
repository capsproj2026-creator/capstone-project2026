<?php

namespace App\Services;

use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RolePermissionService
{
    public const CACHE_KEY = 'role_permissions.matrix';

    /** @var list<string> */
    public const ROLES = ['admin', 'security_head', 'guard'];

    /** @var array<string, string> */
    public const PERMISSION_LABELS = [
        'manage_users' => 'Manage Users',
        'log_violations' => 'Log Violations',
        'clear_penalties' => 'Clear Penalties',
        'view_reports' => 'View Reports',
        'manage_parking' => 'Manage Parking',
        'system_settings' => 'System Settings',
        'manage_admins' => 'Manage Admins',
    ];

    /**
     * Default matrix matching campus policy (and the Settings reference).
     *
     * @return array<string, array{admin: bool, security_head: bool, guard: bool}>
     */
    public function defaults(): array
    {
        return [
            'manage_users' => ['admin' => true, 'security_head' => true, 'guard' => false],
            'log_violations' => ['admin' => true, 'security_head' => true, 'guard' => true],
            'clear_penalties' => ['admin' => true, 'security_head' => true, 'guard' => false],
            'view_reports' => ['admin' => true, 'security_head' => true, 'guard' => true],
            'manage_parking' => ['admin' => true, 'security_head' => true, 'guard' => false],
            'system_settings' => ['admin' => true, 'security_head' => false, 'guard' => false],
            'manage_admins' => ['admin' => true, 'security_head' => false, 'guard' => false],
        ];
    }

    /**
     * @return array<string, array{admin: bool, security_head: bool, guard: bool}>
     */
    public function matrix(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $row = RolePermission::query()->where('id', RolePermission::SINGLETON_ID)->first();
            $defaults = $this->defaults();

            if (! $row || ! is_array($row->matrix)) {
                return $defaults;
            }

            $merged = $defaults;
            foreach ($defaults as $permission => $roles) {
                $stored = $row->matrix[$permission] ?? [];
                foreach (self::ROLES as $role) {
                    if (array_key_exists($role, $stored)) {
                        $merged[$permission][$role] = (bool) $stored[$role];
                    }
                }
            }

            return $merged;
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $incoming
     */
    public function update(array $incoming): array
    {
        $matrix = $this->defaults();

        foreach (array_keys(self::PERMISSION_LABELS) as $permission) {
            foreach (self::ROLES as $role) {
                $matrix[$permission][$role] = (bool) data_get($incoming, "{$permission}.{$role}", false);
            }
        }

        // Always keep at least one path for admins to manage settings/admins
        // so the system cannot be locked out via the UI.
        $matrix['system_settings']['admin'] = true;
        $matrix['manage_admins']['admin'] = true;

        $existing = RolePermission::query()->where('id', RolePermission::SINGLETON_ID)->first();
        if ($existing) {
            $existing->update(['matrix' => $matrix]);
        } else {
            RolePermission::query()->create([
                'id' => RolePermission::SINGLETON_ID,
                'matrix' => $matrix,
            ]);
        }

        Cache::forget(self::CACHE_KEY);

        return $this->matrix();
    }

    public function roleKeyFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $roleId = (int) ($user->user_role_id ?? 0);

        if ($roleId === NavigationService::ROLE_GUARD) {
            return 'guard';
        }

        if ($roleId === NavigationService::ROLE_ADMIN) {
            $title = strtolower(trim((string) ($user->job_title ?? '')));

            return $title === 'security head' ? 'security_head' : 'admin';
        }

        return null;
    }

    public function allows(?User $user, string $permission): bool
    {
        $roleKey = $this->roleKeyFor($user);
        if ($roleKey === null) {
            return false;
        }

        $matrix = $this->matrix();

        return (bool) data_get($matrix, "{$permission}.{$roleKey}", false);
    }

    public function badgeLabel(?User $user): string
    {
        if (! $user) {
            return 'user';
        }

        $title = trim((string) ($user->job_title ?? ''));
        if ($title !== '') {
            return strtolower($title);
        }

        return strtolower($user->roleName());
    }
}
