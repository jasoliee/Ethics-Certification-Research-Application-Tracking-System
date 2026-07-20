<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\ModulePageController;
use App\Http\Controllers\Dashboard\NotificationPageController;
use App\Http\Controllers\Dashboard\ProfilePageController;
use App\Http\Controllers\Dashboard\ResearchApplicationPageController;
use App\Http\Controllers\Dashboard\ReviewerAssignmentPageController;
use App\Http\Controllers\Identity\UserManagementController;
use App\Support\RoleHome;
use Illuminate\Support\Facades\Route;

Route::middleware('no-store')->group(function (): void {
    Route::get('/', function () {
        if (auth()->check()) {
            return redirect()->route(RoleHome::routeNameFor(auth()->user()->role));
        }

        return view('auth.login');
    })->name('home');

    Route::middleware('guest.role')->group(function (): void {
        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
            ->middleware('throttle:12,1')
            ->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('password.update');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth')
        ->name('logout');

    Route::middleware(['auth', 'dashboard.context'])->group(function (): void {
        // All roles enter through one stable URL while retaining role-specific data and authorization.
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::post('/notifications/mark-all-read', [NotificationPageController::class, 'markAllRead'])
            ->name('notifications.mark-all-read');

        Route::prefix('student-faculty-researcher')
            ->name('applicant.')
            ->middleware('role:student_faculty_researcher')
            ->group(function (): void {
                Route::redirect('/landing', '/dashboard')->name('landing');
                Route::get('/applications', ModulePageController::class)
                    ->defaults('pageTitle', 'Application')
                    ->defaults('moduleTitle', 'Application Workspace')
                    ->defaults('moduleMessage', 'Your research ethics applications will be managed here.')
                    ->defaults('moduleIcon', 'file-text')
                    ->name('applications.index');
                Route::get('/applications/create', ModulePageController::class)
                    ->defaults('pageTitle', 'Create Application')
                    ->defaults('moduleTitle', 'Create Application')
                    ->defaults('moduleMessage', 'The application form will be available in this workspace.')
                    ->defaults('moduleIcon', 'file-plus')
                    ->name('applications.create');
                Route::get('/applications/{researchApplication}', [ResearchApplicationPageController::class, 'show'])
                    ->name('applications.show');
                Route::get('/applications/{researchApplication}/requirements', [ResearchApplicationPageController::class, 'requirements'])
                    ->name('applications.requirements');
                Route::get('/revision-certificates', ModulePageController::class)
                    ->defaults('pageTitle', 'Revision and Certificates')
                    ->defaults('moduleTitle', 'Revision and Certificates')
                    ->defaults('moduleMessage', 'Revision milestones, reviewer feedback, and released certificates will appear here.')
                    ->defaults('moduleIcon', 'award')
                    ->name('revision-certificates.index');
                Route::redirect('/reviewer', '/student-faculty-researcher/revision-certificates')->name('reviewer.index');
                Route::redirect('/certificates', '/student-faculty-researcher/revision-certificates')->name('certificates.index');
                Route::get('/reports', ModulePageController::class)
                    ->defaults('pageTitle', 'Reports')
                    ->defaults('moduleMessage', 'Your application reports will appear here.')
                    ->defaults('moduleIcon', 'chart')
                    ->name('reports.index');
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::get('/settings', ModulePageController::class)
                    ->defaults('pageTitle', 'Settings')
                    ->defaults('moduleMessage', 'Account settings will be managed here.')
                    ->defaults('moduleIcon', 'settings')
                    ->name('settings.index');
            });

        Route::prefix('adviser')
            ->name('adviser.')
            ->middleware('role:adviser')
            ->group(function (): void {
                Route::redirect('/landing', '/dashboard')->name('landing');
                Route::get('/applications', ModulePageController::class)
                    ->defaults('pageTitle', 'Application')
                    ->defaults('moduleTitle', 'Submitted Applications')
                    ->defaults('moduleMessage', 'Applications submitted for adviser endorsement will appear here.')
                    ->defaults('moduleIcon', 'file-text')
                    ->name('applications.index');
                Route::get('/applications/{researchApplication}', [ResearchApplicationPageController::class, 'show'])
                    ->name('applications.show');
                Route::controller(UserManagementController::class)->prefix('applicants')->name('applicants.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/import', 'importForm')->name('import.form');
                    Route::post('/import', 'import')->name('import.store');
                    Route::get('/import/template', 'template')->name('import.template');
                    Route::get('/{managedUser}', 'show')->name('show');
                    Route::get('/{managedUser}/edit', 'edit')->name('edit');
                    Route::put('/{managedUser}', 'update')->name('update');
                });
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::get('/settings', ModulePageController::class)
                    ->defaults('pageTitle', 'Settings')
                    ->defaults('moduleMessage', 'Account settings will be managed here.')
                    ->defaults('moduleIcon', 'settings')
                    ->name('settings.index');
            });

        Route::prefix('reviewer')
            ->name('reviewer.')
            ->middleware('role:reviewer')
            ->group(function (): void {
                Route::redirect('/landing', '/dashboard')->name('landing');
                Route::get('/assignments', ModulePageController::class)
                    ->defaults('pageTitle', 'Assignments')
                    ->defaults('moduleTitle', 'Assigned Reviews')
                    ->defaults('moduleMessage', 'Your assigned ethics reviews will appear here.')
                    ->defaults('moduleIcon', 'clipboard')
                    ->name('assignments.index');
                Route::get('/assignments/{reviewerAssignment}', ReviewerAssignmentPageController::class)
                    ->name('assignments.show');
                Route::get('/reviews', ModulePageController::class)
                    ->defaults('pageTitle', 'Review')
                    ->defaults('moduleTitle', 'Review Workspace')
                    ->defaults('moduleMessage', 'Review forms and submitted decisions will be managed here.')
                    ->defaults('moduleIcon', 'file-search')
                    ->name('reviews.index');
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::get('/settings', ModulePageController::class)
                    ->defaults('pageTitle', 'Settings')
                    ->defaults('moduleMessage', 'Account settings will be managed here.')
                    ->defaults('moduleIcon', 'settings')
                    ->name('settings.index');
            });

        Route::prefix('res-lead')
            ->name('res.')
            ->middleware('role:res_lead')
            ->group(function (): void {
                Route::redirect('/landing', '/dashboard')->name('landing');
                Route::get('/applications', ModulePageController::class)
                    ->defaults('pageTitle', 'Applications')
                    ->defaults('moduleTitle', 'Application Screening')
                    ->defaults('moduleMessage', 'Endorsed applications awaiting RES action will appear here.')
                    ->defaults('moduleIcon', 'file-text')
                    ->name('applications.index');
                Route::get('/applications/{researchApplication}', [ResearchApplicationPageController::class, 'show'])
                    ->name('applications.show');
                Route::get('/review-monitoring', ModulePageController::class)
                    ->defaults('pageTitle', 'Review Monitoring')
                    ->defaults('moduleMessage', 'Reviewer assignments, capacity, and progress will be monitored here.')
                    ->defaults('moduleIcon', 'users')
                    ->name('review-monitoring.index');
                Route::get('/certificates', ModulePageController::class)
                    ->defaults('pageTitle', 'Certificates')
                    ->defaults('moduleMessage', 'Certificate release and hold actions will be managed here.')
                    ->defaults('moduleIcon', 'award')
                    ->name('certificates.index');
                Route::get('/reports', ModulePageController::class)
                    ->defaults('pageTitle', 'Reports')
                    ->defaults('moduleMessage', 'Operational and ethics review reports will be available here.')
                    ->defaults('moduleIcon', 'chart')
                    ->name('reports.index');
                Route::controller(UserManagementController::class)->prefix('users')->name('users.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->name('store');
                    Route::get('/import', 'importForm')->name('import.form');
                    Route::post('/import', 'import')->name('import.store');
                    Route::get('/import/template', 'template')->name('import.template');
                    Route::get('/{managedUser}', 'show')->name('show');
                    Route::get('/{managedUser}/edit', 'edit')->name('edit');
                    Route::put('/{managedUser}', 'update')->name('update');
                    Route::patch('/{managedUser}/status', 'changeStatus')->name('status');
                    Route::post('/{managedUser}/password-reset', 'sendPasswordReset')->middleware('throttle:3,1')->name('password-reset');
                });
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::get('/settings', ModulePageController::class)
                    ->defaults('pageTitle', 'Settings')
                    ->defaults('moduleMessage', 'RES configuration and account settings will be managed here.')
                    ->defaults('moduleIcon', 'settings')
                    ->name('settings.index');
            });
    });
});
