<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Dashboard\AdviserApplicationController;
use App\Http\Controllers\Dashboard\AdviserEndorsementController;
use App\Http\Controllers\Dashboard\ApplicantApplicationController;
use App\Http\Controllers\Dashboard\ApplicantRevisionCertificateController;
use App\Http\Controllers\Dashboard\ApplicationDocumentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\NotificationPageController;
use App\Http\Controllers\Dashboard\OnboardingController;
use App\Http\Controllers\Dashboard\ProfilePageController;
use App\Http\Controllers\Dashboard\ResCertificationController;
use App\Http\Controllers\Dashboard\ResearchApplicationPageController;
use App\Http\Controllers\Dashboard\ResLeadApplicationController;
use App\Http\Controllers\Dashboard\ResReportController;
use App\Http\Controllers\Dashboard\ResReviewMonitoringController;
use App\Http\Controllers\Dashboard\ReviewerAssignmentPageController;
use App\Http\Controllers\Dashboard\ReviewerWorkflowController;
use App\Http\Controllers\Dashboard\ReviewFormArtifactController;
use App\Http\Controllers\Identity\ReviewerIdentityReconciliationController;
use App\Http\Controllers\Identity\UserManagementController;
use App\Http\Controllers\Settings\AccountSettingsController;
use App\Http\Controllers\Settings\ResLeadSettingsController;
use App\Http\Controllers\Settings\ReviewerSettingsController;
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

    Route::middleware(['auth', 'term.operational', 'dashboard.context'])->group(function (): void {
        // All roles enter through one stable URL while retaining role-specific data and authorization.
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::post('/notifications/mark-all-read', [NotificationPageController::class, 'markAllRead'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.mark-all-read');
        Route::get('/notifications/bin', [NotificationPageController::class, 'bin'])
            ->name('notifications.bin');
        Route::post('/notifications/bulk', [NotificationPageController::class, 'bulk'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.bulk');
        Route::post('/notifications/all', [NotificationPageController::class, 'updateAll'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.all');
        Route::patch('/notifications/{notification}/read-status', [NotificationPageController::class, 'updateReadStatus'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.read-status');
        Route::delete('/notifications/{notification}', [NotificationPageController::class, 'destroy'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.destroy');
        Route::post('/notifications/bin/bulk', [NotificationPageController::class, 'bulkBin'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.bin.bulk');
        Route::post('/notifications/bin/all', [NotificationPageController::class, 'updateAllBin'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.bin.all');
        Route::patch('/notifications/bin/{notification}/restore', [NotificationPageController::class, 'restore'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.bin.restore');
        Route::delete('/notifications/bin/{notification}', [NotificationPageController::class, 'forceDestroy'])
            ->middleware('throttle:notification-actions')
            ->name('notifications.bin.destroy');
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
                Route::get('/revision-certificates', [ApplicantRevisionCertificateController::class, 'index'])
                    ->name('revision-certificates.index');
                Route::post('/revision-certificates/applications/{researchApplication}/revisions/{applicationRevision}/requirements/{applicationRevisionRequirement}', [ApplicantRevisionCertificateController::class, 'uploadRevision'])
                    ->middleware('throttle:application-upload')
                    ->name('revision-certificates.revisions.documents.store');
                Route::post('/revision-certificates/applications/{researchApplication}/revisions/{applicationRevision}/submit', [ApplicantRevisionCertificateController::class, 'submitRevision'])
                    ->middleware('throttle:revision-workflow')
                    ->name('revision-certificates.revisions.submit');
                Route::post('/revision-certificates/applications/{researchApplication}/survey', [ApplicantRevisionCertificateController::class, 'submitSurvey'])
                    ->middleware('throttle:revision-workflow')
                    ->name('revision-certificates.survey.store');
                Route::post('/revision-certificates/applications/{researchApplication}/certificate/claim', [ApplicantRevisionCertificateController::class, 'claim'])
                    ->middleware('throttle:certificate-workflow')
                    ->name('revision-certificates.certificate.claim');
                Route::get('/revision-certificates/applications/{researchApplication}/reviewer-assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/preview', [ReviewFormArtifactController::class, 'applicantPreview'])
                    ->name('revision-certificates.worksheets.preview');
                Route::get('/revision-certificates/applications/{researchApplication}/reviewer-assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/download', [ReviewFormArtifactController::class, 'applicantDownload'])
                    ->name('revision-certificates.worksheets.download');
                Route::get('/revision-certificates/applications/{researchApplication}/certificates/{certificate}/versions/{certificateVersion}/preview', [ApplicantRevisionCertificateController::class, 'preview'])
                    ->name('revision-certificates.certificate.preview');
                Route::get('/revision-certificates/applications/{researchApplication}/certificates/{certificate}/versions/{certificateVersion}/download', [ApplicantRevisionCertificateController::class, 'download'])
                    ->name('revision-certificates.certificate.download');
                Route::get('/revision-certificates/applications/{researchApplication}/certificates/download-all', [ApplicantRevisionCertificateController::class, 'downloadAll'])
                    ->name('revision-certificates.certificates.download-all');
                Route::redirect('/reviewer', '/student-faculty-researcher/revision-certificates')->name('reviewer.index');
                Route::redirect('/certificates', '/student-faculty-researcher/revision-certificates')->name('certificates.index');
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::controller(AccountSettingsController::class)->prefix('settings')->name('settings.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::put('/profile', 'updateProfile')->middleware('throttle:account-write')->name('profile.update');
                    Route::patch('/username', 'updateUsername')->middleware('throttle:security-change')->name('username.update');
                    Route::patch('/email', 'updateEmail')->middleware('throttle:security-change')->name('email.update');
                    Route::patch('/password', 'updatePassword')->middleware('throttle:security-change')->name('password.update');
                });
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
                Route::controller(AccountSettingsController::class)->prefix('settings')->name('settings.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::put('/profile', 'updateProfile')->middleware('throttle:account-write')->name('profile.update');
                    Route::patch('/username', 'updateUsername')->middleware('throttle:security-change')->name('username.update');
                    Route::patch('/email', 'updateEmail')->middleware('throttle:security-change')->name('email.update');
                    Route::patch('/password', 'updatePassword')->middleware('throttle:security-change')->name('password.update');
                    Route::put('/worksheet-signatory', 'updateWorksheetSignatory')->middleware(['reviewer.enabled', 'throttle:security-change'])->name('worksheet-signatory.update');
                    Route::get('/worksheet-signature/preview', 'previewWorksheetSignature')->middleware('reviewer.enabled')->name('worksheet-signature.preview');
                });
            });

        // Reviewer work is an Adviser capability. Canonical URLs remain under the Adviser area,
        // while stable reviewer.* route names keep notifications and historical links resolvable.
        Route::prefix('adviser/reviewer')
            ->name('reviewer.')
            ->middleware(['reviewer.enabled', 'role:adviser'])
            ->group(function (): void {
                Route::get('/', [DashboardController::class, 'reviewer'])->name('dashboard');
                Route::redirect('/landing', '/adviser/reviewer')->name('landing');
                Route::get('/assignments', [ReviewerAssignmentPageController::class, 'index'])
                    ->name('assignments.index');
                Route::get('/assignments/{reviewerAssignment}', [ReviewerAssignmentPageController::class, 'show'])
                    ->name('assignments.show');
                Route::get('/assignments/{reviewerAssignment}/workspace', [ReviewerAssignmentPageController::class, 'workspace'])
                    ->name('assignments.workspace');
                Route::put('/assignments/{reviewerAssignment}/forms/{reviewFormType}', [ReviewerWorkflowController::class, 'saveForm'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.forms.update');
                Route::get('/assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/preview', [ReviewFormArtifactController::class, 'reviewerPreview'])
                    ->name('assignments.forms.artifacts.preview');
                Route::get('/assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/download', [ReviewFormArtifactController::class, 'reviewerDownload'])
                    ->name('assignments.forms.artifacts.download');
                Route::get('/assignments/{reviewerAssignment}/comments', [ReviewerWorkflowController::class, 'olderComments'])
                    ->name('assignments.comments.index');
                Route::post('/assignments/{reviewerAssignment}/comments', [ReviewerWorkflowController::class, 'addComment'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.comments.store');
                Route::put('/assignments/{reviewerAssignment}/comments/{reviewComment}', [ReviewerWorkflowController::class, 'updateComment'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.comments.update');
                Route::patch('/assignments/{reviewerAssignment}/comments/{reviewComment}/status', [ReviewerWorkflowController::class, 'changeCommentStatus'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.comments.status');
                Route::delete('/assignments/{reviewerAssignment}/comments/{reviewComment}', [ReviewerWorkflowController::class, 'removeComment'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.comments.destroy');
                Route::post('/assignments/{reviewerAssignment}/review', [ReviewerWorkflowController::class, 'saveDecision'])
                    ->middleware('throttle:reviewer-workflow')
                    ->name('assignments.review.store');
                // Reviewer document access remains nested, assignment-gated, and private-disk backed.
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/preview', [ApplicationDocumentController::class, 'preview'])
                    ->name('applications.documents.preview');
                Route::get('/applications/{researchApplication}/documents/{applicationDocument}/download', [ApplicationDocumentController::class, 'download'])
                    ->name('applications.documents.download');
                Route::get('/reviews', [ReviewerAssignmentPageController::class, 'index'])
                    ->name('reviews.index');
                Route::get('/notifications', [NotificationPageController::class, 'index'])->name('notifications.index');
                Route::get('/profile', ProfilePageController::class)->name('profile.show');
                Route::controller(ReviewerSettingsController::class)->prefix('settings')->name('settings.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::put('/profile', 'updateProfile')->middleware('throttle:account-write')->name('profile.update');
                    Route::patch('/username', 'updateUsername')->middleware('throttle:security-change')->name('username.update');
                    Route::patch('/email', 'updateEmail')->middleware('throttle:security-change')->name('email.update');
                    Route::patch('/password', 'updatePassword')->middleware('throttle:security-change')->name('password.update');
                    Route::put('/worksheet-signatory', 'updateWorksheetSignatory')->middleware('throttle:security-change')->name('worksheet-signatory.update');
                    Route::get('/worksheet-signature/preview', 'previewWorksheetSignature')->name('worksheet-signature.preview');
                });
            });

        // Old Reviewer bookmarks may only cross into the equivalent capability-gated Adviser URL.
        // Unsupported or mutating legacy paths remain unavailable, and the destination performs
        // the normal nested-record authorization checks before returning any private content.
        Route::get('/reviewer/{legacyPath?}', function (?string $legacyPath = null) {
            $destination = url('/adviser/reviewer'.($legacyPath ? '/'.ltrim($legacyPath, '/') : ''));
            $query = request()->getQueryString();

            return redirect()->to($destination.($query ? '?'.$query : ''));
        })
            ->where('legacyPath', '.*')
            ->middleware(['reviewer.enabled', 'role:adviser'])
            ->name('reviewer.legacy');

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
                Route::post('/applications/{researchApplication}/classification/draft', [ResLeadApplicationController::class, 'saveScreeningDraft'])
                    ->middleware('throttle:res-workflow')
                    ->name('applications.classification.draft');
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
                Route::get('/applications/{researchApplication}/reviewer-assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/preview', [ReviewFormArtifactController::class, 'resPreview'])
                    ->name('applications.review-form-artifacts.preview');
                Route::get('/applications/{researchApplication}/reviewer-assignments/{reviewerAssignment}/forms/{reviewFormSubmission}/artifacts/{reviewFormArtifact}/download', [ReviewFormArtifactController::class, 'resDownload'])
                    ->name('applications.review-form-artifacts.download');
                Route::get('/review-monitoring', ResReviewMonitoringController::class)
                    ->name('review-monitoring.index');
                Route::get('/review-monitoring/reviewers/{reviewer}/assignments', [ResReviewMonitoringController::class, 'reviewerAssignments'])
                    ->name('review-monitoring.reviewers.assignments');
                Route::get('/review-monitoring/advisers/{adviser}/applications', [ResReviewMonitoringController::class, 'adviserApplications'])
                    ->name('review-monitoring.advisers.applications');
                Route::get('/certificates', [ResCertificationController::class, 'index'])
                    ->name('certificates.index');
                Route::get('/certificates/applications/{researchApplication}/workspace', [ResCertificationController::class, 'workspace'])
                    ->name('certificates.workspace');
                Route::post('/certificates/applications/{researchApplication}/decision-release', [ResCertificationController::class, 'releaseDecision'])
                    ->middleware('throttle:res-workflow')
                    ->name('certificates.decisions.release');
                Route::post('/certificates/applications/{researchApplication}/release', [ResCertificationController::class, 'releaseCertificate'])
                    ->middleware('throttle:certificate-workflow')
                    ->name('certificates.release');
                Route::post('/certificates/release-eligible', [ResCertificationController::class, 'bulkRelease'])
                    ->middleware('throttle:certificate-bulk')
                    ->name('certificates.release-eligible');
                Route::post('/certificates/applications/{researchApplication}/regenerate', [ResCertificationController::class, 'regenerate'])
                    ->middleware('throttle:certificate-workflow')
                    ->name('certificates.regenerate');
                Route::get('/certificates/{certificate}/versions/{certificateVersion}/preview', [ResCertificationController::class, 'previewCertificate'])
                    ->name('certificates.versions.preview');
                Route::get('/certificates/{certificate}/versions/{certificateVersion}/download', [ResCertificationController::class, 'downloadCertificate'])
                    ->name('certificates.versions.download');
                Route::get('/certificates/applications/{researchApplication}/preview-all', [ResCertificationController::class, 'previewAllCertificates'])
                    ->name('certificates.applications.preview-all');
                Route::get('/certificates/applications/{researchApplication}/download-all', [ResCertificationController::class, 'downloadAllCertificates'])
                    ->name('certificates.applications.download-all');
                Route::controller(ResReportController::class)->prefix('reports')->name('reports.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/export', 'export')->middleware('throttle:report-export')->name('export');
                    Route::get('/print', 'printReport')->name('print');
                    Route::get('/survey/print', 'printSurvey')->name('survey.print');
                    Route::get('/applicants/{applicant}', 'applicant')->name('applicants.show');
                    Route::get('/audit-log', 'auditIndex')->name('audit.index');
                });
                Route::controller(UserManagementController::class)->prefix('users')->name('users.')->group(function (): void {
                    Route::get('/', 'index')->name('index');
                    Route::get('/create', 'create')->name('create');
                    Route::post('/', 'store')->middleware('throttle:account-write')->name('store');
                    Route::get('/import', 'importForm')->name('import.form');
                    Route::post('/import', 'import')->middleware('throttle:account-import')->name('import.store');
                    // Restoration routes exist only in the REU Lead group and consume server-owned preview targets.
                    Route::post('/import/restore-account', 'restoreImportAccount')->middleware('throttle:import-confirm')->name('import.restore-account');
                    Route::post('/import/restore-all', 'restoreImportAccounts')->middleware('throttle:import-confirm')->name('import.restore-all');
                    Route::post('/import/confirm', 'confirmImport')->middleware('throttle:import-confirm')->name('import.confirm');
                    // Rate-limit verified workbook generation while retaining the REU Lead role and catalog checks.
                    Route::get('/import/template', 'template')->middleware('throttle:account-template')->name('import.template');
                    Route::post('/mass-action', 'massAction')->middleware('throttle:account-mass-action')->name('mass-action');
                    Route::post('/reviewer-reconciliations/{reviewerIdentityReconciliation}/keep-separate', [ReviewerIdentityReconciliationController::class, 'keepSeparate'])
                        ->middleware('throttle:account-write')
                        ->name('reviewer-reconciliations.keep-separate');
                    Route::post('/reviewer-reconciliations/{reviewerIdentityReconciliation}/merge', [ReviewerIdentityReconciliationController::class, 'merge'])
                        ->middleware('throttle:account-write')
                        ->name('reviewer-reconciliations.merge');
                    // Preserve old bookmarks while keeping the Audit Log owned by Reports.
                    Route::redirect('/audit-log', '/res-lead/reports/audit-log')->name('audit.index');
                    Route::redirect('/profile-options', '/res-lead/settings?tab=options')->name('profile-options.index');
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
                    Route::post('/requirements', 'storeDocumentRequirement')->middleware('throttle:account-write')->name('requirements.store');
                    Route::put('/requirements/{documentRequirement}', 'updateDocumentRequirement')->middleware('throttle:account-write')->name('requirements.update');
                    Route::delete('/requirements/{documentRequirement}', 'destroyDocumentRequirement')->middleware('throttle:account-write')->name('requirements.destroy');
                    Route::put('/deadlines', 'updateDeadlines')->middleware('throttle:account-write')->name('deadlines.update');
                    Route::patch('/academic-terms/{academicTerm}/pause', 'pauseAcademicTerm')->middleware('throttle:account-write')->name('academic-terms.pause');
                    Route::patch('/academic-terms/{academicTerm}/end', 'endAcademicTerm')->middleware('throttle:account-write')->name('academic-terms.end');
                    Route::patch('/academic-terms/{academicTerm}/reactivate', 'reactivateAcademicTerm')->middleware('throttle:account-write')->name('academic-terms.reactivate');
                    Route::put('/profile', 'updateProfile')->middleware('throttle:account-write')->name('profile.update');
                    Route::patch('/username', 'updateUsername')->middleware('throttle:security-change')->name('username.update');
                    Route::patch('/email', 'updateEmail')->middleware('throttle:security-change')->name('email.update');
                    Route::patch('/password', 'updatePassword')->middleware('throttle:security-change')->name('password.update');
                    Route::put('/signatory', 'updateSignatory')->middleware('throttle:security-change')->name('signatory.update');
                    Route::get('/signatory/preview', 'previewSignatory')->name('signatory.preview');
                    Route::get('/certificate-qr/preview', 'previewCertificateQr')->name('certificate-qr.preview');
                    Route::post('/profile-options', 'storeProfileOption')->middleware('throttle:account-option')->name('profile-options.store');
                    Route::put('/profile-options/{profileOption}', 'updateProfileOption')->middleware('throttle:account-option')->name('profile-options.update');
                    Route::patch('/profile-options/{profileOption}/status', 'changeProfileOptionStatus')->middleware('throttle:account-option')->name('profile-options.status');
                    Route::post('/backgrounds', 'uploadBackground')->middleware('throttle:certificate-background')->name('backgrounds.store');
                    Route::patch('/backgrounds/{certificateBackground}/activate', 'activateBackground')->middleware('throttle:certificate-background')->name('backgrounds.activate');
                    Route::post('/backgrounds/reset', 'resetBackground')->middleware('throttle:certificate-background')->name('backgrounds.reset');
                    Route::get('/backgrounds/{certificateBackground}/preview', 'previewBackground')->name('backgrounds.preview');
                });
            });
    });
});
