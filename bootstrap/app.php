<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventBrowserHistory;
use App\Http\Middleware\RedirectAuthenticatedUser;
use App\Http\Middleware\ShareDashboardContext;
use App\Services\AuditLogService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'guest.role' => RedirectAuthenticatedUser::class,
            'no-store' => PreventBrowserHistory::class,
            'role' => EnsureUserHasRole::class,
            'dashboard.context' => ShareDashboardContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            try {
                // Route identity is sufficient for investigation; payloads may contain secrets or files.
                app(AuditLogService::class)->record($request->user(), 'auth.authorization_denied', $request->user(), [
                    'route' => $request->route()?->getName(),
                    'result' => 'denied',
                ]);
            } catch (Throwable) {
                // Authorization still fails closed if audit persistence is unavailable.
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
