# Changelog

All notable project changes should be documented here.

## Unreleased

### 2026-08-22 ENDGAME completion audit

- Added safe automatic screening drafts, page-leave application draft saving, and formal-submission provenance so never-submitted replaced uploads are removed while the submitted V1/V2/V3 history remains immutable.
- Added Reviewer worksheet signatory/signature configuration and corrected generated worksheet title, signature, name, review-date, and continuation-page layout.
- Added an offline deterministic 296x296 default certificate QR for `https://kld.edu.ph/ovprii.php`, immutable fallback/replacement provenance, fixed 30 mm lower-left placement, and plural-recipient Preview All output.
- Completed the RES operational Reports dashboard with all required server filters, eight summary cards, workflow/trend/distribution/turnaround/workload/certificate aggregates, management tables, accessible equivalents, empty states, and fixed aggregate query counts.
- Refined Settings, current-term dashboards, screening/assignment, monitoring/drill-downs, Applicant upload/revision, notifications/Bin, certificate queue/workspace, secure previews, long-value containment, hover/focus, and responsive source contracts.
- Added and applied only `2026_08_22_000000_add_submission_and_worksheet_settings` to local ECRATS MySQL as additive batch 6.
- Final SQLite in-memory suite passes 329 tests and 4,545 assertions. Pint, Vite production build, Blade cache, 172-route discovery, and `git diff --check` pass.
- Authenticated browser/native-preview, pixel-level certificate comparison, and independent QR scan remain pending because no approved browser session, PDF rasterizer, or QR decoder is available. See `Documentations/ENDGAME_REQUIREMENTS_TRACEABILITY_2026-08-22.md`.

### 2026-08-21 DOOMSDAY audit and corrections

- Completed a current-session requirement traceability audit rather than relying on the historical handoff.
- Combined Full Board application releases now freeze and release all three current Reviewers' source versions, anonymous feedback, comments, and actionable requirements; split decisions remain blocked and Approved consensus prepares certificates automatically.
- Added additive combined-release provenance and cycle-derived worksheet business versions without replacing immutable internal artifact history.
- Fixed the MySQL notification-type `DISTINCT`/inherited-order failure, added reusable confirmations for inbox/Bin destructive actions, and guaranteed safe database-error pages without SQL or stack disclosure.
- Removed legacy singular-certificate assumptions from queues, metrics, eligibility, bulk release, survey/claim and private certificate actions; every configured recipient receives an independent personalized certificate.
- Completed server-side academic-term scoping across role dashboards, application/revision/review/certificate/monitoring surfaces and reports while retaining role/ownership limits.
- Aligned Applicant revision, Adviser capability/capacity, Review Monitoring, Certificate Processing/configuration/QR, profile, private-preview, search-field and redundant-subtext contracts with focused regression coverage.
- Applied only `2026_08_21_000000_preserve_combined_release_and_worksheet_business_versions` to local ECRATS MySQL. The final isolated SQLite suite passes 319 tests with 4,414 assertions; Pint, Composer validation, Blade compilation, route discovery and the Vite production build pass.
- Authenticated desktop/tablet/mobile acceptance and pixel-level certificate/reference/QR readability checks remain pending because no in-app browser surface or Poppler renderer was available. See `Documentations/DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md`.

### 2026 Finale integration

- Consolidated Reviewer work into an explicit, immediately enforced Adviser capability, preserving legacy Reviewer IDs/history and adding RES-managed Show/Hide Reviewer and identity reconciliation.
- Added flexible bounded `.xlsx` structure validation, inert external-link handling without resolution, exactly 11-digit phone validation, and moved Dropdown Options into RES Settings.
- Restricted new Applicant uploads to PDF and safe raster images; added requirement/version revision accordions, the ten-question evaluation, anonymous RES aggregates, and complete self-service Profile/Security & Privacy controls.
- Added immutable Reviewer submission versions, edit/resubmit-until-release behavior, current-cycle reassignment, Full Board consensus/conflict release gates, inline asynchronous worksheets, and current-cycle dashboard/list semantics.
- Renamed and privacy-limited Decision & Certificates, added automatic pending certificate generation after final approval, one-calendar-year validity, future-only Certificate/Review Worksheet backgrounds, RES signatory management, and issued-artifact immutability.
- Added RES Adviser/Reviewer Review Monitoring and tightened Adviser Applicant visibility to created-by or formally-submitted relationships.
- Added seven additive `2026_08_17_*` migrations for Reviewer entitlement/reconciliation/conflicts, versioned review evidence/consensus, certificate validity, worksheet-background provenance, questionnaire versioning, and role settings. Provenance-bearing migrations refuse unsafe rollback after use.
- See `Documentations/THE_FINALE_IMPLEMENTATION_2026-08-17.md` for rollout order, superseded contracts, privacy invariants, focused evidence, and final checks still required. The current local database has not yet applied these seven migrations.

### Added

- Role-authorized user-management pages for RES Lead and Research Adviser accounts.
- Separate account name fields, institutional identifiers, creator tracking, and generated usernames.
- Search, role/institution/status filters, pagination, populated states, and empty states for account records.
- Excel-only `.xlsx` account templates with exact role headers, hidden database-backed dropdown values, instructions, and server-authoritative validation.
- Private, bounded account-import previews with separate valid, invalid, duplicate, existing, and warning categories.
- RES Lead management for Year Level, Institution, Department, Program, and Reviewer Classification options.
- Expanded account audit filters and recursive sensitive-metadata sanitization.
- RES Lead account activation/deactivation and one-time email password-reset links.
- Security audit records for account creation, profile changes, status changes, imports, and password resets.
- Repository-wide project guidelines.
- Team contribution workflow.
- Planning template for large changes.
- Requirements, architecture, database, setup, and security/deployment documentation.
- GitHub pull request and issue templates.

### Changed

- Login validation now keeps missing or malformed field errors separate from generic credential mismatches.
- Adviser account authority now includes Student Researcher and Faculty Researcher creation only.
- RES Lead account authority includes researchers, advisers, and reviewers while prohibiting RES Lead creation.
- Replaced unresolved Laravel README conflict with ECRATS project README.
- Reclassified Exempted workflow, disapproved decisions, QR verification, anonymization approval, reviewer conflict declaration, and no-hard-delete/soft-delete handling as confirmed team/client additions instead of unresolved questions.
- Replaced the active CSV account-import workflow with `.xlsx` because the approved process requires workbook dropdown validation, protected option data, exact worksheet structure, and formatted instructions.
- Updated Guzzle and PSR-7 within their existing major versions to address locked dependency security advisories.
