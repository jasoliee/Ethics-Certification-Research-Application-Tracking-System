# Reviewer Workflow

## Implemented Boundary

ECRATS implements the assignment-owned Ethics Reviewer workflow from current assignment access through a submitted recommendation awaiting RES release. The interface follows the Reviewer portion of the local high-fidelity reference and the official `KLD-RES-04-001` Protocol Review Worksheet and `KLD-RES-04-002` Informed Consent Checklist.

This boundary does not release reviewer work to Applicants, produce the consolidated RES decision, generate certificates, or redact identities embedded inside arbitrary uploaded file content.

## Assignment Access and Replacement

`GET /reviewer/assignments` is always scoped to `reviewer_user_id = authenticated user`. Direct access to another Reviewer's assignment is denied.

Only the current, non-superseded assignment grants access. There is no Reviewer conflict-declaration gate. When RES changes the reviewer set before final release, removed assignments are superseded rather than deleted, access ends immediately, and replacement links, actor, reason, prior status, and submitted work remain available for audit history.

## Blind Workspace

The workspace loads only research fields needed for ethics review. Applicant and Adviser account names, email addresses, institutional identifiers, and profile relationships are not selected for the Reviewer view. Reviewer identity is not rendered in Applicant routes.

Current application documents remain on the private `local` disk. Preview and download require:

- the Reviewer role;
- ownership of an assignment for the parent application;
- a document that belongs to the route-bound application.

PDF and image files may stream inline. Word and Excel files use the existing authorized no-store fallback with a protected download. Uploaded content itself may still contain typed names or other identity; ECRATS does not claim content-level anonymization until an approved redaction procedure exists.

## Official Forms

Each assignment has at most one persisted row per form type. The server owns all question keys and allowed answers; browser-supplied labels or unknown keys are rejected.

`KLD-RES-04-001` contains 15 protocol questions with `Yes`, `No`, or `Unable to Assess` answers.

`KLD-RES-04-002` contains an informed-consent applicability gate and 15 checklist questions with `Yes` or `No` answers. When consent is not required, the Reviewer must provide an explanation of at least 10 characters and the item answers are not required.

Both forms support independent Draft and Final states and one recommendation:

- Approved;
- Minor Revision;
- Major Revision; or
- Disapproved.

Finalization requires every applicable answer and a recommendation. A non-Approved recommendation also requires comments of at least 10 characters. A finalized form cannot be edited.

## Comments

An active Reviewer may add comments scoped to:

- the overall application;
- one current or historical document belonging to the assigned application; or
- a page number within such a document.

Categories are General Comment, Clarification, and Required Revision. Bodies are 3 to 2,000 characters. Page comments require a page number from 1 to 10,000. The assignment owner may add, edit, resolve, reopen, or remove comments before submission while the Reviewer Submission period is open. Resolution changes are recorded in immutable status history.

`review_comments.released_at` is reserved for the later RES release operation. Current Applicant pages do not query or render reviewer comments.

## Decision Submission

A Reviewer may save a decision draft at any time the assignment is writable. Final submission requires:

- an active Pending, In Review, or Revision Review assignment;
- an open Reviewer Submission process;
- both official forms in Final state;
- one approved decision value; and
- a final decision comment of 10 to 2,000 characters.

Submitting writes the one-per-assignment `review_submissions` row, changes the assignment to `decision_submitted`, records `submitted_at`, and freezes further forms, comments, and decisions.

For an initial review cycle, the application moves to `review_submitted_pending_release` and `decision_release` only after every assignment in that cycle has submitted. Active RES Leads then receive a neutral notification. No Applicant notification or result visibility is created at this boundary.

## Deadline Behavior

Reviewer writes use the configured `reviewer-submission` process for the active term and fail closed when the process is missing, upcoming, explicitly closed, expired, or outside the active term. Explicit On follows the shared deadline override rules.

Read-only assignment, workspace, form, comment, and decision state remains visible to the owning Reviewer outside the write period. New assignments copy the current Reviewer Submission due date into `reviewer_assignments.review_deadline_at` when a matching configuration exists.

## Persistence

- `reviewer_assignments` preserves assignment sequence, replacement links, supersession actor/time/reason, and prior status; retired conflict fields are removed by a forward migration.
- `review_submissions` stores one draft/submitted overall decision per assignment.
- `review_form_submissions` stores one Draft/Final row per assignment and official form type, including bounded JSON responses, recommendation fields, and immutable catalog/payload snapshots on finalization.
- `review_comments` stores assignment-owned comments with an optional nested document, scope, category, page, resolution state, and future release timestamp; `review_comment_status_changes` preserves resolution history.

Foreign keys cascade review records when an assignment is deleted. A deleted document sets the comment document reference to null without deleting the comment. Unique constraints prevent duplicate overall submissions or duplicate form types per assignment.

## Authorization and Concurrency

Reviewer routes use role middleware, `ReviewerAssignmentPolicy`, Form Requests, CSRF protection, and the `reviewer-workflow` rate limit. The service locks the parent application and assignment before repeating policy checks. Final application projection locks the application and assignment set so concurrent Reviewer submissions cannot publish an incomplete state.

## Audit Boundary

The following actions are recorded:

- `review.form_draft_saved` and `review.form_finalized`;
- `review.comment_added`, `review.comment_updated`, `review.comment_status_changed`, and `review.comment_removed`;
- `review.decision_draft_saved` and `review.decision_submitted`.

Metadata is limited to assignment/comment IDs, form type/status, comment scope/status, decision code, all-submitted state, and resulting application status. Form answers, comments, rationale text, filenames, document content, and private paths are excluded.

## Remaining Limitations

- No approved automatic/manual document-content redaction or anonymized-copy generation.
- No Applicant revision comparison or Reviewer re-review implementation.
- No consolidated Full Board decision, official result release, Applicant feedback view, certificate generation, or QR verification.
