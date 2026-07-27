# Adviser Application Visibility

## Visibility Boundary

Research Advisers see an application only when both conditions are true:

- the application is assigned to the signed-in Adviser; and
- it has crossed the formal submission boundary.

Draft, incomplete, archived, unsubmitted, and other-Adviser records are excluded from the Adviser dashboard, list, detail route, and protected document routes. The record policy repeats this boundary even when a URL is entered directly.

## Submitted Applications

`GET /adviser/applications` provides a 15-row paginated list with search, status, submission-date-from, and submission-date-to filters. Search remains inside the signed-in Adviser's scope and covers safe application and Applicant identity fields.

The detail screen is read-only for this implementation slice. An authorized Adviser can inspect the submitted application information, requirement status, and current private document versions. Files are streamed through authorized preview or download controllers and are never exposed through a public storage URL.

## Dashboard and Notifications

Adviser summary counts and recent rows use the same formal-submission and assignment boundary as the list. A successful Applicant submission creates one database notification that links to the authorized Adviser detail route.

## Pending Workflow

Endorsement, return-for-correction, remarks, status transitions after Adviser review, and related Applicant notifications remain pending. No Adviser decision controls are presented by this slice.
