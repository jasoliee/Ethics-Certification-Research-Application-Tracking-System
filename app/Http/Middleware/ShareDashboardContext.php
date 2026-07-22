<?php

namespace App\Http\Middleware;

use App\Support\DashboardNavigation;
use App\Support\OnboardingGuide;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ShareDashboardContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $fallbackRoute = DashboardNavigation::notificationsRoute($user->role);

            // The shared header needs only the four newest notifications and their display fields.
            $notifications = $user->notifications()
                ->select(['id', 'data', 'read_at', 'created_at'])
                ->latest()
                ->limit(4)
                ->get()
                ->map(function ($notification) use ($fallbackRoute): array {
                    $routeName = $notification->data['route'] ?? null;
                    $routeParameters = $notification->data['route_parameters'] ?? [];

                    return [
                        'id' => $notification->id,
                        'title' => $notification->data['title'] ?? 'ECRATS update',
                        'message' => $notification->data['message'] ?? 'There is a new update on your account.',
                        'icon' => $notification->data['icon'] ?? 'bell',
                        'tone' => $notification->data['tone'] ?? 'green',
                        'url' => $this->notificationUrl($routeName, $routeParameters, $fallbackRoute),
                        'time' => $notification->created_at?->diffForHumans() ?? '',
                        'unread' => $notification->read_at === null,
                    ];
                });

            view()->share([
                'dashboardNavigation' => DashboardNavigation::for($user->role),
                'dashboardNotifications' => $notifications,
                'dashboardUnreadCount' => $user->unreadNotifications()->count(),
                'dashboardNotificationsRoute' => $fallbackRoute,
                'dashboardSettingsRoute' => DashboardNavigation::settingsRoute($user->role),
                'dashboardProfileRoute' => DashboardNavigation::profileRoute($user->role),
                'dashboardRoleLabel' => $user->displayRoleLabel(),
                'dashboardOnboardingGuide' => OnboardingGuide::for($user),
                'dashboardRequiresOnboarding' => $user->password_setup_completed_at !== null
                    && $user->onboarding_completed_at === null,
                'dashboardUserInitials' => Str::of($user->name)
                    ->explode(' ')
                    ->filter()
                    ->take(2)
                    ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))
                    ->implode(''),
            ]);
        }

        return $next($request);
    }

    private function notificationUrl(mixed $routeName, mixed $parameters, string $fallbackRoute): string
    {
        if (! is_string($routeName) || ! Route::has($routeName)) {
            return route($fallbackRoute);
        }

        try {
            return route($routeName, is_array($parameters) ? $parameters : []);
        } catch (\Throwable) {
            return route($fallbackRoute);
        }
    }
}
