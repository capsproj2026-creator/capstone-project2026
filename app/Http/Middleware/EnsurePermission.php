<?php

namespace App\Http\Middleware;

use App\Services\NavigationService;
use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly RolePermissionService $permissions,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user || ! $this->permissions->allows($user, $permission)) {
            return redirect()
                ->to(NavigationService::dashboardUrlFor($user))
                ->with('error', 'You do not have permission to perform that action.');
        }

        return $next($request);
    }
}
