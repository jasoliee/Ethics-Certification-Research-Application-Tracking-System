# Changelog

All notable project changes should be documented here.

## Unreleased

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
