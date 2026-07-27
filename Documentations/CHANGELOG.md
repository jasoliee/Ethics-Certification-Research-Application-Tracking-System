# Changelog

## 2026-07-27

### Added

- Applicant-owned create, continue, edit, detail, requirements, private document, and formal submission workflows.
- A unique editable-draft slot, Thesis/Capstone metadata, explicit application stage, mandatory/type-aware requirement configuration, and four baseline requirements.
- Private versioned PDF, Word, JPEG, and PNG uploads with authorized preview/download and retained replacement history.
- Shared requirement completion and server-enforced configured submission periods.
- Adviser-scoped submitted-application dashboard data, searchable/filterable paginated list, details, private document access, and submission notifications.
- RES-only individual and bulk restoration of actor-previewed archived accounts with conflict checks, preserved original records, and audit events.
- Applicant/Adviser workflow tests and expanded workbook/restoration coverage.

### Changed

- Dashboard summary cards now place a stable icon column on the left and center the count above its label on the right.
- Wide dashboard and administration tables now reuse one focusable internal-overflow behavior.
- Student workbook Row 2 now contains the approved realistic example, controlled by the exact visible sentinel in Instructions.
- Import preview now separates active existing, archived, restored, and restoration-conflict categories.
- Applicant and Adviser dashboards now derive their application data from the formal initial-submission boundary.

### Verification

- `php artisan test` passed 104 tests with 1,812 assertions; all 90 routes compiled and all 124 project PHP files passed syntax checks.
- The additive migration is applied in batch 8; baseline requirement seeding and a non-destructive pretend rollback passed.
- Cache clearing, Pint, strict Composer validation, platform checks, the Vite production build, and `git diff --check` passed.
- Composer reported no locked-package security advisories, and npm reported zero vulnerabilities at the moderate threshold.
- The supplied PDF comparison and 1440/1280/1024/768/390 browser checks remain implemented in code but pending manual visual verification because the required runtimes were unavailable.

## 2026-07-24

### Added

- Excel-only `.xlsx` templates with exact Accounts, hidden Options, and Instructions worksheets.
- Database-backed Reviewer Classification plus add, rename, deactivate, and restore management for all shared profile options.
- Categorized import previews, bounded OOXML validation, valid-row-only single-use confirmation, and post-write setup delivery.
- Expanded audit filters, recursive sensitive-metadata sanitization, reusable table tooltips, consistent badges, and responsive pagination.
- Dedicated Adviser User Management, Dropdown Option Management, and Audit Log guides.

### Changed

- Superseded the active CSV workflow with `.xlsx` so the approved dropdown, formatting, identifier, and instruction requirements can be enforced.
- Updated account import to skip existing and later duplicate identities without overwriting them while reporting conflicting identities as invalid.
- Updated reviewer classification from a fixed PHP enum to a database-managed string catalog that preserves historical values.
- Updated Guzzle and PSR-7 within their current major versions to resolve locked dependency advisories.

### Requirements

- PHP `ext-zip` is now a declared platform requirement for workbook generation and validation.

## 2026-07-22

### Added

- Pending account setup with one-time seven-day password links and delivery state.
- Role-specific onboarding guides with permanent Guide access.
- Role-specific CSV/XLSX templates, preview/confirm import, error reports, and spreadsheet safety checks.
- Mass account deactivate, archive, selected resend, and all-pending resend actions.
- Confirmed surname/identifier correction with username regeneration and notification.
- Applicant initial-submission guard requiring all active documents to be completed.
- MIME-based document icons, authorization-denial auditing, and RES audit history.
- Maintained OVPRII background asset and complete implementation/security documentation set.

### Changed

- Account creators no longer enter usernames, passwords, password confirmation, or Date Joined.
- New accounts remain pending until the account holder chooses a password.
- Footer identifies ECRATS and RES, removes KLD Login, and links the address to Maps.
- Settings is removed from the sidebar but remains in the profile menu.
- Draft Application Status and My Application cards remain empty until accepted submission.

### Known Limitations

- Official document/certificate generation, QR verification, and the later review lifecycle remain incomplete.

## 2026-07-20

### Added

- RES Lead and Adviser user-management workflows.
- Separate account identity fields and institutional identifiers.
- Server-generated usernames and creator tracking.
- CSV account template/import with bounded validation and private cleanup.
- Account status controls, password-reset links, and security audit logs.

### Changed

- Login field validation is separate from generic credential mismatch errors.
- RES Lead can create researcher, adviser, and reviewer accounts but never RES Lead accounts.
- Adviser can create and manage only allowed student and faculty researcher accounts.
- Date created comes from `created_at`; passwords and usernames are not directly edited.

## 2026-07-18

### Added

- Canonical role-aware `/dashboard` route.
- Role-specific profile pages and clickable sidebar profile area.
- Student Researcher and Faculty Researcher account categories.
- Combined applicant Revision and Certificates navigation.
- Header breadcrumb placement and timeline term metadata.
- Reusable delayed research-title tooltip.
- Responsive global KLD footer.
- Dashboard query-count coverage and implementation documentation.

### Changed

- Reduced sidebar width and preserved full role labels.
- Moved notifications out of sidebar navigation.
- Repositioned notification and profile menus.
- Centered Status and Action table columns and normalized status badges.
- Increased login form-panel opacity slightly.
- Reduced dashboard logo transfer size.
- Consolidated repeated dashboard count queries and paginated notification history.

### Preserved

- Authentication, CSRF logout, role middleware, record policies, route authorization, database-driven populated states, and empty states.
