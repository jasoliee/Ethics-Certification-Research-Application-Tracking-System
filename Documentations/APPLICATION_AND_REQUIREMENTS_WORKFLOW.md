# Application and Requirements Workflow

## Implemented Initial Submission and Adviser Decision

The repository implements Applicant-owned application information, private versioned requirement documents, shared completion calculation, configured submission-window enforcement, formally submitted Adviser visibility, and the assigned Adviser's initial return/endorsement decision. Later RES and Reviewer stages are not complete.

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

PDF, DOC, DOCX, XLS, XLSX, JPEG, and PNG files up to 10 MB are accepted after server MIME inspection. Files use randomized names on the private `local` disk. Replacement creates a new current record, marks the prior record non-current, and retains both database and private-file history. Applicants may upload one file at a time or process several selected requirement files through the bounded Upload All endpoint.

Document icons are selected from stored MIME type rather than filename text. Preview is offered only for supported browser-display types; download remains available through authorized controllers.

## Submission Window

The server resolves the highest-priority active `deadline_configurations` row whose key ends in `application-submission`, belongs to the current active term when terms are configured, and targets Applicants or all roles. A future `starts_at` is upcoming, a past `due_at` is closed, and an absent configuration fails closed. Manual `On` overrides the process date range; `Auto` means automatic date evaluation. UI state never replaces this server check.

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

A return changes status to `returned_by_adviser` and lets the Applicant reopen the same record without resetting its original submission timestamp or consuming another formal slot. Endorsement changes status to `adviser_endorsed`, advances the stage to `res_screening`, prevents a repeated Adviser decision, notifies active RES Leads with neutral text, and makes the application visible in the protected RES Endorsed Applications queue.

## Remaining Workflow

RES screening decisions, reviewer assignment, blind review, later revisions, result release, certificate release, QR access, and final archive rules remain governed by `PROJECT_GUIDELINES.md` and require separate implementation and tests.
