# Deadline Configuration

## Scope

RES Lead Settings stores one academic term and six approved process definitions:

1. Application Submission
2. Adviser Endorsement
3. RES Screening and Classification
4. Reviewer Submission
5. Revision Period
6. Reviewing of Revision Period
All six processes use an opening date/time and deadline. The retired Release of Decision and Certificate schedule is deactivated by a forward migration. Configuring a schedule does not by itself complete the later RES, Reviewer, revision, or certificate workflow.

## Academic Term

The RES Lead enters Semester, Academic Year, term Starting Date, and term Ending Date. ECRATS stores that identity in `academic_terms` and links generated `deadline_configurations` and `timeline_calendar_events` rows to it.

For a new term, the browser prevents a Starting Date earlier than the current Philippine date. When an existing configured term is edited, its stored historical Starting Date remains valid and editable. In both paths, the Ending Date minimum follows the selected Starting Date; the server independently requires Ending Date to be on or after Starting Date. Existing term rows are never rewritten merely to satisfy a browser minimum.

Only an active term whose current Philippine date/time falls between its boundaries is current. When terms exist but none is current, term-bound workflow availability fails closed and dashboards use the fallback term label.

## Automatic and Manual State

ECRATS evaluates dates in the application timezone `Asia/Manila`.

- `On`: stores an explicit `manual_status = open` override.
- `Off`: stores an explicit `manual_status = closed` override.
- Automatic upcoming: current time is before `starts_at`.
- Automatic open: current time is from `starts_at` through `due_at`.
- Automatic closed: current time is after `due_at`.
- Unconfigured, inactive, outside-term, or term-mismatched processes are unavailable.

Changing either configured date clears an existing override and returns the process to date evaluation. Saving without changing the switch preserves the existing override.

## Validation and Synchronization

- Every approved process must be present in one settings update so a partial request cannot silently erase a schedule.
- Academic Year uses `YYYY-YYYY`.
- Term Ending Date cannot precede Starting Date.
- Historical Starting Dates remain editable for an existing configured term, so that control does not receive a conflicting `min=today`. A blank new configuration retains today's minimum. Ending Date's browser minimum follows the selected Starting Date, while the server remains authoritative for `after_or_equal` ordering.
- Each process deadline cannot precede its opening.
- Explicit `On` or `Off` overrides runtime process-date evaluation until a date changes.
- Deadline rows and matching timeline events are updated in one database transaction.
- Audit metadata records the term, process keys, and whether each process is automatic or manually open.

## Current Enforcement

Applicant formal submission uses `application-submission`. Adviser return/endorsement uses `adviser-endorsement`, and Reviewer writes use `reviewer-submission`. Their services re-read availability at the protected write boundary, so stale browser buttons cannot bypass a closed period.

Dashboard alerts select role-appropriate current-term deadlines. Applicant timeline events come from the schedule synchronized by RES Lead Settings. Later workflow modules must reuse `DeadlineProcessAvailability` rather than implementing separate date logic.

## Interface

The Deadline Configuration tab contains a bounded term/date summary, Upcoming Deadline and Active Date Range summaries, and one six-row process table. Each row contains its phase, description, opening value, deadline, and an effective `On`/`Off` switch.

The table retains a practical minimum width inside the shared focusable horizontal-scroll boundary. On smaller screens the term and summary fields stack while the process columns remain reachable through the table's bottom scrollbar instead of widening the page.
## Revision deadlines (August 11, 2026)

- `revision-period`, Applicant audience: required before an RES Lead can release a minor/major revision and enforced again on each upload and final revision submission.
- `reviewing-revision-period`, Reviewer audience: required before a submitted revision can be routed and enforced for Reviewer writes in the revision workspace.

The created revision stores the resolved due time as an application-specific boundary. Closing or expiring either configured process is enforced on the server even if a form was already open in a browser.
