# Database and Data Flow

## Account Data

`users` retains split names plus a generated compatibility `name`. Account identity includes unique username, email, institutional identifier, role, optional applicant type, role-specific fields, creator, status, setup/onboarding timestamps, email-delivery state, and soft deletion.

`profile_options` stores normalized, active/inactive shared values for Year Level, Institution, Department, Program, and Reviewer Classification with ordering and creator attribution. Its primary key is the stable option identity. `profile_option_aliases` preserves prior visible labels against that identity and enforces one normalized label owner per field. User profile fields remain canonical strings so renamed or deactivated catalog values do not silently rewrite historical records. Reviewer classification is likewise stored as a bounded string rather than a PHP enum.

The additive onboarding migration backfills existing users as already set up so a deployment does not lock out established accounts. Fresh seeders explicitly mark their active test/admin accounts the same way.

## Account Creation Flow

```text
Form Request -> policy/creation authority -> UserAccountService transaction
-> generated username + hashed random credential -> audit
-> password broker token -> notification -> delivery state
-> user sets password -> active account -> onboarding
```

## Bulk Flow

```text
private .xlsx upload -> bounded OOXML and exact-contract validation
-> active option ID/current-label/historical-alias resolution
-> canonical active-option validation -> batched conflict and username lookup
-> categorized preview with valid, active-existing, and archived rows in actor-scoped JSON
-> optional RES-only restoration of preview-matched original archived rows
-> atomic single-use confirmation -> short transaction per valid account
-> setup notifications after database writes
```

No account rows are written during preview. Invalid, later duplicate, active-existing, archived, and restored-account rows are excluded from the confirmation payload. Restoration clears soft deletion on the original conflict-free row and preserves its ID and relationships. Source uploads are deleted after parsing. Preview payloads contain no password or reset token and expire after 30 minutes.

## Application Submission Flow

```text
owner policy -> unique editable draft -> validated information
-> private versioned requirement uploads -> shared completion calculation
-> configured submission-window + formal-slot check -> locked final revalidation
-> submitted_to_adviser + adviser_review + timestamps
-> Adviser database notification + audit
-> assigned Adviser deadline/completeness revalidation
-> returned_by_adviser for correction OR adviser_endorsed + res_screening
-> Applicant notification + endorsement history + audit
-> RES expedited/full_board/exempted classification with locked mandatory-document readiness
-> exact eligible reviewer assignment OR exempted direct-release boundary
-> conflict-cleared Reviewer workspace + two final official forms + final decision
-> every initial assignment submitted -> review_submitted_pending_release
```

`research_applications` stores the nullable unique `draft_owner_user_id`, optional academic-term link, research type/category, institution, department, program, abstract, target participants, nullable legacy duration text, expected start/end dates, formal submission time, revision cycle, and current stage/status. Releasing `draft_owner_user_id` at submission allows a later new draft while keeping the submitted application. The three-application limit counts non-null formal submission timestamps, not draft rows.

`document_requirements` stores whether a requirement is mandatory and an optional JSON list of applicable research types. `application_documents` retains version rows and a single current pointer per requirement/application pair. Files remain on private storage and are authorized through their parent application.

`academic_terms` provides current semester/year boundaries. Deadline rows and timeline events can reference one term; deadline updates synchronize both sets in one transaction. `endorsements` records each initial Adviser return/endorsement with the assigned Adviser, decision, controlled return reason, remarks, and returned/endorsed timestamp.

`application_screenings` stores one current RES decision per application through a unique foreign key. It records the latest RES actor, bounded administrative states and confirmations, optional notes, Expedited/Full Board/Exempted classification, required reason, and classification time. Corrections update this row under locks and write a separate bounded audit event. Compatible assignments remain; only pending unstarted incompatible assignments may be removed. `research_applications.review_type`, status, and stage remain the queue-optimized workflow projection.

`reviewer_assignments` remains the assignment source of truth. Its `review_type` column distinguishes `initial_review` from later `revision_review`; the application's `review_type` independently stores Expedited or Full Board classification. Assignment sequence and supersession links preserve non-destructive replacement history while the current-set index supports active access and capacity checks. The retired assignment-level conflict fields are removed.

`review_submissions` is one-to-one with an assignment and stores Draft or Submitted state, one bounded decision code, a private decision comment, and submission time. `review_form_submissions` is unique by assignment/form type and stores Draft or Final official-form state, server-validated JSON answers, consent applicability, recommendation, comments, review date, finalization time, and immutable catalog/payload/context snapshots. `review_form_artifacts` stores append-only private PDF versions, Ready/Superseded state, generator/template metadata, hashes, size, and generation time. `review_comments` belongs to one assignment, optionally references an application document, and stores bounded overall/document/page comments plus a nullable future release time.

Reviewer final submission locks the application, both form snapshots, comments, and assignments for the same review cycle. It generates both versioned official PDFs from persisted data and commits only if both succeed. The assignment becomes `decision_submitted`; only when every assignment in that cycle is submitted does the application project to `review_submitted_pending_release` and `decision_release`. Reviewer comments, forms, decisions, and artifacts are not copied to Applicant-visible tables.

## Audit Data

`audit_logs` stores nullable actor/subject references, action, JSON metadata, IP, user agent, and creation timestamp. Metadata is recursively sanitized and bounded. It must exclude credentials, reset/setup tokens, private file contents, raw import rows, cookies, sessions, authorization headers, CSRF values, and API keys.

Refer to `docs/architecture/database-design.md` for the broader planned ERD. The current migrations implement only part of that design.
