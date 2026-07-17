# Architecture Overview

ECRATS uses a modular Laravel monolith. The application stays in one deployable Laravel codebase, but code is grouped by role and business domain to keep ownership clear for a five-member team.

## Request Flow

```text
Route -> Middleware -> Form Request -> Policy -> Controller -> Service -> Model/Query -> View/Redirect/File Response
```

## Principles

- Keep controllers thin.
- Keep Blade views free of queries and workflow logic.
- Put business workflow decisions in services.
- Use policies for record-level access.
- Use middleware for role access.
- Use events/listeners for audit logs and notifications when side effects need to be decoupled.
- Use database transactions around multi-record state changes.

## Role Areas

- Applicant: drafts, uploads, status, revisions, feedback, certificate access
- Adviser: initial review, receipt verification, return, endorsement, expected count declaration
- Reviewer: assigned anonymized workspace, official forms, comments, decisions, re-review
- RES: screening, assignment, releases, timelines, reports, configuration, certificates, audit
- Technical Admin: operational maintenance only

## Planned Route Files

- `routes/auth.php`
- `routes/applicant.php`
- `routes/adviser.php`
- `routes/reviewer.php`
- `routes/res.php`
- `routes/technical-admin.php`
- `routes/shared.php`

Add these only when implementation begins and route loading is configured.
