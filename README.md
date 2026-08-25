# ECRATS

Ethics Certification Review Application and Tracking System (ECRATS) is a Laravel-based web application for managing research ethics submissions, adviser endorsement, RES screening, reviewer workflows, revision tracking, certificate generation, QR-backed certificate access, notifications, and audit records for the KLD Research Ethics Section.

## Current Status

The repository includes username authentication, role middleware, record policies, role dashboards, and controlled account administration. New users receive generated usernames and one-time password setup links; creators never choose a password. RES Lead and Adviser flows support role-specific individual creation plus Excel-only `.xlsx` preview and confirmation. RES Leads can safely restore preview-matched archived accounts without replacing their original records. Database-backed profile options retain immutable identities and historical label aliases so older workbooks can resolve renamed active options.

Applicant draft creation/editing, Institute-aware Adviser selection, private versioned requirement uploads, formal submission, Adviser return/endorsement, RES classification, non-destructive reviewer reassignment, blind Reviewer workspaces, asynchronous comment history, editable completed worksheets, atomic Reviewer decision submission, and versioned official protocol/consent PDF artifacts are implemented and tested. The post-review lifecycle includes exact Reviewer-owned decision release, Applicant revisions and re-review, three-mode batched Release All, private certificate rendering/release/evaluation/claim, and retroactive certificate-background regeneration that preserves issue, release, and claim history. Public QR verification remains outside the completed end-to-end workflow.

The dashboard database tables are an additive implementation slice of the larger module-based ERD. They do not replace the remaining blind-review, release, certificate, storage, and audit migrations described in `docs/architecture/database-design.md`.

## Technology Stack

- Laravel 13
- PHP 8.3
- Blade templates
- Tailwind CSS 4
- Vite
- MySQL for the target local and production database
- Composer
- npm on Windows through `npm.cmd`
- PHPUnit for the current test baseline
- Laragon and Visual Studio Code on Windows

## Main Reference Documents

Primary requirements live in `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`.

Supporting references include:

- `context_files/RSU-MEMO-PROCESS OF ETHICS_FINAL_1-2.pdf`
- `context_files/RES CERTIFIACTE.pdf`
- `context_files/REMS PROTOCAL REVIEW WORKSHEET.docx`
- `context_files/OVPRII.docx`
- external setup and ERD references supplied by the team

When documents conflict, prefer the newest consolidated requirements document, record the conflict, and ask the team before implementing the disputed behavior.

## Setup Checks

Use PowerShell from the repository root.

```powershell
php -v
php --ini
php -m | Select-String zip
composer --version
node --version
npm.cmd --version
php artisan --version
composer validate --strict
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan test
npm.cmd run build
```

Use `npm.cmd` rather than `npm` in PowerShell unless the local execution policy has been intentionally changed.

Excel account import requires PHP's ZIP extension. `composer check-platform-reqs` must report `ext-zip` as successful. On Laragon, enable `extension=zip` in the active `php.ini`, restart affected terminals/services, and confirm it appears in `php -m`.

When ZIP or the locked PhpSpreadsheet package is unavailable, workbook generation/import fails before file creation and returns a safe administrator-facing error. Official reviewer-form PDFs use the locked first-party FPDF/FPDI renderer, private storage, source-template integrity checks, authenticated routes, and append-only artifact versions.

## Dashboard Preview

After pulling the dashboard changes, apply the additive migrations and build the assets:

```powershell
php artisan migrate
npm.cmd run build
php artisan test
```

Normal local seeding keeps the dashboards empty. To inspect the populated reference states, run the optional local-only demo seeder:

```powershell
php artisan db:seed --class=DashboardDemoSeeder
```

Current Excel account templates are downloaded from User Management after selecting an authorized role. Only `.xlsx` is accepted. Imports are limited to 250 account rows and 2 MB, separate invalid, duplicate, active-existing, and archived-account rows, allow confirmation of valid preview rows, and use private actor-scoped temporary storage. Only a RES Lead can restore an original archived row from the current preview. Local setup/reset notifications use the configured mail driver, which defaults to the Laravel log mailer until a real mail service is configured.

Normal seeding creates the four baseline application requirements but intentionally does not invent a dated submission period. Before testing formal Applicant submission, configure an active `deadline_configurations` row whose key ends in `application-submission`, targets Applicants or all roles, and contains the approved opening and due dates.

Start the application with `composer run dev` or `php artisan serve`, then open `http://127.0.0.1:8000/login`.

## Team Workflow

Do not commit directly to `main`. Use small feature branches, pull requests, and review before merging. Large database, authentication, authorization, security, storage, certificate, workflow, or cross-module changes require a plan in `PLANS.md` before implementation.

Start with:

- `PROJECT_GUIDELINES.md` for project and coding rules
- `CONTRIBUTING.md` for branch, commit, PR, and review rules
- `Documentations/README.md` for the implemented dashboard, navigation, components, performance work, and testing guide
- `Documentations/FEATURES_AND_FUNCTIONALITY.md` for the current role-by-role feature catalog and incomplete modules
- `Documentations/IMPLEMENTATION_STATUS_2026-08-10.md` for the exact completed-versus-remaining status of the current implementation brief
- `docs/setup/` for local development and workflow setup
- `docs/requirements/` for source-of-truth summaries
- `docs/architecture/` for module boundaries and database design
- `docs/checklists/security-deployment.md` before production deployment

## Safety Rules

- Do not modify `.env` automatically.
- Do not expose credentials.
- Do not install packages without approval.
- Do not run destructive Git or database commands without explicit approval.
- Do not implement requirements that are unclear or contradicted by source documents.
- Do not store private research documents, payment proofs, reviewer files, or certificates under `public/`.
