# Known Issues and Pending Verification

## Pending External Verification

- Generated account templates pass automated ZIP, Open XML entry, worksheet, named-range, data-validation, PhpSpreadsheet reader, writer-resave, reopen, and HTTP binary checks. Manual Microsoft Excel verification is still pending and must confirm there is no corruption, repair, recovery, or removed-content warning.
- Account Information, responsive tables, form spacing, dashboard summary-card alignment, import categories/restoration, Applicant/Adviser application surfaces, RES screening/assignment, and the initial Reviewer workspace are implemented and covered by rendering/build checks where practical. Final team acceptance at 1440, 1280, 1024, 768, and 390 pixels remains pending until recorded in the manual checklist.
- The RES and Reviewer high-fidelity references, including Reviewer pages 47-69, were rendered for implementation reference. Final side-by-side stakeholder acceptance remains pending.

## Product Scope Limitations

- Reviewer conflict declaration, official forms, private comments, and initial decision submission are implemented. Automated uploaded-content identity detection/redaction, RES conflict reassignment, revision comparison/re-review, consolidated decision release, Exempted direct release, certificates, QR verification, and production deployment remain outside this implementation slice.
- Hiding Applicant/Adviser database fields does not remove identities already typed inside uploaded documents. Reviewer document use therefore requires the approved operational anonymization procedure until a content-redaction feature is specified.
- Student Researcher and Faculty Researcher dashboards use application workflow panels rather than the count-based summary cards used by Adviser, Reviewer, and RES Lead roles.

Record completed external checks in [Manual Visual Validation](MANUAL_VISUAL_VALIDATION.md). Do not remove a pending item solely because automated tests pass.
