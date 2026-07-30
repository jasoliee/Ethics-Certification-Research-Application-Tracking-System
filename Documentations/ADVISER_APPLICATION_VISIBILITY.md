# Adviser Application Visibility and Initial Decisions

## Visibility Boundary

Research Advisers see an application only when both conditions are true:

- the application is assigned to the signed-in Adviser; and
- it has crossed the formal submission boundary.

Draft, incomplete, archived, unsubmitted, and other-Adviser records are excluded from the Adviser dashboard, list, detail route, and protected document routes. The record policy repeats this boundary even when a URL is entered directly.

## Submitted Applications

`GET /adviser/applications` provides a 15-row paginated list with search, status, submission-date-from, and submission-date-to filters. Search remains inside the signed-in Adviser's scope and covers safe application and Applicant identity fields.

The list uses the shared internal horizontal-scroll boundary. An authorized Adviser can inspect submitted application information, requirement status, current private document versions, and prior Adviser decision summaries. Files are streamed through authorized preview or download controllers and are never exposed through a public storage URL.

## Return or Endorse

The Adviser Decision section aligns its explanation and actions on one desktop row and stacks them on narrow screens. Return for Correction uses the red destructive-action treatment while Endorse Application remains green. Only the assigned Adviser may act, and only when all of these remain true:

- the application is a formally submitted initial-cycle record in `submitted_to_adviser`;
- persisted information and every mandatory requirement are complete;
- the Adviser Endorsement deadline process is currently available; and
- the signed-in Adviser still owns the assignment.

Return for Correction requires a controlled reason and written instructions. It records a returned endorsement row, updates the application to `returned_by_adviser`, keeps its original formal-submission timestamp and slot, and notifies the Applicant. The Applicant may reopen the same record for correction.

Endorse Application accepts optional remarks, records an endorsed row, updates the application to `adviser_endorsed`, advances it to `res_screening`, and notifies the Applicant and active RES Leads. The RES notification is neutral and links to the authorized RES detail route. Transaction locking and the new status make the decision single-use.

## Dashboard and Notifications

Adviser summary counts and recent rows use the same formal-submission and assignment boundary as the list. They include relevant assigned records even when an older application does not carry the current term link. A successful Applicant submission creates one database notification that links to the authorized Adviser detail route. Return and endorsement create neutral Applicant notifications and audit events; endorsement additionally creates one notification per active RES Lead.

## Pending Workflow

The Adviser initial decision is complete. RES screening/classification, reviewer assignment, blind review, later revision decisions, result release, and certificate workflows remain pending.
