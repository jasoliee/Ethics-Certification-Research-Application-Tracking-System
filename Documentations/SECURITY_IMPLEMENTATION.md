# Security Implementation

## Identity and Access

- No public registration or creator-selected passwords.
- Active-account login filter and generic invalid-credential response.
- Session regeneration on login and invalidation on logout.
- Role middleware plus record policies for applications and user management.
- Applicant application access is limited to the owning account while Adviser access additionally requires formal submission and assignment.
- Adviser decisions repeat assignment, initial-cycle, completeness, deadline, and status checks inside a row-locked transaction before changing workflow state.
- RES classification, screening correction, and reviewer assignment repeat role, record state, persisted readiness, reviewer eligibility, and capacity checks inside row-locked transactions before changing workflow state. A correction cannot remove assignments after review work starts.
- Reviewer list/detail queries are owner-scoped, assignment details omit Applicant and Adviser profile identity, and private document access requires an assignment to the parent application.
- RES Lead cannot create or manage another RES Lead through these flows.
- No two-factor authentication controls were added because the requirement explicitly excludes them.

## Abuse Controls

Named rate limits cover account writes, import preview/confirm/restore, setup email, mass actions, notifications, onboarding, application writes/uploads, formal submission, Adviser decisions, and RES classification/assignment. Login and password-reset routes have independent limits.

## Data and Files

- Passwords are hashed; internal creation credentials are random and never disclosed.
- Reset tokens use Laravel's hashed broker storage and are never audited.
- Bulk sources and previews are private, actor-scoped, bounded, single-use, and cleaned up after parsing, confirmation, or expiry.
- Archived-account restoration is RES Lead-only, preview-ID-bound, actor-bound, time-bound, row-locked, identity-rechecked, and blocked by active email, identifier, or username conflicts.
- Only structurally valid `.xlsx` is accepted. Parsing rejects renamed files, encryption/password protection, formulas, macros, embedded/ActiveX/OLE content, external relationships, unexpected sheets, changed headers, excessive columns/rows, and oversized archives.
- Excel dropdowns are convenience controls only; every controlled value must resolve to a current active database option ID through its current label or server-owned historical alias.
- Email validation follows standards-compatible syntax and does not impose a Gmail-only or fixed-domain rule.
- Application requirement files use randomized names on the private `local` disk. Preview and download are controller-streamed only after parent-application policy checks. Office fallback responses are no-store, same-origin framed, and expose no private storage path.
- Reviewer candidates are server-filtered and revalidated after application/reviewer row locks. Applicant and Adviser identities are excluded from assignment, and a Reviewer at capacity cannot be assigned from a stale page.
- Private research files, certificates, and payment proofs must never use `public/` storage.

## Auditing

Security-relevant actions record actor, action, subject, bounded metadata, IP address, user agent, and creation time. Metadata keys indicating passwords, credentials, secrets, tokens, authorization, cookies, sessions, CSRF values, or API keys are removed recursively before persistence. RES classification/assignment events additionally omit screening notes, classification reasons, private documents/paths, and reviewer comments. The RES audit view intentionally omits IP address, user agent, unrestricted metadata, and secret-token filtering. Authorization denials are recorded before the role middleware redirects cross-role requests; policy denials remain 403 responses.

## Response Protection

No-store caching, `nosniff`, same-origin framing, strict referrer behavior, restricted browser features, and production HTTPS HSTS are applied by shared middleware.

## Known Limits

The repository does not yet provide malware scanning, production object storage, CSP nonces, queue delivery reconciliation, a safe public audit correlation identifier, reviewer-declared conflict handling, full certificate authorization, or complete blind-review workflows. The custom parser is intentionally limited to the official bounded ECRATS workbook contract. Deployment controls still matter even when application tests pass.
