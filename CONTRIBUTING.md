# Contributing to ECRATS

This workflow is designed for the five-member ECRATS team.

## Branches

Permanent branches:

- `main`: production-ready code only
- `develop`: tested integration code

Integration branches:

- `integration/applicant`
- `integration/adviser`
- `integration/reviewer`
- `integration/res`
- `integration/technical-admin`

Feature branches:

- `feature/<module>/<short-description>`
- `fix/<module>/<short-description>`
- `docs/<short-description>`
- `test/<module>/<short-description>`
- `refactor/<module>/<short-description>`
- `chore/<short-description>`

Examples:

- `feature/applicant/draft-application`
- `feature/reviewer/blind-workspace`
- `fix/adviser/receipt-validation`
- `docs/database-design`

## Daily Workflow

1. Pull the latest branch before starting.
2. Create a focused branch.
3. Work only on assigned module files and approved shared files.
4. Run relevant checks.
5. Push the branch.
6. Open a pull request.
7. Request review before merging.

## Commit Messages

Use:

```text
type(scope): short description
```

Types:

- `feat`
- `fix`
- `docs`
- `test`
- `refactor`
- `chore`
- `security`

Examples:

- `feat(applicant): add draft application form`
- `fix(reviewer): hide applicant identity in workspace`
- `docs(architecture): update database design notes`
- `test(res): cover reviewer capacity limit`

## Pull Requests

Every PR should include:

- What changed
- Why it changed
- Requirement or document reference
- Screenshots for UI changes
- Tests and commands run
- Database or environment impact
- Security or privacy impact

Do not merge a PR with failing tests, unresolved conflict markers, exposed secrets, or unreviewed migrations.

## Code Review

Reviewers should check:

- Does the change match the requirement source?
- Are validation and authorization enforced server-side?
- Are private records protected?
- Are reviewer/applicant identities hidden where required?
- Are migrations coordinated and reversible where practical?
- Are tests appropriate for the risk?
- Are unrelated files left untouched?

## Migration Coordination

- Only one member edits a migration batch at a time.
- Do not modify a migration that has already been shared unless the team agrees.
- Prefer a new migration for changes after a branch is shared.
- Never run destructive database commands without explicit approval.
- Document schema decisions in `docs/architecture/database-design.md`.

## Shared Files That Need Coordination

Coordinate before editing:

- `routes/web.php` and planned role route files
- `app/Models/User.php`
- shared layouts and components
- migrations
- enum/status definitions
- policies and middleware
- authentication code
- certificate and QR logic
- private file storage code
- `PROJECT_GUIDELINES.md`, `PLANS.md`, and architecture docs

## Conflict Recovery

If conflicts happen:

1. Stop and inspect the conflict.
2. Do not delete another member's work just to make the conflict disappear.
3. Resolve by keeping the intended behavior from both sides when possible.
4. Run tests after resolving.
5. Ask for help before using reset, force push, or destructive cleanup.
