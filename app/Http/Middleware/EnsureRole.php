<?php

namespace App\Http\Middleware;

use App\Services\NavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessPortal()) {
            return redirect()->route('login')->with('error', $user?->loginBlockedReason() ?? 'Please sign in to continue.');
        }

        $allowed = collect($roles)
            ->flatMap(fn (string $role) => array_map('trim', explode(',', $role)))
            ->map(fn (string $role) => strtolower($role))
            ->all();

        $userRole = strtolower($user->roleName());

        if (! in_array($userRole, $allowed, true)) {
            return redirect()
                ->to(NavigationService::dashboardUrlFor($user))
                ->with('error', 'You do not have permission to access that area.');
        }

        return $next($request);
    }
}
