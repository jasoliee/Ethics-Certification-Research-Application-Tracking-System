# Testing Guide

## Setup

Run from the repository root in PowerShell:

```powershell
composer install
npm.cmd install
php -m | Select-String zip
php artisan migrate
npm.cmd run build
php artisan test
```

For populated dashboard states:

```powershell
php artisan db:seed --class=DashboardDemoSeeder
php artisan serve --quiet --no-reload
```

Open `http://127.0.0.1:8000/login`.

## Automated Checks

```powershell
composer validate --strict
composer check-platform-reqs
php artisan route:list --except-vendor
php artisan migrate:status
php artisan test
vendor\bin\pint --test
npm.cmd run build
```

## Manual Role Checks

Applicant:

- Confirm login redirects to `/dashboard` and the browser title is Dashboard.
- Confirm Student Researcher or Faculty Researcher appears in the sidebar.
- Confirm the first-login guide appears after password setup and can be reopened later.
- Start the application twice and confirm the same editable draft is continued instead of creating duplicates.
- Complete Student and Faculty forms; confirm Program is required only for the Student flow and only eligible active Advisers appear.
- Confirm the dashboard, application list, details, and requirements use only the signed-in Applicant's records.
- Upload and replace each accepted private file type; confirm the current version changes and prior version history remains.
- Confirm another Applicant cannot edit, view, preview, download, upload to, or submit the record.
- Confirm the same mandatory-requirement completion count appears on dashboard, details, and requirements.
- Confirm missing, pending, or rejected requirements block initial submission.
- Confirm submission is blocked when unconfigured, upcoming, or closed and succeeds only inside the configured period.
- Submit twice and confirm one transition, one Adviser notification, and no duplicate audit event.
- Open Application and Revision and Certificates; verify breadcrumbs and active states.
- Hover a truncated research title or account value for about 0.5 seconds and repeat using keyboard focus.

Adviser:

- Confirm only formally submitted assigned applications contribute to cards, recent rows, and the 15-row paginated list.
- Confirm drafts, unsubmitted records, archived records, and another Adviser's records are absent and denied by direct URL.
- Search and filter by status/submission dates; confirm filters cannot broaden the Adviser scope.
- Confirm Status and Action columns are centered while other columns remain left-aligned.
- Open an application and its current private documents; verify record authorization and breadcrumb links.
- Confirm there are no endorsement or return controls until that workflow is implemented.
- Confirm applicant account creation never asks for a password or Date Joined.

Reviewer:

- Confirm assignment counts, deadlines, and table rows are scoped to the current reviewer.
- Open an assignment, notifications, and profile pages.
- Confirm another reviewer's assignment returns 403.

RES Lead:

- Confirm screening, assignment, review, and result-release counts.
- Confirm Notifications is absent from the sidebar but available from the bell.
- Confirm administrative application records open and other role prefixes redirect.
- Download each role's `.xlsx` template and confirm the worksheets are exactly Accounts, hidden Options, and Instructions.
- Confirm current active options appear as Excel dropdowns and inactive values fail server-side validation.
- Preview a workbook containing valid, invalid, duplicate, and existing rows; confirm only the valid rows once.
- Confirm the Student template Row 2 contains the approved Juan Dela Cruz example and the exact marker appears in Instructions.
- Remove the Instructions marker and confirm Row 2 is validated as ordinary data.
- Confirm active and archived account matches appear in separate containers.
- Restore one and Restore All from the current preview; confirm original IDs and relationships remain, conflicts stay archived, actions are audited, and restored rows are never recreated on confirmation.
- Confirm another RES Lead's preview, an expired preview, and a manipulated archived ID cannot restore an account.
- Confirm CSV, XLS, XLSM, XLSB, renamed files, formulas, macros, external links, password protection, changed headers, extra sheets/columns, and excessive rows are rejected.
- Add, rename, deactivate, and restore a dropdown option; confirm historical user values remain unchanged and advisers cannot manage options.
- Filter the audit report by search, actor role, action, result, target type, and date; confirm filters survive pagination.
- Confirm mass deactivate/archive/resend actions require selection and confirmation.

All roles:

- Click the KLD logo and confirm the KLD profile URL opens.
- Confirm the notification and profile menus do not overlap.
- Confirm View all notifications resolves and Mark all as read works.
- Scroll to the footer and verify all four sections.
- Confirm Settings is absent from the sidebar but present in the profile menu.
- Confirm the footer says About ECRATS, has a Maps link, and has no KLD Login link.
- Check approximately 1440, 1280, 1024, 768, and 390 pixel widths for clipping or whole-page horizontal overflow.
- Confirm wide account/audit tables scroll inside their wrapper, badges remain aligned, pagination is centered, and long values show a tooltip after about 0.5 seconds.
- Check the browser console for errors.

## Verification Baseline

The expanded automated suite covers authentication, role authorization, dashboards, onboarding, account creation, setup-link expiry/single use, Excel generation and import, workbook-structure rejection, marker-controlled example-row handling, active/archive separation and secure restoration, dropdown-option lifecycle, broad valid email domains, mass actions, username correction, Applicant draft and private-document behavior, shared completion, configured submission, Adviser visibility, MIME icons, audit filtering/sanitization, and rate limiting. Always report the exact current test count from command output instead of preserving a count in this document.
