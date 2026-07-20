<?php

namespace App\Providers;

use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Policies\ResearchApplicationPolicy;
use App\Policies\ReviewerAssignmentPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
