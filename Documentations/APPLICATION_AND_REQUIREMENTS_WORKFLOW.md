# Application and Requirements Workflow

## Implemented Through Initial Reviewer Submission

The repository implements Applicant-owned application information, private versioned requirement documents, shared completion calculation, configured submission-window enforcement, formally submitted Adviser visibility, the assigned Adviser's initial return/endorsement decision, RES ethics classification, exact current reviewer assignment, non-destructive reassignment, and initial Reviewer submission awaiting later RES release.

## Draft Boundary

Each Applicant has at most one editable draft slot, enforced by the unique nullable `draft_owner_user_id`. Repeated Start requests converge on that record. Draft information is visible only to its owner and authorized RES Leads; it is excluded from Adviser queries.

Saving validated information advances the working stage to `document_submission`. An Adviser-returned initial submission may re-enter the editable boundary while preserving its original `submitted_at`, formal slot, and revision-cycle number.

## Information Validation

Create, update, and final submission share one server-side contract. Required information includes research title, Thesis or Capstone type, research category, institution, department, Research Adviser, abstract, target participants, Starting Date, and Ending Date. Program is required for Student Researchers and optional for Faculty Researchers. Ending Date cannot precede Starting Date.

Institution, Department, and Program values come from active profile options while preserving an already stored historical value. The selected Adviser must be active, unarchived, have the Adviser role, and have completed account setup.

Legacy applications may retain their previous `expected_duration` text when no date pair exists. New and edited information uses the date pair; ECRATS does not invent dates from old prose.

## Requirements and Documents

Only active requirements applicable to the application's research type appear. The shared calculation counts applicable mandatory requirements:

```text
completion percentage = floor(completed mandatory requirements / mandatory requirements * 100)
```

No configured mandatory requirements is a blocking state. Missing, pending, and rejected mandatory requirements prevent submission. Optional requirements do not reduce the completion percentage.

PDF, DOC, DOCX, XLS, XLSX, JPEG, and PNG files up to 100 MB are accepted after server MIME inspection. Files use randomized names on the private `local` disk. Replacement creates a new current record, marks the prior record non-current, and retains both database and private-file history. Applicants may upload one file at a time or process several selected requirement files through the bounded Upload All endpoint. Successful asynchronous uploads immediately update the completion meter, counts, final checklist, and submit state. The page refreshes only after all requirements are ready and no selected browser file would be lost.

Document icons are selected from stored MIME type rather than filename text. PDF and image content stream inline through authorized controllers. Word and Excel documents open the same private viewer route and receive a no-store, same-origin fallback with an authorized download when the browser cannot render the format.

## Submission Window

The server resolves the highest-priority active `deadline_configurations` row whose key ends in `application-submission`, belongs to the current active term when terms are configured, and targets Applicants or all roles. A future `starts_at` is upcoming, a past `due_at` is closed, and an absent configuration fails closed. Explicit `On` or `Off` overrides the process date range; changing configured dates clears the override. UI state never replaces this server check.

The baseline seeder creates four mandatory requirements but intentionally creates no dated submission window. RES must enter approved opening and due dates before formal submission can succeed.

## Formal Transition

Only the owning Applicant may submit an editable application. Inside a transaction with a row lock, the service rechecks policy authorization, the configured window, the three-formal-application limit, saved information, mandatory requirements, and Adviser eligibility. A confirmation modal precedes the request, but it is not a security boundary.

Successful submission sets:

- `application_status` to `submitted_to_adviser`;
- `current_stage` to `adviser_review`;
- `submitted_at` and `status_updated_at` to the current time; and
- `draft_owner_user_id` to `null`.

The assigned Adviser receives a database notification. The audit log records `application.submitted` and `application.adviser_notified`. Draft creation, information changes, uploads, and replacements are also audited without storing abstracts, participant text, or file contents.

An identical repeated submission by the owner after success is a no-op and does not duplicate the transition or notification.

## Adviser Boundary and Decision

Adviser dashboards, lists, detail pages, and private document access require both assignment to the signed-in Adviser and a formal submission timestamp/status. Drafts, incomplete records, archived records, and another Adviser's applications remain excluded.

The assigned Adviser may decide only a complete initial-cycle submission while the Adviser Endorsement process is available. Return requires an approved reason plus correction instructions. Endorsement permits optional remarks. Both actions lock and revalidate the application, create an `endorsements` history row, notify the Applicant, and write an audit event.

A return changes status to `returned_by_adviser` and lets the Applicant reopen the same record without resetting its original submission timestamp or consuming another formal slot. Endorsement changes status to `adviser_endorsed`, advances the stage to `res_screening`, prevents a repeated Adviser decision, notifies active RES Leads with neutral text, and makes the application visible in the protected RES Applications Queue.

## RES Screening and Classification

The RES Lead queue includes adviser-endorsed and later RES records. Search covers application code, title, Applicant, Adviser, institute, and program. Status, Applicant category, research type, review type, affiliation, and endorsement-date filters can be combined without broadening the post-endorsement visibility boundary.

The screening detail page reuses the current authorized application and private-document records. Classification requires complete administrative and receipt states, three affirmative eligibility checks, current mandatory-document readiness, a review type, and a reason. `ResScreeningWorkflowService` locks the application, repeats policy and readiness checks, rejects a second initial decision, and creates the unique `application_screenings` history. A RES Lead may correct the persisted screening during screening, reviewer assignment, active initial review, or the Exempted boundary through a separate PUT route. Compatible assignments and the current workflow projection are preserved; pending unstarted assignments are removed only when the correction makes them incompatible; started/submitted review work or a later release/certificate state blocks the incompatible correction.

Expedited and Full Board classifications advance to `awaiting_reviewer_assignment`. Exempted advances to the additive `exempted` status and `decision_release` stage without creating assignments. The direct documentation/certificate operation after Exempted remains pending.

## Initial Reviewer Assignment

Expedited requires exactly one eligible reviewer and Full Board exactly three distinct eligible reviewers. Candidate and transaction-time checks require an active, setup-complete Reviewer account, matching classification, no Applicant/Adviser identity conflict, and remaining active capacity. All active classification-matched reviewers are listed by default; exact department matches rank first, then institution matches, then other eligible reviewers. Department is an optional exact filter. Full-capacity reviewers remain visible with their load but cannot be selected.

Assignment locks the application and selected reviewer rows in stable order, repeats every rule, rejects an existing initial assignment set, and writes the existing `reviewer_assignments` records atomically. Success advances to `under_expedited_review` or `under_full_board_review` and the `ethics_review` stage. Applicant and Reviewer notifications remain neutral; audit metadata stores only the classification, reviewer count, and resulting status.

See [RES Screening and Reviewer Assignment](RES_SCREENING_AND_REVIEWER_ASSIGNMENT.md) for the complete route, validation, security, and limitation contract.

## Reviewer Assignment Workspace

The Reviewer Assigned Applications page is implemented with owner-scoped search, review-type/status/research-type/deadline filters, 15-row pagination, empty state, responsive table containment, and protected details. The detail intentionally omits Applicant and Adviser profile identity. Current assignment ownership gates private documents and review work directly; the retired conflict-declaration gate is absent.

The blind workspace provides assignment-gated private documents, bounded asynchronous overall/document comments with incrementally loaded history, independently draftable/finalizable KLD-RES-04-001 and KLD-RES-04-002 forms, and one draft/final recommendation. Reviewer writes require the configured Reviewer Submission period. Both Final form snapshots and a final comment are required before submission; submission atomically generates both versioned private official PDFs and freezes the assignment. The application reaches `review_submitted_pending_release` only after every initial Reviewer submits, without exposing Reviewer identity or private work to the Applicant.

See [Reviewer Workflow](REVIEWER_WORKFLOW.md) for the exact forms, validation, routes, authorization, persistence, and privacy contract.

## Remaining Workflow

Document-content identity detection/redaction, later revision/re-review comparison, consolidated result release, Exempted direct release, certificate release, QR access, and final archive rules remain governed by `PROJECT_GUIDELINES.md` and require separate implementation and tests.
