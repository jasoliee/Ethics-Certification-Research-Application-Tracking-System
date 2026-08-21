# Known Issues and Pending Verification

## August 21, 2026 Current Acceptance Blockers

- Authenticated localhost desktop/tablet/mobile verification is pending. The approved in-app browser discovery returned no connected browser surface; no standalone browser automation or unrelated browser backend was substituted.
- Pixel-level comparison of a representative generated certificate with the supplied `QR to Left.png` is pending. This machine lacks `pdftoppm`/`pdfinfo`, and the exact reference image is not available as a local file. Certificate content/provenance and fixed lower-left QR coordinates/dimensions pass automated checks, but real QR scanner readability is not yet verified.
- The August 21 source and automated audit found no remaining DOOMSDAY functional defect. Rows whose acceptance depends on the two blockers above remain **Not yet verified** in `DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md`.

## Pending External Verification

- Generated account templates pass automated ZIP, Open XML entry, worksheet, named-range, data-validation, PhpSpreadsheet reader, writer-resave, reopen, and HTTP binary checks. Manual Microsoft Excel verification is still pending and must confirm there is no corruption, repair, recovery, or removed-content warning.
- Account Information, responsive tables, form spacing, dashboard summary-card alignment, import categories/restoration, Applicant/Adviser application surfaces, RES screening/assignment, and the initial Reviewer workspace are implemented and covered by rendering/build checks where practical. The Reviewer chooser was checked at 1280, 1024, 768, and 390 pixels with no page-level horizontal overflow; true 1440-pixel and broader team acceptance remain pending.
- The current Reviewer high-fidelity reference pages 54-69 were previously rendered on the original development device for implementation reference. The ignored local PDF is present on this device, but final side-by-side stakeholder acceptance remains pending.
- Submitted protocol and informed-consent artifacts are generated from the integrity-checked official source pages and automated mapping checks cover both form types. A final human review of every printed field and branded continuation page remains pending.
- The application-document CSP `sandbox` incompatibility has been corrected in source and focused header coverage. A live native-PDF iframe check is still required in each supported browser; Office formats intentionally remain download/fallback only.
- The Reviewer worksheet labels and final-review confirmation/result flow are implemented and covered by source/feature contracts. Worksheet-dialog focus entry, Escape, scroll lock, restoration, and responsive layout passed locally at the available widths. A writable completed assignment is still needed for browser acceptance of final-review loading/error/success states.
- Historical configured term dates now share the server's ordering contract, while new terms retain the current-date minimum. Automated contracts pass; manually edit one past configured term and create one new term to confirm the device's native date controls honor both paths.

## Maintenance Findings

- `composer audit --no-dev` reconfirmed on August 11, 2026 reports eight advisories affecting locked `guzzlehttp/guzzle` 7.15.1 and `league/commonmark` 2.8.3. Updating dependencies requires explicit team approval and a focused regression pass; this continuation did not upgrade packages.
- The August 10 repository-wide Pint finding in `ReviewerWorkflowService.php` was resolved while that service gained cycle-aware revision behavior on August 11. Repository-wide Pint now passes.

## Product Scope Limitations

- Official forms, asynchronous private comment CRUD/resolution with incremental history loading, initial decision submission, versioned private official-form PDF artifacts, application-level decision release, Applicant revisions/re-review, Exempted certificate eligibility, private personalized certificate generation/release/claim, configurable QR provenance, and non-destructive RES reassignment are implemented. Automated uploaded-content identity detection/redaction, automated side-by-side comparison, public QR verification, and production deployment remain outside this implementation slice.
- Hiding Applicant/Adviser database fields does not remove identities already typed inside uploaded documents. Reviewer document use therefore requires the approved operational anonymization procedure until a content-redaction feature is specified.
- Student Researcher and Faculty Researcher dashboards use application workflow panels rather than the count-based summary cards used by Adviser, Reviewer, and RES Lead roles.

Record completed external checks in [Manual Visual Validation](MANUAL_VISUAL_VALIDATION.md). Do not remove a pending item solely because automated tests pass.
## Applicant revision/certificate source limitations (August 11, 2026)

- The continuation request cites RES Lead high-fidelity pages 106–108, but the supplied high-fidelity PDFs contain only 30 and 104 pages. The RES queue was implemented from written requirements and the established dashboard design system.
- The August 21 certificate reference supplies an approved lower-left QR visual-placement contract. Configurable private QR assets and immutable placement provenance are implemented. A public verification destination/data contract is still intentionally absent, so issued certificates remain authenticated/private.
- The source certificate's static artwork is preserved as a verified raster background. Dynamic text uses bundled PDF core fonts, so tiny glyph-level differences from the original authoring font can remain while layout and content zones are preserved.
- Live Applicant/RES viewport acceptance remains pending because the August 11 in-app browser runtime exposed no controllable instance. Certificate PDF output itself was rendered and visually checked after correcting the official signature transparency extraction.
