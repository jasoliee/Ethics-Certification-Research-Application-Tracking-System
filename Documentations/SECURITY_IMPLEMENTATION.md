# Security Implementation

## Identity and Access

- No public registration or creator-selected passwords.
- Active-account login filter and generic invalid-credential response.
- Session regeneration on login and invalidation on logout.
- Role middleware plus record policies for applications and user management.
- Applicant application access limited to the owning account while Adviser access additionally requires formal submission and assignment.
- RES Lead cannot create or manage another RES Lead through these flows.
- No two-factor authentication controls were added because the requirement explicitly excludes them.

## Abuse Controls

Named rate limits cover account writes, import preview/confirm/restore, setup email, mass actions, notifications, onboarding, application writes/uploads, and submission. Login and password-reset routes have independent limits.

## Data and Files

- Passwords are hashed; internal creation credentials are random and never disclosed.
- Reset tokens use Laravel's hashed broker storage and are never audited.
- Bulk sources and previews are private, actor-scoped, bounded, single-use, and cleaned up after parsing, confirmation, or expiry.
- Archived-account restoration is RES Lead-only, preview-ID-bound, actor-bound, time-bound, row-locked, identity-rechecked, and blocked by active email, identifier, or username conflicts.
- Only structurally valid `.xlsx` is accepted. Parsing rejects renamed files, encryption/password protection, formulas, macros, embedded/ActiveX/OLE content, external relationships, unexpected sheets, changed headers, excessive columns/rows, and oversized archives.
- Excel dropdowns are convenience controls only; every controlled value is checked against current active database options on the server.
- Email validation follows standards-compatible syntax and does not impose a Gmail-only or fixed-domain rule.
- Application requirement files use randomized names on the private `local` disk. Preview and download are controller-streamed only after parent-application policy checks.
- Private research files, certificates, and payment proofs must never use `public/` storage.

## Auditing

Security-relevant actions record actor, action, subject, bounded metadata, IP address, user agent, and creation time. Metadata keys indicating passwords, credentials, secrets, tokens, authorization, cookies, sessions, CSRF values, or API keys are removed recursively before persistence. The RES audit view intentionally omits IP address, user agent, unrestricted metadata, and secret-token filtering. Authorization denials are recorded before the role middleware redirects cross-role requests; policy denials remain 403 responses.

## Response Protection

No-store caching, `nosniff`, same-origin framing, strict referrer behavior, restricted browser features, and production HTTPS HSTS are applied by shared middleware.

## Known Limits

The repository does not yet provide malware scanning, production object storage, CSP nonces, queue delivery reconciliation, a safe public audit correlation identifier, full certificate authorization, or complete blind-review workflows. The custom parser is intentionally limited to the official bounded ECRATS workbook contract. Deployment controls still matter even when application tests pass.
