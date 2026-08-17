# 2026 Finale Implementation Record

Last updated: 2026-08-17

Implementation status: integrated in the current working tree; final local acceptance is still in progress. This record is the authoritative description of the August 17 Finale batch. It supersedes older documentation wherever the contracts below differ.

## Authority and scope

The product requirements in `D:\Downloads\THE FINALE (3).txt` are the newest client brief for this batch. The file was treated as a requirements source, not as authority to override system/developer operating instructions. Existing source, migrations, tests, `context_files/`, `docs/`, and `Documentations/` were inspected before and during implementation.

The batch is intentionally production-oriented and cross-cutting. It changes authentication compatibility, authorization, account management, spreadsheet validation, application uploads, review evidence, consensus and release rules, certificate generation, settings, monitoring, reports, and responsive Blade/JavaScript interfaces. It does not deploy the system, publish a tunnel, upload data, change `.env`, or send project material to an external service.

## Implemented contract

### Adviser account with Reviewer capability

- `adviser` is now the only interactive account role for both Adviser and Reviewer work. A legacy `reviewer` row is normalized in place to `adviser`, preserving its `users.id` and all foreign-key-linked assignments, submissions, comments, artifacts, notifications, and audits.
- `users.reviewer_enabled` is an explicit, deny-by-default entitlement. Reviewer routes require authentication, the Adviser role, and a database-refreshed entitlement check on every request, so hiding access affects an already authenticated session immediately.
- Canonical Reviewer pages live under `/adviser/reviewer`. Stable `reviewer.*` route names preserve internal links; the old `/reviewer/{legacyPath?}` compatibility route crosses into the Adviser area only after the same capability gate. Legacy standalone Reviewer credentials are rejected and audited.
- Adviser navigation owns a keyboard/mobile-expandable Reviewer submenu after Applicants. It contains Reviewer Dashboard and Assignments.
- RES User Management retains multi-select and adds idempotent Show Reviewer and Hide Reviewer actions. Only active Advisers are changed. Applicants and already-matching rows are ignored with counts. Hiding an Adviser with active assignments requires explicit warning confirmation, immediately removes route access, and does not delete work.
- Reviewer classifications support Expedited and Full Board together. Capacity, active load, setup status, entitlement, account status, declared conflicts, existing disqualifications, and self-endorsement exclusion are enforced in the server query and repeated inside the assignment transaction.
- Possible pre-existing duplicate Adviser/Reviewer people are not guessed at. Candidate pairs are stored in `reviewer_identity_reconciliations`; RES may either keep them separate or merge history through the audited reconciliation workflow. The source identity is retained for audit rather than hard-deleted.

### Account management and Excel import

- A Reviewer is no longer a creatable/importable account type. RES creates Applicants and Advisers, then separately grants an Adviser Reviewer capability.
- Phone numbers are required as exactly 11 numeric digits on individual create, edit, and bulk import. The server is authoritative.
- User Management no longer hosts the Dropdown Option control. The existing option lifecycle is under RES Settings > Dropdown Options with its authorization, aliases, historical labels, usage counts, throttles, and audits intact.
- `.xlsx` import is now structural rather than template-origin/fingerprint bound. The parser discovers recognized required headers within bounded rows/columns and accepts renamed, reordered, or hidden worksheets and reordered columns.
- Inert external-workbook relationship metadata is allowed, but ECRATS never opens, resolves, evaluates, or fetches its target. Formulas, macros, DDE/OLE-style active content, encrypted workbooks, malformed archives, unsupported formats, unsafe cell structures, excess rows/columns, and invalid account data remain rejected.
- Validation remains server-side and row-specific. Preview never creates accounts; confirmation remains actor-bound and single-use. `.xls` is not enabled because the installed safe parser contract is `.xlsx` only.

### Applicant documents, revisions, evaluation, and certificate view

- New Applicant uploads accept only PDF and safe raster JPG/JPEG, PNG, GIF, and WebP files. Extension, MIME type, decoded signature/content, size, nested ownership, and workflow state are checked on the server. Existing historical Office files remain privately streamable through the authenticated fallback; they cannot be newly uploaded.
- Returned Application Information contains Edit Information beside its heading. The read-only returned detail hides remove controls and routes Reupload Documents to the upload workspace, where replacement/removal actions belong.
- During a revision, Certification is hidden and the requirement-based feedback/revision experience is full width. Each requirement is a closed accordion with a document-version selector, feedback for that requirement/version, anonymous Full Board reviewer labels, retained history, and the next-version upload control.
- On final approval, revision submission is hidden and Certification becomes the active state. A generated/released certificate remains claim-gated by the Applicant evaluation.
- Questionnaire version 2 contains the exact ten 1-5 rating questions in System Experience and Ethics Review Process plus optional suggestions/comments. Duplicate responses remain blocked. RES Reports loads anonymous questionnaire aggregates and counts without Applicant identities or free-text comments.

### Adviser scope, profile, and document access

- Adviser application details combine Application Information and Applicant Information in one responsive panel.
- Adviser private PDF/image previews use authenticated same-origin routes with inline headers, `no-store`, `nosniff`, same-origin framing, referrer protection, ownership checks, Open in New Tab, and Download fallbacks. No third-party viewer or public object URL is used.
- Adviser > Applicants is scoped before search, pagination, direct detail, export, or API access. An Applicant is visible only when created by that Adviser or when an application has been formally submitted to that Adviser; merely selecting an Adviser on a draft does not expose the Applicant.
- Advisers may declare an expected endorsement count. The shared statistics service provides declared expected, completed endorsements, received/awaiting endorsement, remaining expected, and not-yet-received counts to Adviser pages and authorized RES monitoring.

### Reviewer workspace, versions, and reassignment

- Reviewer Dashboard and Assigned Applications use current, non-superseded assignments. Revision Review is distinct from initial review. Completed means the latest applicable cycle has a final Approved result with no open revision.
- Review Comment, Review Worksheet, and Review Assessment are independent, initially closed accordions; multiple sections may remain open. Worksheet selection is inline rather than modal.
- Worksheet saves and completion are asynchronous and update only the worksheet interaction. The page and other expanded panels remain in place. The no-JavaScript server path remains authoritative.
- The Informed Consent gate shows only the applicability question initially. Yes requires all 15 dependent answers. No keeps them hidden/cleared and requires the comment/explanation path. The same conditional rule is enforced server-side.
- A Reviewer may edit and resubmit their own decision, comment evidence, and worksheets until RES releases the decision. Every submission creates an immutable `review_submission_versions` snapshot with hashes, form/comment/artifact evidence, submitter, and timestamp; current draft/change state is separate.
- Previous Versions and Comments are requirement accordions with document-version selection and only the signed-in Reviewer's own corresponding comments. Historical worksheet artifacts remain available through nested owner/RES-authorized routes.
- RES may replace Reviewers after initial or revision work starts. Removal immediately supersedes access and notifies the removed Adviser, but retains every draft/submission/version for audit. Remaining work is untouched. Eligibility is rechecked under lock and consensus uses only the current replacement set.

### Full Board consensus and decision release

- Reviewers remain blind to each other's identity, comments, forms, and decisions. Monitoring and Applicant feedback use anonymous Reviewer 1/2/3 labels where needed.
- Consensus evaluates the latest immutable submission version from every current assignment in the active cycle. Three agreeing Full Board decisions produce a persisted consensus signature. A disagreement records the conflicted state/time, notifies RES, receives red priority treatment, and sorts ahead of non-conflicted queue items.
- Both individual and bulk decision release call the same consensus gate. Split Full Board decisions cannot be released manually or in bulk. A later Reviewer resubmission automatically reevaluates and clears the conflict once all current decisions agree.
- Release stores exact source-version provenance, the consensus signature, and a frozen Applicant-visible feedback snapshot. Subsequent drafts cannot rewrite a released result.
- The Decision & Certificates queue contains only the three requested metrics and privacy-limited columns. Applicant identity is neither queried nor rendered before a certificate exists. The modal exposes Open Review Workspace and Release Decision through the same policy/transaction path.

### Certificate, backgrounds, signatory, and validity

- A released Approved decision with no remaining revision advances to `for_certificate_release` and automatically generates a Pending Certificate Release artifact. RES still controls the later certificate release/claim boundary.
- `issued_date` and `valid_until` are persisted on the certificate and each version. Validity is one calendar year using `addYearNoOverflow`, including a February 29 issue date becoming February 28 of the following non-leap year.
- Issued/released binaries are immutable. A background or signatory change affects future generation only and never regenerates or silently replaces an existing certificate.
- Background Management is under RES Settings with independent Certificate Background and Review Worksheet Background histories. Each has its own active type, private preview, validated PDF/JPEG/PNG upload, activation/reset, status, version sequence, audit trail, and future-output boundary.
- Generated official Reviewer worksheets record the selected review-background row and SHA-256 provenance. Certificates retain their certificate-background and signatory provenance.
- RES Profile supports an authorized printed signatory name and private transparent PNG signature. The server checks decoded PNG type, size, dimensions, transparency, and SHA-256 integrity. Previously issued PDFs remain unchanged.

### Review Monitoring, profiles, and security settings

- RES Review Monitoring now contains anonymous application/assignment metrics, current-cycle filters, overdue/due-soon states, Full Board conflict priority, permitted drill-down links, reviewer-enabled Adviser capacity cards, and Adviser endorsement-workload filters/table/timelines.
- Application progress projections omit Applicant identity and credentials. Reviewer workload may identify the staff Adviser to authorized RES users but never exposes review comments or other Reviewers' private content.
- Applicant, Adviser, and RES profiles render authenticated account data. Role-owned allowlists prevent forged role, entitlement, classification, capacity, account-status, and institutional-identifier changes.
- Applicant and Adviser Security & Privacy support username, email, and password changes. Email/password changes require the current password, rotate the remember token, revoke other database-backed sessions when supported, retain the current regenerated session, are rate-limited, and are audited without credentials.
- Reviewer capability, classification, and capacity remain RES-managed fields inside the Adviser profile; there is no separate Reviewer profile.

## Database migrations

Apply the migrations in timestamp order. As of the documentation pass on 2026-08-17, all seven are present but still reported **Pending** by the current local database.

| Migration | Purpose | Existing-data behavior | Down/rollback behavior |
| --- | --- | --- | --- |
| `2026_08_17_000000_add_reviewer_entitlement_to_users_table.php` | Reviewer entitlement, multi-classification JSON, entitlement index, reconciliation candidates | Converts legacy Reviewer rows in place and preserves IDs/FKs; records possible Adviser matches without merging | Refuses rollback after entitlement, classification, or reconciliation data exists |
| `2026_08_17_000100_create_reviewer_conflicts_table.php` | Persistent application/Adviser conflict exclusions | Additive; unique application/reviewer pair with declaration/clear attribution | Standard table rollback; back up populated conflict records before any rollback |
| `2026_08_17_000200_version_reviewer_submissions_and_consensus.php` | Immutable submission versions, draft/dirty state, soft-deleted comments, consensus, source-version/frozen-release provenance | Backfills every existing submitted review as version 1 and attaches current artifacts/releases | Refuses rollback when later versions or release provenance exist |
| `2026_08_17_000300_add_certificate_validity_dates.php` | `issued_date` and `valid_until` on certificates/versions | Backfills from existing generated/current versions with one-calendar-year validity | Refuses rollback while issued validity provenance exists |
| `2026_08_17_000400_add_background_provenance_to_review_form_artifacts.php` | Review worksheet background ID/hash | Additive nullable provenance for historical compatibility | Refuses rollback once an artifact references a background |
| `2026_08_17_010000_expand_applicant_survey_responses_for_questionnaire_v2.php` | Questionnaire version and optional suggestions/comments | Preserves legacy four-rating rows as version 1; new rows are version 2 | Standard column rollback; export populated v2 feedback before any rollback |
| `2026_08_17_020000_add_role_settings_fields.php` | Adviser expectations, RES signatory metadata, typed backgrounds | Additive fields; existing backgrounds default to certificate type | Refuses rollback after role settings or worksheet background data exists |

These migrations are intentionally forward-safe rather than promising a destructive automatic rollback after new provenance exists. Once production writes use the new schema, prefer a forward corrective migration. Never use `migrate:fresh`, `db:wipe`, or an unreviewed broad rollback.

## Rollout order

1. Take and verify a database and private-storage backup. Confirm the release contains all seven migrations and the matching application code.
2. Put the application into the team's normal controlled maintenance/deployment mode. Deploy PHP/Blade/JS/CSS together; do not serve new routes against the old schema.
3. Run `php artisan migrate --no-interaction`. Review reconciliation candidates; do not manually merge identities before the migration completes.
4. Clear/cache configuration and Blade views according to the deployment procedure, then run the Vite production build or deploy its reviewed artifact.
5. In RES User Management, resolve every pending identity candidate as Merge or Keep Separate. Then review each entitled Adviser's classifications, capacity, conflict records, and active assignments.
6. In RES Settings, review signatory data, Certificate Background, Review Worksheet Background, Dropdown Options, and deadline configuration. Existing issued certificates must resolve to their prior immutable version.
7. Smoke-test one Applicant, one Adviser without Reviewer access, one enabled Adviser Reviewer, and one RES Lead. Confirm legacy Reviewer login is rejected, the legacy URL cannot bypass entitlement, private files remain protected, and a Full Board conflict blocks both release paths.
8. Only after focused/full automated checks and local responsive acceptance pass should the batch be considered release-ready.

## Security and privacy invariants

- Hidden UI is never the authorization boundary. Reviewer entitlement, role, current assignment, nested parent ownership, workflow status, deadline, and release consensus are checked server-side.
- Applicant identity is not selected for Reviewer workspaces or pre-certificate Decision & Certificates/monitoring projections. Reviewer identity is never Applicant-visible; released Full Board feedback uses anonymous labels.
- New uploads, historic documents, generated worksheets, backgrounds, signatures, certificates, and import previews remain on private storage and are delivered only through authenticated, policy-authorized routes with non-cacheable defensive headers.
- Spreadsheet parsing is offline and value-only. External targets and formulas are never resolved or evaluated.
- Reassignment, resubmission, result release, and certificate release preserve immutable source/version provenance. No active workflow deletes prior Reviewer evidence or issued certificate binaries.
- Audit metadata excludes passwords, reset/setup tokens, private paths, workbook contents, worksheet answers, comment bodies, and other confidential payloads.
- Self-service account writes are target-fixed to the authenticated user and field allowlisted. Security-sensitive changes require current-password verification where specified and are rate-limited.

## Intentional supersession of older contracts

| Older documented behavior | Finale contract |
| --- | --- |
| Separate `reviewer` login/account/profile and `/reviewer` primary area | Adviser account plus `reviewer_enabled`; `/adviser/reviewer` is canonical |
| RES could create/import a Reviewer account | RES grants or hides Reviewer capability on an active Adviser |
| Overall Reviewer submission permanently froze all work | Reviewer may resubmit their own versioned evidence until RES release |
| Split Full Board decisions could be released manually | Persisted conflict blocks individual and bulk release until consensus |
| Workbook had to originate from the exact generated template/fingerprint | Any bounded structurally valid `.xlsx` with recognized required headers is accepted |
| External workbook metadata caused blanket rejection | Inert metadata is accepted; targets remain unresolved/unfetched |
| New Applicant Office uploads were permitted | Only PDF and safe raster images are accepted for new uploads |
| Certificate page managed one certificate background and retroactively regenerated issued files | Settings manages two future-only background types; issued binaries remain unchanged |
| Approved release used the generic accepted/result state before later generation | Approved/no-revision release enters `for_certificate_release` and auto-generates a pending artifact |
| Worksheet chooser was modal and individual completion refreshed/finalized the interaction | Chooser is inline; worksheet save/complete is asynchronous; versioned overall resubmission remains possible until release |
| Applicant feedback and revision were separate containers | One requirement/version accordion owns released feedback and replacement upload |
| Four legacy evaluation ratings | Versioned ten-question 1-5 questionnaire plus optional comments and anonymous RES aggregates |

The older guides retain historical value, but this file governs these changed behaviors.

## Automated evidence

New focused coverage includes:

- `tests/Feature/Auth/ReviewerEntitlementFoundationTest.php`
- `tests/Feature/Identity/AdviserReviewerEntitlementTest.php`
- `tests/Feature/Identity/ReviewerIdentityReconciliationTest.php`
- `tests/Feature/Dashboard/AdviserReviewerCapabilityTest.php`
- `tests/Feature/Dashboard/AdviserApplicantScopeAndProfileTest.php`
- `tests/Feature/Dashboard/ApplicationDetailPresentationTest.php`
- `tests/Feature/Dashboard/ApplicantRevisionPresentationTest.php`
- `tests/Feature/Dashboard/ReviewConsensusWorkflowTest.php`
- `tests/Feature/Dashboard/ReviewerReassignmentWorkflowTest.php`
- `tests/Feature/Dashboard/ResReviewMonitoringTest.php`
- `tests/Feature/Settings/RoleAccountSettingsTest.php`
- `tests/Feature/Settings/ResAssetSettingsTest.php`

Existing account/import, Applicant workflow, Reviewer workflow/artifact, dashboard/navigation, certificate/release, and RES queue tests were updated for the new contract. Reported focused runs completed for the role/settings, Adviser scope/profile, reassignment, and related combined slices. Route discovery currently succeeds with 157 routes; Blade caching and a Vite production build have also completed during integration.

## Verification still required before release sign-off

- Run the complete Laravel suite once after all parallel integration activity stops. Any final focused repair run must precede, not replace, that full pass.
- Run repository-wide Pint, strict Composer validation, Composer platform requirements, the final route listing, Blade compilation, Vite production build, and `git diff --check` against the settled tree.
- Apply the migrations to an approved local/staging database and confirm the second migrate run is empty. The current local status still shows the seven August 17 migrations as Pending; no claim of migration execution is made here.
- Complete authenticated local browser checks at 1440, 1280, 1024, 768, and 390 pixels. Include keyboard/focus behavior, Adviser Reviewer submenu, monitoring filters/tables, settings tabs/dialogs, requirement/workspace accordions, asynchronous worksheet save/error/success, release dialogs, and contained scrolling.
- Open a generated/imported workbook in supported desktop Excel and complete one local round trip. Visually inspect both official worksheet types on the configured Review Worksheet Background and an auto-generated certificate on the configured Certificate Background/signatory.
- Reconfirm the existing locked dependency advisories before deployment and follow the team's approved dependency-update process separately.

## Genuine remaining limitations

- Automated identity detection/redaction inside Applicant-authored PDF/image content is not implemented. Operational anonymization remains necessary even though database identity fields are excluded from blind views.
- Automated side-by-side visual document comparison is not implemented; authorized users compare immutable versions through the requirement/version selectors.
- Legacy `.xls` import is not supported. `.xlsx` is the only enabled account workbook format.
- Historic Office files retain the authenticated fallback/download path; ECRATS does not use a third-party Office viewer. New Office uploads are rejected.
- Public QR/control-number certificate verification remains outside this batch because an approved public metadata and visual-placement contract is still absent. Full certificate access stays authenticated and private.
- Production hosting, mail delivery, backups/restores, monitoring infrastructure, and deployment acceptance remain operational work rather than repository implementation.

## Local-data handling confirmation

This implementation/documentation pass used local repository files, local database metadata, and local test/build commands only. No project data, credentials, database contents, uploaded documents, screenshots, or private system information was intentionally published or sent to an external service.
