# Changelog

## 2026-08-23 — stopped INFINITY SAGA continuation

- Added RES Requirements Configuration and shared Reviewer Worksheet Configuration; earlier focused settings/artifact coverage passed.
- Adjusted generated worksheet signatory/title/continuation layout and selected notification, assignment, revision, reviewer-decision, and settings presentation contracts.
- Implemented combined RES certificate Preview/Download All and real-reviewer/date workspace summaries, but these final edits are not yet verified.
- **Database incident:** an inadequately isolated test retry targeted local MySQL and removed ECRATS data. Current key-table counts are zero; migrations after July 29 are pending. Binary logs remain available and contain the pre-drop boundary. No recovery has been attempted.
- The user stopped new work. `INFINITY_SAGA_CONTINUATION_HANDOVER_2026-08-23.md` is the authoritative continuation ledger.

## 2026-08-22

### Added

- Persisted, authorized screening drafts and page-leave application draft saving without bypassing final workflow validation.
- Reviewer Worksheet Configuration with private signature preview and immutable generated-artifact snapshots.
- Deterministic offline default certificate QR metadata and plural-recipient Preview All PDF composition.
- Full RES operational Reports page and focused authorization/filter/aggregate/query-count coverage.

### Corrected

- Draft document replacement now deletes only never-formally-submitted rows/private bytes; initial/C1/C2 submitted versions remain the maximum retained business history.
- Settings, dashboard current-term scope, monitoring/drill-down filters, notifications/Bin layout, Applicant revision controls, certificate queue/workspace, private embedded previews, and long-value/responsive boundaries now follow the ENDGAME contracts.
- Generated worksheets support multiline titles and place the signature above a centered larger name/date without third-page overlap.
- Recipient-aware report and certificate states no longer treat a partial certificate set as fully released.

### Database and verification

- Added and applied `2026_08_22_000000_add_submission_and_worksheet_settings.php` as safe additive batch 6. It introduces formal-submission metadata, nullable worksheet settings, and an owner/application/workflow draft table; no existing record is deleted.
- Final complete SQLite in-memory suite: 329 tests, 4,545 assertions, passed.
- Pint, Vite production build, Blade cache, 172-route discovery, migration status, and whitespace checks passed.
- Browser/native-preview, pixel/raster certificate comparison, and independent QR scanning remain pending for the documented local-tooling reasons.

## 2026-08-21

### Corrected

- Full Board application-level release now requires agreement from all three current submissions and freezes all three source-version IDs, anonymous feedback groups and actionable requirements. Approved consensus enters certificate preparation without a redundant manual decision-release action.
- Review worksheet display versions are now business versions V1/V2/V3 from initial/C1/C2, independently of immutable internal artifact revision numbers.
- Notification type filtering resets the relation's inherited `created_at` order before the MySQL-safe distinct type query. Inbox/Bin individual, selected and all destructive actions share one accessible confirmation dialog, and database failures never render SQL or stack traces to users.
- All certificate consumers use the application's complete recipient certificate set for queue state, metrics, eligibility, generation, release, evaluation/claim and private preview/download.
- Term selection or All now scopes role dashboards, lists, revisions, assignments, monitoring, drill-downs, certification and reports on the server without broadening role/ownership access.
- Adviser Reviewer Capability/Capacity, Review Monitoring tables/drill-downs, Applicant revision controls, certificate queue/configuration/QR provenance, profile summaries, username stability, private previews, embedded search icons and required subtext removal were audited and covered by focused tests.
- Draft cleanup retries a synchronized-filesystem directory walk once when the directory still exists, preventing an already-completed user discard from surfacing a transient `Lstat failed` error.

### Database

- Added and applied `2026_08_21_000000_preserve_combined_release_and_worksheet_business_versions.php`. It adds/backfills combined release provenance and worksheet business versions without deleting data and refuses rollback that would discard a combined provenance set.

### Verification

- Changed-area suite: 178 tests, 2,534 assertions, passed.
- Final complete SQLite in-memory suite: 319 tests, 4,414 assertions, passed.
- Repository-wide Pint, Composer validation, Blade compilation, route discovery and Vite production build passed.
- Authenticated browser viewport/interaction acceptance and pixel-level certificate/reference/QR readability remain pending because no browser surface and no Poppler renderer were available. Details are in `DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md` and `DOOMSDAY_IMPLEMENTATION_STATUS_2026-08-21.md`.

## 2026-08-17

### Added

- Added Adviser-owned Reviewer capability with a live `reviewer_enabled` gate, multi-classification/capacity profile, accessible Adviser submenu, legacy URL containment, RES Show/Hide Reviewer mass actions, and conservative legacy identity reconciliation.
- Added immutable Reviewer submission versions, soft-retained comments, exact release-source/frozen-feedback provenance, persisted Full Board consensus/conflict state, conflict notifications/priority, and one release gate shared by individual and bulk actions.
- Added current-cycle Reviewer replacement after work starts, including immediate revocation/notification, retained superseded evidence, locked eligibility revalidation, and replacement-only consensus.
- Added persisted certificate issue/valid-through dates, automatic Pending Certificate Release generation after a final Approved result, Review Worksheet Background provenance, typed future-only background histories, and private RES signatory settings.
- Added versioned ten-question Applicant evaluation data and anonymous aggregate RES reporting.
- Added role-owned Profile and Security & Privacy settings, Adviser endorsement targets/live statistics, strict Adviser Applicant scope, and RES Review Monitoring for Adviser and Reviewer workloads.

### Changed

- Reviewer is no longer a separately interactive or creatable account. Adviser is the primary identity and Reviewer is a supplementary, deny-by-default entitlement. Canonical pages are under `/adviser/reviewer`.
- `.xlsx` import now recognizes required headers structurally across reordered/renamed/hidden sheets instead of requiring template origin/fingerprint. Inert external-link metadata is accepted but never resolved; formulas and active content remain blocked.
- Phone validation now requires exactly 11 numeric digits on create, edit, and import. Dropdown Option management moved from User Management into RES Settings.
- New Applicant documents are limited to PDF and safe JPG/JPEG, PNG, GIF, and WebP content. Historical Office files remain available through private authorized fallback routes.
- Applicant revisions now use requirement/version accordions; returned detail controls, Adviser combined information, secure preview fallbacks, Reviewer assignment layout, inline worksheet selection/asynchronous saves, and current-cycle dashboard status were aligned with the Finale brief.
- Decision & Certificates now uses three metrics and a privacy-limited queue. Applicant identity is excluded before a certificate exists; split Full Board decisions cannot be released.
- Certificate/background changes are future-only. The August 13 retroactive regeneration contract is superseded; issued certificate binaries and their issue/release/claim provenance are retained unchanged.

### Security and migration notes

- Seven additive migrations dated `2026_08_17` preserve legacy IDs and backfill submitted reviews/validity where applicable. Identity, review-version/release, certificate-validity, worksheet-background, and role-settings migrations contain explicit unsafe-rollback guards.
- Reviewer capability, assignment ownership, conflict/capacity/self-endorsement eligibility, consensus, private-file nesting, and self-service account allowlists are all enforced server-side. Applicant and Reviewer identities remain excluded from their opposing blind contexts.
- As of this documentation pass, `php artisan migrate:status` reports all seven new migrations as Pending. Apply them only through the rollout sequence in `THE_FINALE_IMPLEMENTATION_2026-08-17.md` with a verified database/private-storage backup.

### Verification status

- Focused automated slices for role/settings, Adviser scope/profile, reassignment, and their related regressions passed during integration. Route discovery succeeds with 157 routes; Blade caching and the Vite production build passed at intermediate checkpoints.
- Final acceptance remains open for the settled-tree full Laravel suite, repository-wide Pint, strict Composer/platform checks, final route/migration/Blade/Vite/diff checks, non-destructive migration execution, and authenticated local UI checks at 1440, 1280, 1024, 768, and 390 pixels.
- The authoritative implementation, intentional supersessions, rollout/rollback rules, evidence list, pending checks, and remaining limitations are in `THE_FINALE_IMPLEMENTATION_2026-08-17.md`.

## 2026-08-13

### Changed

- Restricted RES application search to approved application metadata, reorganized RES detail into a full-width requirements-first layout, and moved the RES-only Audit Log under Reports while removing Applicant Reports access.
- Restricted Student Adviser selection and backend validation to active eligible same-department Advisers while retaining Faculty cross-department selection.
- Made Reviewer dashboard results current-assignment driven, reordered the workspace rail to Review Comment, Review Worksheet, and Review Assessment, simplified overall/document comments, corrected Protocol item 15, and enforced 15 non-whitespace recommendation characters.
- Added editable Completed worksheets and moved immutable form snapshots/artifacts into the atomic overall review submission boundary. Informed Consent `No` now clears/disables dependent answers and renders them as not applicable.
- Replaced RES decision overrides/document recovery mapping with a read-only review workspace and exact Reviewer-submission release. Revision decisions no longer require a document-linked comment.
- Added typed Certificate/Decision/Both Release All processing with eligible counts, explicit confirmation, idempotent per-record outcomes, bounded batches, notifications, and detailed audit provenance.
- Made certificate-background activation regenerate all active historical/current certificate renderings while preserving issue/release/claim history, retaining superseded binaries, and leaving the previous valid version active on failure.

### Verification

- Added and updated focused tests for name-search exclusion, report ownership, department-scoped Adviser eligibility, assignment freshness, overall/document comments, editable worksheets, consent conditional behavior, RES read-only boundaries, typed bulk release, historical certificate dates/claims, and failed regeneration retention.
- The complete Laravel suite passes with 249 tests and 3,650 assertions. Focused affected suites, changed-file Pint, strict Composer validation, platform requirements, the 133-route listing, isolated migration up/rollback/up checks, Blade compilation, the Vite production build, and `git diff --check` pass.
- Signed-in browser acceptance passed at 1440, 1280, 1024, 768, and 390 pixels across the RES details/certification/review-release surfaces, Reviewer dashboard/workspace/conditional consent form, and Applicant navigation/application pages. The tested pages have no whole-page horizontal overflow or browser-console warnings/errors; intended wide tables retain internal bottom scrolling.
- See `REVIEW_RELEASE_CERTIFICATE_GUIDELINE_2026-08-13.md` for the authoritative implementation and authorization contract.

## 2026-08-12

### Changed

- Reorganized RES Certificate Processing around a concise four-metric summary, bounded search/state filters, and one readable certification queue instead of dense per-application action cards.
- Moved application-specific decision release, selected comments, certificate generation/retry/preview/download/regeneration, and immutable version history into a focused selected-application dialog.
- Moved certificate-background preview, upload, activation, reset, version history, and independent pagination into a separate management dialog; bulk eligible release now uses its own confirmation dialog.
- Added responsive queue containment, compact semantic state badges, phone-stacked dialog content, shared focus trapping/Escape/backdrop closing, focus restoration, scroll locking, and validation-aware dialog reopening without changing server routes or workflow rules.
- Replaced the certification queue's decorative circular state markers with pagination-aware row numbers.
- Prevented the narrow Reviewer review rail from compressing the Review Comments card beneath Review Worksheets. Required Revision comments now select a specific source document automatically, show the exact requirement and filename, and are rejected server-side if stored as overall-only.
- Added an RES recovery mapping for immutable submitted reviews whose older General/Overall comments lack a document link. RES must select at least one comment and explicitly map one or more current application files; the resulting revision requirement records the mapping without rewriting Reviewer-authored content.
- Fixed an invisible Applicant evaluation-validation failure that left the certification state at Survey Required with no explanation. Survey validation now uses the page's `certificateSurvey` error bag, errors render inside the Certification panel and beside affected fields, and both feedback fields expose the same five-character minimum in the browser and server.

### Verification

- Added RES Certificate Processing feature coverage for global metrics, filtered queue results, preserved action URLs/dialog contracts, application-scoped validation re-entry, and role isolation.
- Focused certification workflow tests pass with 9 tests and 68 assertions. Blade compilation, changed-file Pint, the Vite production build, and `git diff --check` pass.
- The numbered queue, Reviewer comment invariant, intrinsic rail sizing, and RES legacy-comment recovery path pass 22 focused tests with 296 assertions. Blade compilation, changed-file Pint, the production Vite build, and `git diff --check` also pass.
- The combined certification, Reviewer, and RES regression set passes with 28 tests and 358 assertions. The complete Laravel suite passes with 241 tests and 3,541 assertions.
- The in-app browser runtime exposed no controllable browser instance, so final live viewport and modal interaction acceptance remains documented as pending.

## 2026-08-10

### Changed

- Made the reviewer-assignment history and official-artifact migrations resumable after partially committed MySQL DDL; repeated migration execution is idempotent and seeding completes normally.
- Deferred official KLD-RES-04-001 and KLD-RES-04-002 PDF generation until the complete overall review is submitted. Both versioned artifacts now include persisted worksheet data plus the final decision and complete assignment-comment record, and incomplete legacy artifacts are not presented as current official versions.
- Limited the initial Reviewer comment history to the newest 20 entries with assignment-authorized cursor loading, an independent bounded scrollbar, authoritative counts, duplicate-request guards, and delete confirmation.
- Aligned the centered workspace summary and worksheet status/official-artifact controls with the current pages 54-69 reference and explicit metadata/page-comment overrides.
- Standardized Reviewer worksheet presentation as `Not Started`, `In Progress`, and `Completed` without changing the persisted Draft/Final enum contract.
- Replaced the final-review native confirmation with an accessible styled confirmation/result dialog that displays the selected decision, blocks incomplete submissions, prevents duplicate requests, preserves Save Draft and no-JavaScript POST behavior, and exposes loading, validation, error, and success states.
- Limited the term `min=today` browser rule to new term entry. Existing configured historical starts remain editable, while Ending Date dynamically stays on or after Starting Date and server ordering remains authoritative.

### Security and Reliability

- Overall submission and both private artifact rows commit atomically. A rendering failure removes partial files, retains Final worksheet snapshots, and leaves the decision unsubmitted with a safe validation error.
- Ready artifacts require a submitted parent review and nested authenticated policy checks. Prior artifact versions are retained as Superseded rather than overwritten or deleted.
- Native PDF/image application-document streams retain private nested authorization and defensive headers while replacing CSP `sandbox` with the official-artifact same-origin framing policy; protected Office fallback behavior is unchanged.

### Verification

- Added focused coverage for deferred generation, persisted decision/comment mapping, generation rollback, version supersession, pre-submission denial, private artifact integrity headers, comment pagination, and duplicate prevention.
- Added focused contracts for worksheet wording, final-review modal/accessibility selectors and JSON result data, duplicate final-submission denial, historical term controls, and non-sandboxed private preview headers.
- Local MySQL migration and seeding completed successfully. Before verification was postponed, 81 focused tests passed with 1,649 assertions across official artifacts, Reviewer workflow/catalog, assignment pages, RES visibility/settings/screening, dashboard roles, and workbook templates.
- Continuation verification passed 64 focused tests with 1,586 assertions and the complete 226-test suite with 3,386 assertions. Changed-file Pint, strict Composer validation, platform requirements, 116-route listing, migration status, Blade compilation, Vite production build, and `git diff --check` pass.
- Composer metadata now uses the patch-compatible FPDI `~2.6.8` constraint without changing the locked FPDI 2.6.8 or FPDF 1.8.6 packages. Repository-wide Pint still reports an untouched service file, and Composer audit reports existing dependency advisories; both are documented for separate team action.
- Local browser checks covered the Reviewer worksheet chooser at 1280, 1024, 768, and 390 pixels plus Escape/focus restoration. The remaining 1440, writable final-result, spreadsheet-application, and official-PDF visual checks are recorded in `IMPLEMENTATION_STATUS_2026-08-10.md`.

## 2026-08-04

### Added

- Additive Reviewer workflow persistence for conflict state, one overall submission, the two official form submissions, and assignment-owned review comments.
- Conflict-gated blind Reviewer workspace with protected documents, overall/document/page comments, independent form drafts/finalization, decision drafts, and immutable final submission.
- Server-owned KLD-RES-04-001 protocol and KLD-RES-04-002 informed-consent contracts with exact answer/recommendation validation.
- Separate policy-authorized direct Download actions beside every RES requirement View Document action.

### Changed

- Superseded on August 5: initial Reviewer assignments required a one-time conflict declaration. Current assignments now grant immediate blind-workspace access and inherit the active Reviewer Submission deadline when configured.
- Applications move to `review_submitted_pending_release` only after every initial Reviewer assignment in the review cycle submits.
- Centered the requested RES Applicant Category, Applicant Submitted/requirement, Reviewer Application Code/Status/Deadline, and User Management Name columns; Reviewer deadline text remains dark.
- Reworked RES screening actions, bottom warning, direct document actions, decision actions, and Reviewer selection into contained desktop/tablet/phone layouts. Reviewer filters now live inside Eligible Reviewers and selected removal uses an X icon.
- Kept all wide requirement, assignment, and Reviewer tables in bottom horizontal scrollers with no extra bottom padding or page-level overflow.

### Security

- Superseded on August 5: Reviewer work previously required a cleared conflict state. It now requires exact current-assignment ownership, an active unsubmitted assignment, and an open Reviewer Submission period. Parent/nested document authorization is repeated for every private preview/download.
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
- Superseded on August 5: past dates are accepted when ranges remain ordered. Explicit `On`/`Off` overrides runtime dates, and changing either process date restores automatic date evaluation.
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
## 2026-08-11

### Applicant revision and certification

- Replaced Applicant and RES certificate placeholders with complete, role-scoped dashboards.
- Added explicit decision/comment release, two-cycle revision records, requirement-specific immutable replacements, and direct same-Reviewer re-review.
- Added official-template certificate generation, single/bulk release, regeneration/version history, safe failure state, and future-only background versioning.
- Added post-release Applicant evaluation, explicit claim, and private claimed-version preview/download.
- Added audit events, notifications, throttles, ownership checks, deadline enforcement, focused tests, and workflow documentation.
