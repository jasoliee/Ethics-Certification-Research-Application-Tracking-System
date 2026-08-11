# Document and Certificate Generation

## Current State

The official source reference `context_files/OVPRII.docx` was inspected and its maintained background image was extracted without resizing to:

```text
resources/assets/official/ovprii-document-background.png
```

Verified asset properties:

- Dimensions: 1414 x 2000 pixels
- Size: 629,036 bytes
- SHA-256: `04A7F600AF3BAE57D9F11150107F97C8CBDA858988C586E68C6FB0BBA6925B61`

## Official Reviewer Form Artifacts

KLD-RES-04-001 and KLD-RES-04-002 use `resources/assets/official/rems-review-forms.pdf` as an integrity-checked source. The server-owned catalog maps each finalized worksheet answer and worksheet comment to the correct official page. When the Reviewer submits the complete overall review, the generator also appends a branded continuation record containing the persisted final decision, decision comment, submitted timestamp, and every assignment comment that exists at submission.

Worksheet finalization stores immutable catalog, payload, context, and attestation snapshots but does not create an official artifact. Overall submission locks both final snapshots and the comment record, generates both PDFs, stores hashes and generator/template versions on private storage, and commits only after both artifacts succeed. A failed render removes partial files and leaves the worksheets Final while the overall review remains unsubmitted.

Artifact versions are append-only. A later submitted version supersedes the prior Ready version without deleting its row or private file. Only the current Ready version is listed as official on the RES application page; authenticated nested routes and policy checks protect preview/download. Applicants and Advisers have no artifact route.

Official artifact previews and authenticated browser-native application PDF/image previews use private no-store responses with safe MIME types, `nosniff`, `SAMEORIGIN`, no-referrer, restricted browser-feature permissions, and a CSP limited to no ambient source access, same-origin frame ancestors, and no base URI. CSP `sandbox` is intentionally absent because it prevents some built-in PDF viewers from loading inside the authorized iframe. This does not create public links or alter the stricter first-party HTML fallback used for Word and Excel files.

The styled final-review dialog does not generate documents on the client. It posts to the same authorized overall-submission endpoint, and only the locked server transaction may create both artifacts and return the non-sensitive decision label, submitted timestamp, and same-origin assignment return URL used by the result state. Repeated or stale submissions remain blocked by assignment/review state.

## Certificate implementation

The certificate path is implemented from the separately supplied `context_files/RES CERTIFIACTE.pdf`, not from the OVPRII background. It uses integrity-checked official artwork and signature resources, immutable private PDF versions, policy-protected streams, application-code control numbers, explicit RES release, post-release evaluation, and explicit Applicant claim. Generation/release audit events include provenance and file hashes without private answers or paths.

The remaining certificate limitation is public QR/control-number verification: the supplied official certificate has no QR area and no approved public metadata contract. No QR is added until both are approved. See `Documentations/APPLICANT_REVISION_AND_CERTIFICATION.md` for the complete mapping and operational checks.
## Official RES certificate pipeline (August 11, 2026)

Certificates are now generated as private, immutable PDF versions from the supplied `context_files/RES CERTIFIACTE.pdf` design and verified derived resources. Eligibility, generation success, explicit RES release, Applicant evaluation, and explicit claim are separate server-enforced states. An activated background affects future generations only; existing version records preserve their template/background hashes. Full field mapping, integrity hashes, failure behavior, and operational checks are in `Documentations/APPLICANT_REVISION_AND_CERTIFICATION.md`.
