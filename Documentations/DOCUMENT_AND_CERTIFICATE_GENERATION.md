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

## Certificate Limitation

The repository does not yet contain an approved certificate rendering service, generation route, release policy, or QR verification contract. The OVPRII background asset is prepared for that future generator and is not proof that certificate generation is complete.

## Required Future Controls

- Keep certificate templates, generated certificates, and research documents outside `public/`.
- Authorize generation and download through policies.
- Bind generated output to an immutable application/result version.
- Preserve approved background proportions and print resolution.
- Store generation/release audit events and file hashes.
- Expose only approved public-safe metadata through QR verification.
- Test sample PDF output visually and verify that no text clips or overlaps.

Do not implement certificate wording, signatures, serial numbering, QR visibility, or release conditions without approved requirements.
