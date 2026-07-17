<?php

namespace App\Http\Middleware;

use App\Support\RoleHome;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAuthenticatedUser
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route(RoleHome::routeNameFor($request->user()->role));
        }

        return $next($request);
    }
}
