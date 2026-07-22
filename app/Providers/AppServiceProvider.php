<?php

namespace App\Providers;

use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Policies\ResearchApplicationPolicy;
use App\Policies\ReviewerAssignmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        Gate::policy(ResearchApplication::class, ResearchApplicationPolicy::class);
        Gate::policy(ReviewerAssignment::class, ReviewerAssignmentPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureRateLimits();
    }

    private function configureRateLimits(): void
    {
        $key = fn (Request $request): string => (string) ($request->user()?->id ?? $request->ip());

        RateLimiter::for('account-write', fn (Request $request) => Limit::perMinute(10)->by($key($request)));
        RateLimiter::for('account-import', fn (Request $request) => Limit::perMinute(5)->by($key($request)));
        RateLimiter::for('import-confirm', fn (Request $request) => Limit::perMinute(5)->by($key($request)));
        RateLimiter::for('setup-email', fn (Request $request) => Limit::perMinute(3)->by($key($request)));
        RateLimiter::for('account-mass-action', fn (Request $request) => Limit::perMinute(3)->by($key($request)));
        RateLimiter::for('notification-actions', fn (Request $request) => Limit::perMinute(20)->by($key($request)));
        RateLimiter::for('onboarding', fn (Request $request) => Limit::perMinute(10)->by($key($request)));
        RateLimiter::for('application-submit', fn (Request $request) => Limit::perMinute(5)->by($key($request)));
    }
}
