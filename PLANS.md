# ECRATS Implementation Plans

Use this file for large or risky work. Small isolated fixes do not need a formal plan.

## When a Plan Is Required

Create or update a plan before:

- Database schema changes
- Authentication or account-management changes
- Role, permission, middleware, or policy changes
- File upload, private storage, certificate, or QR access changes
- Reviewer anonymity or double-blind workflow changes
- Cross-module workflow changes
- Package installation
- Large UI layout changes shared by multiple roles
- Deployment, backup, or production configuration changes

## Plan Template

```markdown
## Plan: <short title>

### Goal
What user or team outcome this work should achieve.

### Source Documents
- Primary requirement:
- Supporting diagrams/forms:
- Conflicts or missing decisions:

### Scope
Included:
- 

Excluded:
- 

### Implementation Approach
- Backend:
- Frontend:
- Database:
- Authorization:
- Files/storage:
- Notifications/audit:

### Files Expected to Change
- 

### Tests and Verification
- 

### Risks and Rollback
- 

### Approval Notes
Approved by:
Date:
```

## Active Plans

## Plan: Account restoration and applicant-to-adviser submission

### Goal
Finish the July 27, 2026 user-management corrections and deliver a secure, functional Student/Faculty applicant workflow from idempotent draft creation through private document upload and final submission to the assigned Research Adviser.

### Source Documents
- Primary requirement: `PROMPT FOR USER MANAGEMENT.txt`, July 27, 2026.
- Visual reference: `Copy of ECRATS High Fidelity.pdf`, especially pages 9, 10, 12-14, 16-17, and 29-30.
- Existing implementation found: shared role dashboards, application/document/deadline models, application statuses, policy registration, private local storage, audit logging, database notifications, Excel-only account imports, bounded private import previews, profile options, and an initial requirement-gated submission service.
- Missing functionality: applicant draft create/edit forms, configurable research-type requirements, secure upload/replace/view/download, submission-window enforcement, adviser notification, adviser-scoped list/filter/detail pages, archived-account preview separation, and real individual/bulk restoration.
- Visual limitation: the local environment has no PDF renderer or connected browser, so visual matching remains implemented in code but pending manual verification.

### Scope
Included:
- Correct shared summary-card horizontal composition, reusable internal-overflow wrappers, account-form heading spacing, and the exact Student Researcher workbook example.
- Split active and archived import matches; allow RES Lead-only individual and current-preview bulk restoration with expiry, ownership, conflict checks, locking, audit history, and preserved user IDs.
- Add an idempotent one-draft applicant flow, application information Form Requests, database-backed Adviser selection, active submission-window checks, research-type requirement resolution, private versioned document handling, and one idempotent final submission transaction.
- Connect applicant dashboard details and requirement progress to the same completion service used by submission.
- Add Adviser dashboard/list/detail visibility for formally submitted assigned applications only, with search, filters, pagination, and secure document access.
- Add focused tests, documentation, and manual-validation checklists.

Excluded:
- Adviser endorsement/return decisions, RES screening actions, reviewer evaluation, revision decisions, certificate generation, and payment-gateway integration.
- Public document URLs, arbitrary workbook restoration IDs, permanent account deletion changes, or installation of new packages.
- Claims of PDF/browser visual acceptance without direct observation.

### Implementation Approach
- Backend: add focused applicant/adviser controllers, Form Requests, a requirement-progress service, a private document service, and a shared archived-account restoration service while keeping controllers thin.
- Frontend: extend the existing dashboard layout and component language with application form, document workspace, details/checklist dialogs, adviser list/detail views, and reusable overflow regions.
- Database: add application-information/workflow fields and requirement applicability/mandatory configuration through additive migrations; add only the approved Student template option defaults that are currently missing.
- Authorization: expand `ResearchApplicationPolicy` for draft creation/update/upload/submission and submitted Adviser access; restrict restore actions to RES Lead and the actor-owned unexpired import preview.
- Files/storage: store randomized files on the private `local` disk, validate MIME/extension/size, keep current/version history, stream authorized previews/downloads through controllers, and never expose stored paths.
- Notifications/audit: persist the assigned Adviser's database notification in the same transaction as submission and record draft, update, upload, replace, submit, adviser notification, restoration, and blocked-restoration events without file contents or secrets.

### Files Expected to Change
- `app/Enums`, `app/Models`, `app/Policies`, `app/Http/Controllers`, `app/Http/Requests`, and `app/Services`
- `database/migrations`, factories, and local/testing seed data
- `routes/web.php`
- Dashboard and identity Blade components/views, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- Focused Dashboard and Identity feature tests
- `PLANS.md`, changelogs, README, application/user-management/security/data-flow/routes/testing/UI/manual-validation documentation

### Tests and Verification
- Focused hero-card, workbook, restoration, applicant workflow, private document, adviser scope, notification, audit, deadline, idempotency, pagination, and responsive-structure tests.
- `php artisan optimize:clear`, `php artisan migrate`, `php artisan migrate:status`, `php artisan route:list`, focused tests, full `php artisan test`, Pint, `npm.cmd run build`, and `git diff --check`.
- Review migration rollback SQL/structure without running a destructive rollback against important local data.
- Manual PDF/viewport verification remains pending unless a renderer or connected browser becomes available.

### Risks and Rollback
- Existing application rows receive nullable/defaulted fields, so the additive migration preserves current dashboard and seeded records.
- Requirement applicability defaults to both research types and mandatory status to preserve current behavior.
- Restoration reactivates the original row only after checking active identity conflicts; failures remain archived and are returned as structured conflicts.
- File replacement preserves database history and removes no previously referenced private version.
- Rollback removes only newly added schema. It deliberately leaves the two approved shared option values because older catalog rows have no reliable migration-ownership marker; avoiding team-data deletion is more important than removing harmless lookup values.

### Verification Status (2026-07-27)
- `php artisan test` passed 104 tests with 1,812 assertions.
- The additive migration is applied in batch 8; baseline requirement seeding and a non-destructive pretend rollback both passed.
- Cache clearing, all 90 registered routes, syntax checks for 124 PHP files, Pint, strict Composer validation, platform requirements, the Vite production build, and `git diff --check` passed.
- `composer audit --locked` found no security advisories, and `npm.cmd audit --audit-level=moderate` found zero vulnerabilities.
- The PDF comparison, Microsoft Excel opening check, and 1440/1280/1024/768/390 browser checks remain implemented in code but pending manual visual verification.

### Approval Notes
Approved by: User request
Date: 2026-07-27

## Plan: Account-management UI and verified workbook corrections

### Goal
Correct the approved account-management and dashboard presentation, produce Excel templates that survive a trusted Xlsx-reader round trip, and keep bulk-import validation details in one secure, accessible modal state.

### Source Documents
- Primary requirement: attached correction specification, July 24, 2026.
- Visual references: supplied account-information, reset-link, dashboard-card, validation-modal, workbook-error, and table-overflow screenshots.
- Existing implementation: the current account policies, private preview lifecycle, bounded OOXML parser, reusable dashboard components, and the active Excel-only account-administration plan.
- Verification boundary: automated package and Xlsx-reader checks can validate the generated file, but Microsoft Excel and responsive visual acceptance remain manual checks until directly observed.

### Scope
Included:
- Restore the three-part account-information header, use the shared green-outline reset action, contain account-table overflow, and normalize individual-form section spacing.
- Center shared dashboard summary-card content vertically as icon, count, and label without changing role queries, labels, colors, or authorization.
- Correct the generated OOXML package, add trusted PhpSpreadsheet round-trip verification, validate required entries, named ranges, data validation, response headers, and private temporary-file cleanup.
- Keep the general import message concise, render categorized details only in the Show Errors modal, and add a persistent accessible error badge with a short reduced-motion-safe attention state.
- Extend focused tests, documentation, manual visual checks, and source comments required by this correction.

Excluded:
- Changing application status meanings, dashboard count calculations, user-management authorization, reset-token behavior, or import confirmation rules.
- Installing a browser framework or claiming manual Microsoft Excel or visual acceptance without direct observation.
- Replacing the hardened bounded parser for untrusted uploaded workbooks with unrestricted spreadsheet evaluation.

### Implementation Approach
- Backend: keep controllers thin, verify each trusted generated file before download, catch generation failures safely, and reuse one private user-bound validation-result structure.
- Frontend: update shared Blade/CSS/JavaScript components so all supported roles inherit the same card, table, button, spacing, modal, and accessibility behavior.
- Database: no schema change is planned; validation state remains bounded, private, user-bound, and expiring.
- Authorization: preserve existing policies, gates, role middleware, CSRF protection, and route throttles.
- Files/storage: keep private temporary `.xlsx` files, verify package entries and Xlsx readability, and delete delivered, rejected, expired, or cancelled artifacts.
- Dependencies: add `phpoffice/phpspreadsheet` for trusted Xlsx generation verification and automated round-trip tests.

### Files Expected to Change
- `composer.json`, `composer.lock`, `app/Services/Identity/SafeSpreadsheet.php`, `UserManagementController`, and focused identity tests
- Shared dashboard summary-card Blade/CSS and role dashboard tests
- User-management Blade views, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- `PLANS.md`, changelogs, account/dashboard/import/testing documents, and `Documentations/MANUAL_VISUAL_VALIDATION.md`

### Tests and Verification
- Focused account authorization, reset-link, pagination, summary-card, workbook round-trip, download response, validation-state, and modal rendering tests.
- `php artisan optimize:clear`, `php artisan route:list`, `php artisan test`, Pint, Composer validation/platform checks, dependency audits, `npm.cmd run build`, and `git diff --check`.
- Manual Microsoft Excel and responsive checks remain explicitly pending unless directly observed.

### Risks and Rollback
- PhpSpreadsheet adds a maintained workbook dependency; rollback removes the package and trusted round-trip verifier but must not restore the invalid workbook response.
- Shared CSS changes affect all role dashboards and account tables, so focused rendering contracts and the full frontend build guard regressions.
- Validation details remain session-bound and escaped; rollback must preserve user isolation, expiry, and secure cleanup.

### Approval Notes
Approved by: User request
Date: 2026-07-24

## Plan: Excel-only account administration completion

### Goal
Complete the existing RES Lead and Adviser account-management workflows using a secure Excel-only import, centrally managed dropdown options, consistent audit/report behavior, and reusable responsive table components based on high-fidelity pages 1-8.

### Source Documents
- Primary requirement: attached account-management completion specification, July 23, 2026.
- Visual reference: `ECRATS High Fidelity (5).pdf`, pages 1-8, plus the supplied current user-table, status-badge, and RES Lead dashboard screenshots.
- Existing implementation: the current private preview/confirmation flow, account policies, option catalog, shared Blade components, and focused feature tests.
- Superseded decision: the earlier July 23 plan retained CSV because of a prior requirement conflict. The latest approved specification explicitly replaces the active CSV workflow with `.xlsx` only.

### Scope
Included:
- Exactly three worksheet templates (`Accounts`, `Options`, and `Instructions`) with role-specific columns, a sentinel example row, active database options, named-range dropdowns, and bounded OOXML generation.
- Server-side workbook structure, archive, formula, external-link, embedded-content, header, row, controlled-value, authorization, duplicate, and existing-account validation.
- Private single-use import previews, separate validation categories, batch conflict checks, valid-row-only confirmation, result reporting, and post-transaction setup-email delivery.
- Database-backed Year Level, Institution, Department, Program, and Reviewer Classification options with add, edit, deactivate, restore, usage visibility, and historical-value preservation.
- RES Lead and Adviser list/view/create/import responsiveness; shared badge, tooltip, table, pagination, status, and outline-button behavior; expanded audit filtering without exposing secret tokens.

Excluded:
- Mandatory queue infrastructure, because the current local/client environment does not guarantee a continuously running worker.
- Password-protected workbook decryption, legacy Excel conversion, macros, formula evaluation, or external workbook retrieval.
- A visible audit token filter. The current schema has no non-secret correlation, request, trace, or public event identifier; authentication and setup tokens must remain undisclosed.
- Guessing Department or Program defaults before the team supplies approved values.

### Implementation Approach
- Backend: preserve the existing controller/service boundaries, replace CSV branches with a bounded `.xlsx` contract, batch duplicate lookups, keep policies authoritative, and sanitize audit metadata before persistence.
- Frontend: extend the existing Blade/CSS/JavaScript components instead of introducing a second design system; keep table scrolling inside its container and use one delegated 0.5-second tooltip implementation.
- Database: add Reviewer Classification defaults to `profile_options`; no destructive user-profile conversion is required because historical profile values remain stored on `users`.
- Authorization: RES Lead manages shared options and all permitted non-RES-Lead account types; Advisers remain limited to authorized Student and Faculty Researcher records.
- Files/storage: accept one `.xlsx` up to 2 MB and 250 account rows, use private temporary storage, expire previews after 30 minutes, and remove uploads after parsing.
- Notifications/audit: create accounts before sending setup mail, report delivery separately, and never persist passwords, setup/reset tokens, complete workbooks, or SMTP details in audit metadata.

### Files Expected to Change
- `app/Enums`, `app/Models`, `app/Services/Identity`, `app/Services/AuditLogService.php`, identity Form Requests, and `UserManagementController`
- `database/migrations`
- `routes/web.php`
- Identity Blade views, shared dashboard components, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- Focused Identity tests and account-management documentation

### Tests and Verification
- Focused Excel generation/import, option lifecycle, authorization, duplicate, email-domain, audit, pagination, and UI contract tests.
- `php artisan route:list`, focused tests, full `php artisan test`, Pint, Composer checks, `npm.cmd run build`, and `git diff --check`.
- Browser verification at approximately 1440, 1280, 1024, 768, and 390 pixels when the local server and in-app browser are available.

### Verification Status (2026-07-24)
- Focused Identity and account-authorization coverage passed with 28 tests and 320 assertions before final parser hardening.
- Final `php artisan test` passed with 73 tests and 813 assertions.
- Pint, Blade compilation, Composer strict validation, platform requirements including `ext-zip`, all 75 application routes, migration status, and the Vite production build passed.
- `composer audit --locked` found no security advisories; `npm.cmd audit --audit-level=moderate` found zero vulnerabilities.
- The additive migration ran successfully against the local database. `git diff --check` passed, nothing is staged, and neither `.env` nor `outputs/` is tracked.
- The local login route returned HTTP 200 at `http://127.0.0.1:8000/login`. Responsive interactive screenshots were not completed because the discoverable in-app browser failed to attach a fresh webview tab twice; no visual result is claimed.

### Risks and Rollback
- The migration is additive and inserts only missing Reviewer Classification options. Rollback removes only migration-owned defaults that are not referenced as creator-managed records.
- Previously downloaded CSV/XLSX templates are not accepted under the new active contract; users must download a current official `.xlsx` template.
- Existing user profile strings remain intact when an option is edited or deactivated. Restoring the migration does not rewrite historical values.
- Custom bounded OOXML handling avoids a new package dependency, but intentionally supports only the documented three-sheet template contract rather than arbitrary spreadsheets.

### Approval Notes
Approved by: User request
Date: 2026-07-23

## Plan: User management import, options, audit, and shared table polish

### Goal
Align RES Lead and Adviser account-management workflows with the July 23, 2026 UI and import guide while preserving secure onboarding, role authorization, private import staging, and team-compatible shared components.

### Source Documents
- Primary requirement: attached organized user-management guide, July 23, 2026.
- Supporting implementation: existing account-management services, policies, Blade views, and high-fidelity-inspired dashboard components.
- Conflicts or missing decisions: CSV files cannot contain spreadsheet dropdown validation, column widths, or wrap-text styling. CSV remains the only visible template and upload choice. Shared database options will drive CSV examples and validation; the existing internal XLSX generator will retain spreadsheet-only formatting and dropdown support for compatibility. Department and Program begin without guessed defaults and can be populated by RES Lead.

### Scope
Included:
- CSV-only visible bulk-import controls, revised role headers, realistic ignored example rows, error modal, and skip-without-error handling for repeated or existing accounts.
- Database-backed Year Level, Institution, Department, and Program options with RES Lead creation, shared form use, CSV validation, and internal XLSX dropdown generation.
- Reviewer classification support for Expedited, Full Board, and Exempted.
- User and adviser account-list responsiveness, consistent truncation tooltips, centered/equal-width badges, compact checkbox columns, and reusable centered pagination.
- Searchable/filterable audit log with actor role and hidden onboarding/password-setup-completion events.
- Add Account, individual account form, and account information layout corrections.

Excluded:
- Displaying or filtering password-reset, setup, import-confirmation, or error-report tokens. ECRATS intentionally does not store those secrets in audit records.
- Inventing Department or Program option lists before the team supplies them.
- Removing backend subject information from audit records; only the RES Lead table column is removed.

### Implementation Approach
- Backend: extend the current identity services and controller, keep policies authoritative, skip duplicates/existing identities during preflight, and preserve confirmation-token single use.
- Frontend: reuse Blade/CSS/JavaScript patterns for accessible dialogs, delayed table tooltips, responsive scroll containers, filters, and pagination.
- Database: add a normalized profile-option table with active values, creator attribution, uniqueness per field, and required Institution/Year Level defaults.
- Authorization: only RES Lead can add shared dropdown options; advisers consume active options but cannot modify them.
- Files/storage: keep uploads in private local storage, show CSV controls only, and continue deleting temporary upload/preview/error artifacts through the existing lifecycle.
- Notifications/audit: keep account setup notifications unchanged; record option creation without secrets and omit specified completion events from the visible report only.

### Files Expected to Change
- `app/Enums`, `app/Models`, `app/Services/Identity`, `app/Http/Requests/Identity`, and `app/Http/Controllers/Identity`
- `database/migrations`
- `routes/web.php`
- `resources/views/identity/users`, shared dashboard components, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- Focused identity feature tests and user-management documentation

### Tests and Verification
- Focused tests for CSV headers/example rows, duplicate/existing skips, real row errors, dropdown-option authorization/use, audit visibility/filtering, and revised UI text.
- Full `php artisan test`, `php artisan route:list`, `npm.cmd run build`, Pint, and `git diff --check`.

### Risks and Rollback
- The option table is additive; rollback removes only shared option records and leaves existing user profile text intact.
- Existing profile values not yet in the option table remain visible and editable without silently rewriting them.
- Database unique constraints remain the final duplicate defense during confirmation; stale previews fail safely.

### Verification Status (2026-07-23)
- `php artisan test`: 68 tests and 646 assertions passed.
- `php artisan route:list`: 77 routes registered, including the RES Lead profile-option endpoint.
- Blade view compilation, the Vite production build, Pint, and `git diff --check` passed.
- The additive `profile_options` migration ran successfully against the local database.
- Interactive browser/mobile screenshots were not available because this session had no connected in-app browser or Chrome instance.

### Approval Notes
Approved by: User request
Date: 2026-07-23

## Plan: Account management and secure onboarding

### Goal
Replace the temporary user-management modules with a complete, role-authorized account workflow for RES Lead and Research Adviser users, while correcting shared dashboard, onboarding, and application-readiness behavior.

### Source Documents
- Primary requirement: attached consolidated implementation prompt, July 21, 2026 (sections 1-38 and final additions).
- Supporting designs: `ECRATS High Fidelity (5).pdf`, pages 1-8, plus the confirmed supervisor requirement for formatted CSV account imports.
- Conflicts or missing decisions: the final note calls for a random system password while the security requirements prohibit distributing passwords. ECRATS will create a random unusable internal credential and distribute only the username and one-time setup link. Official certificate generation is not yet implemented, so this phase can establish and document the maintained OVPRII asset contract but cannot claim generated-certificate verification.

### Scope
Included:
- Separate account name fields, institutional identifiers, creator tracking, profile details, and system-generated usernames.
- Searchable, filterable, paginated populated and empty user-management states.
- Server-enforced role creation, record visibility, editing, account status, and password-reset permissions.
- Individual account creation and bounded CSV imports with private temporary storage and audit records.
- Role-specific CSV/XLSX templates, import preview/confirmation, row-level errors, and one-time confirmation tokens.
- Secure email reset links without exposing or directly editing existing passwords.
- Pending-setup accounts, one-week single-use setup links, delivery status, single and mass resend actions, and soft-delete/mass-deactivation controls.
- One-time role-specific onboarding with a permanent Guide control.
- Shared footer, navigation, profile, breadcrumb, empty-state, and applicant requirement-state corrections.
- Field-specific login validation and generic credential mismatch errors only after required inputs pass validation.

Excluded:
- Technical Admin onboarding until that role is formally added to the implemented role enum.
- Profile photo uploads and two-factor authentication.
- Infrastructure controls such as a WAF, TLS termination, database encryption, and production mail delivery configuration.

### Implementation Approach
- Backend: thin controllers, Form Requests, policies, identity services, Laravel password broker, and parameterized Eloquent queries.
- Frontend: shared responsive Blade views for full-page role selection, creation-mode dialog, account form, preview, table mass actions, onboarding, and corrected dashboard components.
- Database: additive setup, onboarding, delivery, role-profile, and soft-delete fields; retain `users.name` as a generated compatibility display value.
- Authorization: RES Lead can manage non-RES-Lead accounts; advisers can create and manage only applicants within their allowed relationship scope.
- Files/storage: validate CSV/XLSX structure, MIME type, headers, template version, formulas, row count, and values; process previews from private local storage and delete temporary files after confirmation or expiry cleanup.
- Notifications/audit: use one-time Laravel password-broker tokens, role-safe setup notifications, delivery-state tracking, and security audit events without secrets or tokens.

### Files Expected to Change
- `app/Enums`, `app/Models`, `app/Policies`, `app/Services/Identity`, and `app/Http/*`
- `database/migrations`, factories, and seeders
- `routes/web.php`
- `resources/views`, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- Account, authentication, navigation, and authorization tests
- Account-management and deployment documentation

### Tests and Verification
- Focused account-management, authorization, import, password-setup, onboarding, navigation, requirement-readiness, and login tests.
- Full `php artisan test`, Pint, route list, migration status, Composer validation/platform checks, and `npm.cmd run build`.
- Desktop and mobile browser screenshots when the in-app browser connection is available.

### Risks and Rollback
- Existing users are backfilled conservatively from `users.name`; new fields remain additive so rollback does not discard the compatibility name or authentication records.
- User creation uses unique database constraints and transactions to protect generated usernames and institutional identifiers.
- CSV/XLSX uploads are capped to keep synchronous imports practical for the current deployment and are removed after parsing or expiry.

### Verification Status (2026-07-22)
- `php artisan test`: final run passed 61 tests and 587 assertions.
- Pint, Composer validation/platform checks, route registration, migration status, additive migration, idempotent seeding, and the Vite production build passed.
- `npm audit`: no vulnerabilities.
- `composer audit`: four medium advisories affect locked `guzzlehttp/guzzle 7.14.1`; all require `7.15.1` or later. Dependency updates await explicit approval.
- Live HTTP login page returned 200 at `http://127.0.0.1:8001/login`.
- Interactive browser screenshots remain unavailable because this session has no connected in-app browser.

### Approval Notes
Approved by: User request
Date: 2026-07-20

## Completed Plans

## Plan: Login authentication and role landing pages

### Goal
Implement the first working authentication slice for ECRATS: username/password login, seeded testing accounts, role-based temporary landing pages, logout, and backend role-access guards.

### Source Documents
- Primary requirement: attached login functionality request, July 17, 2026
- Supporting diagrams/forms: high-fidelity login page PDF, page 7; design guide pages 1-6
- Conflicts or missing decisions: Account creation screens are not part of this slice, so account-creation role rules will be implemented as backend service logic and tests for future controllers to reuse.

### Scope
Included:
- Proportional login UI scaling so the connected `1040 x 650` container fits common laptop/desktop viewports
- Stable inline login errors with username/password validation
- Laravel session authentication
- User role fields and seed/test accounts
- Temporary role landing routes
- Logout
- Role access middleware
- Authenticated-login redirect and browser-history cache protection
- Backend account-creation authorization service and tests

Excluded:
- Finished dashboards
- Full account creation UI
- Password reset
- Email verification
- Production account onboarding workflow

### Implementation Approach
- Backend: Laravel session guard with a focused auth controller and form request.
- Frontend: Preserve the previous login design ratio, scale the complete desktop container when necessary, and reserve space for inline validation errors.
- Database: Add username, role, and account status columns to `users`, with a unique username supporting up to 30 characters.
- Authorization: Add role middleware for temporary landing pages and account creation service rules.
- Files/storage: No file storage changes.
- Notifications/audit: No notification or audit changes in this slice.

### Files Expected to Change
- `routes/web.php`
- `app/Models/User.php`
- `app/Enums/UserRole.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/PreventBrowserHistory.php`
- `app/Http/Middleware/RedirectAuthenticatedUser.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Services/Identity/AccountCreationAuthorizationService.php`
- `app/Services/Identity/UserAccountService.php`
- `app/Support/RoleHome.php`
- `bootstrap/app.php`
- `database/migrations/*_add_login_fields_to_users_table.php`
- `database/seeders/*`
- `database/factories/UserFactory.php`
- `resources/views/auth/login.blade.php`
- `resources/views/landing/role.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `tests/Feature/Auth/*`
- `tests/Unit/Services/*`

### Tests and Verification
- `php artisan test`
- `php artisan route:list`
- `php artisan migrate`
- `php artisan db:seed`
- `npm.cmd run build`

### Risks and Rollback
- Existing users without usernames receive a unique `user-{id}` fallback before the database makes the username column required.
- Rollback removes the new user login columns and temporary auth routes/pages.

### Approval Notes
Approved by: User request
Date: 2026-07-17
