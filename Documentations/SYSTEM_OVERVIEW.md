# System Overview

## Purpose

ECRATS is the Ethics Certification Review Application and Tracking System for the KLD Research Ethics Section. The current repository is a Laravel 13 modular monolith using PHP 8.3, Blade, Tailwind CSS, Vite, MySQL, and PHPUnit.

## Implemented Scope

- Username authentication with active-account checks, throttling, and generic credential failures.
- Role dashboards for Student/Faculty Researcher, Research Adviser, Ethics Reviewer, and RES Lead.
- RES account administration and adviser-controlled applicant administration.
- Server-generated usernames, pending setup accounts, one-time password setup links, and role onboarding guides.
- CSV/XLSX account import with preview, confirmation, validation reports, and private temporary storage.
- Database-driven applicant requirements, MIME-based document icons, and guarded initial submission.
- Notifications, profile/settings access, audit records, deadlines, timelines, and responsive shared layout.

## Partially Implemented or Planned

Several module pages are still workspaces. Adviser decisions, RES screening, blind reviewer forms, revisions, final result release, certificates, QR verification, payments, and full official-document generation are not complete end-to-end workflows. The repository includes the approved OVPRII background asset, but no certificate/document rendering service or route currently consumes it.

## Main Boundaries

- Controllers validate the HTTP interaction and call services.
- Form Requests enforce request-specific validation and authorization.
- Policies enforce record-level access.
- Services own account creation, bulk import, submission, and auditing transactions.
- Blade views display prepared data and do not query the database.
- Private files belong on Laravel's non-public storage disk.

Read `PROJECT_GUIDELINES.md` before changing security, roles, workflow status, storage, or database design.
