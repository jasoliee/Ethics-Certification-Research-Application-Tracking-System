# Application and Requirements Workflow

## Current Implemented Slice

The repository currently stores research applications, active document requirements, versioned application documents, deadlines, timelines, and selected workflow statuses. Several create/edit/upload module pages are still placeholders, so this guide describes only the enforced initial-submission boundary.

## Dashboard Behavior

Before server-accepted initial submission, Application Status and My Application remain in empty-state form even when a draft exists. This prevents a draft from appearing as an official submitted record. Requirements may still show their current server status so the applicant can complete the checklist.

Document icons are selected from the stored MIME type, never from filename text:

- PDF
- Word document
- Image
- Spreadsheet
- Generic fallback

Missing requirements use the explicit `missing` state. Uploaded requirements may be pending, completed, or rejected.

## Submission Guard

Only the owning Student/Faculty Researcher may submit. The application must be draft/incomplete, never previously submitted, and every active requirement must have a current document with `completed` validation status.

Submission runs in a transaction, changes status to `submitted_to_adviser`, records submission/status timestamps, and writes `application.submitted`. Missing, pending, or rejected requirements stop the transition with a row-independent validation message.

## Remaining Workflow

Adviser endorsement, RES screening, reviewer assignment, blind review, revisions, result release, certificate release, QR access, and final archive rules remain governed by `PROJECT_GUIDELINES.md` and require separate implementation/tests.
