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
- Confirm draft Application Status and My Application cards remain empty until submission.
- Confirm missing, pending, or rejected requirements block initial submission.
- Open Application and Revision and Certificates; verify breadcrumbs and active states.
- Hover a truncated research title or account value for about 0.5 seconds and repeat using keyboard focus.

Adviser:

- Confirm only assigned applicants contribute to cards and tables.
- Confirm Status and Action columns are centered while other columns remain left-aligned.
- Open an application and verify record authorization and breadcrumb links.
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

The expanded automated suite covers authentication, role authorization, dashboards, onboarding, account creation, setup-link expiry/single use, Excel generation and import, workbook-structure rejection, dropdown-option lifecycle, duplicate/existing separation, broad valid email domains, mass actions, username correction, application submission, MIME icons, audit filtering/sanitization, and rate limiting. Always report the exact current test count from command output instead of preserving a count in this document.
