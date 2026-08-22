# ENDGAME Changed Files - 2026-08-22

This inventory records the ECRATS-only working-tree files changed by the DOOMSDAY/ENDGAME completion audit. Temporary QA renders and JUnit files were removed before this inventory. No unrelated file, `.env`, dependency lockfile, or deployment configuration changed.

## Documentation and planning

- `CHANGELOG.md`, `PLANS.md`: project-level outcome, plan, migration, verification, and open acceptance.
- `docs/requirements/traceability.md`: points the general requirements map to the current consolidated ledger and expands Reports.
- `Documentations/CHANGELOG.md`, `FEATURES_AND_FUNCTIONALITY.md`, `DOCUMENT_AND_CERTIFICATE_GENERATION.md`, `TESTING_GUIDE.md`: current behavior and verification contracts.
- `Documentations/DOOMSDAY_IMPLEMENTATION_STATUS_2026-08-21.md`, `DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md`: mark the August 21 files as historical checkpoints.
- `Documentations/ENDGAME_IMPLEMENTATION_STATUS_2026-08-22.md`, `ENDGAME_REQUIREMENTS_TRACEABILITY_2026-08-22.md`, `RES_OPERATIONAL_REPORTS.md`, this file: current status, every-requirement evidence, Reports contract, and exact inventory.
- `Documentations/KNOWN_ISSUES.md`, `MANUAL_VISUAL_VALIDATION.md`: authenticated browser/native-preview/raster/scanner limitations and the exact remaining manual checklist.
- `Documentations/README.md`: current documentation reading order and scope.

## Controllers, requests, models, and routes

- `app/Http/Controllers/Dashboard/ApplicantApplicationController.php`: remove Applicant list term filtering.
- `ApplicantRevisionCertificateController.php`: recipient/version/revision presentation and no term filter.
- `DashboardController.php`: current-term-only role dashboards and ignored dashboard term query.
- `NotificationPageController.php`: MySQL-safe notification type query and academic-term filter data.
- `ResCertificationController.php`: plural integrity-checked Preview All, including pending generated certificates.
- `ResLeadApplicationController.php`: screening draft save/restore and corrected assignment flow data.
- `ResReportController.php`: validated RES-only operational report filters/data.
- `ResReviewMonitoringController.php`: workload and secure drill-down filters.
- `app/Http/Controllers/Settings/ResLeadSettingsController.php`: default QR/settings presentation support.
- `ReviewerSettingsController.php`: Worksheet Configuration update/private preview.
- `app/Http/Requests/ResLead/SaveScreeningDraftRequest.php`: partial screening draft validation/authorization.
- `app/Http/Requests/Settings/UpdateDeadlineSettingsRequest.php`: historical term dates but non-past, ordered process dates.
- `UpdateWorksheetSignatoryRequest.php`: Reviewer name/PNG signature validation.
- `app/Models/AcademicTerm.php`: current-first filter label/order.
- `ApplicationDocument.php`: formal-submission boundary persistence/cast.
- `User.php`: Reviewer worksheet configuration fields.
- `WorkflowDraft.php`: owner/application/workflow-scoped draft model.
- `routes/web.php`: screening draft, worksheet settings/preview, and plural certificate preview routes.

## Services and database

- `app/Services/Applications/AdviserEndorsementService.php`, `ResearchApplicationSubmissionService.php`, `ReviewConsensusService.php`, `ReviewerWorkflowService.php`, `ApplicationRevisionWorkflowService.php`: academic-term notification metadata and current workflow corrections.
- `ApplicationDocumentService.php`: delete never-submitted replacements; retain immutable submitted V1/V2/V3; fix safe replacement/remove handling.
- `OfficialReviewFormArtifactService.php`: multiline title and corrected signature/name/date placement with integrity checks.
- `ResScreeningWorkflowService.php`, `WorkflowDraftService.php`: safe draft lifecycle, locked reauthorization, final cleanup, and privacy-limited audit metadata.
- `app/Services/Certificates/CertificateReleaseService.php`, `OfficialCertificateGenerationService.php`, `DefaultCertificateQrService.php`: plural workflow metadata, default QR fallback/provenance, and fixed placement.
- `app/Services/Dashboard/DashboardDataService.php`: current-term role data and Applicant pre-application timeline.
- `app/Services/Reports/ApplicantSurveyReportService.php`, `OperationalReportService.php`: filter-aware anonymous surveys and complete operational aggregates/tables/query optimization.
- `app/Services/Settings/AcademicTermResolver.php`, `WorksheetSignatorySettingsService.php`: current-first terms and private future-only worksheet settings.
- `database/migrations/2026_08_22_000000_add_submission_and_worksheet_settings.php`: additive formal-submission metadata, worksheet fields, and workflow drafts.
- `database/seeders/DashboardDemoSeeder.php`, `ResLeadSeeder.php`, `TestingUserSeeder.php`: consistent institution/department/program and safe complete mock profiles.

## Frontend assets and views

- `resources/css/dashboard.css`: ENDGAME settings, deadlines, security, monitoring, notifications, revision, certificate, report, hover/focus, overflow, and responsive contracts.
- `resources/js/dashboard.js`: automatic/page-leave drafts, animated uploads, collapsibles, delayed long-value tooltips, confirmation interactions, and revision preview state.
- `resources/views/components/dashboard/academic-term-filter.blade.php`, `summary-card.blade.php`: current-first labels and optional clickable cards.
- Application views: `dashboard/applications/adviser-index.blade.php`, `applicant-index.blade.php`, `form.blade.php`, `partials/document-dialog.blade.php`, `res-index.blade.php`, `res-reviewers.blade.php`, `res-show.blade.php`, `revision-certificates.blade.php`, `show.blade.php`.
- Review/certificate/report views: `dashboard/assignments/index.blade.php`, `certificates/res-index.blade.php`, `certificates/res-workspace.blade.php`, `notifications.blade.php`, `reports/res-index.blade.php`, `reviews/res-adviser-applications.blade.php`, `reviews/res-monitoring.blade.php`, `reviews/res-reviewer-assignments.blade.php`.
- Dashboard role views: `dashboard/roles/adviser.blade.php`, `applicant.blade.php`, `res-lead.blade.php`, `reviewer.blade.php`.
- Settings views: `settings/account.blade.php`, `partials/profile-form.blade.php`, `partials/security-forms.blade.php`, `res-lead.blade.php`, `reviewer.blade.php`.

## Automated tests

- `tests/Feature/Dashboard/ApplicantApplicationWorkflowTest.php`: auto-upload/formal-retention V1/V2/V3 behavior.
- `ApplicantRevisionPresentationTest.php`: exact Revision/Certificates structure.
- `CertificateReleaseWorkflowTest.php`: default/replacement QR provenance, plural/pending Preview All, page count, tamper and authorization.
- `DashboardNotificationTest.php`: MySQL-safe query, terms, confirmations, Bin/actions, safe failures.
- `OfficialReviewFormArtifactTest.php`: cycle-exact business versions and worksheet configuration snapshots/layout.
- `ResLeadScreeningWorkflowTest.php`: draft authorization/restore/audit/final cleanup.
- `ResOperationalReportTest.php`: report authorization, filters, values, recipient states, data quality, accessibility, and query-count stability.
- `ResReviewMonitoringTest.php`: monitoring columns/filters/drill-down authorization.
- `ReviewConsensusWorkflowTest.php`: every Full Board reviewer feedback/actionable requirement release.
- `ReviewerReassignmentWorkflowTest.php`: corrected workflow redirect/history assertion.
- `RoleDashboardTest.php`: current-term dashboards, pre-application timeline, no query override, and shared table overflow.
- `tests/Feature/Settings/ResLeadSettingsTest.php`, `ReviewerSettingsTest.php`: dates/default QR/settings structure and Worksheet Configuration validation/private preview.
