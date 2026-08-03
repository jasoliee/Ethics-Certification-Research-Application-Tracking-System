# Requirements Traceability

This file maps implementation areas to the current source documents. Update it when requirements change.

| Area | Primary source | Supporting source | Implementation notes |
| --- | --- | --- | --- |
| Account management | July 20, 2026 account-management requirements | High Fidelity (5), pages 1-8, and supervisor CSV note | Separate names and institutional ID, generated username, role-limited creation, search/filter/pagination, CSV import, status control, reset links, and audit logs. Newer written rules override editable Date Joined, direct password editing, and RES/Admin creation shown in the mockup. |
| Login validation | July 20, 2026 account-management requirements | Existing approved login design | Field errors remain specific; generic auth error appears only after required fields pass validation and credentials do not match an active account. |
| Completeness validation | Consolidated project documentation | System design flow 5 | Block formal submission until all required fields and documents are valid. |
| Adviser endorsement | Consolidated project documentation | RES process memo and system design flow 6 | Adviser verifies receipt image and handles only initial endorsement. |
| RES screening | Consolidated project documentation plus team/client addition | System design flow 8 and High Fidelity (6), pages 1-4, plus High Fidelity (7) corrections | Implemented with complete/receipt/eligibility gates, persisted reason, Expedited/Full Board/Exempted classification, safe re-editing, locking, audit, and neutral notification. Exempted bypasses standard reviewer assignment/review. |
| Reviewer assignment | Consolidated project documentation | ERD module 7, system design flow 9, High Fidelity (6), pages 5-13, and High Fidelity (7) | Initial assignment is implemented: exactly one Expedited or three Full Board reviewers using active/setup-complete status, classification, prioritized department/institution matches, current capacity, locked revalidation, and duplicate prevention. The owner-scoped Reviewer assignment list/details are implemented; conflict declaration and blind document redaction remain pending. |
| Blind review | Consolidated project documentation plus team/client addition | KLD-RES-04 forms and system design flows 10 to 12 | Reviewer workspace must hide applicant identity. Conflict declaration gates full blind-review access. |
| Decision release | Consolidated project documentation plus team/client addition | System design flow 13 | Hold reviewer decisions until official RES release. Decisions include accepted, minor revision, major revision, and disapproved. |
| Revision cycles | Consolidated project documentation | System design flows 15 to 17 | Preserve versions and enforce maximum two cycles by default. |
| Feedback | Consolidated project documentation | System design flow 18 | Feedback can unlock certificate eligibility. |
| Certificates and QR | Consolidated project documentation plus team/client addition | Certificate sample and system design flows 19 to 20 | Control number and QR access include public-safe verification plus protected full certificate access. |
| Notifications and Regala | Consolidated project documentation | System design flows 14 and 21 | Use neutral wording before official release. |
| Reports and monitoring | Consolidated project documentation | System design flow 23 | Include adviser expected counts and reviewer capacity. |
| Audit logging | Consolidated project documentation | ERD module 10 and system design flow 25 | Log major workflow and configuration actions. |

## Confirmed Additions from Team/Client Communication

- Controlled CSV account creation through a fixed header template.
- RES Lead researcher/adviser/reviewer creation and adviser applicant-only creation.
- System-generated usernames and normalized account identity fields.
- Exempted application path.
- Disapproved/rejected decision outcome support, with `disapproved` preferred.
- Public-safe QR/control-number certificate verification.
- RES-controlled anonymization approval.
- Soft-delete/no-hard-delete policy for audit-sensitive records.

## Details Still Needed Before Coding

- Exact certificate control-number format.
- Exact public QR verification fields.
- Final production hosting, backup, and email service details.
