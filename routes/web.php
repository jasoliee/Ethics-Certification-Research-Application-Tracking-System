<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Dashboard\AdviserApplicationController;
use App\Http\Controllers\Dashboard\AdviserEndorsementController;
use App\Http\Controllers\Dashboard\ApplicantApplicationController;
use App\Http\Controllers\Dashboard\ApplicationDocumentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\ModulePageController;
use App\Http\Controllers\Dashboard\NotificationPageController;
use App\Http\Controllers\Dashboard\OnboardingController;
use App\Http\Controllers\Dashboard\ProfilePageController;
use App\Http\Controllers\Dashboard\ResearchApplicationPageController;
use App\Http\Controllers\Dashboard\ResLeadApplicationController;
use App\Http\Controllers\Dashboard\ReviewerAssignmentPageController;
use App\Http\Controllers\Identity\UserManagementController;
use App\Http\Controllers\Settings\ResLeadSettingsController;
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
            ->middleware('throttle:notification-actions')
            ->name('notifications.mark-all-read');
        Route::post('/onboarding/complete', OnboardingController::class)
            ->middleware('throttle:onboarding')
            ->name('onboarding.complete');

        Route::prefix('student-faculty-researcher')
            ->name('applicant.')
            ->middleware('role:student_faculty_researcher')
            ->group(function (): void {
                Route::redirect('/landing', '/dashboard')->name('landing');
                // Applicant application routes keep draft creation, information updates, documents, and submission explicit.
                Route::get('/applications', [ApplicantApplicationController::class, 'index'])
                    ->name('applications.index');
                Route::get('/applications/create', [ApplicantApplicationController::class, 'create'])
                    ->name('applications.create');
                Route::post('/applications', [ApplicantApplicationController::class, 'store'])
                    ->middleware('throttle:application-write')
                    ->name('applications.store');
                Route::get('/applications/{researchApplication}/edit', [ApplicantApplicationController::class, 'edit'])
                    ->name('applications.edit');
                Route::put('/applications/{researchApplication}', [ApplicantApplicationController::class, 'update'])
                    ->middleware('throttle:application-write')
                    ->name('applications.update');
                Route::delete('/applications/{researchApplication}', [ApplicantApplicationController::class, 'destroy'])
                    ->middleware('throttle:application-write')
                    ->name('applications.destroy');
                Route::get('/applications/{researchApplication}', [ResearchApplicationPageController::class, 'show'])
                    ->name('applications.show');
                Route::get('/applications/{researchApplication}/requirements', [ResearchApplicationPageController::class, 'requirements'])
                    ->name('applications.requirements');
                Route::post('/applications/{researchApplication}/requirements/upload-all', [ApplicationDocumentController::class, 'storeMany'])
                    ->middleware('throttle:application-upload')
                    ->name('applications.documents.store-all');
                Route::post('/applications/{researchApplication}/requirements/{documentRequirement}', [ApplicationDocumentController::class, 'store'])
                    ->middleware('throttle:application-upload')
                    ->name('applications.documents.store');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/preview', [ApplicationDocumentController::class, 'preview'])
                    ->name('applications.documents.preview');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
                    ->name('applications.documents.download');
                Route::delete('/applications/{researchApplication}/documents/{applicationDocument}', [ApplicationDocumentController::class, 'destroy'])
                    ->middleware('throttle:application-write')
                    ->name('applications.documents.destroy');
                Route::post('/applications/{researchApplication}/submit', [ResearchApplicationPageController::class, 'submit'])
                    ->middleware('throttle:application-submit')
                    ->name('applications.submit');
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
                // Adviser application routes expose only formally submitted, assigned records and protected files.
                Route::get('/applications', [AdviserApplicationController::class, 'index'])
                    ->name('applications.index');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/preview', [ApplicationDocumentController::class, 'preview'])
                    ->name('applications.documents.preview');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
                    ->name('applications.documents.download');
                Route::get('/applications/{researchApplication}', [ResearchApplicationPageController::class, 'show'])
                    ->name('applications.show');
                Route::post('/applications/{researchApplication}/endorse', [AdviserEndorsementController::class, 'endorse'])
                    ->middleware('throttle:application-write')
                    ->name('applications.endorse');
                Route::post('/applications/{researchApplication}/return', [AdviserEndorsementController::class, 'returnForCorrection'])
                    ->middleware('throttle:application-write')
                    ->name('applications.return');
                Route::controller(UserManagementController::class)->prefix('applicants')->name('applicants.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->middleware('throttle:account-write')->name('store');
                    Route::get('/import', 'importForm')->name('import.form');
                    Route::post('/import', 'import')->middleware('throttle:account-import')->name('import.store');
                    Route::post('/import/confirm', 'confirmImport')->middleware('throttle:import-confirm')->name('import.confirm');
                    // Rate-limit verified workbook generation while retaining the Adviser role and catalog checks.
                    Route::get('/import/template', 'template')->middleware('throttle:account-template')->name('import.template');
                    Route::get('/{managedUser}', 'show')->name('show');
                    Route::get('/{managedUser}/edit', 'edit')->name('edit');
                    Route::put('/{managedUser}', 'update')->middleware('throttle:account-write')->name('update');
                    Route::patch('/{managedUser}/username', 'regenerateUsername')->middleware('throttle:account-write')->name('username');
                    Route::post('/{managedUser}/password-reset', 'sendPasswordReset')->middleware('throttle:setup-email')->name('password-reset');
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
                Route::get('/assignments', [ReviewerAssignmentPageController::class, 'index'])
                    ->name('assignments.index');
                Route::get('/assignments/{reviewerAssignment}', [ReviewerAssignmentPageController::class, 'show'])
                    ->name('assignments.show');
                // Reviewer document access remains nested, assignment-gated, and private-disk backed.
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/preview', [ApplicationDocumentController::class, 'preview'])
                    ->name('applications.documents.preview');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
                    ->name('applications.documents.download');
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
                Route::get('/applications', [ResLeadApplicationController::class, 'index'])
                    ->name('applications.index');
                Route::get('/applications/{researchApplication}', [ResLeadApplicationController::class, 'show'])
                    ->name('applications.show');
                Route::post('/applications/{researchApplication}/classification', [ResLeadApplicationController::class, 'classify'])
                    ->middleware('throttle:res-workflow')
                    ->name('applications.classification.store');
                // Screening corrections use a separate idempotent route and the same bounded write throttle.
                Route::put('/applications/{researchApplication}/classification', [ResLeadApplicationController::class, 'updateScreening'])
                    ->middleware('throttle:res-workflow')
                    ->name('applications.classification.update');
                Route::get('/applications/{researchApplication}/reviewers', [ResLeadApplicationController::class, 'reviewers'])
                    ->name('applications.reviewers.index');
                Route::post('/applications/{researchApplication}/reviewers', [ResLeadApplicationController::class, 'assignReviewers'])
                    ->middleware('throttle:res-workflow')
                    ->name('applications.reviewers.store');
                // RES detail access also streams protected documents through authorization-aware controller routes.
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/preview', [ApplicationDocumentController::class, 'preview'])
                    ->name('applications.documents.preview');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
                    ->name('applications.documents.download');
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
                    Route::post('/', 'store')->middleware('throttle:account-write')->name('store');
                    Route::get('/import', 'importForm')->name('import.form');
                    Route::post('/import', 'import')->middleware('throttle:account-import')->name('import.store');
                    // Restoration routes exist only in the RES Lead group and consume server-owned preview targets.
                    Route::post('/import/restore-account', 'restoreImportAccount')->middleware('throttle:import-confirm')->name('import.restore-account');
                    Route::post('/import/restore-all', 'restoreImportAccounts')->middleware('throttle:import-confirm')->name('import.restore-all');
                    Route::post('/import/confirm', 'confirmImport')->middleware('throttle:import-confirm')->name('import.confirm');
                    // Rate-limit verified workbook generation while retaining the RES Lead role and catalog checks.
                    Route::get('/import/template', 'template')->middleware('throttle:account-template')->name('import.template');
                    Route::post('/mass-action', 'massAction')->middleware('throttle:account-mass-action')->name('mass-action');
                    Route::get('/audit-log', 'auditIndex')->name('audit.index');
                    Route::get('/profile-options', 'profileOptionsIndex')->name('profile-options.index');
                    Route::post('/profile-options', 'storeProfileOption')->middleware('throttle:account-option')->name('profile-options.store');
                    Route::put('/profile-options/{profileOption}', 'updateProfileOption')->middleware('throttle:account-option')->name('profile-options.update');
                    Route::patch('/profile-options/{profileOption}/status', 'changeProfileOptionStatus')->middleware('throttle:account-option')->name('profile-options.status');
                    Route::get('/{managedUser}', 'show')->name('show');
                    Route::get('/{managedUser}/edit', 'edit')->name('edit');
                    Route::put('/{managedUser}', 'update')->middleware('throttle:account-write')->name('update');
                    Route::patch('/{managedUser}/username', 'regenerateUsername')->middleware('throttle:account-write')->name('username');
                    Route::patch('/{managedUser}/status', 'changeStatus')->middleware('throttle:account-write')->name('status');
                    Route::delete('/{managedUser}', 'destroy')->middleware('throttle:account-write')->name('destroy');
                    Route::post('/{managedUser}/password-reset', 'sendPasswordReset')->middleware('throttle:setup-email')->name('password-reset');
                });
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::controller(ResLeadSettingsController::class)->prefix('settings')->name('settings.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::put('/deadlines', 'updateDeadlines')->middleware('throttle:account-write')->name('deadlines.update');
                    Route::patch('/username', 'updateUsername')->middleware('throttle:account-write')->name('username.update');
                    Route::patch('/password', 'updatePassword')->middleware('throttle:account-write')->name('password.update');
                });
            });
    });
});
