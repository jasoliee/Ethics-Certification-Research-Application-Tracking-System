# DOOMSDAY Implementation Status - 2026-08-21

> Historical checkpoint. The current status is [ENDGAME Implementation Status - 2026-08-22](ENDGAME_IMPLEMENTATION_STATUS_2026-08-22.md).

This record covers the complete `DOOMSDAY-INPUT.txt` audit and supersedes the August 17 document only for the requirements changed or reverified here. The row-by-row authority is [DOOMSDAY Requirements Traceability](DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md).

## Outcome

The current source and automated test suite contain no known unresolved DOOMSDAY functional defect. The audit repaired combined Full Board releases, worksheet business versions, the notification MySQL 3065 query and destructive confirmations, multi-recipient certificate consumers, academic-term scope, certificate QR/configuration provenance, responsive source contracts, safe database error rendering, and a synchronized-filesystem draft cleanup race.

Manual authenticated browser acceptance and pixel-level certificate/reference inspection remain pending because the in-app browser exposed no connected surface and this machine has no Poppler renderer. Those requirements are **Not yet verified**, not complete.

## Implemented and reverified

- Reviewer assignment uses the Adviser capability model and never uses historical Expedited/Full Board Reviewer classifications. Endorser, status, capacity, conflict, assignment and role rules are enforced server-side.
- Individual and bulk Adviser creation share Reviewer Capability/Capacity validation and row-level import errors.
- One Full Board application release freezes all three current submission versions and releases every anonymous feedback/actionable-requirement group. Conflicts remain blocked; Approved consensus enters secure certificate preparation automatically.
- Document display versions increment only for actual reviewed replacements. Worksheet display versions are cycle-derived V1/V2/V3 while internal artifacts stay immutable and independently versioned.
- Certificate generation quotes titles, excludes Payment Proof, places the qualifying document list on the next line, snapshots signatory/validity/background/QR provenance, and uses a fixed lower-left QR region.
- Every certificate recipient has an independent personalized certificate. Metrics, queues, release, eligibility, survey, claim, preview/download and bulk paths aggregate the plural relationship.
- Applicant revision pages use three steps, application-specific navigation, collapsible summaries, automatic single-file uploads, anonymous combined feedback, protected version actions, actionable-revision validation and released worksheet history.
- Review Monitoring uses reviewer/adviser workload tables, the requested calculations and filters, secure scoped drill-downs and no legacy assignment-progress panel.
- Notification type filtering is MySQL-safe; inbox/Bin filters, 20-row pagination and individual/selected/all actions use one accessible confirmation modal where confirmation is required.
- Selected academic term or All is applied server-side to relevant dashboards, lists, queues, monitoring, drill-downs and reports after role/ownership scoping.
- Profile update allowlists preserve username and institutional ID; summaries display authorized real account data; private previews remain same-origin, authenticated, nested and defensively headed.
- All explicitly listed redundant subtext is absent while functional guidance, errors, warnings and statuses remain.

## Database migration

`2026_08_21_000000_preserve_combined_release_and_worksheet_business_versions.php` is additive and was applied to local ECRATS MySQL as batch 5.

- Adds nullable JSON `application_decision_releases.source_review_submission_version_ids` and backfills each historical singular source as a one-element array.
- Adds nullable/indexed `review_form_artifacts.business_version` and backfills from the parent assignment's `review_cycle + 1`.
- Preserves all rows and files; no truncation, reset, rollback or reseed is used.
- Refuses a down migration that would discard combined multi-source release provenance.
- Post-migration audit retained one release and two artifacts with zero missing provenance, zero missing business versions and zero cycle/version mismatches.

## Verification

- Changed-area SQLite in-memory run: 178 tests, 2,534 assertions, passed.
- Final complete SQLite in-memory run: 319 tests, 4,414 assertions, passed.
- Draft-discard filesystem regression: passed three consecutive isolated runs.
- Repository-wide Pint: passed after mechanically formatting eight pre-existing style findings.
- Strict Composer validation and platform requirements: passed.
- Blade cache: passed.
- Route discovery: passed.
- Vite production build: passed; optional Fontaine optimization notice only.
- PHP syntax for all changed PHP files and `git diff --check`: passed.
- Local QA certificate: generated successfully; extracted text/layout content passed structural review.

The first complete post-change run had one Windows/OneDrive `Lstat failed` race while Laravel deleted a fake test-disk directory. The discard service now retries once only when the directory still exists and treats an already-removed directory as success. The isolated repetitions and final complete suite pass.

## Changed source areas

- Application/list/monitoring/certificate/notification controllers: server-side term scope, plural certificates, MySQL-safe notification types and requested queue/workload projections.
- Review/revision/certificate/dashboard/report services: combined release evidence, actionable-comment validation, worksheet business versions, multi-recipient eligibility/release, QR placement/provenance and term-aware aggregates.
- Models and the August 21 migration: combined release source IDs and worksheet business versions.
- `bootstrap/app.php` and `resources/views/errors/database.blade.php`: user-safe database failures without SQL/trace disclosure.
- Dashboard Blade, `resources/css/dashboard.css` and `resources/js/dashboard.js`: application/revision/certificate/monitoring/notification/profile layout and interaction contracts, automatic uploads and reusable confirmations.
- Seed/test support and focused Feature tests: updated current capability semantics and regression coverage for every material repair.
- Root and `Documentations/` planning/status/changelog/testing/manual-validation/known-issues/feature/traceability records.

## Pending manual acceptance

- Authenticated localhost checks at desktop, tablet and mobile widths, including focus, modals, filters, empty/populated states, automatic upload feedback and native private previews.
- Rasterize and compare a representative certificate with the exact supplied `QR to Left.png`; scan a real configured QR asset for readability. The current QA PDF used a local deterministic high-contrast placement marker for structural generation only, not a semantic QR payload.
- Open the existing generated account workbook in supported desktop Excel, as already tracked by the pre-existing manual checklist.

No deployment, external publication, package installation, `.env` change, commit or push was performed.
