# Known Issues and Pending Verification

## Recovered local database incident — 2026-08-23

- Local `ecrats_db` was destructively reset when a test retry relied on `--env=testing` instead of explicit process-level SQLite variables.
- Under explicit user restoration authorization, copied row-format binary logs were replayed through the pre-drop boundary into the isolated `ecrats_recovery_20260823`, validated, exported, and restored to a recreated live `ecrats_db`.
- Live and recovery now match across 40 table names, every row count, and table checksums; all 45 migrations are Ran, `CHECK TABLE` passes, tested orphan counts are zero, and referenced private artifacts exist with matching stored hashes.
- The isolated recovery database, copied binlogs, damaged-state dump, verified recovery dump, and helpers are deliberately retained. They must not be deleted or replayed without explicit user direction.
- The critical remaining operational risk is test isolation: never rely on `--env=testing` or `phpunit.xml` alone. Explicitly force and preflight process-only SQLite `:memory:` before any future authorized test, and run tests serially.
- Final combined-certificate and RES-workspace changes are implemented but unverified. The full INFINITY SAGA audit, final suites/build/checks, authenticated browser run, private-preview checks, and raster/QR acceptance are incomplete.
- Full details: `INFINITY_SAGA_CONTINUATION_HANDOVER_2026-08-23.md` and `MYSQL_POINT_IN_TIME_RECOVERY_2026-08-23.md`.

## August 22, 2026 Current Acceptance Blockers

- Authenticated localhost desktop/tablet/mobile verification is pending. The approved in-app browser discovery returned zero browser sessions on both attempts; no standalone or external browser backend was substituted. This blocks live pointer/keyboard, long-value, upload-progress, modal, responsive-overflow, and populated/empty-state acceptance.
- Native private PDF/image iframe, Open in New Tab, Download, and Office fallback behavior remains pending in a real authenticated browser. Nested policies and defensive response-header tests pass, but tests are not a native-viewer result.
- The supplied certificate PDF is available and its lower-left QR reference zone was inspected. A representative ECRATS PDF was generated and text/source coordinates pass, but pixel-level side-by-side comparison is pending because no installed PDF rasterizer can render either PDF. Independent default/replacement QR scanning is also pending because no approved local QR decoder/scanner is installed.
- The August 22 source and automated audit found no remaining known functional defect. Every browser/visual/scanner-dependent row remains **Not yet verified** in `ENDGAME_REQUIREMENTS_TRACEABILITY_2026-08-22.md`.

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
