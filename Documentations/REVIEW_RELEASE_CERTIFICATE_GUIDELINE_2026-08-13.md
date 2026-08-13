# Reviewer-Owned Decisions, Releases, and Certificate Continuity

Last updated: August 13, 2026

This document records the implemented continuation contract. Where older documentation describes worksheet finalization by the worksheet Submit button, RES decision overrides, Applicant Reports, or future-only certificate release, this document is authoritative.

## RES application and reporting boundaries

- RES application search is enforced in the backend against application code, research title, status, category, institution, department, program, semester, and academic year where supported. Applicant first, middle, last, and display names are not search fields.
- The RES detail page places the full-width Requirement Checklist first. Application Details, Research Information, and Screening and Classification form the responsive row below it. Checklist status, upload date, and action columns are centered; long filenames wrap and the table keeps a bottom horizontal scrollbar.
- Reports is RES-only. Its Audit Log entry owns the existing filters, term filters, pagination, and access policy. The former user-management audit URL is only a compatibility redirect.
- Applicant navigation and backend routes contain no Reports destination; direct Applicant report URLs are unavailable.

## Applicant adviser selection

- Student applicants see and may submit only an active, setup-complete Research Adviser whose department matches the Student account department.
- Faculty applicants may select any active, setup-complete Research Adviser regardless of department.
- The same rules are applied by the selector query and authoritative request validation. Inactive, archived, incomplete-setup, self, and wrong-role records are rejected. The form preserves old input and displays a department-specific empty state.

## Reviewer workspace and ownership

- Latest Assigned Reviews queries the authenticated Reviewer's current assignment rows and orders by the latest assignment update, assignment date, and identifier. Superseded/revoked assignments, archived applications, replacements owned by another Reviewer, and unrelated Reviewers' records are excluded.
- Supporting-document cells are centered, filenames wrap, private delivery remains assignment-authorized, and Open/Download controls use equal dimensions.
- The right rail and keyboard reading order are Review Comment, Review Worksheet, then Review Assessment. `Review Tools` is no longer used.
- The comment composer has Category, Document, and Comment. `Entire Application` creates an overall comment; a current application document creates a document-linked comment. Display status is `Unresolved`, while the existing resolved/unresolved data model remains intact.
- Protocol question 15 is `Are there any other concerns in the study?`. Recommendation comments require at least 15 non-whitespace characters in both browser and server validation.

## Worksheet completion and atomic review submission

- Worksheet `Submit` stores a `completed` worksheet with `completed_at`; it remains editable. Draft saves clear completion state.
- Only overall `Submit Review` finalizes both completed worksheets, their immutable snapshots, comments, recommendation, and decision together.
- The service locks the application and assignment and performs final snapshots, both private PDF artifacts, submission status, application projection, timestamps, and audit recording in one transaction. A generation failure rolls the final submission back and leaves the worksheets completed/editable. Duplicate final submission is rejected.
- After final submission, normal worksheet, comment, and decision writes are denied by backend state checks as well as disabled controls.
- If informed consent is `No`, the explanation is required and full width, the 15 dependent answers are cleared and stored as not applicable, and generated output marks them `N/A`. If it is `Yes`, all applicable answers are visible and required. Consent item-level comment inputs are not part of the form; the overall recommendation comment remains.

## RES read-only review and decision release

- Reviewers own their decisions, Required Revision document links, comments, and two worksheets. RES cannot edit, resolve, remove, map, or override those records.
- The RES certificate module opens an RES-only read-only workspace with current private supporting documents, submitted decisions, comments, and finalized worksheet artifacts.
- RES releases one exact submitted Reviewer decision. The source submission must belong to the current complete review cycle. The released decision is derived from that submission; request-supplied decisions or document mappings are ignored/not accepted.
- All comments from the selected Reviewer assignment are released with that decision. Document-linked Required Revision comments create replacement requirements. A valid revision decision no longer requires a document-specific comment, so an overall recommendation can still be released without trapping the application.

## Individual and bulk release

- Certificate and decision release remain separate server-authorized operations. Certificate eligibility requires an exemption or an approved released decision.
- Release All has exactly `Certificate`, `Decision`, and `Both Certificate and Decision`. The modal shows precomputed eligible-record counts, explains backend revalidation, and requires confirmation.
- Bulk decision release is automatic only when every current required Reviewer submitted the same decision. Split decisions remain manual in the read-only workspace.
- Records are processed in bounded batches and each record uses its transactional release service. Existing releases are skipped, uniqueness/state checks prevent duplicates, and notifications are emitted only for new releases.
- The result reports Successfully released, Already released, Ineligible, and Failed. `release.bulk_completed` records the RES actor, release type, start/end timestamps, counts, affected application identifiers, and bounded failure details. Individual release audits retain exact per-record provenance.

## Retroactive certificate backgrounds

- Upload, activate, and reset operations make the selected background active and batch-regenerate every active Released or Claimed certificate whose current binary uses another background.
- Regeneration creates a new Ready version and supersedes (but does not delete) the old binary. Applicants resolve the new current version through authenticated preview/download routes.
- Certificate number, recipient/application association, original `generated_at` issue date, release actor/date, certificate status, claim actor/date, and release history are preserved. `regenerated_at` and `regeneration_reason` record the new rendering separately, so a background change never makes an old certificate look newly issued.
- Claimed certificates remain claimed and their claimed-version pointer advances atomically to the new current version.
- Each certificate regenerates in its own transaction. A partial or failed render is deleted, the prior Ready version remains current, the RES summary identifies failures, and the background/regeneration audits record the outcome. Background changes do not send duplicate Applicant release notifications.

## Authorization and audit summary

RES certificate/report routes use RES role middleware and record policies; Reviewer writes require a current owned assignment; Applicant application, revision, evaluation, claim, and certificate routes require ownership. All private documents, forms, backgrounds, and certificates remain outside public storage and are delivered only through nested authorized controllers with private no-store responses.

New/updated audit actions include worksheet draft/completion, final decision submission, exact decision release provenance, individual certificate release, `release.bulk_completed`, background activation, `certificate.background_regenerated`, and `certificate.background_regeneration_completed`. Audit metadata excludes comment bodies, worksheet answers, private paths, and other sensitive content.
