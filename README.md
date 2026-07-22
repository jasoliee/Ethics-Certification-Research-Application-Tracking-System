# ECRATS

Ethics Certification Review Application and Tracking System (ECRATS) is a Laravel-based web application for managing research ethics submissions, adviser endorsement, RES screening, reviewer workflows, revision tracking, certificate generation, QR-backed certificate access, notifications, and audit records for the KLD Research Ethics Section.

## Current Status

The repository includes username authentication, role middleware, record policies, role dashboards, and controlled account administration. New users receive generated usernames and one-time password setup links; creators never choose a password. RES Lead and Adviser flows support role-specific individual creation plus CSV/XLSX preview and confirmation. Onboarding, mass account actions, audit history, database-driven requirements, MIME-based icons, and guarded initial application submission are implemented and tested.

Modules outside these areas still open shared temporary workspaces. Adviser decisions, RES screening, blind review, revisions, result release, certificate rendering, and QR verification are not yet complete end-to-end workflows. The maintained OVPRII background asset is prepared under `resources/assets/official`, but no official document generator currently consumes it.

The dashboard database tables are an initial implementation slice of the larger module-based ERD. They do not replace the remaining application, screening, review, release, certificate, storage, and audit migrations described in `docs/architecture/database-design.md`.

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

CSV and XLSX account templates are downloaded from User Management after selecting an authorized role. Imports are limited to 250 rows and 2 MB, require preview and explicit confirmation, and use private temporary storage. Local setup/reset notifications use the configured mail driver, which defaults to the Laravel log mailer until a real mail service is configured.

Start the application with `composer run dev` or `php artisan serve`, then open `http://127.0.0.1:8000/login`.

## Team Workflow

Do not commit directly to `main`. Use small feature branches, pull requests, and review before merging. Large database, authentication, authorization, security, storage, certificate, workflow, or cross-module changes require a plan in `PLANS.md` before implementation.

Start with:

- `PROJECT_GUIDELINES.md` for project and coding rules
- `CONTRIBUTING.md` for branch, commit, PR, and review rules
- `Documentations/README.md` for the implemented dashboard, navigation, components, performance work, and testing guide
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
