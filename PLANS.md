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

## Plan: INFINITY SAGA continuation and database-recovery handover

Status: **Stopped by user on 2026-08-23. Feature work is incomplete. The local MySQL database was successfully restored and verified on 2026-08-23.**

The prioritized RES Requirements Configuration and Reviewer Worksheet Configuration work is implemented with focused test evidence, while the final combined-certificate/RES-workspace edits remain unverified. A PHPUnit retry connected to local MySQL and removed the ECRATS data/schema. With the user's explicit restoration authorization, copied binary logs were replayed only into `ecrats_recovery_20260823` through the pre-drop boundary, validated, exported, and then imported into a recreated live `ecrats_db`. Live and recovery now match exactly across 40 tables, row counts, and checksums; all 45 migrations are Ran, integrity checks pass, and referenced private artifacts exist with matching hashes.

Do not run new feature work, tests, migrations, builds, cleanup, or browser work under the current stop instruction. Preserve the verified fallback database and `tmp/mysql-recovery-20260823/`. When work is later resumed, first make test isolation mechanically safe with explicit process-only SQLite variables and an in-process connection preflight, then continue the unverified INFINITY SAGA source audit. See `Documentations/INFINITY_SAGA_CONTINUATION_HANDOVER_2026-08-23.md` and `Documentations/MYSQL_POINT_IN_TIME_RECOVERY_2026-08-23.md`.

## Plan: ENDGAME manual-QA corrections and operational reports

Status: Source, test, build, and additive local-migration work completed on 2026-08-22. Authenticated viewport/native-preview and certificate raster/scanner acceptance remain pending because the approved local tooling is unavailable.

### Goal
Reaudit the full DOOMSDAY contract plus `D:\Downloads\ENDGAME.txt`, correct every verified ECRATS gap, add management-grade RES reporting, preserve formal evidence and local data, and maintain a status/evidence row for every requirement.

### Scope and safety
- Included: Settings, dashboards, screening drafts/assignment, monitoring/drill-downs, Applicant drafts/uploads/revision/retention, Reviewer worksheets, notifications, plural certificates/default QR, secure previews, current-term behavior, Reports, tests, safe forward migration, and documentation.
- Excluded: internet/external services, third-party analytics/viewers, unrelated files/databases, `.env`, destructive database commands, deployment/tunnels, commit, and push.

### Completion evidence
- `Documentations/ENDGAME_REQUIREMENTS_TRACEABILITY_2026-08-22.md` is the row-by-row ledger.
- `Documentations/ENDGAME_IMPLEMENTATION_STATUS_2026-08-22.md` records implementation, migration, verification, and remaining acceptance.
- Final SQLite suite: 329 tests, 4,545 assertions, passed.
- Pint, production build, Blade cache, 172 routes, migration status, and diff checks passed.
- Additive migration `2026_08_22_000000_add_submission_and_worksheet_settings` is local batch 6.

### Remaining acceptance
- Connect an authenticated in-app browser and execute the cross-role desktop/tablet/mobile checklist, including native private previews.
- Rasterize/compare a representative certificate and independently scan the default and configured QR assets using approved local tools.

## Plan: Complete DOOMSDAY requirement audit and safe corrections

Status: Source implementation and automated/database verification completed on 2026-08-21; authenticated responsive and certificate pixel/reference acceptance remain pending external runtime availability.

### Goal
Audit every requirement in `D:\Downloads\DOOMSDAY-INPUT.txt` against current ECRATS code, isolated tests, local database behavior and authenticated localhost UI, repair every verified gap without destructive data handling, and maintain an evidence-backed row-by-row ledger.

### Source Documents
- Direct user instructions and `D:\Downloads\SAFETY NET.txt` are the controlling safety authority.
- `D:\Downloads\DOOMSDAY-INPUT.txt` is the complete product/acceptance authority.
- Supplied screenshots, including `QR to Left.png`, are visual references only.
- `D:\Downloads\DOOMSDAY-HANDOFF.txt` is historical context and is never completion evidence.

### Scope
Included:
- Reviewer capability/eligibility, Adviser individual/bulk account fields, Full Board release, versioning, certificate content/configuration/recipients, monitoring, Applicant revision, Reviewer workspace, profiles, secure previews, notification workflows, subtext removal and term filtering.
- Additive schema/provenance, focused/full tests, local MySQL migration verification, static/build checks, documentation and localhost browser/PDF acceptance when the approved runtimes are available.

Excluded:
- Internet/external services, third-party viewers, `.env` changes, destructive database commands, unrelated cleanup, deployment, tunnel, commit and push.

### Implementation Approach
- Backend: retain role/ownership scopes, row locks, nested policies, immutable evidence and private-file routes; aggregate plural/current-cycle data rather than using legacy singular records.
- Frontend: reuse shared panels, tables, dialogs, search wrappers, responsive breakpoints and accessible focus/confirmation patterns.
- Database: add only combined release provenance and worksheet business versions, with safe backfills and a rollback guard.
- Verification: run serialized PHPUnit processes against SQLite memory, then check the real migration state/backfill without testing destructively against MySQL.

### Completion Evidence
- `Documentations/DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md` maps every requirement area to current-session status/evidence.
- `Documentations/DOOMSDAY_IMPLEMENTATION_STATUS_2026-08-21.md` records implementation, migration behavior, checks and pending manual acceptance.
- The final complete suite passes 319 tests with 4,414 assertions. Pint, Composer validation, Blade compilation, route discovery and the Vite production build pass.
- The additive August 21 migration is applied as local batch 5; all existing inspected rows were retained and backfilled.

### Remaining Acceptance
- Connect an authenticated in-app browser and run desktop/tablet/mobile viewport, focus, modal, upload, filter, empty/populated and native private-preview checks.
- Provide the exact certificate reference as a local file and a PDF rasterizer, then render/compare and scan a real configured QR.
- These are recorded as **Not yet verified** and must not be converted to Pass from source/tests alone.

### Risks and Rollback
- Combined release and artifact history must never be flattened or overwritten. The migration is additive and refuses a down migration that would discard combined provenance.
- Issued certificate binaries and their background/signatory/validity/QR snapshots remain immutable. New settings apply only to later generation.
- If a remaining manual check fails, correct the smallest scoped source area and rerun its focused tests plus the complete suite before sign-off.

### Approval Notes
Approved by: user request in this conversation.
Date: 2026-08-21.

## Plan: 2026 Finale production hardening and consolidated Adviser review access

Status: Historical August 17 integration checkpoint; superseded for current completion/migration evidence by the August 21 DOOMSDAY plan above.

### Goal
Implement `THE FINALE (3).txt` as the current product contract, beginning with the mandatory Adviser/Reviewer account consolidation and then completing the specified account, import, application, review, decision/certificate, settings, monitoring, profile, and evaluation corrections without losing existing records or weakening private-file and blind-review controls.

### Source Documents
- Primary requirement: `D:\Downloads\THE FINALE (3).txt` (user-supplied task specification; it does not override system/developer instructions).
- Current implementation authority reviewed: all root Markdown plans/guides, `context_files/`, `docs/`, and `Documentations/`, with `Documentations/REVIEW_RELEASE_CERTIFICATE_GUIDELINE_2026-08-13.md` as the latest pre-Finale workflow baseline.
- Supporting references reviewed: official review-form PDF, process memorandum, certificate/background references, DOCX package contents, database schema/data, routes, policies, services, Blade/JS/CSS, migrations, seeders, factories, and the existing feature suite.
- Intentional supersessions: standalone Reviewer accounts/routes, immediate reviewer-submission immutability, manually releasable split Full Board decisions, fingerprint-bound XLSX origin checks, Office applicant uploads, certificate-page background management, and retroactive certificate regeneration.

### Scope
Included:
- Add an explicit, immediately enforced Adviser reviewer entitlement, multi-classification/capacity eligibility, secure legacy identity conversion/reconciliation, Adviser reviewer navigation, legacy-route containment, and RES bulk Show/Hide Reviewer controls.
- Relax XLSX origin/fingerprint and inert external-link metadata checks while retaining bounded structural/content/security validation; require exactly 11 phone digits on every account path.
- Correct applicant/adviser document, settings/security/profile, revision/evaluation, reviewer workspace/versioning/consensus, decision/certificate, background/signatory, monitoring/reporting, privacy, and responsive UI behavior listed in the Finale brief.
- Preserve immutable documents, audit attribution, reviewer anonymity, assignment/release history, certificate binaries, and private storage authorization.

Excluded:
- Production deployment, external network calls, commit/push, destructive fixture replacement, or unrelated cleanup.
- Automatic merging of ambiguous duplicate people without an RES-confirmed reconciliation choice.

### Implementation Approach
- Backend: introduce capability-based reviewer authorization on Adviser users, keep canonical reviewer route names while moving paths below `/adviser/reviewer`, add consensus/review-version/certificate/settings services, and preserve workflow locks and audit trails.
- Frontend: add accessible Adviser reviewer subnavigation, inline/accordion reviewer work surfaces, requirement-centric Applicant revisions, privacy-safe decision/certificate queues, and responsive settings/monitoring/profile surfaces.
- Database: additive migrations with an in-place legacy reviewer conversion that retains user IDs/FKs, explicit reconciliation candidates, reviewer classifications/conflicts, versioned submitted reviews, certificate validity/signatory/background metadata, and the ten-question evaluation schema.
- Authorization: database-backed reviewer-entitlement middleware on every reviewer endpoint plus updated policies, nested ownership checks, conflict/consensus release gates, and self-service password verification/session revocation.
- Files/storage: retain private random-name storage, MIME/signature/size checks and secure streaming; never resolve workbook external targets; keep issued certificate binaries tied to immutable background/signatory versions.
- Notifications/audit: audit every entitlement, reconciliation, reviewer edit/release, consensus transition, settings/background/signatory, and security mutation; notify affected users without exposing blind identities.

### Files Expected to Change
- Migrations/models/enums, reviewer/account/certificate/application/evaluation services, policies/middleware/requests/controllers, routes, seeders/factories.
- Dashboard/navigation/settings/account/application/assignment/certificate/monitoring/report Blade views, shared JS/CSS, and focused documentation.
- Existing feature tests plus new consolidation, consensus, security, validity, privacy, import, upload, and responsive markup tests.

### Tests and Verification
- Establish and record the pre-change test baseline; after Phase 1 run focused authentication/account/navigation/policy/assignment tests before proceeding.
- Run the complete PHPUnit suite, Pint, Composer strict/platform checks, route/migration/Blade/Vite checks, scoped Git diff review, and browser checks at 1440, 1280, 1024, 768, and 390 pixels.
- Verify database migrations and compatibility conversion against the existing local MySQL data without deleting or reseeding it.

### Implementation and Verification Status
- The coordinated implementation is present in the working tree. The Adviser Reviewer entitlement, identity reconciliation, flexible safe XLSX reader, restricted Applicant uploads, versioned Reviewer resubmission/consensus, reassignment, pending certificate generation/validity, typed future-only backgrounds, signatory/settings/security controls, Applicant/Adviser/Reviewer presentation changes, anonymous survey aggregates, and RES monitoring have focused automated coverage.
- Route discovery succeeds with 157 routes. Focused role/settings, Adviser scope/profile, reassignment, and related combined slices have passed during integration; Blade caching and a Vite production build have also passed at intermediate checkpoints.
- Release sign-off remains open until the settled tree passes one complete Laravel suite, repository-wide Pint, strict Composer/platform checks, final route/migration/Blade/Vite/diff checks, approved non-destructive migration execution, and authenticated viewport/keyboard/async interaction checks at 1440, 1280, 1024, 768, and 390 pixels.
- The current local database reports all seven `2026_08_17_*` migrations as Pending. No migration or seed execution is claimed. See `Documentations/THE_FINALE_IMPLEMENTATION_2026-08-17.md` for the rollout order, rollback guards, security invariants, superseded contracts, and exact pending verification.

### Risks and Rollback
- Highest risks are duplicate Adviser/Reviewer identities, authorization bypasses, split-decision release, historical submission mutation, and historical certificate regeneration. Migrations are additive/in-place and preserve source IDs; ambiguous identities are queued for explicit RES reconciliation.
- New capability gates deny by default. Existing document/certificate versions remain immutable and recoverable. Each workflow mutation is transactional and audited.

### Approval Notes
Approved by: user request in this conversation.
Date: 2026-08-17.

## Plan: Reviewer-owned decisions, unified releases, and cross-role workflow corrections

Status: Completed on 2026-08-13.

### Goal
Apply the August 13 consolidated guideline across RES application/report/certificate surfaces, Applicant navigation and Adviser selection, and Reviewer assignment/comment/worksheet workflows while preserving private files, blind-review boundaries, historical review records, certificate identity, and existing release/claim history.

### Source Documents
- Primary authority: the August 13 paste-ready continuation guideline supplied in `C:\Users\User\.codex\attachments\24f5ff51-d7af-4d58-94ec-4d7c2b5b2b63\pasted-text.txt` and the eight accompanying defect/reference screenshots.
- Existing authority reviewed: `README.md`, `PROJECT_GUIDELINES.md`, `PLANS.md`, `context_files/README.md`, relevant `docs/` architecture/requirements guides, and current Reviewer, RES screening, release, certificate, authorization, navigation, audit, and Applicant workflow documentation.
- The newest guideline supersedes the August 11-12 future-only certificate-background rule and the temporary RES decision/document override recovery. Reviewer submissions are now authoritative; RES releases a specific submitted decision without changing it or selecting revision files.
- Direct work on `main` is allowed by the newest collaboration prompt for this task. No commit or push is included unless the user separately requests it.

### Scope
Included:
- Restrict RES application text search to approved non-applicant fields; make the requirement checklist full width above a responsive three-panel details row; center requested table columns; keep internal bottom scrolling and bounded filenames.
- Move the RES-only Audit Log under Reports with updated routes, links, breadcrumbs, active navigation, preserved filters/pagination/policy checks, and a compatibility redirect from the retired User Management URL.
- Remove Applicant Reports navigation and route access; restrict Student Adviser choices and validation to active eligible Advisers in the Applicant account department while preserving Faculty cross-department selection, self-exclusion, validation state, and an empty state.
- Correct Reviewer dashboard freshness/current-assignment scoping, Supporting Documents alignment, equal file actions, right-rail order, and `Review Assessment` terminology.
- Replace the comment scope selector with one Document selector containing `Entire Application`; derive overall/document scope on the server, retain historical overall/page records, validate assignment-visible document ownership, display `Unresolved`, and keep applicant anonymity.
- Correct Protocol item 15 numbering without changing stable response keys; enforce at least 15 non-whitespace recommendation-comment characters in browser and server validation.
- Add an editable `completed` worksheet state. Worksheet `Submit` validates and saves a complete but editable worksheet; the overall review transaction snapshots and finalizes both worksheets, comments, recommendation, and decision together, generates official artifacts, blocks duplicates, and enforces the lock server-side.
- Apply the informed-consent gate in UI, normalization, validation, persistence, and generated output: `No` clears dependent answers, expands/left-aligns the explanation, and skips dependent requirements; `Yes` restores them. Remove per-item consent comments while retaining the overall recommendation field.
- Add an RES-only read-only review workspace for current submitted decisions, Reviewer comments, worksheets/artifacts, and protected supporting documents. Release derives a particular decision from its submitted review record and automatically releases its associated comments; RES cannot alter review content or choose revision documents.
- Replace bulk certificate-only release with explicit `Certificate`, `Decision`, or `Both Certificate and Decision` options, server-calculated eligible counts, explicit confirmation, independent eligibility rechecks, idempotent skipping, bounded failure reporting, notifications, transactions, batching, and audit summaries.
- Make certificate-background activation regenerate every active past/present certificate into a new traceable version. Preserve certificate number, recipient/application, original issue/release dates and actor, claim state/time/actor, and prior binaries; store regeneration time/reason separately, swap the current version only after a complete private file exists, and leave the last valid version active on failure.

Excluded:
- New packages, `.env` changes, public certificate-file access, public QR metadata decisions, changing the two-revision-cycle limit, hard deletion of historical comments/forms/certificates, or exposing Applicant/Reviewer identity across blind boundaries.
- A continuously running external queue worker. Safe bounded database/file batches will be used because this repository does not guarantee worker availability; every certificate is isolated so one failure cannot invalidate prior renderings or successful records.

### Implementation Approach
- Backend: keep controllers thin; extend Form Requests/policies and workflow services; derive release decisions from nested submitted review records; use row locks, uniqueness checks, bounded ID batches, and idempotent service results.
- Frontend: reuse dashboard panels, overflow regions, badges, modals, focus trapping, and private document dialog; stack at compact widths and keep action labels/validation states accessible.
- Database: add a forward migration for worksheet `completed_at`/state compatibility, decision-release source provenance, and certificate-version regeneration metadata. Convert only unsubmitted legacy finalized worksheets to editable completed state; preserve submitted final snapshots and artifacts.
- Authorization: keep RES certificate/report routes role-gated, Reviewer writes current-assignment-gated, Applicant routes owner-gated, and Adviser identifiers validated against the role/account/department boundary.
- Files/storage: keep documents, worksheets, certificates, and backgrounds on the private disk behind authorized routes. New background renders use create-then-commit pointer swaps; failed files are removed and old valid pointers remain.
- Notifications/audit: preserve neutral pre-release wording and idempotency. Record source review submission, release type, actor/time, affected IDs/counts, regeneration reason/result, and bounded failures without private content or paths.

### Files Expected to Change
- RES/Applicant/Reviewer controllers, Form Requests, policies/services/models/enums, dashboard navigation/routes, relevant Blade/CSS/JavaScript, a forward migration, and focused factories/tests.
- `docs/` requirements/architecture material plus `Documentations/FEATURES_AND_FUNCTIONALITY.md`, affected workflow/navigation/security/audit/certificate guides, changelog, testing/manual-validation records, and traceability.

### Tests and Verification
- Focused tests for every requested search, route, Adviser, current-assignment, comment, worksheet, consent, read-only RES, single/bulk release, background regeneration, preserved issue date/claim, nested authorization, idempotency, and failure behavior.
- Run focused suites, one complete Laravel suite, changed-file Pint, `composer validate --strict`, platform requirements, route listing, migration status plus isolated migration checks, Blade compilation, Vite production build, and `git diff --check`.
- Use the in-app browser skill for local responsive/keyboard/modal/private-route verification at approximately 1440, 1280, 1024, 768, and 390 pixels when a controllable browser is available. Record unavailable or data-blocked checks rather than claiming visual acceptance.

### Risks and Rollback
- Full-board submissions can contain different decisions. Single release requires RES to select one exact submitted review. Bulk decision release is eligible only when the current-cycle submitted decisions agree; conflicting sets remain ineligible for explicit RES review rather than inventing an outcome.
- Historical `final` worksheets attached to submitted reviews remain immutable. Only `final` worksheets whose overall review is still unsubmitted are migrated to `completed`, retaining responses while removing premature finality.
- Background regeneration never overwrites a binary. Each successful version supersedes the prior active rendering only after storage and database persistence succeed; failures leave the old current/claimed rendering usable.
- Forward rollback must refuse to discard issued regeneration provenance or submitted review history without explicit archival handling.

### Approval Notes
Approved by: August 13 user instruction to read and implement the complete attached guideline precisely.
Date: 2026-08-13

### Completion Notes
- Implemented the complete Reviewer-owned decision, read-only RES release, typed bulk release, retrospective certificate-background regeneration, report/navigation, Adviser eligibility, dashboard freshness, comment, worksheet, consent, and responsive layout contract described above.
- The complete Laravel suite passes with 249 tests and 3,650 assertions. Focused affected suites, changed-file Pint, strict Composer validation, platform requirements, the 133-route listing, isolated migration up/rollback/up checks, Blade compilation, the Vite production build, and `git diff --check` pass.
- Signed-in browser acceptance passed at 1440, 1280, 1024, 768, and 390 pixels for RES application details, Certificate Processing and Release All, the RES read-only review workspace, the Reviewer dashboard/workspace/conditional consent form, and Applicant navigation/application pages. No tested page has whole-page horizontal overflow or console warnings/errors; intended wide tables retain internal bottom scrolling.

## Plan: Visible Applicant evaluation validation and claim handoff

Status: Completed on 2026-08-12.

### Goal
Ensure an Applicant who submits an invalid post-review evaluation sees the exact validation problem in the Certification panel, and verify that a valid evaluation immediately exposes and completes the explicit certificate claim action.

### Source Documents
- Primary issue: August 12 report that submitting the evaluation did not allow certificate claim.
- Live read-only evidence: the newest released certificate and ready version have complete release provenance, but no `applicant_survey_responses` row or survey audit event exists for that application.
- Workflow authority: evaluation completion remains required before explicit claim.

### Scope
Included:
- Route survey FormRequest validation into the `certificateSurvey` error bag already rendered by the Applicant page.
- Align textarea browser constraints with the server's five-character minimum and keep invalid values visible.
- Render evaluation validation feedback next to the survey form and cover the complete HTTP evaluation-to-claim handoff.

Excluded:
- Auto-claiming a certificate, bypassing evaluation, changing survey answers, mutating live Applicant data, changing claim authorization, or changing database schema.

### Implementation Approach
- Add the intended named error bag to `StoreApplicantSurveyRequest`.
- Add matching `minlength` attributes and contextual error rendering in the certification panel.
- Exercise invalid evaluation, valid evaluation, claim-form visibility, successful claim, and persisted version ownership through real routes.

### Files Expected to Change
- `app/Http/Requests/Applicant/StoreApplicantSurveyRequest.php`
- `resources/views/dashboard/applications/revision-certificates.blade.php`
- `tests/Feature/Dashboard/CertificateReleaseWorkflowTest.php`
- affected planning/changelog/workflow documentation

### Tests and Verification
- Focused certificate release/evaluation/claim tests, Blade compilation, changed-file Pint, the Vite production build, `git diff --check`, and the complete Laravel suite.

### Risks and Rollback
- The persistence and claim gates remain unchanged; this corrects validation visibility and client/server constraint parity only.
- No live survey is synthesized and no existing certificate record is mutated.

### Approval Notes
Approved by: August 12 user report requesting repair of the evaluation-to-claim failure.
Date: 2026-08-12

### Completion Notes
- `StoreApplicantSurveyRequest` now flashes validation failures to the Certification panel's `certificateSurvey` error bag, and the panel renders both a contextual summary and field-level messages while preserving submitted values.
- Both required feedback fields expose the server-owned five-character minimum to the browser. A valid evaluation continues through the existing explicit claim gate without changing certificate eligibility, ownership, or persistence rules.
- The focused certification/Reviewer/RES regression set passes with 28 tests and 358 assertions. The complete Laravel suite passes with 241 tests and 3,541 assertions; changed-file Pint, Blade compilation, the Vite production build, and `git diff --check` pass.

## Plan: Numbered certification queue and document-linked revision comments

Status: Completed on 2026-08-12, with live responsive acceptance pending because no controllable browser instance was available.

### Goal
Replace the RES certification queue's non-interactive circular status markers with pagination-aware row numbers, prevent the responsive Reviewer rail from overlapping its comment controls, and ensure both new and already-submitted reviews can supply the exact documents needed for an RES minor/major revision release.

### Source Documents
- Primary issue evidence: the screenshots supplied on August 12, 2026 showing static queue circles, the RES revision-release validation failure, and the Review Worksheets card overlapping the Reviewer comment Reference, Document, and Comment controls at a narrow width.
- Workflow authority: `Documentations/APPLICANT_REVISION_AND_CERTIFICATION.md` and the existing rule that revision requirements must identify an exact protected source document.

### Scope
Included:
- Number-only, pagination-aware certification queue row labels.
- Intrinsic Reviewer rail row sizing so Review Worksheets cannot overlay the comment form at narrow or zoomed widths.
- Reviewer composer wording, visible guidance, current-document synchronization, and automatic document scope for Required Revision comments.
- Server validation that prevents new or edited Required Revision comments from being stored without a document.
- An RES recovery mapping that lets an authorized lead associate current application documents with selected legacy General/Overall comments after the Reviewer submission is already immutable.
- Focused regression coverage and documentation updates.

Excluded:
- Weakening the RES revision-release requirement, mutating Reviewer-authored comment scope/content, or changing routes, authorization, Applicant visibility, certificate generation, or database schema.

### Implementation Approach
- Frontend: make Reviewer rail rows intrinsic and scrollable without overlap; expose an understandable `Applies to` choice, identify the exact document and filename, synchronize the composer when a Reviewer selects a document, and automatically switch Required Revision comments to that selected document.
- Backend: validate the category/scope relationship before the workflow service persists a comment; for immutable legacy overall comments, validate RES-selected current documents and merge them with any documents already linked by selected Required Revision comments.
- RES queue: derive row numbers from the paginator's `firstItem()` plus the loop index so filtering and later pages remain correctly numbered.

### Files Expected to Change
- `resources/views/dashboard/certificates/res-index.blade.php`
- `resources/views/dashboard/assignments/workspace.blade.php`
- `resources/js/dashboard.js`
- `resources/css/dashboard.css`
- `app/Http/Requests/Reviewer/StoreReviewCommentRequest.php`
- `app/Http/Requests/ResLead/ReleaseApplicationDecisionRequest.php`
- `app/Services/Applications/ApplicationRevisionWorkflowService.php`
- `app/Http/Controllers/Dashboard/ResCertificationController.php`
- focused Reviewer and RES feature tests plus affected documentation

### Tests and Verification
- Cover pagination-aware number rendering, non-overlapping rail contracts, Reviewer document choices, automatic composer hooks, rejection/acceptance of unlinked/linked Required Revision comments, and RES recovery mapping for already-submitted General/Overall comments.
- Re-run affected certification/reviewer workflow tests, Blade compilation, Pint, the Vite production build, `git diff --check`, and the full Laravel suite.
- Exercise the Reviewer composer and RES queue in the local browser when a controllable instance is available.

### Risks and Rollback
- Existing historical General/Overall comments remain readable and unchanged; only new/edited Required Revision comments receive the stricter invariant, while RES recovery stores the chosen source document on the new revision requirement rather than rewriting Reviewer authorship.
- No migration or stored workflow record is altered. Rollback is limited to view, JavaScript, CSS, request-validation, test, plan, and documentation changes.

### Approval Notes
Approved by: August 12 user request to replace static certification markers and fix the missing Reviewer document-comment path.
Date: 2026-08-12

### Completion Notes
- Certification queue markers are now paginator-aware numbers; no selection behavior was removed because the original circles were non-interactive status decoration.
- Reviewer rail rows use intrinsic max-content sizing, the composer synchronizes the selected document, and request validation prevents new or edited Required Revision comments without a document.
- RES can recover an immutable submitted General/Overall comment by selecting the exact current same-application source documents; the mapping is written only to the new revision requirements and counted in bounded audit metadata.
- Before the evaluation follow-up, the complete Laravel suite passed with 240 tests and 3,517 assertions. The 22-test focused workflow set passed with 296 assertions; Blade compilation, changed-file Pint, the Vite production build, and `git diff --check` passed. The unavailable browser runtime is recorded in `Documentations/MANUAL_VISUAL_VALIDATION.md`.

## Plan: RES certificate-processing information architecture refresh

Status: Completed on 2026-08-12, with live viewport acceptance pending because no controllable browser instance was available.

### Goal
Replace the dense certificate cards and always-visible background controls with the supplied clean queue-and-modal design while preserving every existing server-authorized decision, certificate, version, survey/claim, background, audit, notification, and pagination workflow.

### Source Documents
- Primary visual authority: the two Certificate Processing screenshots supplied on August 12, 2026.
- Functional authority: the completed Applicant revision and certification lifecycle documented in `Documentations/APPLICANT_REVISION_AND_CERTIFICATION.md`.
- Existing dashboard components, typography, status badges, overflow regions, pagination, and accessible modal behavior remain the design-system baseline.

### Scope
Included:
- RES-only Certificate Processing page hierarchy, metrics, filters, queue table, selected-application modal, bulk confirmation modal, and background-management modal.
- Responsive table containment, modal focus/escape/backdrop behavior, validation re-entry, action loading states, and clear empty/result states.
- Controller-provided unfiltered queue metrics and focused rendered-view/interaction contracts.

Excluded:
- Changes to certificate eligibility, decision-release rules, generation, files, database schema, routes, authorization, audit payloads, notifications, or Applicant workflow.

### Implementation Approach
- Backend: derive four summary counts from the same relevant-record scope without changing filtered pagination or eager-loaded workflow data.
- Frontend: render a compact summary strip and one queue table; move record-specific actions/history into application-scoped dialogs and all background actions/history into one separate dialog.
- Accessibility: reuse the shared focus-restoring, scroll-locking, backdrop, Escape, and Tab-trapping modal infrastructure; reopen the correct dialog on server validation.
- Responsive behavior: preserve a readable wide table inside the established keyboard-focusable overflow component and stack modal summaries/actions on narrow viewports.

### Files Expected to Change
- `app/Http/Controllers/Dashboard/ResCertificationController.php`
- `resources/views/dashboard/certificates/res-index.blade.php`
- `resources/css/dashboard.css`
- `resources/js/dashboard.js`
- focused Certificate Processing feature tests and affected documentation

### Tests and Verification
- Focused RES page tests for metrics, queue labels, modal action preservation, background management, filters, validation reopening, and role denial.
- Blade compilation, Pint, Vite production build, `git diff --check`, and the relevant Laravel feature tests.
- Local browser viewport/modal checks when a controllable browser instance is available; otherwise record the limitation without substituting a different backend.

### Risks and Rollback
- Actions remain ordinary POST/PATCH/GET routes inside server-rendered dialogs, so disabling JavaScript changes presentation rather than authorization or data flow.
- Metrics use cloned read-only queries; filtered queue data and pagination remain authoritative.
- Rollback is limited to view/controller/CSS/JavaScript/test/documentation changes; no migration or stored workflow data changes.

### Approval Notes
Approved by: August 12 user request to redesign certification from the supplied references without compromising functionality or data flow.
Date: 2026-08-12

### Completion Notes
- Replaced the dense application cards and inline background manager with an unfiltered metric strip, bounded queue filters, one responsive certification table, application-scoped workflow dialogs, a bulk-release confirmation, and a separate background-management dialog.
- Preserved every existing route, ordinary server-rendered form, eligibility rule, validation re-entry path, private preview/download path, immutable version history, audit/notification effect, and independent application/background paginator.
- Focused certification tests, Blade compilation, Pint, the Vite production build, and `git diff --check` pass. The browser runtime exposed no controllable instance, so target-width interaction acceptance remains explicitly recorded in `Documentations/MANUAL_VISUAL_VALIDATION.md`.

## Plan: Applicant revision, released feedback, and certificate lifecycle

Status: Completed on 2026-08-11, with live browser viewport acceptance explicitly pending because no controllable browser instance was available.

### Goal
Complete the post-review workflow from an authorized RES result release through Applicant document revision, assignment-owned re-review, final approval, official-template certificate generation/release, required evaluation, explicit claim, and private certificate access without returning revisions to Adviser endorsement or initial RES screening.

### Source Documents
- Primary authority: the August 11 Applicant Revision and Certification continuation prompt attached to this work session.
- Business rules: `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`, limited to revision, result visibility, certificate, feedback, authorization, status, and data requirements.
- Applicant UI authority: local-only `context_files/references/ECRATS High Fidelity (8).pdf`, pages 18-27. Page 28 begins a different role section.
- Certificate authority: tracked `context_files/RES CERTIFIACTE.pdf` (source SHA-256 `998e7a943c81a83afb13df162a85eb08007c4eb2aa1ea51fedfa9909cd5ff960`). Its A4 page, embedded institutional background/watermark, labels, and field placement will control generation.
- Missing reference: the supplied high-fidelity files contain 30 and 104 pages respectively, so requested RES Lead pages 106-108 do not exist locally. RES queue/background UI will follow the continuation prompt and the established dashboard design system; this limitation will be recorded in the completion report.
- Intentional precedence: the continuation prompt requires RES to release/generate before the Applicant completes the survey and claims the certificate. This supersedes older documentation wording that places feedback before certificate generation/release. Claim/view/download remains gated by survey completion.

### Scope
Included:
- Explicit, auditable release of an aggregate Applicant-visible decision and selected reviewer comments by RES.
- At most two application-revision cycles, deadline-enforced revised uploads, required-document replacement tracking, immutable document history, and idempotent submission.
- New re-review assignment rows linked to prior assignments, preserving every prior review/form/comment record and using the reviewing-revision deadline.
- Current Reviewer access to current revised files plus bounded read-only prior document/comment history, while superseded/unrelated Reviewers remain denied.
- Applicant revision/certification workspace with high-fidelity-inspired progress, released feedback, uploads, evaluation, claim, and private certificate actions.
- RES certification queue, single/bulk release, explicit regeneration, failure reporting, and versioned private background management.
- Official-background certificate generation, version metadata/hashes, eligibility/release/claim state, private access, notifications, audit events, tests, and documentation.

Excluded:
- Adviser or initial RES screening during re-review.
- Public certificate lookup/verification, third-party rendering/viewers, external publishing/deployment, and silent rewriting of issued certificate files.
- Invented signatory, approval, validity, or document-review data when authoritative database values are absent; generation will use documented safe labels or fail before release where a required value has no source.

### Implementation Approach
- Backend: add focused workflow services for decision release/revisions and certificate lifecycle; use row locks, idempotency checks, safe file cleanup, server-side eligibility, and application-scoped queries.
- Frontend: replace Applicant and RES certificate placeholders with responsive dashboard pages, progressive server-rendered forms, accessible confirmations/status feedback, secure previews, bounded filters, and mobile stacking.
- Database: add non-destructive decision-release, revision, revision-requirement, survey, certificate, certificate-version, and background-version records; add reviewer cycle linkage and comment releasing actor metadata with foreign keys/indexes.
- Authorization: extend application policy abilities for owned revision/survey/claim/certificate access; retain current-assignment Reviewer authorization; add RES-only result/certificate/background abilities and nested controller checks.
- Files/storage: preserve application document history; store revised files, generated PDFs, and uploaded backgrounds on the private disk; keep the official source intact and use its extracted embedded background/watermark for stable server-owned certificate rendering.
- Notifications/audit: notify Applicants only after authorized result/certificate actions and notify the correct active Reviewers after revision submission. Audit metadata will contain bounded IDs/status/count/hash data, never comment bodies, survey answers, file bytes, private paths, or raw exceptions.

### Expected Files and Public Contracts
- New migrations/models/enums/factories for releases, revisions, evaluation completion, certificates/versions, and background versions.
- New Applicant and RES controllers, Form Requests, policies/service methods, routes, views, and focused CSS/JavaScript enhancements.
- Reviewer workspace/controller/service integration for revision deadlines and read-only prior versions/comments.
- Certificate renderer and derived official background resource, with source/template/generator version mapping documented.
- Feature tests covering ownership, released visibility, deadlines, versioning, assignment linkage, prior-history authorization, survey/claim gates, single/bulk release, generation cleanup, private access, and background future-only behavior.

### Tests and Verification
- Complete code/documentation before final verification, then review the entire diff and run the continuation-focused feature tests.
- Run the complete Laravel suite once, followed by Pint, strict Composer validation, platform requirements, route listing, migration status, Blade compilation, Vite production build, and `git diff --check`.
- Apply migrations to the local database if required after automated tests pass.
- Inspect Applicant and RES pages at 1440, 1280, 1024, 768, and 390 pixels, including keyboard/dialog/scrolling/loading/error behavior.
- Generate local sample certificates, render them privately, compare against the official template, and verify background changes affect only newly generated versions.

### Risks and Rollback
- Migration rollback will refuse to drop issued certificate or submitted revision history rather than silently discard it.
- A certificate file is written before release state commits and deleted if persistence fails; failed generation never becomes released or claimable.
- Bulk release processes records independently so one safe failure does not roll back successful releases.
- Uploaded background activation is transactional; invalid assets leave the prior active version untouched. Existing certificate versions are immutable, and regeneration is an explicit audited action.

### Approval Notes
Approved by: August 11 user instruction to apply the complete attached guideline.
Date: 2026-08-11

### Completion Record
- Added the persisted release/revision/survey/certificate/background schema and non-destructive version relationships.
- Implemented selective RES decision release, two-cycle Applicant replacement submission, same-Reviewer re-review history, certificate eligibility/generation/release/regeneration, background provenance, evaluation, explicit claim, and private access.
- Replaced the Applicant and RES placeholders and extended the Reviewer workspace with bounded prior-cycle context.
- Added focused workflow/security/failure tests and synchronized the feature, security, deadline, Reviewer, certificate, visual-validation, known-issues, changelog, and traceability documentation.
- Verification: 8 focused tests / 71 assertions passed; the single full-suite run passed 233 tests / 3,454 assertions; Pint, strict Composer validation, platform requirements, route listing, isolated fresh migrations, Blade cache, Vite production build, and `git diff --check` passed. The configured MySQL migration was applied successfully; its guarded resume path recovered from MySQL's initial rejection of an overlong generated constraint name after the constraint was shortened. Composer audit continues to report the eight pre-existing advisories recorded in Known Issues.
- A real A4 certificate was generated and rendered. Visual review found and corrected the official signature transparency mask before the final render passed. Applicant/RES live viewport acceptance remains pending because browser discovery returned no available instance.

## Plan: Reviewer workflow correction, RES reassignment, deadline semantics, and shared reliability fixes

Status: Implemented on 2026-08-10; external acceptance items remain documented.

Continuation checkpoint: 2026-08-09. Functionality is prioritized over final pixel polishing, with the Reviewer Workspace, asynchronous comments, secure document access, simplified Reviewer navigation, and RES classification/reassignment layout as the current acceptance slice.

Continuation checkpoint: 2026-08-10. The attached implementation brief and local-only `context_files/references/ECRATS High Fidelity (8).pdf` pages 54-69 are the current acceptance authority for this slice. The reference PDF is explicitly ignored by Git. Existing completed behavior will be preserved while the remaining gaps are closed: resumable MySQL migrations, final-review-time official artifact generation, inclusion of the persisted overall decision and applicable assignment comments in those artifacts, RES-only visibility after complete submission, bounded/incrementally loaded comments, duplicate-request protection, delete confirmation, and local responsive/accessibility verification. The explicit brief overrides the prototype by removing `+ Page Comment` and hiding Institution, Reviewer, and Researcher / Study Leader rows in worksheet modals.

Handoff checkpoint: 2026-08-10. Implementation and prior focused results are recorded in `Documentations/IMPLEMENTATION_STATUS_2026-08-10.md`. Further automated/browser verification is intentionally postponed until the remaining code/fidelity items in that handoff are completed. No additional test, build, migration, or browser command was run after the user requested implementation-first sequencing.

Completion checkpoint: 2026-08-10. The remaining implementation items are now represented in source and focused tests: worksheet UI states are Not Started/In Progress/Completed; final review uses an accessible asynchronous confirmation/result dialog with progressive POST fallback; term-date controls enforce ordering without rejecting stored historical starts; and authorized PDF/image previews use a non-sandboxed same-origin CSP while Office fallback behavior remains unchanged. Source, dependency, documentation, and automated verification reconciliation is complete; only the recorded external acceptance items remain.

Verification checkpoint: 2026-08-10. The continuation-focused set passed 64 tests/1,586 assertions and the complete suite passed 226 tests/3,386 assertions. Changed-file Pint, strict Composer validation, platform requirements, route listing, migration status, Blade compilation, Vite production build, and `git diff --check` pass. Local Reviewer checks passed at 1280, 1024, 768, and 390 pixels; the browser capped 1440 at 1280 and disconnected before the RES date-control check. Manual Excel/PDF acceptance, a writable final-review browser path, existing Composer advisories, and unrelated repository-wide Pint drift remain tracked in the implementation status and known issues.

The interrupted MySQL DDL state found on 2026-08-10 is handled by guarding each independently committed migration operation. Both affected migrations now resume safely after partial application; `php artisan migrate --no-interaction` and `php artisan db:seed --no-interaction` complete on the local MySQL database. No existing application or reviewer data is deleted by this recovery.

### Goal
Apply the August 5 client requirements across the Applicant, Adviser, Reviewer, RES, deadline, document, profile-option, and workbook surfaces while preserving private files, reviewer blindness, immutable review history, and existing application records.

### Superseded Requirements and Sources
- The August 5 implementation prompt is the current client authority. It supersedes the required Reviewer conflict-declaration gate, Administrative Screening fields, immutable started assignments, the Adviser In Review card, the Reviewer Near Deadline card, the Release Decision and Certification deadline, and `On`/`Auto` toggle wording recorded in older plans and documentation.
- Reviewer UX follows the local-only `context_files/references/ECRATS High Fidelity (8).pdf` pages 54-69. Official form content follows the committed, integrity-checked REMS source pages for KLD-RES-04-001 and KLD-RES-04-002.
- Protocol item 13 about study disclosure of conflicts of interest remains official form content; only the assignment-level Reviewer refusal/declaration workflow is removed.
- The locked FPDF/FPDI renderer is installed. Finalized worksheet snapshots remain independent of rendering; both integrity-checked private official PDFs are generated atomically only when the overall review is submitted.
- The August 9 continuation removes Review, Notifications, and Settings from the Reviewer sidebar only. Their protected routes remain available for dashboard cards, the notification bell, the profile menu, and authorized direct access.

### Implementation Approach
- Shared documents: make `eye` unslashed, retain independent password reveal controls, add trusted preview-kind metadata, and provide keyboard-accessible 25%-200% image/PDF controls, reset, fit, supported rotation/fullscreen, and same-origin new-tab access without exposing storage paths. PDF and browser-safe images may render inline; Word and Excel remain behind the same authorized viewer route and protected download fallback until a local, privacy-preserving Office renderer is approved and installed.
- Applicant timeline: replace collection-index progress with canonical milestone keys derived from application status/stage; scope scheduled dates to the application's academic term and preserve honest missing/skipped states when deadline rows are absent or reordered.
- Reviewer access and tasks: drop conflict columns through a forward migration; remove conflict enum/request/route/policy/service/UI/audit/notification behavior; retain owner-scoped task, notification, and settings routes while limiting the Reviewer sidebar to Home and Assignments.
- Reviewer workspace: keep the selected private document and comment composer/list visible together in a responsive review studio; keep current and historical versions bounded; add asynchronous JSON comment create/update/delete and resolution/reopen operations with immediate server-rendered updates, loading/empty/success/error states, nested ownership, CSRF, deadline checks, audit history, and progressive form fallback. Use Not Started/In Progress/Completed worksheet labels and a focus-contained final-review confirmation/result dialog that displays the selected decision without replacing authoritative server validation.
- Official forms: retain the exact 15 protocol and 15 consent items, stable keys, exact options, per-item comments, consent gate, other concerns, and recommendation-specific reasons. Draft/final requests return JSON when requested and finalized records preserve immutable catalog/data snapshots. Overall submission generates versioned private artifacts from those snapshots plus the persisted final decision and complete assignment-comment record; failures roll back submission and clean up partial files.
- Adviser: remove the In Review query/card, count every post-endorsement state under Endorsed, and use a three-card responsive grid plus container-aware submitted-application filters.
- RES classification/reassignment: forward-drop the six Administrative Screening columns while retaining classification, basis, actor, and timestamp; repeat mandatory-document readiness under lock; use a full-width responsive two-column classification editor, align Re-edit Decision with View Assignment, and keep saved screening content full width. Place the validated reassignment reason in Selected Reviewer immediately above Save Reviewer Set and remove the redundant conflict-exclusion note. Replace destructive assignment deletion with supersession history, linked replacements, required reasons, current-set scopes, transactional eligibility/capacity rechecks, immediate old-reviewer access revocation, neutral notifications, and completion recomputation.
- Deadlines: retire only matching result-release configuration/timeline rows through a forward deactivation migration. Keep six scheduled processes; display effective `On`/`Off`; persist explicit Open/Closed overrides; clear overrides when dates change; evaluate inclusive Asia/Manila boundaries with missing/reversed intervals closed. Keep existing historical term starts editable, preserve the current-date minimum for blank new entries, and keep end-after-start ordering aligned across Blade, JavaScript, and server validation.
- Profile options/workbooks: preserve the paginator's Eloquent collection and eager-load aliases before usage counting. Add a spreadsheet runtime guard so missing ZIP/PhpSpreadsheet yields an administrator-safe validation error before writing or returning XLSX headers; retain private cleanup and the official workbook contract. The environment still requires approved manual ZIP enablement and `composer install` before genuine round-trip verification.
- Documentation: update current contracts across project guidelines, README files, feature/workflow/security/testing/responsive/import/dropdown/deadline/database guides, requirements, architecture, known issues, manual validation, and changelog while preserving historical changelog/plan entries as historical records.

### Schema, Routes, and Public Contracts
- Add forward migrations to remove conflict and administrative fields; add assignment supersession/replacement metadata and uniqueness/indexes; add comment status history and immutable reviewer-form artifact metadata; deactivate only retired release-deadline rows.
- Add authenticated, role- and policy-protected Reviewer task/workspace JSON routes for comment CRUD/resolution and form draft/finalization, plus private finalized-artifact preview/download routes.
- Add RES-only replacement routes using dedicated Form Requests and existing workflow throttling. Existing assignment/document routes remain nested and private; superseded assignments become RES-audit-readable but lose Reviewer document/write access.
- Replace integer timeline indices with named milestone-state contracts. Deadline submissions carry effective-state/original-state data so unchanged saves preserve automatic mode while explicit changes persist Open or Closed.

### Tests and Verification
- Add focused coverage for eye/password separation, viewer metadata/controls/security headers, every application timeline state and term/missing-row case, conflict removal, Reviewer task scoping, AJAX comment CRUD/resolution, exact form catalogs and snapshots, Adviser counts/filter markup, classification simplification, reassignment history/access/completion, deadline boundaries/overrides/retirement, profile-option pagination/aliases, and missing spreadsheet-runtime handling.
- Run focused suites first, then the full Laravel suite, route list, migration status, strict Composer validation, platform requirements, Pint, the Vite production build, and `git diff --check`.
- Browser verification targets 1440, 1280, 1024, 768, and 390 pixels for document controls, payment images, Reviewer task/workspace/modals/navigation, Adviser filters, RES classification/reassignment, deadline controls, and keyboard focus. This continuation inspected the Reviewer workspace/chooser at 1280, 1024, 768, and 390 pixels with working Escape/focus restoration and no page-level horizontal overflow. The browser capped 1440 at 1280 and disconnected before the deadline page check. The high-fidelity PDF workspace/comment pages 4-7 and form pages 12-13 were previously rendered on the original device; final side-by-side stakeholder acceptance remains pending.
- August 9 verification: the focused continuation suite passed 45 tests with 664 assertions; the full Laravel suite completed 201 tests with 175 passed, 26 environment-dependent skips, and 1,949 assertions. Pint, Blade compilation, the Vite production build, and `git diff --check` passed.
- Rerun genuine XLSX package/round-trip tests and desktop spreadsheet acceptance on the agreed application matrix. Render both submitted official form artifacts and visually compare their source pages and branded continuation records before final stakeholder acceptance.

### Risks and Rollback
- Superseded reviewer work is never deleted. Replacement is reversible at the workflow level by another audited replacement; migration rollback removes only new metadata after explicit data-impact review.
- Old Reviewer access is denied by current-assignment scopes immediately after supersession, while authorized RES retains history.
- Deadline retirement deactivates exact known rows rather than deleting data, and migration rollback does not blindly reactivate previously inactive records.
- Missing ZIP remains an explicit workbook environment blocker and fails safely. Official PDF generation is implemented; any rendering/storage failure leaves worksheets finalized but the overall review unsubmitted and removes partial files.

### Approval Notes
Approved by: August 5 user implementation request, except dependency installation and PHP extension changes remain pending explicit approval.
Date: 2026-08-05

## Plan: Blind Reviewer workspace and cross-role viewport corrections

Status: Completed on 2026-08-04.

### Goal
Implement the source-defined initial Ethics Reviewer workflow and the requested RES Lead, Applicant, and Reviewer presentation corrections so assigned reviewers can work privately, complete the two official forms, add bounded comments, and submit a decision for later RES release without exposing role identities or private storage paths.

### Source Documents
- Primary requirement: attached cross-role Reviewer functionality and viewport specification, August 3, 2026.
- Visual reference: local-only `context_files/local/ECRATS High Fidelity.pdf`, pages 47-69.
- Official forms: `context_files/REMS PROTOCAL REVIEW WORKSHEET.docx`, including KLD-RES-04-001 and KLD-RES-04-002.
- Supporting requirements: `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`, `PROJECT_GUIDELINES.md`, and the completed RES screening/assignment plan below.
- Confirmed decisions: KLD-RES-04-001 has 15 server-defined Yes/No/Unable-to-Assess items; KLD-RES-04-002 has the consent-applicability gate and 12 Yes/No items; both forms use Approved, Minor Revision, Major Revision, or Disapproved recommendations and independent draft/final states. Final review submission additionally requires a decision and decision comment.
- Unresolved client dependency: the final procedure for detecting and removing identity embedded inside arbitrary uploaded document content is not approved. This slice hides Applicant/Adviser account fields and enforces assignment-gated private access but does not claim content-level file redaction.

### Scope
Included:
- Add a required no-conflict/conflict-declared gate before a Reviewer may enter the blind workspace; a declared conflict blocks review work for RES handling and is not a general decline action.
- Persist one review submission per assignment, two independently draftable/finalizable official reviewer forms, and assignment-owned overall/document/page comments.
- Validate form responses against immutable server-side question catalogs, require every applicable answer and recommendation for form finalization, and require both final forms plus the final decision comment before review submission.
- Support Approved, Minor Revision, Major Revision, and Disapproved reviewer decisions; freeze submitted review work, mark the assignment Decision Submitted, and move the application to For Result Release only after every active initial Reviewer submits.
- Keep Reviewer comments and decisions unavailable to Applicant routes until a later explicit RES release process; do not add direct Applicant-Reviewer messaging.
- Keep Reviewer application and document reads assignment-owned, role-protected, private-disk backed, and free of Applicant/Adviser account identity.
- Add bounded audit events for conflict declaration, form draft/final saves, comments, and final review submission without recording form answers, comments, decision rationale, filenames, or private paths.
- Apply the requested RES queue/detail/screening/requirement/assignment, User Management, Applicant dashboard/requirements, and Reviewer table alignment and responsive-layout corrections.
- Add direct RES requirement downloads beside protected previews while retaining nested authorization.
- Synchronize feature, route, authorization, security, testing, UI, limitation, and changelog documentation.

Excluded:
- Automatic or manual document-content redaction, OCR, conversion, public URLs, third-party viewers, selected-text annotations, e-signature upload, revision comparison/re-review, RES consolidated Full Board decisions, result release, certificates, QR verification, reviewer reassignment UI, or package installation.

### Implementation Approach
- Backend: use dedicated Form Requests, immutable question catalogs, thin Reviewer controllers, and a transactional review-workflow service with row locks and repeated policy/deadline checks.
- Frontend: extend the existing Reviewer assignment detail into a responsive blind workspace with accessible conflict confirmation, comments, official form dialogs, completion feedback, and final confirmation; reuse existing dashboard panels, buttons, badges, modals, icons, and internal table overflow.
- Database: add conflict-state fields to `reviewer_assignments`; add one-to-one `review_submissions`, one-per-form `review_form_submissions`, and assignment-owned `review_comments` with foreign keys, unique constraints, bounded indexes, JSON response storage, draft/final timestamps, and soft release fields.
- Authorization: extend `ReviewerAssignmentPolicy` with conflict, review, comment, form, and submit abilities; every write requires Reviewer ownership, an active assignment, no declared conflict, and an unsubmitted review. Nested document authorization remains tied to the parent application assignment.
- Files/storage: retain current private `local` storage and controller streaming. Reviewer-facing database content omits identity fields; content-level file anonymization remains blocked on the client-approved procedure.
- Deadlines: gate Reviewer writes through the configured Reviewer Submission process and retain read-only access outside the write window; initial assignment deadlines use the active process deadline when configured.
- Notifications/audit: notify RES Leads neutrally only after the complete assignment set reaches pending release. Audit metadata is limited to IDs, form type/status, comment scope, decision code, and resulting workflow status.

### Files Expected to Change
- Reviewer enums, models, migrations, policies, requests, service, controller, routes, assignment views, dialogs, JavaScript, and CSS
- RES application controller/views and shared responsive table/layout styles
- Applicant dashboard/application views and shared table styles
- Reviewer, RES, Applicant, authorization, validation, document, and responsive-structure tests
- `PLANS.md` and current documentation under `Documentations/`

### Tests and Verification
- Prove assignment-only Reviewer access, exact-record denial, identity omission, conflict gating, private preview/download authorization, deadline enforcement, draft restoration, required form validation, comment ownership/validation, final-decision gating, submitted-work immutability, and all-reviewers-complete pending-release transition.
- Prove direct RES document download authorization and requested table/header/action structure.
- Run focused and full Laravel tests, `php artisan route:list`, migration status, Pint, Blade compilation, the Vite production build, PHP syntax checks, and `git diff --check`.
- Inspect representative desktop, tablet, and mobile widths for RES application details/assignment, Applicant tables, Reviewer list/detail/workspace, dialogs, action alignment, and internal bottom scrollbars.

### Risks and Rollback
- JSON form responses are intentionally constrained by server catalogs at every finalization; malformed or unknown keys are rejected rather than trusted.
- Concurrent final submissions lock the application and active assignment set before projecting the application to pending release.
- Rolling back removes the new review records and assignment conflict fields; no existing application documents or screening/assignment records are deleted.
- Uploaded files may still contain identity within their content until KLD approves and the project implements a redaction/anonymized-copy process.

### Approval Notes
Approved by: User request
Date: 2026-08-03

### Verification Status (2026-08-04)
- The focused Reviewer/RES suite passed 26 tests with 288 assertions. The complete Laravel suite passed 179 tests with 2,650 assertions.
- Route registration, migration status, Blade compilation, Pint, strict Composer validation, platform requirements, the Vite production build, and `git diff --check` passed.
- Interactive local-browser checks passed at 1440px, 768px, and 390px for RES details, requirement downloads/overflow, screening controls, Reviewer assignment, Applicant list/details, Reviewer assignment/conflict/workspace/forms, and the requested table alignment. Wide tables remained in zero-bottom-padding internal scrollers with no page-level horizontal overflow.
- Content-level identity redaction, RES conflict reassignment, re-review/revision comparison, official result release, certificate/QR work, and final stakeholder acceptance remain explicitly outside this completed slice.

## Plan: RES decision corrections, private previews, and Reviewer assignments

Status: Completed on 2026-08-03.

### Goal
Complete the August 3, 2026 RES Lead, Applicant, and Reviewer viewport improvements so saved screening decisions can be corrected safely, reviewer selection remains broad but prioritized, requirement uploads reflect persisted progress immediately, authorized document access has a clear preview experience, and Reviewers receive a real assigned-applications workspace.

### Source Documents
- Primary requirement: attached cross-role viewport specification, August 3, 2026.
- Visual reference: `ECRATS High Fidelity (7).pdf`, pages 1-4, covering populated/empty Reviewer assignments, row states, and assignment details.
- Bug references: supplied RES reviewer-assignment and application-details screenshots showing sticky-header overlap and screening-summary wrapping defects.
- Existing contracts: private application storage, nested document authorization, RES screening/assignment transactions, Reviewer assignment policy, shared dashboard components, and the current uncommitted August 2 RES workflow.

### Scope
Included:
- Allow RES Leads to re-edit persisted administrative screening fields and classification details under authorization, row locks, validation, bounded audit metadata, and safe assignment reconciliation.
- Remove only unstarted pending assignments when a changed decision makes them stale; reject incompatible changes after review work has started or been submitted.
- List every active, setup-complete Reviewer matching the required reviewer classification, rank application department/institution matches first, add an exact Department filter, and remove the Availability filter/column while retaining current load and capacity.
- Correct RES classification-summary wrapping and keep queue, screening, selection, and confirmation states responsive.
- Raise the private application-document limit to 100 MB per file, update client copy, and keep MIME inspection, randomized private paths, policies, and throttles unchanged.
- Update requirement checklist state immediately after asynchronous uploads and refresh only after complete success when no selected browser file remains.
- Keep PDF/image inline streaming; provide Word/Excel files through an authorized in-app fallback with protected download, including Reviewer routes scoped by assignment policy.
- Replace the temporary Reviewer assignments page with a scoped, filterable, paginated populated/empty table and expand assignment details without exposing Applicant account identity.
- Add focused regression coverage and synchronize workflow, route, upload, preview, security, UI, limitation, and changelog documentation.

Excluded:
- Office-to-HTML conversion, third-party document viewers, public document URLs, blind-redaction generation, review forms/decisions, conflict declarations, reviewer reassignment history, certificate generation, or package installation.

### Implementation Approach
- Backend: keep controllers thin; use dedicated Form Requests and locked workflow-service updates for screening corrections; scope Reviewer queries by the authenticated reviewer ID.
- Frontend: reuse existing dashboard headings, filters, internal-overflow tables, status badges, empty states, document dialog, and responsive breakpoints while applying the hierarchy from the supplied reference.
- Database: reuse the one-per-application screening row and existing assignment timestamps/statuses; no schema change is required for this correction batch.
- Authorization: add an explicit screening-update policy ability; retain parent-application and assignment ownership checks for every private document route.
- Files/storage: accept at most 100 MB per file on the private local disk; raw Office content remains non-inline and is never sent to a public conversion service.
- Notifications/audit: notify removed pending Reviewers neutrally and audit only prior/new classification, removed count, and resulting status, excluding notes, reasons, filenames, and private paths.

### Files Expected to Change
- RES screening Form Requests, policy, workflow/eligibility services, controller, routes, and focused tests
- RES detail/assignment Blade views, shared dashboard CSS/JavaScript
- Applicant upload requests/service/controller/views/JavaScript and document tests
- Reviewer assignment controller, index/detail views, routes, navigation tests, and authorization tests
- Current workflow, route, security, upload/preview, feature, UI, limitation, testing, and changelog documentation

### Tests and Verification
- Prove safe screening edits before/after pending assignment, blocked changes after review starts, stale-assignment removal, exact counts, broad Reviewer visibility, match ordering, Department filtering, and inactive exclusion.
- Prove the 100 MB boundary, asynchronous checklist hooks, role-authorized PDF/Office preview behavior, Reviewer-only assignment listing, direct-record denial, filters, empty state, and pagination.
- Run focused and full Laravel tests, Pint, strict Composer/platform checks, route listing, migration status, Blade compilation, the Vite production build, PHP syntax checks, and `git diff --check`.
- Inspect representative desktop, tablet, and phone states in a local browser and record remaining manual-only checks.

### Risks and Rollback
- Pending assignment removal is destructive by design but limited to unstarted rows and recorded in audit; started/submitted work blocks incompatible decision changes.
- A 100 MB Laravel rule still requires production PHP and reverse-proxy request limits above 100 MB; deployment documentation must call out that external configuration boundary.
- Office fallback pages display metadata and a secure download path rather than attempting unsafe browser execution or public conversion.
- All changes are reversible without schema rollback because this phase adds no migration.

### Approval Notes
Approved by: User request
Date: 2026-08-03

### Verification Status (2026-08-03)
- Safe RES correction, stale-pending-assignment reconciliation, started-review protection, exact Reviewer counts, classification matching, Department filtering, owner-scoped Reviewer pages, 100 MB validation, checklist hooks, and protected preview behavior are covered by focused Feature tests.
- The final full Laravel suite, Pint, strict Composer validation, platform requirements, routes, migration status, Blade compilation, the Vite production build, and `git diff --check` passed.
- Browser checks passed at 1280px and 390px for RES screening, reviewer selection, Reviewer assignment list/detail, the private Excel fallback, and the populated submitted Applicant checklist. A live upload transition remains manual because the populated Applicant test account has no editable draft.

## Plan: RES Lead screening and reviewer assignment

### Goal
Implement the initial RES Lead administrative screening, ethics classification, and reviewer-assignment workflow represented by High Fidelity pages 80-92 so adviser-endorsed applications can move securely into expedited review, full-board review, or the exempted documentation path.

### Source Documents
- Primary requirement: attached RES screening and reviewer-assignment specification, August 2, 2026.
- Visual reference: `ECRATS High Fidelity (6).pdf`, all 13 cropped pages corresponding to original pages 80-92.
- Existing contracts: `PROJECT_GUIDELINES.md`, current application/adviser workflow services, private document controllers, account reviewer profiles, audit logging, notifications, and shared dashboard components.
- Current limitation retained: reviewer evaluation forms, conflict declarations beyond known applicant/adviser conflicts, result release, and certificate generation are later workflow slices.

### Scope
Included:
- Expand the RES Applications Queue with search and filters for workflow status, applicant category, research type, review type, institute/program, and endorsement date range.
- Add a one-per-application administrative screening record with completeness, receipt-check state, eligibility confirmations, notes, review classification, reason, actor, and timestamp.
- Add RES-only classification and reviewer-assignment writes using Form Requests, policy checks, transactions, row locks, database uniqueness, audit events, and neutral notifications.
- Require exactly one eligible active reviewer for expedited review and exactly three for full-board review; enforce reviewer classification, institutional discipline matching, active workload capacity, and known applicant/adviser conflict exclusion.
- Add an exempted terminal screening status that bypasses reviewer assignment and enters the documented direct-release/documentation boundary.
- Build responsive queue, screening, protected-document, reviewer-selection, confirmation, and assignment-result interfaces using existing dashboard patterns.
- Add focused feature coverage and synchronize workflow, route, feature, security, testing, limitation, and changelog documentation.

Excluded:
- Reviewer worksheets and submissions, full conflict-of-interest declarations, review monitoring implementation, result/certificate release, QR verification, blind-review redaction, reviewer reordering persistence, or public file links.
- New packages, `.env` changes, destructive migrations, or changes to unrelated account-management modules.

### Implementation Approach
- Backend: keep controllers thin; centralize classification and assignment transitions in an application service that repeats authorization and eligibility checks inside retryable transactions.
- Frontend: reuse the shared layout, status badges, icon system, private document viewer, overflow wrapper, modal behavior, and compact dashboard styling while adapting the high-fidelity information hierarchy.
- Database: add a uniquely constrained `application_screenings` table; retain `research_applications.review_type` and `application_status` as the authoritative workflow projection; reuse the existing reviewer-assignment uniqueness constraint.
- Authorization: add explicit application-policy abilities for classification and assignment, in addition to the existing RES route middleware.
- Files/storage: retain private controller-authorized preview/download routes and never expose storage paths.
- Notifications/audit: notify selected reviewers and the applicant with bounded neutral messages; record review type, resulting status, assignment count, and identifiers without notes or document contents.

### Files Expected to Change
- `PLANS.md`, application/review enums, application-screening migration/model, research application relationships, policy, Form Requests, workflow service, controller, routes, and rate limits
- RES queue/screening/assignment Blade views, dashboard CSS/JavaScript, factories/seed data as required
- Focused RES workflow feature tests
- Application workflow, routes/navigation, features, security, testing, known-limitations, and changelog documentation

### Tests and Verification
- Prove RES-only classification, exact expedited/full-board reviewer counts, eligibility/capacity enforcement, duplicate protection, and exempted assignment bypass.
- Prove queue filter/search behavior, protected detail/document access, responsive overflow contracts, and accessible assignment confirmation structure.
- Run focused and full Laravel tests, route listing, Blade compilation, Pint, Composer checks, Vite production build, migration status, and `git diff --check`.
- Inspect representative desktop, tablet, and mobile states through the in-app browser when available and record any remaining manual-only checks.

### Risks and Rollback
- Concurrent classification and assignment requests are serialized with row locks; database unique constraints remain the final duplicate defense.
- Reviewer capacity can change concurrently, so selected reviewer rows are locked and active workload is recalculated before insert.
- The migration is additive. Rollback removes only screening records and leaves applications, assignments, users, and private files intact.
- Exempted applications stop at a documented direct-release boundary until certificate generation and release are implemented.

### Approval Notes
Approved by: User request
Date: 2026-08-02

### Implementation Status

- Completed on 2026-08-02 without package installation, `.env` changes, destructive migrations, or public document exposure.
- The full Laravel suite passed 164 tests with 2,486 assertions; the focused RES workflow suite passed 12 tests with 141 assertions.
- Routes, migration status, Blade compilation, Pint, Composer validation/platform requirements, PHP syntax checks, the Vite production build, and `git diff --check` passed.
- Queue, screening, reviewer selection, and confirmation states were interactively verified at 1440, 1280, 1024, 768, and 390 pixels with no page-level horizontal overflow or browser-console warnings.

## Plan: RES workflow visibility, validation, and responsive layout completion

### Goal
Complete the July 30, 2026 cross-role correction batch so RES Lead deadline controls are authoritative and readable, adviser endorsements become visible and notify the RES Lead, account imports report precise validation failures, and shared application and user-management pages remain usable from desktop through smartphone widths.

### Source Documents
- Primary requirement: attached `pasted-text.txt`, July 30, 2026.
- Visual references: the supplied RES Lead deadline-configuration redesign and current RES Lead, Adviser, Applicant, account-management, bulk-import, and application screenshots.
- Project direction: `PROJECT_GUIDELINES.md`, current active plans, existing role policies/services/tests, and the high-fidelity prototype under `context_files/`.
- Existing approved decisions retained: private files stay controller-authorized; application limits count formal submissions rather than drafts; deadline availability is enforced server-side; teammate documentation and dirty work are preserved.
- Clarification for this batch: a checked manual process toggle forces that process open regardless of its configured open/deadline range; an unchecked toggle returns the process to automatic date-based availability. Configured dates must still be valid, ordered, and non-past when saved.

### Scope
Included:
- Redesign Deadline Configuration around one term summary, Upcoming Deadline and Active Date Range summaries, and a responsive seven-phase table. Do not render a Manual Toggles On summary.
- Reject past term/process dates and times in the browser and backend; keep process rows bounded, date/time values readable, and tabs/content aligned at all supported widths.
- Confirm manual-open priority in the shared deadline resolver and protect all workflow actions with the same server-side availability contract.
- Add or complete a RES Lead endorsed-applications landing page with route, navigation, authorization, pagination, and access to every application that has entered the RES flow through Adviser endorsement.
- Persist a neutral database notification for active RES Leads when an Adviser endorses an application, without exposing private document paths, reviewer identity, or unnecessary applicant details.
- Correct individual user detail/edit alignment, application-count centering, horizontal account-status actions, Reactivate labeling, and removal of the edit-page Dropdown Options shortcut.
- Keep the active `.xlsx` workbook flow, left-align the Official Template block, accept alphanumeric student numbers, cap phone numbers at 11 digits, and report workbook errors with row, field, reason, and expected format.
- Correct Adviser detail decisions/status spacing and horizontal table overflow.
- Correct Applicant detail, submission checklist, duration-date, landing-header, and upload-control alignment while preserving predictable narrow-screen stacking.
- Add concise maintenance comments only where workflow or validation intent is not self-evident, and synchronize current documentation.

Excluded:
- New packages, destructive schema rewrites, public document URLs, reviewer-identity disclosure, `.env` changes, hard deletion outside existing approved account lifecycle behavior, or changes beyond the established seven deadline processes.

### Implementation Approach
- Backend: reuse the existing deadline, endorsement, notification, import, and policy boundaries; extend them only where the audited behavior is missing. Keep controllers thin and validate every authoritative rule on the server.
- Frontend: reuse the existing dashboard layout, icon component, dialog patterns, shared overflow wrapper, and responsive CSS. Keep same-row controls at matching heights and allow horizontal scrolling at the table boundary.
- Database: prefer the existing notification and endorsement schema. Add no migration unless the audit proves current persisted structures cannot satisfy the approved behavior.
- Authorization: RES Lead pages remain RES Lead-only; Adviser actions remain assigned-application-only; Applicant document and application actions remain owner-only.
- Notifications/audit: notify all eligible active RES Leads after a committed Adviser endorsement and retain existing Applicant notification/audit behavior.

### Files Expected to Change
- `PLANS.md`, deadline request/service/resolver/settings view, dashboard CSS/JavaScript, and focused settings tests
- Adviser endorsement service/controller/views, RES Lead applications route/controller/view/navigation, policy/query logic, and focused notification/access tests
- User-management controller/service/views and workbook validation/import tests
- Applicant and Adviser application views plus shared responsive structural tests
- Current feature, workflow, route, testing, UI, notification, import, and changelog documentation

### Tests and Verification
- Prove every saved term/process timestamp rejects past values, ordered ranges remain required, and manual-open overrides date-based closure.
- Prove endorsement creates one neutral notification per active RES Lead and the endorsed-applications page is inaccessible to other roles.
- Prove workbook validation accepts alphanumeric student numbers, rejects phone numbers over 11 digits, and identifies row, field, reason, and expected format.
- Retain Adviser action authorization tests and add structural assertions for aligned actions, nonredundant status labels, responsive upload controls, and reusable table overflow.
- Run focused suites, full `php artisan test`, `php artisan route:list`, Blade compilation, Pint, Composer checks, `npm.cmd run build`, and `git diff --check`.
- Inspect desktop, tablet, and smartphone layouts through a controllable browser when available; otherwise document the unavailable manual check.

### Risks and Rollback
- A force-open toggle intentionally bypasses the process date window at runtime, so only RES Lead-authorized settings changes may control it and every change remains audited.
- Notification dispatch must occur after a successful endorsement transaction and remain idempotent with the single eligible workflow transition.
- Layout changes stay within existing role views/shared CSS and can be reverted independently from workflow behavior.

### Approval Notes
Approved by: User request
Date: 2026-07-30

### Implementation Status
- Completed on 2026-07-30 without adding packages, changing `.env`, or adding a migration for this correction batch.
- Full Laravel suite: 154 tests passed with 2,345 assertions. The responsive-action adjustment also passed its focused 17-test, 182-assertion suite.
- Routes, Blade compilation, Pint, Composer validation/platform requirements, migration status, the Vite production build, and `git diff --check` passed.
- Desktop, tablet, and smartphone browser observation remains pending because no controllable browser session was available; the acceptance cases are recorded in `Documentations/MANUAL_VISUAL_VALIDATION.md`.

## Plan: Dashboard reflection, dated research duration, and stable import options

### Goal
Complete the July 29, 2026 Applicant, Adviser, RES Lead, deadline, document-submission, and bulk-import correction batch so role dashboards reflect relevant stored applications, new research durations use validated dates, deadline controls remain understandable and authoritative, and renamed dropdown labels do not invalidate older official workbooks.

### Source Documents
- Primary requirement: attached `pasted-text.txt`, July 29, 2026.
- Project direction: `PROJECT_GUIDELINES.md`, current implementation plans, existing dashboard/application/settings/user-management code, and the high-fidelity prototype under `context_files/`.
- Visual evidence: supplied Applicant, Adviser, RES Lead, document modal, application detail, import, and deadline screenshots showing missing dashboard data, separated application/progress sections, long modal-title overflow, and native fieldset border gaps.
- Existing approved decisions retained: the maximum of three counts formally submitted applications only; a checked deadline toggle forces a process open while an unchecked toggle returns it to automatic date-based behavior; private documents remain controller-authorized.
- Workbook limitation: an Excel list-validation cell stores only its visible selected label. The current macro-free, formula-free Accounts worksheet cannot safely carry a hidden stable ID beside every selection without changing its approved columns or trusting editable workbook mappings.
- Approved-compatible fallback: keep `profile_options.id` as immutable identity, record prior labels in a server-owned alias table whenever a label changes, and resolve current labels or historical aliases to that ID during import before storing the current canonical label.

### Scope
Included:
- Keep the existing reusable horizontal-overflow boundary on every table and preserve responsive Applicant, Adviser, RES Lead, and import layouts.
- Select the Applicant's newest non-archived application without hiding it because of term linkage; include all relevant assigned/submitted Adviser records and administrative RES records in dashboard counts and recent lists. Deadline alerts and timeline calendars remain tied to the active configured term.
- Add an Applicant submission open/closed label, a real confirmation dialog before formal submission, the exact approved checklist order, a combined research-title/completion section, and a red left-aligned submission-limit warning.
- Replace new/edit research-duration text entry with required Starting Date and Ending Date fields, requiring the end date to be on or after the start date.
- Add nullable date columns while retaining the legacy duration string only as a historical display/validation fallback so already-submitted records are not corrupted or made unusable.
- Keep document filenames/titles bounded inside the viewing modal and expose the complete truncated value through the existing delayed tooltip.
- Render each deadline process heading inside its complete bordered section, display the manual switch as On/Off, and preserve manual-open priority over automatic Philippine-time date evaluation.
- Align Adviser decision copy and actions horizontally on desktop, stack them predictably on narrow screens, and refine application section spacing.
- Preserve centered Official Template content and horizontal Deactivate/Delete controls already present.
- Add server-owned profile-option aliases and canonicalize bulk-import dropdown labels through stable option identities; inactive and unknown options remain invalid.
- Update current project documentation for dashboard, deadline, application, upload, user-management, and import behavior.

Excluded:
- Dropping or rewriting legacy `expected_duration` data, guessing dates from prose, updating historical user/application snapshots automatically after an option rename, adding formulas/macros to templates, changing the approved `.xlsx` column contract, package installation, `.env` changes, or implementing a manual forced-closed state.

### Implementation Approach
- Backend: adjust role dashboard query scope; pass the shared application-submission window to the application index; extend application information validation with date-pair rules and a legacy persisted-record fallback; canonicalize controlled bulk fields through the profile-option catalog.
- Frontend: reuse accessible project dialogs, status/error patterns, delayed tooltip behavior, shared overflow regions, stable control dimensions, and responsive grid/flex boundaries.
- Database: add nullable `expected_start_date` and `expected_end_date` columns; add `profile_option_aliases` linked to immutable `profile_options.id`, uniquely constrained by field and normalized historical label.
- Authorization: preserve all existing Applicant ownership, Adviser assignment, RES Lead settings/options, private-document, and final-submission policy checks.
- Files/storage: do not change private upload storage or workbook temporary-storage boundaries.
- Notifications/audit: retain existing submission and user-management events; include stable option identity in option-update audit metadata without exposing workbook or private-file paths.

### Files Expected to Change
- `PLANS.md`, additive migration/model/relationship for duration dates and profile-option aliases
- Application model, information/draft services, controllers, form/list/requirements/detail views, dashboard data service, CSS, and JavaScript
- Profile option catalog and bulk account validation/import tests
- Deadline settings view/JavaScript/CSS and related tests
- Applicant/Adviser/RES dashboard, application workflow, and documentation tests/files

### Tests and Verification
- Prove newest Applicant data appears even when a configured term exists; Adviser/RES dashboards include relevant legacy or differently linked records while remaining role/status scoped.
- Prove open/closed status labels and server submission availability follow automatic dates and manual-open priority.
- Prove new duration dates are required, reject an ending date before the starting date, persist correctly, and retain readable legacy duration display.
- Prove formal submission opens a confirmation dialog structurally and keeps the server-side transition authoritative.
- Prove an old workbook label remains valid after its option is renamed, resolves to the same immutable option ID, stores the current canonical label, and fails if that option is inactive.
- Retain the all-Blade-table overflow assertion and add structural checks for merged submission overview, bounded modal title, deadline borders, On/Off labels, and Adviser decision alignment.
- Run focused suites, full `php artisan test`, migrations/status, route listing, Blade cache, Pint, Composer checks, `npm.cmd run build`, and `git diff --check`.
- Inspect desktop, tablet, and smartphone layouts through a controllable browser when available; otherwise report that check as unavailable.

### Risks and Rollback
- Both schema changes are additive. Rollback removes only the two date columns and alias table; legacy duration strings and current option rows remain intact.
- Alias uniqueness is enforced within each option field, preventing one historical label from resolving to two identities. Renaming to another option's current or historical label is rejected.
- Dashboard application records intentionally stop using academic-term exclusion because the supplied screenshots and requirement explicitly require existing relevant records to appear. Timeline and deadline configuration remain current-term aware.
- Final submission, deadline availability, and import creation remain server-validated even when client controls are bypassed.

### Approval Notes
Approved by: User request
Date: 2026-07-29

## Plan: Deadline, application, and adviser-endorsement completion

### Goal
Complete the July 28, 2026 Applicant, Adviser, dashboard, RES Settings, deadline, and shared-interface batch so current records and Philippine-time deadlines are reflected consistently, application identifiers follow the approved public format, and complete initial submissions can be securely returned or endorsed by their assigned adviser.

### Source Documents
- Primary requirement: attached `pasted-text.txt`, July 28, 2026.
- Project direction: `PROJECT_GUIDELINES.md`, the consolidated system documentation, the module-based ERD, and the existing uncommitted term/document/settings implementation.
- Workflow references: `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`, `context_files/RSU-MEMO-PROCESS OF ETHICS_FINAL_1-2.pdf`, and `context_files/local/ECRATS COMPLETE MODULE-BASED ERD DA.txt`.
- Visual reference: `context_files/local/ECRATS High Fidelity.pdf`, especially Adviser pages 35-45.
- Superseded decisions: new application codes use `RES-Year-ApplicantType-InstitutionAcronym-MMDDYYYY-Random` instead of the previously planned monthly sequence format; a manual-open deadline override may keep a process available outside its date range instead of remaining date-bounded.
- Confirmed user decision: the maximum of three counts formally submitted applications, not drafts. Returning and resubmitting the same application remains one application slot.

### Scope
Included:
- Preserve reusable bottom horizontal scrolling for every table and complete the requested Applicant, Adviser, user-action, import-template, modal, and responsive alignment corrections.
- Generate collision-checked application codes containing the applicant type and approved institution acronym without exposing database identifiers.
- Configure seven role-mapped deadline processes, including Reviewer revision review; use Asia/Manila comparisons; allow manual-open override; and model decision/certificate release as one exact date.
- Keep dashboard counts, recent records, deadline alerts, and timeline information sourced from current application and RES configuration data.
- Move username changes beside password changes in Security and Privacy, add confirmation for both actions, and present mismatch errors on both password fields.
- Refine Applicant document headers, upload queue controls, Upload All placement, fixed-width uploaded filenames, private previews, and authorized download fallback.
- Add Adviser-only access to assigned, complete initial submissions; record each return or endorsement with a reason/remarks contract; transition endorsed records to RES screening; notify the applicant; and audit both actions.

Excluded:
- Returning post-review revision cycles to the Adviser, public document URLs, unsafe Office-document embedding, package installation, `.env` changes, reviewer workflow implementation, or certificate generation/release implementation.
- Inventing additional adviser decision fields not supported by the ERD/prototype.

### Implementation Approach
- Backend: centralize application-code composition, Philippine-time deadline resolution, role-specific dashboard deadlines, and Adviser endorsement transitions in focused services with thin controllers and Form Requests.
- Frontend: retain established dashboard patterns while using unframed responsive sections, consistent table overflow, stable upload-control geometry, accessible confirmation/decision modals, and clear authorized preview fallback.
- Database: add an `endorsements` history table with application/adviser foreign keys, `returned|endorsed` status, return reason, optional remarks, and action timestamps. Keep all changes additive and preserve existing application/document rows.
- Authorization: extend the application policy so only the assigned active Adviser can inspect or decide an eligible application; recheck assignment, status, completeness, and initial-submission eligibility under a transaction lock.
- Files/storage: retain private storage and controller-streamed previews/downloads. No private path or unsupported executable content becomes public.
- Notifications/audit: notify only the owning Applicant with neutral workflow text and record actor, application, action, and non-sensitive decision metadata through the existing audit infrastructure.

### Files Expected to Change
- `PLANS.md`, additive endorsement migration/model/service/request/controller/routes, application policy/status workflow, notifications, and focused tests
- Application-code generator/draft service and Applicant application tests/views
- Deadline catalog/resolver/configuration service/settings/dashboard services and related tests/views
- RES Security and Privacy, deadline configuration, shared dashboard CSS/JavaScript, Applicant document submission, Adviser application details, and user-management/import views

### Tests and Verification
- Focused tests for code format/uniqueness, Philippine-time automatic state, manual-open priority, role deadline mapping, exact release dates, current dashboard records, credential confirmation/mismatch handling, upload queue preservation, preview authorization/fallback, and shared table overflow.
- Adviser tests must prove assigned-only access, complete-initial-submission eligibility, return/endorse persistence and transitions, revision bypass, notification/audit effects, and rejection of incomplete or ineligible records.
- Add policy/service/UI tests proving drafts do not count, a fourth formal application is rejected under the applicant row lock, and resubmitting one of the same three records does not consume another slot.
- Run affected suites, full `php artisan test`, `php artisan route:list`, `php artisan migrate:status`, Blade compilation, Pint, Composer validation/platform checks, `npm.cmd run build`, PHP syntax checks, and `git diff --check`.
- Inspect desktop, tablet, and smartphone layouts through a controllable browser when available; otherwise document the unavailable visual check and retain structural responsive assertions.

### Risks and Rollback
- Endorsement history is additive. Rollback removes only the new table; existing applications and private files remain intact.
- Application status transitions are transaction-locked and policy-protected so double decisions, stale pages, and cross-Adviser access cannot create conflicting workflow history.
- Manual-open override is intentionally stronger than date bounds; automatic mode still follows the active academic term and configured Philippine-time range.
- Existing application codes remain valid historical identifiers; only newly created applications receive the new public format.
- The formal-submission limit is enforced under the applicant row lock and excludes the current record during resubmission, preventing concurrent fourth submissions without penalizing returned corrections.

### Approval Notes
Approved by: User request
Date: 2026-07-28

## Plan: Term-aware settings, application records, and document submission corrections

### Goal
Complete the July 28, 2026 cross-role settings and application batch so deadlines are date-bound and term-aware, application and audit records can be filtered historically, document versions follow revision cycles, and all shared interfaces remain usable at narrow viewports without weakening private-file authorization.

### Source Documents
- Primary requirement: attached `pasted-text.txt`, July 28, 2026.
- Project direction: `PROJECT_GUIDELINES.md`, the current application/account-management architecture, and committed implementation at `a1c32d4`.
- Visual references: `context_files/local/ECRATS High Fidelity.pdf` and existing dashboard, settings, account-management, table, modal, tooltip, and form patterns.
- Prior plan superseded in two places: eligible unsubmitted draft discard now permanently removes the draft and its private files instead of archiving it; an enabled manual deadline toggle remains bounded by its configured opening/deadline dates instead of overriding elapsed dates.

### Scope
Included:
- Add Profile, Deadline Configuration, and Security and Privacy tabs to RES Lead Settings, with Profile as the default and validation returning users to the affected tab.
- Add one normalized academic-term record containing semester, academic year, start, and end; link current deadline configurations, newly created applications, and new audit events to that term.
- Show the configured term label only while its timeframe is active; otherwise use the neutral `Semester and Academic Year` label.
- Add the Revision Period process, reject past process dates on both client and server, and make all process availability the conjunction of active term, opening date, deadline, active flag, and manual toggle.
- Filter application lists/dashboard records and audit logs by semester and academic year through stable term identifiers while retaining explicit date filters.
- Make document display versions equal the application's current revision cycle: initial cycle is Version 1, replacements in the same cycle keep the same version, and reopening a formally Adviser-returned application advances the cycle once before resubmission.
- Add authorized multi-requirement upload, per-file feedback, retained browser selections when one file is uploaded, fixed-width filename controls/tooltips, and safe inline PDF/image preview with authorized download fallback for unsupported office formats.
- Permanently delete only owner-controlled Draft/Incomplete applications that never crossed formal Adviser or RES review, including their private document files, after explicit confirmation.
- Generate unique public application codes as `MM-YYYY-Sequence-Random`, using a month/year sequence and a collision-checked uppercase alphanumeric suffix without exposing a raw database ID.
- Complete requested password-validation presentation, user reactivate/action alignment, restoration/import layout, reusable table overflow, favicon, and profile-menu cleanup.

Excluded:
- Implementing the full reviewer revision lifecycle, incrementing revision cycles outside an approved revision transition, converting private Office documents to HTML/PDF, public file URLs, or installing a document-conversion package.
- Reassigning historical records to a guessed term. Existing records remain nullable/unscoped until an explicit term can be determined; date filters continue to work for them.
- Changing `.env`, authentication architecture, reviewer anonymity, certificate access, or private storage disks.

### Implementation Approach
- Backend: introduce an `AcademicTerm` model/resolver; stamp new applications and audit events with the active term; add reusable term filters; centralize deadline state calculation; keep controllers thin and policy checks authoritative.
- Frontend: use accessible settings tabs, stable error slots, native picker constraints with visible focus/hover treatment, reusable overflow regions, fixed-width ellipsis controls, and one JavaScript upload queue that submits selected files without reloading the page.
- Database: add `academic_terms`; nullable indexed `academic_term_id` links on deadlines, research applications, and audit logs; and `current_revision_cycle` defaulting to 1 on research applications. Preserve existing rows and foreign-key safety in rollback.
- Authorization: keep settings RES Lead-only, batch upload Applicant-owner-only, previews parent-application-authorized, and permanent discard restricted to unsubmitted Draft/Incomplete records.
- Files/storage: continue private local storage and response streaming. Permanently discarded draft files are collected inside the locked transaction and deleted only after database commit; unsupported inline types receive an authorized download fallback.
- Workflow: `manual_status = closed` always closes a process; `open` or legacy `null` can operate only while the active term and configured start/deadline window are current. Document replacement does not advance `current_revision_cycle`; the existing `ReturnedByAdviser` to editable transition advances it exactly once.
- Notifications/audit: new audit events receive the active term automatically; draft deletion records only non-sensitive identifying metadata without retaining a morph reference to the deleted draft.

### Files Expected to Change
- `PLANS.md`, additive migrations, academic-term/deadline/application/audit models and services
- RES settings controller, requests, view, CSS, and JavaScript
- Application/audit list controllers, filters, dashboards, views, and shared table components
- Applicant document request/controller/service/routes/views and private preview fallback
- Draft/application-code services, navigation layout, favicon markup, user-management views, and focused Feature tests

### Tests and Verification
- Focused tests for past-date rejection, active-term labels, date-bounded manual availability, dashboard alerts, term filters, inactive-account reactivation, revision-cycle document versions, batch upload behavior, authorized preview/fallback, permanent draft deletion/file cleanup, and application-code format/uniqueness.
- Structural tests for settings tabs, stable password errors, user-management alignment, filename controls/tooltips, profile-menu cleanup, favicon use, and every table residing in the shared overflow boundary.
- Run affected Feature suites, full `php artisan test`, `php artisan route:list`, `php artisan migrate:status`, PHP syntax checks, Pint, Composer validation/platform checks, Blade compilation, `npm.cmd run build`, and `git diff --check`.
- Run migration rollback only in pretend mode. Perform desktop, tablet, and smartphone browser checks when a controllable browser is available.

### Risks and Rollback
- Term links are nullable and additive, so legacy records remain readable; rollback removes only new links, cycle metadata, and the term table.
- Permanent draft discard is intentionally irreversible. Eligibility is rechecked under a database lock, submitted records are forbidden, and private-file deletion occurs only after the committed database deletion.
- Monthly sequence allocation is serialized against matching month/year codes and still protected by the existing unique index plus bounded collision retries.
- Browser preview remains intentionally limited to PDF and safe image MIME types; Word and Excel files are never rendered as executable public content.

### Approval Notes
Approved by: User request
Date: 2026-07-28

## Plan: RES settings and workflow reliability corrections

### Goal
Deliver a RES Lead-only settings workspace and correct the attached July 27, 2026 Adviser, Applicant, account-lifecycle, document, import, authentication, and shared-table behaviors without weakening existing authorization, private storage, or audit history.

### Source Documents
- Primary requirement: attached `pasted-text.txt`, July 27, 2026.
- Project direction: `PROJECT_GUIDELINES.md`, the current application/account-management documentation, and the committed implementation at `a8b6c44`.
- Visual references: `context_files/local/ECRATS High Fidelity.pdf` plus the existing dashboard settings and compact administrative form patterns.
- Existing process definitions: application submission, Adviser endorsement, reviewer submission, RES screening/classification, and result/certificate release from `DashboardDemoSeeder`.
- Prototype limitation: the available high-fidelity PDF includes RES Lead navigation but no dedicated RES Lead deadline-settings screen. The new screen will therefore reuse the established settings-page visual language rather than inventing an unrelated composition.

### Scope
Included:
- Replace the RES Lead Settings placeholder with RES-only deadline, semester, username, and password controls.
- Add additive deadline metadata so a manual `open` or `closed` state overrides configured dates; existing rows with no override retain automatic date behavior.
- Synchronize the configured semester and mapped process dates with existing timeline calendar events, and keep Applicant submission enforced by the same server-side window resolver used by the dashboard.
- Correct Adviser account navigation/filtering and application status/action alignment.
- Correct Applicant list actions, progress-derived submission checklist, clickable document-title modal, secure replacement/download fallback, current-document removal, and archive-based draft discard.
- Correct RES Lead individual deactivate/reactivate/archive controls, inactive-account login messaging, optional Position/Designation account creation, restore-dialog alignment, and repeat bulk-import validation.
- Preserve the existing assisted password-reset architecture while proving active-account reset, single use, and seven-day expiry.
- Apply reusable internal horizontal overflow and centered badge/action alignment to every current table.

Excluded:
- A public forgot-password request form, arbitrary document types, public document URLs, permanent account or application deletion, package installation, and changes to Adviser endorsement, RES screening, reviewer, revision, or certificate lifecycle transitions.
- New deadline process names beyond the five already established in repository seed/configuration logic.
- Claims of browser/PDF visual acceptance without direct observation.

### Implementation Approach
- Backend: add a focused RES settings controller, Form Requests, deadline configuration service, self-account update service, account archive action, document detach action, and draft archive action while retaining thin controllers.
- Frontend: build the RES settings workspace from existing dashboard form controls; consolidate Applicant file actions into a title-triggered modal; add confirmation states and shared button/table refinements.
- Database: add nullable `semester_label` and nullable `manual_status` to `deadline_configurations`. `manual_status = open|closed` overrides dates, while `null` preserves legacy automatic scheduling.
- Authorization: keep settings routes inside the RES Lead middleware group; add explicit policy checks for draft discard, document removal, account status, and archive actions.
- Files/storage: document removal clears only the current pointer and preserves the private version/file for audit history; replacement remains versioned and no private path is exposed.
- Workflow: draft discard changes only eligible Draft/Incomplete records to Archived, clears the unique draft slot, preserves related history, and never touches formally submitted records.
- Notifications/audit: retain assisted reset delivery and record deadline changes, self credential changes, blocked inactive login, document detach, draft discard, status/reactivation, and archive actions without sensitive values.

### Files Expected to Change
- `PLANS.md`, workflow/account/security/testing/UI documentation
- Deadline and timeline migration/model/service/controller/request/view files
- Authentication controller/login view and shared login assets
- User-management policy/service/controller/query/view/import files
- Applicant application/document policy/service/controller/view files
- Shared dashboard CSS/JavaScript, routes, seed/demo configuration, and focused Feature tests

### Tests and Verification
- Focused tests for RES settings authorization and persistence, deadline/manual override submission behavior, self username/password updates, complete/incomplete submission, document detach, draft discard, optional designation, account deactivate/reactivate/archive, inactive login modal, repeat import validation, active-account assisted reset, reset single use/expiry, Adviser filters/actions, and structural table/modal behavior.
- Run the affected Feature suites, full `php artisan test`, `php artisan route:list`, `php artisan migrate:status`, Pint, Composer validation/platform checks, `npm.cmd run build`, PHP syntax checks, and `git diff --check`.
- Check the migration rollback in pretend mode only; do not run destructive migration or database commands.
- Desktop, tablet, and smartphone visual checks remain pending unless a connected browser is available.

### Implementation Checkpoint
- Implemented on 2026-07-27 without a package installation, public reset request form, public document path, hard delete, or `.env` change.
- Verified the complete Laravel suite at 123 passing tests and 1,999 assertions; focused settings, authentication, identity, Applicant, Adviser, document, deadline, and import tests also pass.
- Verified all Blade templates compile, all current table elements are inside the shared horizontal-overflow boundary, all 96 routes register, the additive migration is applied and has valid pretend rollback SQL, Pint passes, the production Vite build succeeds, and `git diff --check` is clean.
- Browser-driven desktop, tablet, and smartphone screenshots remain the only pending check because that development session had no controllable browser available.

### Risks and Rollback
- Manual deadline overrides are nullable so existing date-controlled records keep their current behavior until explicitly saved through Settings.
- Canonical settings rows receive higher priority than demo rows; rollback removes only the additive metadata and leaves existing deadline/timeline records intact.
- Document detach and draft discard preserve history intentionally; rollback can restore application/document current state from audited database records without recovering deleted files.
- Username/password changes use uniqueness, current-password validation, hashing, remember-token rotation, rate limits, and audit metadata that excludes passwords.
- Individual archive uses existing user soft deletes and the existing restore-import path; no hard deletion is introduced.

### Approval Notes
Approved by: User request
Date: 2026-07-27

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
# Plan: Complete August 20 ECRATS cross-viewport update

Status: In progress on 2026-08-20.

## Goal

Implement the complete attached August 20 specification across reviewer eligibility, account creation/import, Full Board release, document/worksheet versions, certificate configuration and personalized output, Applicant and Reviewer workspaces, notifications, profile stability, secure previews, term filtering, responsive presentation, and required copy removal while preserving all historical and private records.

## Authority And Safety

- The attached August 20 prompt is the newest requirement and overrides older Reviewer-classification, certificate-validity, QR, upload, and release behavior.
- Changes use additive or guarded forward migrations only. No database reset, broad rollback, record deletion, `.env` change, commit, push, deployment, tunnel, or external data transfer is authorized.
- Legacy Reviewer classification columns remain historical compatibility data but will no longer control eligibility or current UI.
- Private documents, worksheets, QR/signature assets, and certificates remain on authenticated same-origin routes with role/ownership checks and defensive response headers.

## Implementation Batches

1. Add safe schema support for certificate recipients, per-certificate QR/validity provenance, RES certificate configuration, and seven-day notification bin retention.
2. Remove Reviewer classification from current validation, imports, profiles, filters, and assignment eligibility; add RES-controlled Reviewer capability/capacity to individual and workbook Adviser creation; preserve usernames during identity changes.
3. Implement filtered/paginated notification inbox and bin with individual, selected, all, restore, unread/read, confirmation, and scheduled seven-day purge behavior.
4. Implement automatic initial/revision uploads, exact document/worksheet business-version rules, revision status/navigation/panels, released worksheet access, Reviewer worksheet modals, consent gating, actionable revision-comment validation, and own-history privacy.
5. Implement Certificate Configuration with signatory, signature, QR, fixed validity snapshot, personalized recipient certificates, corrected title/document layout, and fixed QR placement against the official PDF reference.
6. Correct automatic Approved processing, application-level non-Approved release, Full Board conflict placement, Decision & Certificates filters/modal layout, version tables, and Review Monitoring workload/drill-down behavior.
7. Add server-side term selection to affected dashboards, queues, monitoring, reviews, revisions, decisions, and reports without broadening role visibility.
8. Remove the specified redundant subtext last, complete responsive/search/profile layout fixes, and update all implementation, feature, changelog, testing, visual-validation, plan, and known-issue records.

## Verification

- Add and run focused feature/unit tests for every changed server-side contract, then run the complete Laravel suite where feasible.
- Run the guarded migration on the configured database, migration status, route listing, Blade compilation, Pint on changed PHP files, Vite production build, and `git diff --check`.
- Start the local application and inspect authenticated desktop, tablet, and mobile surfaces with the available browser tooling.
- Generate a representative personalized certificate, render it to PNG, and visually inspect title quoting, reviewed-document line break, validity, recipient, signatory, and QR placement.

## Known Input Limitation

- The checked-in official certificate PDF contains no QR area and the attachment includes no separate certificate reference image. The implementation will use a fixed non-overlapping lower-right placement on the official design and document the exact coordinates; generated output will be rendered and visually verified in this session.
