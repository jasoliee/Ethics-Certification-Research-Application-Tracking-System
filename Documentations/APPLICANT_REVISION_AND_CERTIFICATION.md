# Applicant Revision and Certification Workflow

Last updated: August 11, 2026

## Implemented outcome

The Applicant `Revision and Certificates` page and RES Lead `Certificate Processing` page now implement the post-review lifecycle as persisted, server-authorized workflows. They replace the former module placeholders.

The authoritative sequence is:

1. Every current Reviewer submits the active review cycle.
2. The application enters `review_submitted_pending_release`; Applicant comments remain hidden.
3. An RES Lead explicitly releases one final decision and a selected set of comments.
4. A revision decision creates a deadline-bound revision record and document-specific replacement requirements.
5. The Applicant uploads immutable replacement versions and submits directly to the same authorized Reviewer set.
6. An approval decision (or exemption) makes the application eligible for RES certificate generation and release.
7. The Applicant completes the evaluation form only after a generated certificate has been released.
8. The Applicant explicitly claims the current certificate version before private preview or download is allowed.

This ordering follows the continuation specification where it is more specific than older planning documents: RES generation/release precedes evaluation, and evaluation precedes claim.

## Source references and fidelity decisions

- Applicant pages were adapted from pages 18–28 of `context_files/references/ECRATS High Fidelity (8).pdf`: Revision Requirements, Detailed Comments, Revision Submission, Evaluation Form, and Certification.
- The requested RES Lead reference pages 106–108 are not present in the supplied high-fidelity files. `ECRATS High Fidelity (8).pdf` has 30 pages and `context_files/local/ECRATS High Fidelity.pdf` has 104 pages. The RES page therefore follows the established dashboard system and the written workflow requirements.
- `context_files/RES CERTIFIACTE.pdf` is the authoritative certificate design. The implementation preserves the source file unchanged and uses a verified clean background and verified signature extracted from it.
- The approved application code is the certificate/control number because existing codes already follow the RES-issued format; a second synthetic identifier is not created.
- The supplied official certificate contains no QR or public-verification area. Certificate access is authenticated and private. A public verification design remains outside this implementation.

## Persisted records

Migration `2026_08_11_000000_create_revision_and_certificate_workflow.php` adds:

- `application_decision_releases`: one authoritative release per application/review cycle.
- `application_revisions`: at most two numbered Applicant revision cycles at the service boundary.
- `application_revision_requirements`: exact source and replacement document links.
- `applicant_survey_responses`: one completed response per application; answers are not copied into audit metadata.
- `certificate_backgrounds`: versioned, validated background assets with one future-generation active pointer.
- `certificates`: one lifecycle aggregate per application, with released and claimed version pointers.
- `certificate_versions`: immutable generated artifacts, template/background hashes, generation actor/time, release actor/time, and claim metadata.
- `review_comments.application_decision_release_id` and `released_by_user_id`.
- `reviewer_assignments.review_cycle`.
- `application_documents.file_sha256` for idempotent same-file revision uploads.

Rollback intentionally refuses while decision, revision, or certificate-version history exists. This prevents issued or relied-upon evidence from being silently discarded.

## Revision behavior

- Applicants see only comments attached to an explicit RES release. Reviewer identities are rendered as `Reviewer 1`, `Reviewer 2`, and so on.
- A minor/major revision release must include at least one `required_revision` comment linked to a protected document.
- The RES-configured `revision-period` must be current when a revision decision is released and remains enforced on upload and submission. The revision also stores its own due date.
- Only document requirements selected by RES become mandatory replacements.
- Each replacement is stored on the private local disk as a new immutable version. The prior database row and bytes remain available to authorized workflow participants.
- Re-uploading identical bytes within the same revision cycle is idempotent and does not create a duplicate version.
- Submission requires every marked replacement and a configured current `reviewing-revision-period`.
- Re-review assignments retain the same Reviewers, link to the prior assignment, and bypass a second Adviser review and initial RES screening.
- Reviewers receive the new current documents plus bounded, read-only access to prior document versions and their own earlier review/comments. An unrelated Reviewer remains denied.
- A third revision cycle is rejected by the service.

## Certificate generation and lifecycle

Eligibility requires either an exemption or a released approved decision. `CertificateReleaseService` uses row locks and idempotency checks so repeated release requests do not create duplicate versions.

Each PDF is generated in A4 portrait format using the verified official resources:

- Official source PDF SHA-256: `998e7a943c81a83afb13df162a85eb08007c4eb2aa1ea51fedfa9909cd5ff960`
- Derived official background SHA-256: `d7332a1bfbca1abd35434b9016008188537f137795fa01222296c103256a848f`
- Official signature SHA-256: `bd83c53334d58e369e4010be3c2b4828c3529d974f2e2c26c8576369666f8ee3` (the source image plus its official transparency mask)

Dynamic fields use authoritative application data: application code, research title, Applicant name/type/institution, current protected document names and latest upload date, review type, approval date, expected study dates/duration, and issue date. Long text is wrapped and font-fitted within reserved zones. Static wording and signatory details follow the supplied official certificate.

Generation writes a private PDF before the database transaction commits the ready version. If generation or persistence fails, the partial file is removed and no released/ready version is exposed. A first-generation failure records `generation_failed` with a bounded internal failure code; regeneration failure leaves the previously issued version intact.

An RES Lead may:

- release one eligible certificate;
- release all currently eligible certificates with an independent per-application summary;
- regenerate an already issued certificate as a new immutable version;
- upload, validate, preview, activate, or reset a certificate background.

Accepted background formats are a decodable one-page portrait A4-compatible PDF, JPEG, or PNG. Raster assets must be at least 596 by 842 pixels. Activation affects future generations only; each existing version retains its background ID and hashes.

Applicants cannot preview or download a merely released certificate. After the required evaluation is stored, an explicit claim binds the Applicant to the current ready version. Regeneration clears the aggregate claim pointer, preserves prior version claim metadata, and requires a new explicit claim of the replacement version.

## Routes and authorization

Applicant routes are under `applicant.revision-certificates.*`; RES routes are under `res.certificates.*` and `res.certificate-backgrounds.*`. All write routes are throttled. Parent/child IDs are checked together in requests, controllers, policies, and services; IDs from another application or certificate return 403/404 rather than crossing ownership boundaries.

Generated PDFs and background files use the private `local` disk. Controller streams add no-store, no-sniff, same-origin framing, no-referrer, and restrictive permissions headers. No public storage symlink or public URL is used.

## Operational checks

After deployment:

1. Run migrations and confirm the official background is initialized by opening RES Certificate Processing.
2. Configure active `revision-period` and `reviewing-revision-period` deadlines for the current academic term before releasing revisions.
3. Confirm `context_files/RES CERTIFIACTE.pdf` and both `resources/certificates` files exist with the hashes above.
4. Exercise release, revision, approval, certificate release, survey, and claim with separate Applicant, Reviewer, and RES Lead accounts.
5. Retain the official source and issued private files in the deployment backup policy.
