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
- Upload and replace each accepted private file type up to 100 MB; confirm the current version changes and prior version history remains.
- Select several requirement files, use Upload All, and confirm progress/checklist state updates immediately. Confirm a failed selected file remains selected and a final refresh occurs only after all files succeed.
- Open PDF/image and Word/Excel documents; confirm browser-safe formats render inline and Office formats show the authorized fallback/download without exposing a private path.
- Confirm another Applicant cannot edit, view, preview, download, upload to, or submit the record.
- Confirm the same mandatory-requirement completion count appears on dashboard, details, and requirements.
- Confirm missing, pending, or rejected requirements block initial submission.
- Confirm submission is blocked when unconfigured, upcoming, or closed and succeeds only inside the configured period.
- Confirm Submit Application stays aligned with the checklist heading and Target Participants remains beside the stacked Starting/Ending dates.
- Submit twice and confirm one transition, one Adviser notification, and no duplicate audit event.
- Open Application and Revision and Certificates; verify breadcrumbs and active states.
- Hover a truncated research title or account value for about 0.5 seconds and repeat using keyboard focus.

Adviser:

- Confirm only formally submitted assigned applications contribute to cards, recent rows, and the 15-row paginated list.
- Confirm drafts, unsubmitted records, archived records, and another Adviser's records are absent and denied by direct URL.
- Search and filter by status/submission dates; confirm filters cannot broaden the Adviser scope.
- Confirm Status and Action columns are centered while other columns remain left-aligned.
- Open an application and its current private documents; verify record authorization and breadcrumb links.
- Confirm Return for Correction is red, Endorse Application is green, both are aligned, and only an assigned initial-cycle Adviser can use them during the configured period.
- Confirm the submitted-application table exposes its bottom horizontal scrollbar when columns do not fit.
- Confirm applicant account creation never asks for a password or Date Joined.

Reviewer:

- Confirm assignment counts, deadlines, and table rows are scoped to the current reviewer.
- Search and filter Assigned Applications, confirm pagination and empty state, and verify all displayed records belong to the signed-in Reviewer.
- Open an assignment and confirm Applicant/Adviser profile identities are absent. Before conflict clearance, confirm private preview/download and the workspace are unavailable.
- Record No Conflict and confirm the blind workspace and current private document preview/download become available. On another assignment, declare a conflict and confirm work remains blocked and repeated declarations fail.
- Save and restore drafts for both official forms. Confirm unknown questions/answers fail, applicable final answers are required, and non-Approved recommendations require comments.
- Add overall, document, and page comments; confirm scope fields validate, another assignment cannot own the target document/comment, and removal is blocked after submission.
- Confirm writes fail when Reviewer Submission is unconfigured/upcoming/closed and read-only state remains visible.
- Finalize both forms, submit one of the four decisions with a comment, and confirm the assignment becomes immutable. For a multi-reviewer cycle, confirm the application reaches pending release only after every Reviewer submits.
- Confirm no Reviewer name, comment, form, or decision appears in Applicant pages before an explicit release feature exists.
- Check the assignment list, conflict detail, workspace, forms, and documents at desktop, tablet, and phone widths; horizontal overflow must stay inside the document/table region.
- Open notifications and profile pages.
- Confirm another reviewer's assignment returns 403.

RES Lead:

- Confirm screening, assignment, review, and result-release counts.
- Confirm Notifications is absent from the sidebar but available from the bell.
- Confirm administrative application records open and other role prefixes redirect.
- Download each role's `.xlsx` template and confirm the worksheets are exactly Accounts, hidden Options, and Instructions.
- Confirm current active options appear as Excel dropdowns, a prior label resolves to the same active option after rename, the current canonical label is stored, and inactive option identities fail server-side validation.
- Preview a workbook containing valid, invalid, duplicate, and existing rows; confirm only the valid rows once.
- Confirm a 12-digit phone is rejected with row, field, reason, and expected format; confirm an 11-digit phone and alphanumeric Student Number validate.
- Confirm the Student template Row 2 contains the approved Juan Dela Cruz example and the exact marker appears in Instructions.
- Remove the Instructions marker and confirm Row 2 is validated as ordinary data.
- Confirm active and archived account matches appear in separate containers.
- Restore one and Restore All from the current preview; confirm original IDs and relationships remain, conflicts stay archived, actions are audited, and restored rows are never recreated on confirmation.
- Confirm another RES Lead's preview, an expired preview, and a manipulated archived ID cannot restore an account.
- Confirm CSV, XLS, XLSM, XLSB, renamed files, formulas, macros, external links, password protection, changed headers, extra sheets/columns, and excessive rows are rejected.
- Add, rename, deactivate, and restore a dropdown option; confirm alias ownership remains stable, historical user values remain unchanged, and advisers cannot manage options.
- Filter the audit report by search, actor role, action, result, target type, and date; confirm filters survive pagination.
- Confirm mass deactivate/archive/resend actions require selection and confirmation.
- Endorse an application and confirm every active RES Lead receives one neutral notification while inactive RES Leads do not.
- Open the RES Applications Queue and confirm pre-endorsement submissions and drafts are absent, all approved filters stay scoped, pagination is 15 rows, and the table scrolls internally.
- Open a ready screening record and verify incomplete administrative gates or stale mandatory-document state cannot be classified.
- Verify Expedited accepts exactly one eligible reviewer, Full Board exactly three distinct eligible reviewers, full-capacity rows cannot be selected, and Exempted exposes no assignment action.
- Confirm department matches appear before institution/other eligible reviewers, Department filtering works, and no Availability column or filter is present.
- Re-edit a saved screening: compatible assignments remain, incompatible pending assignments are removed, and a started assignment prevents the correction.
- Confirm the RES requirement checklist provides authorized View and direct Download actions, while another role or mismatched nested document is denied.
- Check the Reviewer assignment page at desktop, tablet, and phone widths: no Eligibility card, filters remain inside Eligible Reviewers, context text does not overlap, selected removal uses the X icon, and the confirmation action remains contained.
- Confirm ordered term/process ranges, inclusive Asia/Manila boundaries, explicit `On`/`Off` overrides, and date changes clearing an existing override.

All roles:

- Click the KLD logo and confirm the KLD profile URL opens.
- Confirm the notification and profile menus do not overlap.
- Confirm View all notifications resolves and Mark all as read works.
- Scroll to the footer and verify all four sections.
- Confirm Settings is absent from the sidebar but present in the profile menu.
- Confirm the footer says About ECRATS, has a Maps link, and has no KLD Login link.
- Check approximately 1440, 1280, 1024, 768, and 390 pixel widths for clipping or whole-page horizontal overflow. Include Applicant application/list/details/requirements, the document and submission dialogs, Adviser list/decision actions, and RES deadline sections.
- Confirm wide account/audit tables scroll inside their wrapper, badges remain aligned, pagination is centered, and long values show a tooltip after about 0.5 seconds.
- Check the browser console for errors.

## Verification Baseline

The expanded automated suite covers authentication, role authorization, dashboard reflection across term links, onboarding, account creation, setup-link expiry/single use, Excel generation/import, workbook-structure rejection, marker-controlled example rows, active/archive separation and secure restoration, dropdown identities and historical aliases, mass actions, username correction, Applicant date-pair validation, formal-count limits, private-document behavior, shared completion, configured/manual-open submission, confirmation structure, Adviser visibility/decisions, MIME icons, audit filtering/sanitization, and rate limiting. Always report the exact current test count from command output instead of preserving a count in this document.
