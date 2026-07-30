# System Overview

## Purpose

ECRATS is the Ethics Certification Review Application and Tracking System for the KLD Research Ethics Section. The current repository is a Laravel 13 modular monolith using PHP 8.3, Blade, Tailwind CSS, Vite, MySQL, and PHPUnit.

## Implemented Scope

- Username authentication with active-account checks, throttling, and generic credential failures.
- Role dashboards for Student/Faculty Researcher, Research Adviser, Ethics Reviewer, and RES Lead.
- RES account administration and adviser-controlled applicant administration.
- Server-generated usernames, pending setup accounts, one-time password setup links, and role onboarding guides.
- Excel-only `.xlsx` account import with exact workbook contracts, readable dropdowns, immutable server-side option identity, historical-label aliases, categorized preview, RES-only archived-account restoration, single-use confirmation, and private temporary storage.
- RES Lead dropdown-option administration and filtered account audit reporting with recursive sensitive-metadata sanitization.
- Applicant-owned draft information, validated Starting/Ending Dates, a three-formal-application limit, database-driven requirement completion, private versioned/batch uploads, configured initial submission, and confirmation before workflow transition.
- Assigned-Adviser visibility, deadline-gated return-for-correction and endorsement decisions, Applicant notification, and auditable advancement to RES Screening.
- Current role dashboards, profile/settings access, notifications, audit records, active-term deadlines/timelines, reusable horizontal table overflow, and responsive shared layout.

## Partially Implemented or Planned

Several module pages are still workspaces. RES screening decisions, blind reviewer forms, later revisions, final result release, certificates, QR verification, payments, and full official-document generation are not complete end-to-end workflows. Adviser return/endorsement is implemented only for a complete initial submission in its configured deadline period. The repository includes the approved OVPRII background asset, but no certificate/document rendering service or route currently consumes it.

## Main Boundaries

- Controllers validate the HTTP interaction and call services.
- Form Requests enforce request-specific validation and authorization.
- Policies enforce record-level access.
- Services own account creation, bulk import/restoration, application drafts/documents/submission, and auditing transactions.
- Blade views display prepared data and do not query the database.
- Private files belong on Laravel's non-public storage disk.

Read `PROJECT_GUIDELINES.md` before changing security, roles, workflow status, storage, or database design.
