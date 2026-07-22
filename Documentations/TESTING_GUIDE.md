# Testing Guide

## Setup

Run from the repository root in PowerShell:

```powershell
composer install
npm.cmd install
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
- Hover a truncated research title for one second and repeat using keyboard focus.

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
- Download each role's CSV/XLSX template, preview an import, and confirm once.
- Confirm formulas/macros/external XLSX links are rejected and no account is created.
- Confirm mass deactivate/archive/resend actions require selection and confirmation.

All roles:

- Click the KLD logo and confirm the KLD profile URL opens.
- Confirm the notification and profile menus do not overlap.
- Confirm View all notifications resolves and Mark all as read works.
- Scroll to the footer and verify all four sections.
- Confirm Settings is absent from the sidebar but present in the profile menu.
- Confirm the footer says About ECRATS, has a Maps link, and has no KLD Login link.
- Check desktop, tablet, and mobile widths for clipping or overlap.
- Check the browser console for errors.

## Verification Baseline

The expanded automated suite covers authentication, role authorization, dashboards, onboarding, account creation, setup-link expiry/single use, CSV/XLSX import, formula rejection, mass actions, username correction, application submission, MIME icons, audit events, and rate limiting. Always report the exact current test count from the command output instead of preserving a count in this document.
