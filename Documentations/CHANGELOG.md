# Changelog

## 2026-08-04

### Added

- Additive Reviewer workflow persistence for conflict state, one overall submission, the two official form submissions, and assignment-owned review comments.
- Conflict-gated blind Reviewer workspace with protected documents, overall/document/page comments, independent form drafts/finalization, decision drafts, and immutable final submission.
- Server-owned KLD-RES-04-001 protocol and KLD-RES-04-002 informed-consent contracts with exact answer/recommendation validation.
- Separate policy-authorized direct Download actions beside every RES requirement View Document action.

### Changed

- Initial Reviewer assignments now require a one-time conflict declaration and inherit the active Reviewer Submission deadline when configured.
- Applications move to `review_submitted_pending_release` only after every initial Reviewer assignment in the review cycle submits.
- Centered the requested RES Applicant Category, Applicant Submitted/requirement, Reviewer Application Code/Status/Deadline, and User Management Name columns; Reviewer deadline text remains dark.
- Reworked RES screening actions, bottom warning, direct document actions, decision actions, and Reviewer selection into contained desktop/tablet/phone layouts. Reviewer filters now live inside Eligible Reviewers and selected removal uses an X icon.
- Kept all wide requirement, assignment, and Reviewer tables in bottom horizontal scrollers with no extra bottom padding or page-level overflow.

### Security

- Reviewer work requires exact assignment ownership, cleared conflict state, an active unsubmitted assignment, and an open Reviewer Submission period. Parent/nested document authorization is repeated for every private preview/download.
- Applicant and Adviser database identity is omitted from Reviewer pages; Reviewer identity, forms, comments, and decisions remain unavailable to Applicant routes before official release.
- Reviewer audit events store bounded state and identifiers only, excluding form answers, comment/decision text, filenames, document content, and private paths.

### Verification

- The focused Reviewer/RES suite passed 26 tests with 288 assertions; the complete Laravel suite passed 179 tests with 2,650 assertions.
- Pint, strict Composer validation, platform requirements, route registration, migration status, Blade compilation, the Vite production build, and `git diff --check` passed.
- Interactive checks passed at 1440px, 768px, and 390px for RES details/assignment, Applicant tables/details, Reviewer list/conflict/workspace/forms, action alignment, and internal bottom scrollbars without whole-page horizontal overflow.

## 2026-08-03

### Added

- Safe RES screening correction for administrative fields, notes, classification, and rationale, with compatible-assignment preservation and started-work protection.
- Reviewer Assigned Applications list, search/filtering, pagination, policy-authorized details, and assignment-gated private document routes.
- Same-origin no-store Word/Excel preview fallback and immediate Applicant upload progress/checklist synchronization.

### Changed

- Raised the private application-document limit from 10 MB to 100 MB in validation, interface guidance, tests, and documentation.
- Reviewer selection now lists all active classification-matched candidates, prioritizes department then institution matches, provides a Department filter, removes the Availability column, and keeps capacity visible.
- Screening/classification summaries and Reviewer assignment surfaces now wrap responsively without overlapping labels, badges, or values.

### Security

- Screening corrections lock application, screening, and assignment rows; pending unstarted assignments may be reconciled, but started or submitted review work cannot be overwritten.
- Reviewer assignment pages and document requests remain scoped to the authenticated Reviewer's exact assignment. Applicant and Adviser profile identities are omitted from the Reviewer detail.

### Verification

- The final Laravel suite passed 172 tests with 2,573 assertions; the focused RES correction suite passed 12 tests with 124 assertions, and the Applicant workflow suite passed 18 tests with 202 assertions.
- Pint, strict Composer validation, platform requirements, route registration, migration status, Blade compilation, the Vite production build, and `git diff --check` passed.
- Interactive checks passed at 1280px and 390px for RES classification, reviewer selection, Reviewer assignment list/detail, the private Excel fallback, and the populated submitted Applicant checklist. A live Applicant upload transition remains a manual check because the populated test account has no editable draft.

## 2026-08-02

### Added

- RES Lead screening details with application/research summaries, the current private requirement checklist, administrative checks, notes, and Expedited, Full Board, or Exempted classification.
- Additive `application_screenings` persistence with a single initial classification per application and explicit `exempted` workflow status support.
- Eligible reviewer selection, exact one-reviewer Expedited and three-reviewer Full Board assignment, confirmation, immutable results, and responsive candidate/capacity views.
- RES classification and assignment Form Requests, policy abilities, transactional row locking, late eligibility/capacity revalidation, duplicate protection, named throttling, bounded audits, and neutral notifications.
- Focused workflow, authorization, queue filter/search, document action, invalid count, duplicate, capacity, and Exempted-path tests.

### Changed

- Removed obsolete term-filter breakpoints from the RES Applications Queue so the current screening filters no longer overlap, and restored Apply Filters above Reset Filters in the rightmost desktop column.
- Expanded the former Endorsed Applications landing page into the complete post-endorsement Applications Queue with Applicant category, research type, review type, institute/program, and endorsement-date filters.
- Updated local demo accounts and histories so post-screening demo statuses have their required screening records and the Expedited assignment page has an eligible reviewer.

### Preserved

- Private research files remain outside `public/` and require nested application/document authorization.
- Audit metadata excludes screening notes, classification reasons, file contents, reviewer comments, and private storage paths.
- Blind review, reviewer-declared conflicts, decision release, Exempted direct certificate processing, certificate rendering, and QR verification remain pending.

### Verification

- The full Laravel suite passed 164 tests with 2,486 assertions; the focused RES screening suite passed 12 tests with 141 assertions.
- Strict Composer validation, platform requirements, route registration, migration status, Blade compilation, Pint, PHP syntax checks, the Vite production build, and `git diff --check` passed.
- Interactive queue, screening, reviewer selection, and confirmation checks passed at 1440, 1280, 1024, 768, and 390 pixels with no page-level horizontal overflow or browser-console warnings.

## 2026-07-31

### Changed

- Made the RES Endorsed Applications filters respond to the sidebar-adjusted content container, with four-column, two-column, and compact paired phone arrangements.
- Aligned the RES filter actions in the rightmost column by moving Apply Filters above Clear and Academic Year into the lower filter row.
- Compacted RES application columns and placed horizontal-only overflow directly under the table, while retaining the page's existing vertical scrollbar.
- Contained the RES application panel and wide table within a zero-minimum page grid so phone layouts remain complete at default zoom and the table scrollbar no longer escapes the viewport.
- Allowed the RES application table to fill wider panels at reduced browser zoom while preserving its mobile overflow minimum.
- Reset standalone status-form spacing inside the account lifecycle row so Deactivate or Reactivate aligns exactly with Delete Account.
- Matched the Security and Privacy heading to the left-aligned Profile heading throughout RES Lead Settings.
- Restyled Applicant create/edit forms as three responsive icon-headed information sections with an unframed action row, while preserving existing fields and validation.

## 2026-07-30

### Added

- RES-only Endorsed Applications landing page with protected navigation, safe search/status/term/date filters, 15-row pagination, and an internal horizontal-scroll table.
- Neutral database notifications to every active RES Lead when an Adviser endorses an application for screening.
- Focused coverage for RES queue visibility and authorization, active/inactive RES notifications, strict deadline dates, 11-digit phone validation, alphanumeric Student Numbers, and the corrected layout hooks.

### Changed

- Rebuilt Deadline Configuration as a bounded seven-phase table with Upcoming Deadline and Active Date Range summaries; removed the aggregate Manual Toggles On summary.
- Every term start, process opening, deadline, and release value now rejects past input in the browser and backend even when Manual Open is selected. Manual `On` remains a runtime date override; `Auto` restores date evaluation.
- Limited Phone Number to 11 digits and preserved alphanumeric Student Number/Employee ID validation in individual and Excel account creation.
- Aligned Applicant header/checklist/upload/form controls, made Adviser Return for Correction red, removed duplicate detail-stage text, centered account application metrics, aligned account lifecycle actions, removed the edit-profile Dropdown Options shortcut, and left-aligned the import template guidance.
- Adjusted latest-endorsement eager loading for SQLite/MySQL-compatible pagination after a cross-database ambiguity was found by the new queue test.

### Preserved

- Private application documents remain outside `public/` and stream only through authorized routes.
- Drafts remain excluded from the three-formal-application limit and from Adviser/RES queues.
- RES screening decisions and later Reviewer/result/certificate workflows remain pending.

## 2026-07-29

### Added

- Required Applicant Starting Date and Ending Date fields with ordered-date validation, formatted detail display, and a legacy duration-text fallback for existing records.
- `profile_option_aliases` so renamed Year Level, Institution, Department, Program, and Reviewer Classification labels retain one immutable option identity for older official workbooks.
- Applicant final-submission confirmation, exact readiness checklist ordering, combined application/completion overview, Open/Closed submission label, and red formal-limit warning.
- Regression coverage for newest Applicant selection, cross-term Adviser/RES dashboard visibility, date ranges, submission labels/dialog structure, complete deadline borders, and active/inactive historical workbook labels.
- A maintained current-feature catalog and a dedicated deadline-configuration guide.

### Changed

- Applicant dashboard now selects the newest-created owned application instead of allowing a later edit to an older record to displace it.
- Applicant, Adviser, and RES application dashboard queries no longer hide relevant stored records solely because their academic-term link is missing or historical; deadlines and timeline events remain current-term aware.
- New/edited research information stores expected dates while preserving the old duration column only for historical compatibility.
- Bulk account validation resolves current labels, historical aliases, or stable numeric IDs to one active option and stores its current canonical label.
- Requirement upload controls, long document-modal values, Adviser decision alignment, application section spacing, deadline borders/On-Off labels, and narrow-screen behavior were refined.
- Documentation now records the three-formal-application limit, private Excel requirement uploads, Adviser return/endorsement, manual-open versus automatic deadline behavior, and the remaining incomplete review/certificate modules.

### Preserved

- Draft applications do not consume the maximum of three formal application slots.
- A returned application reuses its existing formal slot and original submission timestamp.
- Private documents remain controller-authorized and outside `public/`.
- The approved macro-free `.xlsx` Accounts columns remain unchanged; stable option resolution is server-owned.

### Verification

- `php artisan test` passed 149 tests with 2,271 assertions using an isolated compiled-view path to avoid the running Windows development server's Blade-cache lock.
- All 169 project PHP files passed syntax checks; Pint, strict Composer validation, platform requirements, all 99 routes, Blade compilation, the Vite production build, Markdown local-link validation, and `git diff --check` passed.
- Both July 29 additive migrations ran in batch 12. Pretend rollback confirmed that rollback removes only `profile_option_aliases` and the two expected-duration date columns.
- Browser acceptance at the documented desktop/tablet/mobile widths remains pending because no controllable browser session was available.

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
