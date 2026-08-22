# Testing Guide

## Setup

Run from the repository root in PowerShell:

```powershell
composer install
npm.cmd install
php -m | Select-String zip
npm.cmd run build
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

Never point automated tests at local ECRATS MySQL. Inspect `php artisan migrate:status` first and run `php artisan migrate --no-interaction` only as a separately reviewed forward-migration step; never use `migrate:fresh`, `db:wipe`, broad rollback or a destructive reset.

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
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
vendor\bin\pint --test
npm.cmd run build
```

## August 22, 2026 ENDGAME Verification

- Final repaired dashboard/static slice: 4 tests, 73 assertions, passed.
- Final complete SQLite in-memory suite: 329 tests, 4,545 assertions, passed.
- The sole pending migration, `2026_08_22_000000_add_submission_and_worksheet_settings`, was reviewed as additive and applied to local ECRATS MySQL as batch 6. Post-status reports every migration Ran. No test targeted MySQL.
- Repository-wide Pint, Vite production build, Blade cache, 172-route discovery, and `git diff --check` passed. Vite emitted only its optional Fontaine optimization notice.
- Representative certificate PDF generation and text extraction passed. Source constants place the QR at x=24 mm/y=237 mm as a fixed 30 mm square in the supplied lower-left reference zone.
- Authenticated browser/native-preview checks were not performed because the approved in-app browser reported zero sessions. Pixel comparison and independent QR decode were not performed because no PDF rasterizer or QR decoder is installed. These remain Pending in `MANUAL_VISUAL_VALIDATION.md` and must not be inferred from automated tests.

## August 21, 2026 DOOMSDAY Verification

- Changed-area serialized SQLite run: 178 tests, 2,534 assertions, passed.
- Final complete SQLite in-memory run after formatting and the filesystem-race repair: 319 tests, 4,414 assertions, passed.
- The draft-discard regression also passed three consecutive isolated repetitions.
- The only pending migration was `2026_08_21_000000_preserve_combined_release_and_worksheet_business_versions`; it was applied to local ECRATS MySQL as batch 5 after a read-only status/count preflight. Post-checks found no missing release provenance, no missing worksheet business version and no cycle/version mismatch.
- Repository-wide Pint, Composer validation, Blade compilation, route discovery and the Vite production build passed.
- The prohibited-subtext scan found none of the 35 exact strings in Blade templates.
- Authenticated desktop/tablet/mobile validation was not performed because the in-app browser reported no connected browser surface. Pixel-level certificate comparison was not performed because Poppler is unavailable and the exact reference image is not present as a local file. Do not treat automated source/render tests as substitutes; see `MANUAL_VISUAL_VALIDATION.md`.

## August 10, 2026 Continuation Verification

- Focused Reviewer/artifact/preview/settings/workbook coverage: 64 tests, 1,586 assertions, passed.
- Complete Laravel suite: 226 tests, 3,386 assertions, passed.
- Passed: changed-file Pint, `composer validate --strict`, `composer check-platform-reqs`, 116-route listing, migration status, `php artisan view:cache`, `npm run build`, and `git diff --check`.
- At the August 10 checkpoint, repository-wide Pint had one unrelated service formatting finding; the August 11 cycle-aware update resolved it and repository-wide Pint now passes. Composer audit still has the current Guzzle/CommonMark advisories. See `KNOWN_ISSUES.md` before release.
- Local browser coverage completed at 1280, 1024, 768, and 390 pixels for the Reviewer workspace/chooser and its Escape/focus-restoration behavior. Remaining manual items are recorded in `MANUAL_VISUAL_VALIDATION.md`.

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
- Confirm the Reviewer sidebar contains only Home and Assignments. Confirm Review routes still open from dashboard cards/direct links, Notifications from the bell, and Settings from the profile menu.
- Open an assignment and confirm Applicant/Adviser profile identities are absent and another Reviewer's assignment, workspace, comments, and documents remain forbidden.
- Open PDF, Word, and Excel documents from the workspace. Confirm PDF/browser-safe content uses the protected inline viewer with no CSP `sandbox`, while Office files use the same-origin authorized fallback/download; verify the actual file type is identified and no private storage path or third-party viewer URL appears.
- Save and restore drafts for both official forms. Confirm the visible states move from Not Started to In Progress to Completed, unknown questions/answers fail, applicable final answers are required, and non-Approved recommendations require comments.
- Keep a selected document visible while adding overall and document comments. Confirm create, edit, resolve/reopen, and confirmed remove update without a full-page refresh; newly added comments appear immediately; duplicate requests are blocked; the newest 20 and Load Older cursor preserve total/history; and loading, empty, success, validation-error, and request-error states are readable. Historical page comments must remain readable without being silently remapped.
- Disable JavaScript and confirm the comment form fallback still validates and persists. Confirm another assignment cannot own the target document/comment and all mutations are blocked after final submission.
- Confirm writes fail when Reviewer Submission is unconfigured/upcoming/closed and read-only state remains visible.
- Confirm Submit Review does not open the final dialog until both worksheets, one decision, and a 10-to-2,000-character comment are present. Verify the dialog shows the selected decision and irreversible warning; cancel, backdrop, and Escape restore focus; focus remains trapped; one request enters a loading state; safe server/network errors remain recoverable; and success shows the result state and return action. Save Draft must remain independent, and a no-JavaScript final POST must still use server validation.
- Finalize both forms and confirm no official artifact is exposed yet. Submit one of the four decisions with a comment; confirm two Ready private PDFs are generated from persisted form/decision/comment data, prior versions become Superseded without deletion, a duplicate final request is denied, and the assignment becomes immutable. Simulate one renderer failure and confirm no partial artifact or submitted review commits. For a multi-reviewer cycle, confirm the application reaches pending release only after every Reviewer submits.
- Confirm no Reviewer name, comment, form, or decision appears in Applicant pages before an explicit release feature exists.
- Check the assignment list, workspace, forms, simultaneous document/comment panes, and documents at desktop, tablet, and phone widths; stacked layouts and internal overflow must not create whole-page horizontal scrolling.
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
- Re-edit a saved screening: Re-edit Decision sits beside View Assignment, the classification and saved summary fill the available width, and incompatible current assignments are superseded without deleting their history.
- Confirm the RES requirement checklist provides authorized View and direct Download actions, while another role or mismatched nested document is denied.
- Check the Reviewer assignment page at desktop, tablet, and phone widths: no Eligibility card, filters remain inside Eligible Reviewers, context text does not overlap, selected removal uses the X icon, the reassignment reason sits above Save Reviewer Set, and the confirmation action remains contained.
- Confirm a new term exposes the current-date Starting Date minimum, an existing configured historical start remains editable, and Ending Date follows Starting Date in the browser while the server rejects only reversed ranges. Also confirm inclusive Asia/Manila process boundaries, explicit `On`/`Off` overrides, and date changes clearing an existing override.

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
## Applicant revision and certificate coverage (August 11, 2026)

Focused feature coverage lives in `tests/Feature/Dashboard/ApplicantRevisionCertificationWorkflowTest.php` and `tests/Feature/Dashboard/CertificateReleaseWorkflowTest.php`. It exercises selective comment release, Reviewer anonymity, immutable/idempotent replacement versions, direct same-Reviewer re-routing, cross-user denial, real official PDF generation, survey-before-claim enforcement, private access, background version isolation, and safe generation failure.
