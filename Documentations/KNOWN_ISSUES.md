# Known Issues and Pending Verification

## Pending External Verification

- Generated account templates pass automated ZIP, Open XML entry, worksheet, named-range, data-validation, PhpSpreadsheet reader, writer-resave, reopen, and HTTP binary checks. Manual Microsoft Excel verification is still pending and must confirm there is no corruption, repair, recovery, or removed-content warning.
- Account Information, responsive tables, form spacing, dashboard summary-card alignment, import categories/restoration, and Applicant/Adviser application surfaces are implemented and covered by rendering/build checks where practical. Browser acceptance at 1440, 1280, 1024, 768, and 390 pixels remains pending manual visual verification because a browser runtime was unavailable.
- The supplied high-fidelity PDF could not be rendered in the current environment, so side-by-side visual comparison and screenshot evidence remain pending.

## Product Scope Limitations

- The later end-to-end ethics review, decision, certificate, QR verification, and production deployment workflows remain outside this correction task unless another implementation document marks them complete.
- Student Researcher and Faculty Researcher dashboards use application workflow panels rather than the count-based summary cards used by Adviser, Reviewer, and RES Lead roles.

Record completed external checks in [Manual Visual Validation](MANUAL_VISUAL_VALIDATION.md). Do not remove a pending item solely because automated tests pass.
