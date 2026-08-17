<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReviewerEntitlement
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $sessionUser = $request->user();

        if (! $sessionUser) {
            return redirect()->route('login');
        }

        // Do not trust the user instance cached in a long-lived authenticated session.
        // This fresh database check makes a disable action effective on the next request.
        $entitled = User::query()
            ->whereKey($sessionUser->getKey())
            ->reviewerEnabled()
            ->exists();

        if (! $entitled) {
            try {
                app(AuditLogService::class)->record(
                    $sessionUser,
                    'auth.reviewer_entitlement_denied',
                    $sessionUser,
                    [
                        'route' => $request->route()?->getName(),
                        'result' => 'denied',
                    ],
                );
            } catch (\Throwable) {
                // Entitlement enforcement fails closed if audit persistence is unavailable.
            }

            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
