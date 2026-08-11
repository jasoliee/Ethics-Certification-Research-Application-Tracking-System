# Requirements Traceability

This file maps implementation areas to the current source documents. Update it when requirements change.

| Area | Primary source | Supporting source | Implementation notes |
| --- | --- | --- | --- |
| Account management | July 20, 2026 account-management requirements | High Fidelity (5), pages 1-8, and supervisor CSV note | Separate names and institutional ID, generated username, role-limited creation, search/filter/pagination, CSV import, status control, reset links, and audit logs. Newer written rules override editable Date Joined, direct password editing, and RES/Admin creation shown in the mockup. |
| Login validation | July 20, 2026 account-management requirements | Existing approved login design | Field errors remain specific; generic auth error appears only after required fields pass validation and credentials do not match an active account. |
| Completeness validation | Consolidated project documentation | System design flow 5 | Block formal submission until all required fields and documents are valid. |
| Adviser endorsement | Consolidated project documentation | RES process memo and system design flow 6 | Adviser verifies receipt image and handles only initial endorsement. |
| RES classification | August 5, 2026 client implementation prompt | High Fidelity reviewer/RES corrections | Mandatory document readiness is rechecked under lock. RES stores only Expedited/Full Board/Exempted classification, basis, actor, and timestamp; the superseded Administrative Screening fields are removed. |
| Reviewer assignment | August 5, 2026 client implementation prompt | High Fidelity reviewer assignment pages | Exactly one Expedited or three Full Board current reviewers are enforced. Reassignment is allowed before final release, supersedes rather than deletes old rows, links replacements, revalidates eligibility/capacity, and immediately revokes old access. |
| Blind review | August 10, 2026 client implementation prompt | High Fidelity (8), pages 54-69; KLD-RES-04-001 and KLD-RES-04-002 | Current assignment owners receive blind private document/workspace access without a conflict-declaration gate. Official 15-item forms display Not Started/In Progress/Completed while retaining Draft/Final persistence; final submission uses an accessible confirmation/result dialog with server-authoritative validation and duplicate protection. Native PDF/image previews use private same-origin non-sandboxed headers, Office files retain the protected fallback, and versioned official PDFs are generated from the persisted complete review. |
| Deadline scheduling | August 10, 2026 implementation handoff | Current Deadline Configuration contract | New terms retain the current-date browser minimum; existing configured historical starts remain editable; Ending Date follows Starting Date; server `after_or_equal` validation remains authoritative and stored term data is not rewritten. |
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

- Exact public QR verification fields.
- Final production hosting, backup, and email service details.
## Applicant revision and certification traceability (August 11, 2026)

| Requirement | Implementation | Verification |
| --- | --- | --- |
| Applicant sees only authorized released feedback | `ApplicationDecisionRelease`, released-comment relation, Applicant controller query | selective release/visibility feature test |
| Maximum two direct-to-Reviewer revision cycles | `ApplicationRevisionWorkflowService::MAX_REVISION_CYCLES`, cycle-linked assignments | revision workflow service/feature coverage |
| Replace only identified documents; preserve versions | `ApplicationRevisionRequirement`, `ApplicationDocumentService::uploadRevision` | immutable and idempotent upload assertions |
| Separate Applicant and Reviewer revision deadlines | `DeadlineProcessAvailability` checks for `revision-period` and `reviewing-revision-period` | workflow feature test plus deadline suite |
| RES generates/releases certificates from official design | `OfficialCertificateGenerationService`, `CertificateReleaseService` | real PDF generation assertion and rendered QA |
| Evaluation precedes explicit claim | `ApplicantCertificateService` | survey/claim feature test |
| Issued versions and background provenance remain immutable | `CertificateVersion`, `CertificateBackground` | regeneration/background isolation test |
| Private, role-scoped access and safe failures | policies/controllers, private disk, failure state/cleanup | cross-user denial and failure feature tests |
