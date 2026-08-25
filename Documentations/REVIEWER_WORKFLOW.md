# Reviewer Workflow

## Implemented Boundary

ECRATS implements the assignment-owned Ethics Reviewer workflow from current assignment access through a submitted recommendation awaiting RES release. The interface follows the Reviewer portion of the local high-fidelity reference and the official `KLD-RES-04-001` Protocol Review Worksheet and `KLD-RES-04-002` Informed Consent Checklist.

This boundary does not release reviewer work to Applicants, produce the consolidated RES decision, generate certificates, or redact identities embedded inside arbitrary uploaded file content.

## Assignment Access and Replacement

`GET /reviewer/assignments` is always scoped to `reviewer_user_id = authenticated user`. Direct access to another Reviewer's assignment is denied.

Only the current, non-superseded assignment grants access. There is no Reviewer conflict-declaration gate. When RES changes the reviewer set before final release, removed assignments are superseded rather than deleted, access ends immediately, and replacement links, actor, reason, prior status, and submitted work remain available for audit history.

## Reviewer Navigation

The Reviewer sidebar intentionally contains only Home and Assignments. Review is omitted because its owner-scoped task views overlap Assignments, Notifications is reached through the top-bar bell, and Settings is reached through the avatar/profile menu. The protected `reviewer.reviews.index`, `reviewer.notifications.index`, and `reviewer.settings.*` routes remain registered for dashboard cards, menus, bookmarks, and authorized direct access; removing sidebar links does not relax or remove their role middleware.

## Blind Workspace

The workspace loads only research fields needed for ethics review. Applicant and Adviser account names, email addresses, institutional identifiers, and profile relationships are not selected for the Reviewer view. Reviewer identity is not rendered in Applicant routes.

The responsive workspace follows high-fidelity pages 54-69 with a private document library, the selected-document viewer, and a rail ordered as Review Comment, Review Worksheet, and Review Assessment. A compact centered summary stays above the studio. On narrow screens the panes stack, while each wide document or table region remains internally bounded. The official form dialogs show only Title of the Study, Application Code, Type of Review, and Date Received; Institute, Reviewer, and Researcher / Study Leader are intentionally omitted from the modal interface.

Current application documents remain on the private `local` disk. Preview and download require:

- the Reviewer role;
- ownership of an assignment for the parent application;
- a document that belongs to the route-bound application.

The interface displays the stored file name/type and uses same-origin, policy-authorized routes. PDF and browser-safe image files may stream inline. `.doc`, `.docx`, `.xls`, and `.xlsx` files use the authorized no-store viewer fallback with a protected download because the repository has no approved local Office-to-browser renderer. Remote Office/Google viewers are not used because they would disclose private file contents to a third party. Uploaded content itself may still contain typed names or other identity; ECRATS does not claim content-level anonymization until an approved redaction procedure exists.

Inline PDF/image responses use a non-sandboxed CSP limited to `default-src 'none'`, same-origin frame ancestors, and no base URI so native browser PDF viewers can render inside the authorized workspace iframe. They also retain `SAMEORIGIN`, `nosniff`, private `no-store`, no-referrer, and restricted browser-feature headers. The Office fallback keeps its separate HTML CSP and does not expose a public URL.

## Official Forms

Each assignment has at most one persisted row per form type. The server owns all question keys and allowed answers; browser-supplied labels or unknown keys are rejected.

`KLD-RES-04-001` contains 15 protocol questions with `Yes`, `No`, or `Unable to Assess` answers.

`KLD-RES-04-002` contains an informed-consent applicability gate and 15 checklist questions with `Yes` or `No` answers. When consent is not required, the Reviewer must provide an explanation of at least 10 characters; dependent answers are cleared, hidden, disabled, and generated as `N/A`. When consent is required, every dependent answer is required. Consent items have no individual comment inputs.

Both forms support independent Draft and Completed states before the overall decision and one recommendation:

- Approved;
- Minor Revision;
- Major Revision; or
- Disapproved.

The Reviewer interface presents those persisted states as `Not Started`, `In Progress`, and `Completed`. Saving an incomplete worksheet draft changes its display to In Progress; submitting a valid worksheet changes it to Completed and records `completed_at`. Completed worksheets remain editable until overall review submission.

Worksheet completion requires every applicable answer, a recommendation, and a recommendation comment containing at least 15 non-whitespace characters. Overall review submission then finalizes both Completed worksheets together and stores immutable catalog, payload, context, and authenticated-attestation snapshots. After that boundary, the forms cannot be edited.

When the overall review is submitted, both official PDFs are generated together from the persisted form snapshots. Each artifact preserves the official template pages and mappings, then adds a branded continuation record containing the persisted overall decision, decision comment, submitted timestamp, and the complete assignment-comment record. Generation is atomic with submission: if either PDF fails, partial files and rows are removed, both forms remain Completed and editable, and the review remains unsubmitted. Existing artifact rows are never overwritten; a new version supersedes the prior Ready version while retaining its private file and audit metadata.

## Comments

An active Reviewer may add comments for:

- the overall application through `Entire Application`; or
- one current document belonging to the assigned application.

Categories are General Comment, Clarification, and Required Revision. Bodies are 3 to 2,000 characters. The assignment owner may add, edit, resolve, reopen, or remove comments before submission while the Reviewer Submission period is open. Resolution changes are recorded in immutable status history, and open comments are labeled `Unresolved` in the interface.

The comment form progressively enhances to asynchronous requests. Create, edit, resolve/reopen, and confirmed remove operations request JSON, keep the document pane in place, render the server-escaped comment item immediately, and expose loading, success, validation-error, request-error, and empty-list states through an accessible live region. Explicit request guards prevent duplicate composer or row-action submissions. The newest 20 comments load initially inside a bounded scroll area; an assignment-authorized cursor endpoint incrementally loads older server-rendered items while the total count remains authoritative. Normal form submissions remain the fallback when JavaScript is unavailable. The server remains authoritative: the asynchronous path uses the same Form Requests, nested assignment/document ownership, CSRF token, deadline rules, service locks, and audit events as the fallback path.

`review_comments.released_at` is reserved for the later RES release operation. Current Applicant pages do not query or render reviewer comments.

## Decision Submission

A Reviewer may save a decision draft at any time the assignment is writable. Final submission requires:

- an active Pending, In Review, or Revision Review assignment;
- an open Reviewer Submission process;
- both official forms in Completed state;
- one approved decision value; and
- a final decision comment of 10 to 2,000 characters.

Submitting writes the one-per-assignment `review_submissions` row, generates and versions both verified private official artifacts, changes the assignment to `decision_submitted`, records `submitted_at`, and freezes further forms, comments, and decisions. RES may list/preview/download only the current Ready artifacts after this submitted state; Applicant and Adviser routes expose none.

With JavaScript available, final submission first validates the two Completed worksheets, selected decision, and 10-character decision comment, then opens a focus-contained confirmation dialog showing the selected decision and an irreversible-action warning. Cancel, Escape, and backdrop dismissal restore focus before a request begins. Confirm submits once through the same protected endpoint, locks dialog controls while pending, and shows safe validation/request errors or a success result with a return link. Save Draft does not open this dialog. Without JavaScript, the normal form POST remains available and the same Form Request, policy, deadline, transaction lock, and service checks remain authoritative.

For an initial review cycle, the application moves to `review_submitted_pending_release` and `decision_release` only after every assignment in that cycle has submitted. Active RES Leads then receive a neutral notification. No Applicant notification or result visibility is created at this boundary.

## Deadline Behavior

Reviewer writes use the configured `reviewer-submission` process for the active term and fail closed when the process is missing, upcoming, explicitly closed, expired, or outside the active term. Explicit On follows the shared deadline override rules.

Read-only assignment, workspace, form, comment, and decision state remains visible to the owning Reviewer outside the write period. New assignments copy the current Reviewer Submission due date into `reviewer_assignments.review_deadline_at` when a matching configuration exists.

## Persistence

- `reviewer_assignments` preserves assignment sequence, replacement links, supersession actor/time/reason, and prior status; retired conflict fields are removed by a forward migration.
- `review_submissions` stores one draft/submitted overall decision per assignment.
- `review_form_submissions` stores one Draft/Completed/Final row per assignment and official form type, including bounded JSON responses, recommendation fields, `completed_at`, and immutable catalog/payload/context snapshots only at overall finalization.
- `review_form_artifacts` stores append-only private PDF versions, Ready/Superseded state, template and generator versions, file/template hashes, size, name, and generation time.
- `review_comments` stores assignment-owned comments with an optional nested document, scope, category, page, resolution state, and future release timestamp; `review_comment_status_changes` preserves resolution history.

Foreign keys cascade review records when an assignment is deleted. A deleted document sets the comment document reference to null without deleting the comment. Unique constraints prevent duplicate overall submissions or duplicate form types per assignment.

## Authorization and Concurrency

Reviewer routes use role middleware, `ReviewerAssignmentPolicy`, Form Requests, CSRF protection, and the `reviewer-workflow` rate limit. The service locks the parent application and assignment before repeating policy checks. Final application projection locks the application and assignment set so concurrent Reviewer submissions cannot publish an incomplete state.

## Audit Boundary

The following actions are recorded:

- `review.form_draft_saved` and `review.form_completed`;
- `review.comment_added`, `review.comment_updated`, `review.comment_status_changed`, and `review.comment_removed`;
- `review.decision_draft_saved` and `review.decision_submitted`.

Metadata is limited to assignment/comment IDs, form type/status, comment scope/status, decision code, all-submitted state, and resulting application status. Form answers, comments, rationale text, filenames, document content, and private paths are excluded.

## Remaining Limitations

- No approved automatic/manual document-content redaction or anonymized-copy generation.
- No privacy-preserving Word/Excel content renderer is installed; Office files retain authorized fallback and download behavior rather than being sent to a third-party viewer.
- No automated side-by-side document diff. Reviewers instead receive authorized read-only prior versions beside current replacement files.
- No public QR verification. Explicit result release, Applicant feedback, re-review, and private certificate generation/claim are implemented.

## Revision re-review continuation (August 11, 2026)

Applicant revisions route directly to the same current Reviewer set through linked `revision_review` assignments; they do not repeat Adviser or initial RES screening. The `reviewing-revision-period` is enforced independently of the initial Reviewer deadline. In a revision workspace, the Reviewer sees current replacement files plus bounded, read-only earlier versions and only that Reviewer's earlier comments/submission. Completion remains cycle-aware: the application waits for every current assignment in the same `review_cycle` before entering authorized RES release.
