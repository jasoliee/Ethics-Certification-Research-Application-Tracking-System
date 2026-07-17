<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\RoleHome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowedRoles = array_map(
            fn (string $role): string => UserRole::tryFrom($role)?->value ?? $role,
            $roles,
        );

        if (! in_array($user->role?->value, $allowedRoles, true)) {
            return redirect()->route(RoleHome::routeNameFor($user->role));
        }

        return $next($request);
    }
}
