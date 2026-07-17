# ECRATS Project Guidelines

These guidelines apply to the whole repository unless a more specific project guideline document is added in a subdirectory.

## Project Overview

ECRATS is the Ethics Certification Review Application and Tracking System for the KLD Research Ethics Section. It supports controlled account creation, applicant submissions, adviser endorsement, RES screening, reviewer assignment, blind review, revisions, feedback, certificate generation, QR-backed certificate access, notifications, reporting, configuration, and audit logging.

This is a production-intended academic system. Keep solutions practical for a five-member student team, but do not weaken security, privacy, auditability, or authorization boundaries.

## Technology Stack

- Laravel 13 and PHP 8.3
- Blade templates, Tailwind CSS 4, Vite
- MySQL as the target database
- Composer and npm
- PHPUnit as the current test framework
- Laragon, Windows PowerShell, and Visual Studio Code for local development

## Source-of-Truth Hierarchy

1. Latest consolidated requirements: `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`
2. Official process memo: `context_files/RSU-MEMO-PROCESS OF ETHICS_FINAL_1-2.pdf`
3. System design, flowcharts, DFDs, ERD, and module references supplied by the team
4. Official forms and templates: `context_files/REMS PROTOCAL REVIEW WORKSHEET.docx`, `context_files/RES CERTIFIACTE.pdf`, and related files
5. Setup and scaffolding references supplied by the team
6. Existing Laravel source code

Team/client additions may be newer than the checked-in project documents. Treat those additions as valid requirements when they are identified, record them in docs, and keep a note that the formal source document may lag behind.

When sources conflict:

- Identify the conflicting files or sections.
- Prefer the newest consolidated and client-aligned requirements.
- Record the conflict in docs or the task summary.
- Ask before implementing disputed behavior.
- Do not invent missing business rules.

## Repository Structure

Current Laravel folders stay in their Laravel-standard locations. Planned ECRATS code should follow these boundaries:

- `app/Http/Controllers/Auth`
- `app/Http/Controllers/Applicant`
- `app/Http/Controllers/Adviser`
- `app/Http/Controllers/Reviewer`
- `app/Http/Controllers/Res`
- `app/Http/Controllers/TechnicalAdmin`
- `app/Http/Requests/<RoleOrModule>`
- `app/Models/<Domain>`
- `app/Services/<Domain>`
- `app/Policies/<Domain>`
- `app/Enums/<Domain>`
- `resources/views/<role-or-shared>`
- `routes/auth.php`, `routes/applicant.php`, `routes/adviser.php`, `routes/reviewer.php`, `routes/res.php`, `routes/technical-admin.php`, `routes/shared.php`

Do not create this full structure until a feature needs it. Prefer small, reviewed additions.

## Architecture Rules

- Use a modular monolith.
- Keep controllers thin: validate, authorize, call a service, return a response.
- Put workflow logic in services.
- Put reusable complex validation in Form Requests and custom Rules.
- Put record-level access in Policies.
- Put role gates in middleware.
- Use transactions for workflow state changes that touch multiple records.
- Use Eloquent relationships and scopes first; add repositories only for complex reusable query boundaries.
- Use enums for approved roles, statuses, review types, decisions, document states, and certificate states once implementation begins.
- Use `ResearchApplication` as the model name, not `Application`, to avoid confusion with Laravel's application container.

## Laravel Conventions

- Follow Laravel naming and directory conventions unless an approved architecture note says otherwise.
- Use `id` as primary keys for new Laravel tables unless the team explicitly approves custom primary key names.
- Use `foreignId(...)->constrained()` style migrations where possible.
- Keep migrations physically flat under `database/migrations`.
- Coordinate migration edits. Only one person should edit the same migration batch at a time.
- Do not run `migrate:fresh`, `db:wipe`, or destructive migration commands without explicit approval.

## Blade and Tailwind Rules

- Blade files are presentation only.
- Do not query the database from Blade views.
- Keep role views separated so applicant and reviewer contexts do not mix.
- Use shared components only for neutral UI that does not leak confidential information.
- Keep reviewer identity hidden from applicants.
- Keep applicant identity hidden from reviewers in blind-review views.
- Use Tailwind utility classes consistently and avoid one-off styling sprawl.

## Database and Workflow Rules

- Incomplete applications must not enter the official RES review queue.
- Adviser endorsement applies only to the initial complete submission.
- Revisions route directly to assigned reviewer or reviewers, not back to the adviser.
- Expedited review requires one reviewer.
- Full Board review requires three reviewers.
- Exempted applications are a confirmed addition: they are screened and documented by RES, bypass standard reviewer assignment/review, and move toward the approved certificate/documentation path after RES confirms eligibility.
- Reviewer capacity defaults to 30 active assignments.
- Reviewer conflict declaration is a confirmed addition: it is not a general decline/refusal option, but a required conflict-status gate before full blind-review access. If conflict is declared, RES must handle replacement or reassignment.
- Reviewer comments stay hidden until official RES result release.
- Disapproved/rejected outcomes are confirmed additions. Use `disapproved` as the preferred system wording because it matches the official reviewer forms; treat older `rejected` references as equivalent unless the team approves a different label.
- Maximum revision cycles default to two unless the approved requirements change.
- Certificate release requires approval and required pre-release steps, including feedback if applicable.
- QR access is a confirmed addition with two levels: public-safe verification may show only approved certificate metadata, while full certificate viewing/downloading remains protected by authentication and authorization.

## Security and Privacy

- No public self-registration.
- No public certificate-verifier role unless the client explicitly approves a public-safe verification design.
- Do not expose `.env`, app keys, tokens, passwords, API keys, payment proof details, or private document paths.
- Store uploaded research documents, payment proofs, anonymized reviewer files, and certificates on private storage disks.
- Validate file type, file size, and required documents on the server.
- Enforce role-based and assignment-based authorization.
- Audit major workflow actions.
- Use neutral notifications and Regala messages that do not reveal unreleased decisions or reviewer identity.

## Testing Requirements

Add or update tests for implemented behavior. For each feature, prefer focused Feature tests that cover:

- Authorization and forbidden access
- Validation failures
- Happy path workflow transitions
- Confidentiality rules
- Database side effects

Run relevant checks before finishing:

```powershell
composer validate --strict
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan test
npm.cmd run build
```

Use `npm.cmd` in PowerShell.

## Git Workflow

- Do not commit directly to `main`.
- Use branches described in `CONTRIBUTING.md`.
- Keep changes focused and reviewable.
- Do not rewrite unrelated files.
- Do not force-push, reset, or delete branches without explicit approval.
- Report every file created, changed, renamed, or deleted.

## Work Rules

- Analyze before editing.
- For large or cross-cutting work, write or update a plan in `PLANS.md` first.
- Ask before installing packages, changing `.env`, modifying authentication architecture, changing database design, changing file storage strategy, or altering security boundaries.
- Preserve existing work. If unrelated changes are present, leave them alone.
- Do not claim a feature works unless it has been verified.
- Prefer small phases over attempting the whole application at once.

## Definition of Done

A task is done only when:

- Requirements and source documents have been checked.
- Implementation is scoped to the request.
- Validation and authorization are handled server-side.
- Relevant tests or verification commands have run, or the reason they could not run is documented.
- New conflicts or unclear requirements are recorded.
- Changed files and remaining risks are summarized.
