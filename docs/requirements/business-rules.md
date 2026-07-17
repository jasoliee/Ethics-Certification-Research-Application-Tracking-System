# Business Rules

These rules are derived from the consolidated project documentation, supporting process/design references, and team/client additions that may be newer than the checked-in documents.

## Submission and Adviser Rules

- Applicants may save drafts without complete requirements.
- Formal submission is blocked until required fields and documents are complete.
- Incomplete applications do not enter the official RES review queue.
- The adviser verifies the receipt image or payment proof before endorsement.
- The adviser may return or endorse the initial submission.
- After adviser endorsement, revisions route directly to reviewers, not back to the adviser.

## RES Rules

- RES Lead/Admin screens adviser-endorsed applications.
- RES Lead/Admin classifies applications as Expedited, Full Board, or Exempted.
- Exempted applications are documented and screened by RES, bypass standard reviewer assignment/review, and proceed through the approved direct certificate/documentation path after RES confirms eligibility.
- RES Lead/Admin assigns reviewers based on discipline, fixed classification, availability, active status, and capacity.
- RES Lead/Admin controls official result release.
- RES Lead/Admin generates and releases certificates only after all prerequisites are complete.

## Reviewer Rules

- Expedited review requires one reviewer.
- Full Board review requires three reviewers.
- Reviewer capacity defaults to 30 active applications.
- Reviewers access only assigned anonymized applications.
- Reviewers must complete assigned reviews; ordinary decline/refusal behavior is not part of the current approved flow.
- Reviewer conflict declaration is a confirmed addition and acts as a required gate before full blind-review access. If a conflict is declared, RES handles replacement or reassignment.

## Decision and Revision Rules

- Reviewer comments remain hidden until official RES release.
- Reviewer decisions include `accepted`, `minor_revision`, `major_revision`, and `disapproved`. Use `disapproved` as the preferred label; map older `rejected` references to `disapproved` unless the team approves a different naming standard.
- Maximum revision cycles default to two.
- Revision submissions preserve document version history.
- Minor and major revisions route directly to assigned reviewers.
- Full Board deliberation may occur outside the system before final decisions are submitted.

## Certificate and QR Rules

- One certificate is generated per approved student group or faculty application.
- Certificate release requires approval and required pre-release steps, including feedback when enabled.
- Each certificate has a unique control number and QR token/link.
- QR access supports public-safe verification of approved certificate metadata.
- Full certificate viewing/downloading requires authentication and authorization.
- Do not expose private certificate files, QR tokens, applicant records, or internal workflow details through public verification.

## Confirmed Additions to Carry Forward

- Exempted classification and direct certificate/documentation path.
- Disapproved/rejected outcome support, with `disapproved` as the preferred wording.
- Public-safe QR/control-number verification plus protected full certificate access.
- RES-controlled anonymization approval before reviewer-visible document access.
- Soft-delete/no-hard-delete policy for records that may need administrative hiding while preserving auditability.

## Details Still Needed Before Coding

- Exact certificate wording for Exempted applications.
- Exact control-number format.
- Exact public certificate metadata fields allowed during QR/control-number verification.
