# Database Design

This design uses the module-based ERD reference as an input, then aligns it with Laravel conventions and the latest ECRATS requirements. It is not a migration script.

## Key Implementation Decisions

- Use Laravel-standard `id` primary keys for new tables unless the team explicitly approves custom primary key names.
- Keep foreign keys descriptive, such as `applicant_user_id`, `adviser_user_id`, and `generated_by_user_id`, all referencing `users.id`.
- Keep migrations physically flat in `database/migrations`.
- Use PHP enums or backed enum-like constants for statuses and decision values once implementation begins.
- Use private storage paths for uploaded documents, receipt images, anonymized files, and certificates.
- Use a no-hard-delete posture for audit-sensitive records. Add `deleted_at` only where administrative hiding/recovery is useful and legally safe; do not soft-delete audit logs.

## Reference ERD Analysis

The supplied ERD draft has 39 entities across 10 modules:

1. Account creation and role management
2. Academic and research reference
3. Application and group management
4. Requirements, documents, and anonymization
5. Timeline and deadline configuration
6. Payment, adviser endorsement, and monitoring
7. RES screening, reviewer assignment, and capacity
8. Blind review, decision release, and revision
9. Feedback, certificate, and QR access
10. Notifications, Regala, configuration, and audit

The structure is broadly aligned with the project plan. Confirmed additions to carry forward before migration work:

- Add `exempted` review type support.
- Add `disapproved` decision/status support. Treat older `rejected` wording as equivalent to `disapproved`.
- Add reviewer conflict declaration as a gate before full blind-review access.
- Add public-safe QR/control-number verification plus protected full certificate access.
- Add RES-controlled anonymization approval before reviewer-visible document access.
- Apply a soft-delete/no-hard-delete policy where records may need hiding without destroying history.

Implementation convention adjustments:

- Convert custom PK names like `application_id` to Laravel `id` unless the team chooses otherwise.
- Rename generic implementation models: `applications` maps to `ResearchApplication`, `documents` maps to `ApplicationDocument`, and `reviews` maps to `EthicsReview`.

## Proposed Migration Order

1. Extend `users` for ECRATS roles and account status, or add companion role/profile tables while preserving Laravel authentication compatibility.
2. Create role profile tables: `student_profiles`, `faculty_profiles`, `adviser_profiles`, `reviewer_profiles`, `res_admin_profiles`.
3. Create academic reference tables: `institutes`, `programs`, `research_types`.
4. Create requirements table: `document_requirements`.
5. Create application tables: `research_applications`, `application_members`.
6. Create document tables: `application_documents`, `anonymized_documents`.
7. Create timeline tables: `deadline_configurations`, `timeline_calendar_events`.
8. Create adviser tables: `payment_verifications`, `endorsements`, `adviser_monitoring_records`.
9. Create RES screening and assignment tables: `screenings`, `reviewer_assignments`, `reviewer_capacity_snapshots`.
10. Create review tables: `ethics_reviews`, `review_comments`, `review_worksheet_responses`.
11. Create release and revision tables: `decision_release_batches`, `decision_release_batch_items`, `revision_cycles`.
12. Create feedback tables: `feedback_forms`, `feedback_questions`, `feedback_responses`, `feedback_answers`.
13. Create certificate tables: `certificates`, `certificate_verification_logs`.
14. Create communication and audit tables: `notifications`, `regala_message_templates`, `regala_message_logs`, `system_configurations`, `audit_logs`.
15. Add final indexes, uniqueness constraints, and any cross-module foreign keys that need delayed ordering.

## Core Tables

### users

Use Laravel's existing `users` table as the authentication base. Add ECRATS fields only through reviewed migrations.

Candidate additions:

- `first_name`
- `middle_name`
- `last_name`
- `role`
- `account_status`
- `created_by_user_id`
- `last_login_at`

### research_applications

Represents one ethics application from a student group leader or faculty researcher.

Important fields:

- `application_code`
- `applicant_user_id`
- `adviser_user_id`
- `applicant_type`
- `research_type_id`
- `institute_id`
- `program_id`
- `research_title`
- `abstract`
- `application_status`
- `review_type`
- `completeness_status`
- `current_revision_cycle`
- `submitted_at`

Review type should include `expedited`, `full_board`, and `exempted`.

### application_documents

Stores initial and revised document metadata, never raw file contents.

Important fields:

- `research_application_id`
- `document_requirement_id`
- `uploaded_by_user_id`
- `revision_cycle_id`
- `original_file_name`
- `stored_file_path`
- `mime_type`
- `file_size_bytes`
- `document_version`
- `validation_status`
- `is_current`
- `uploaded_at`

### ethics_reviews

Stores reviewer decisions and held/released state.

Important fields:

- `reviewer_assignment_id`
- `research_application_id`
- `revision_cycle_id`
- `review_status`
- `review_decision`
- `overall_comment`
- `submitted_at`
- `held_at`
- `released_at`

Decision values should include `accepted`, `minor_revision`, `major_revision`, and `disapproved`. Use `disapproved` as the canonical system label and map older `rejected` wording to it.

### certificates

Stores certificate metadata and private file path.

Important fields:

- `research_application_id`
- `feedback_response_id`
- `control_number`
- `qr_token`
- `certificate_file_path`
- `certificate_status`
- `issued_at`
- `released_at`
- `valid_until`
- `generated_by_user_id`
- `revoked_at`
- `remarks`

Public QR/control-number verification should expose only approved certificate metadata. Full certificate viewing or downloading must remain authenticated and authorized.

## Index and Constraint Notes

- Unique: user email, profile employee/student numbers, application code, control number, QR token.
- Composite uniqueness: one current document per application/requirement/revision context.
- Index statuses used by queues: application status, review status, assignment status, certificate status.
- Index deadline fields used by monitoring dashboards.
- Store audit affected entity as a logical/polymorphic reference, not a foreign key to every workflow table.
- Do not hard-delete audit-sensitive workflow records. Prefer status fields, archive states, and selective `deleted_at` columns where the team needs administrative hiding or recovery.

## Confirmed Additions Reflected in the Database Plan

- `review_type` includes `exempted`.
- Review decisions include `disapproved`.
- Reviewer assignments include conflict declaration fields.
- Anonymized documents include preparation and approval tracking by RES-authorized users.
- Certificates support public-safe verification logs and protected full access.
- Audit-sensitive records follow a no-hard-delete posture.

## Details Still Needed Before Migrations

- Should the team keep Laravel default `users.id` or convert to custom `user_id` primary keys? Recommendation: keep `id`.
- Exact public certificate metadata fields allowed in QR/control-number verification.
- Exact certificate control-number format.
- Exact table-by-table `deleted_at` list.
