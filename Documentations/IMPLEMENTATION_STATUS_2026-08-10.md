# August 10, 2026 Implementation Status

> Historical handoff: this file records the August 10/11 baseline. The Adviser/Reviewer account model, spreadsheet contract, Reviewer resubmission/consensus rule, Applicant upload/evaluation contract, background behavior, certificate queue/generation rule, settings, and monitoring were superseded on August 17. Use [2026 Finale Implementation Record](THE_FINALE_IMPLEMENTATION_2026-08-17.md) for current behavior and pending acceptance.

This is the current handoff for the requirements in the attached ECRATS Laravel implementation brief. It distinguishes implemented work from verification and follow-up work that has not been completed. No deployment, public tunnel, external upload, or transmission of project data was performed.

## Completed

### Database recovery and seed command

- Fixed `2026_08_05_001000_preserve_reviewer_assignment_history` so every MySQL DDL operation checks whether its column, index, or foreign key already exists. The failed migration can therefore resume after MySQL committed only part of it, including the duplicate `reviewer_assignments_application_fk_index` case.
- Added the same partial-application protection to the review-form-artifact migration.
- Added a rollback preflight that refuses to discard repeated reviewer-assignment history.
- `php artisan migrate --no-interaction` completed against the local `ecrats_db`; a repeat reported that there was nothing left to migrate.
- `php artisan db:seed --no-interaction` completed successfully, including the RES Lead, application configuration, and testing-user seeders.

### RES Lead requirements

- The application-detail assignment action displays `Re-edit Assignment` without changing its route or behavior.
- Applicant Category and Research Type were removed only from the RES Applications landing table and filters. Their stored values, forms, detail displays, models, and validation remain intact.
- RES dashboard application rows use the actual Adviser relationship, eagerly loaded, with `Not assigned` as the fallback.
- Deadline Configuration has bounded containers, wrapping term fields and summaries, an internally scrollable process table, usable date/time controls, and responsive desktop/tablet/mobile rules.

### Bulk account workbook

- Template generation/download retains `.xlsx`, the correct MIME/disposition headers, required worksheets, headers, formatting, named ranges, dropdown validation, protection, and sample/instruction rows.
- Server-side guards reject an unavailable spreadsheet runtime or an invalid/corrupt workbook before returning misleading XLSX headers.
- Existing binary, ZIP/Open XML, structure, dropdown, reader/resave/reopen, and download-response coverage remains in place.

### Reviewer workspace and comments

- The high-fidelity PDF was copied to `context_files/references/ECRATS High Fidelity (8).pdf` for local reference and ignored by Git.
- The desktop workspace uses aligned, bounded Documents, Preview, and Review Tools columns; smaller widths stack logically with internal scrolling.
- The application summary is centered and includes the Research Title with safe wrapping.
- `+ Page Comment` and its dedicated creation flow were removed while Open, Download, and the main comment workflow remain.
- Comment create/edit, resolve/reopen, and confirmed delete use asynchronous requests, immediate escaped server-rendered updates, duplicate-request guards, preserved input on failure, and accessible loading/success/error feedback.
- Comment history is bounded to the newest 20 records and supports assignment-authorized incremental loading with accurate totals.
- Eligible actions live in a keyboard-accessible three-dot menu that closes on outside click, Escape, scroll, resize, or opening another menu. Backend assignment ownership and write-window checks remain authoritative.
- Historical page comments remain readable and are not silently converted when edited; page-comment editing is intentionally unavailable after removal of the page-comment workflow.
- Secure document selection has loading/failure states and stale-load protection.

### Reviewer worksheets

- Required worksheets open from Review Tools and consistently display `Not Started`, `In Progress`, or `Completed` while retaining the existing Draft/Final persistence values, progress, draft restoration, and direct navigation.
- Closing a worksheet warns about unsaved changes; closing is blocked while a draft save is in progress. Modal focus is contained and restored.
- Institute, Reviewer, and Researcher / Study Leader were removed from both modal metadata summaries. Title, application code, review type, and date received remain.
- Question blocks have reusable responsive spacing; long questions wrap; Protocol retains No/Yes/Unable to Assess and Informed Consent retains Yes/No.
- The consent explanation is shown and enabled only when consent is marked unnecessary.
- Recommendations use accessible radio groups, and finalization remains server-validated and immutable.
- Final review submission now uses a styled confirmation/result dialog instead of the native browser confirmation. It shows the selected decision and irreversible warning, blocks incomplete worksheets/decision/comment before opening, traps and restores focus, supports Escape/cancel, prevents duplicate requests, and reports loading, validation, request-error, and success states. The ordinary POST remains the no-JavaScript fallback and server validation remains authoritative.

### Official review documents

- Worksheet finalization now freezes the validated catalog, payload, and internal assignment context without publishing an early official artifact.
- Submitting the overall review atomically generates both official PDFs from persisted final worksheet snapshots, the persisted decision/comment, and the complete applicable assignment-comment record.
- The integrity-checked official REMS pages, logos, form codes, layout, response positions, recommendations, attestation, and branded continuation pages are retained.
- Artifacts use private storage, authenticated nested routes, hashes, template/generator metadata, timestamps, and monotonically increasing versions. Older Ready versions become `Superseded` rather than being overwritten or deleted.
- RES lists only current Ready artifacts backed by a Submitted review. The owning current Reviewer and authorized RES Lead are checked by policy; incomplete or failed submissions are not exposed as official.
- If either PDF fails, partial files are removed, the database transaction rolls back, finalized worksheet snapshots remain, and the overall review remains unsubmitted with a non-sensitive error.
- Authenticated PDF/image application-document previews now use the same non-sandboxed, same-origin framing CSP as official artifacts, plus `no-store`, `nosniff`, no-referrer, and restricted Permissions Policy headers. Office formats retain the separate protected fallback and download path.

### Deadline date compatibility

- Term dates use required native date controls and one ordering rule: Ending Date must be on or after Starting Date. Existing historical starts remain editable, new terms retain the current-date browser minimum, JavaScript updates the ending-date minimum from the selected start, and the server retains the same `after_or_equal` authority without changing stored terms.

### Documentation and verification

- Updated the README, implementation plan, changelog, requirements traceability, Reviewer/application/dashboard/database/document-generation/features/testing/manual-validation/known-issues guides, and this handoff.
- The continuation-focused set passed 64 tests with 1,586 assertions. The complete Laravel suite then passed 226 tests with 3,386 assertions.
- Changed PHP files pass Pint. Strict Composer validation, Composer platform requirements, all 116 application routes, migration status, Blade compilation, the Vite production build, and `git diff --check` pass. All migrations through `2026_08_09_000000_create_review_form_artifacts` are already applied, so no migration or seed command was needed in this continuation.
- Composer installed the existing lock without package changes. FPDF 1.8.6 and FPDI 2.6.8 are installed; the FPDI root constraint is `~2.6.8`, and only the lock content hash changed.
- A clean localhost session inspected the Reviewer workspace/worksheet chooser at 1280, 1024, 768, and 390 pixels with no page-level horizontal overflow. The chooser's focus entry, Escape close, body scroll lock, and trigger-focus restoration worked. The browser backend capped the attempted 1440-pixel viewport at 1280.
- A direct local render previously produced both official forms with their continuation content. Automated checks and the partial browser pass do not replace final human spreadsheet/PDF acceptance.

## Remaining

### External/manual acceptance

- Complete a true 1440-pixel browser pass. Recheck the final-review confirmation/result states with a writable, fully completed assignment; the available seeded assignment was correctly read-only because its Reviewer Submission window was closed.
- Manually check the historical configured-term and blank new-term date controls. The local browser disconnected before this page-level check; automated Blade/JavaScript/server tests passed.
- Download/open the workbook in a clean browser/device and Microsoft Excel or another supported spreadsheet application; confirm no repair/corruption warning and complete one import round trip.
- Visually compare every generated Protocol and Informed Consent field plus continuation pages against the official source templates.
- Approve a dependency refresh for the current Guzzle and CommonMark advisories recorded in `KNOWN_ISSUES.md`.

## Intentional Differences from the Prototype

- `+ Page Comment` is absent because the written requirement explicitly overrides the prototype.
- Institute, Reviewer, and Researcher / Study Leader are absent from worksheet modal metadata because the written requirement explicitly overrides the prototype; internal audit context remains stored.
- Office documents are not sent to third-party viewers. Unsupported inline formats use the authenticated first-party fallback and Download action to preserve confidentiality.
## August 11 continuation: Applicant revision and certification

Completed after the August 10 baseline:

- explicit RES decision and selected-comment release;
- two-cycle, document-specific Applicant revision with immutable versions and direct same-Reviewer re-review;
- Applicant evaluation followed by explicit claim;
- official-template private certificate generation, release, bulk release, regeneration history, and future-only background versioning;
- Applicant, Reviewer-history, and RES interfaces, authorization, throttles, notifications, audit events, tests, and synchronized documentation.

Verification passed for 8 focused tests/71 assertions, one full suite of 233 tests/3,454 assertions, Pint, Composer validity/platform requirements, route discovery, isolated fresh migrations, configured MySQL migration application, Blade compilation, Vite production build, diff whitespace, and rendered A4 certificate inspection. The migration's guarded resume path recovered safely after MySQL first rejected an overlong generated constraint name; the affected names are now explicit and portable. Eight pre-existing locked-package advisories remain documented. Live Applicant/RES viewport acceptance remains pending because the in-app browser exposed no controllable instance.
