# Security Implementation

## Identity and Access

- No public registration or creator-selected passwords.
- Active-account login filter and generic invalid-credential response.
- Session regeneration on login and invalidation on logout.
- Role middleware plus record policies for applications and user management.
- Adviser access limited to owned/assigned applicants.
- RES Lead cannot create or manage another RES Lead through these flows.
- No two-factor authentication controls were added because the requirement explicitly excludes them.

## Abuse Controls

Named rate limits cover account writes, import preview/confirm, setup email, mass actions, notifications, onboarding, and submission. Login and password-reset routes have independent limits.

## Data and Files

- Passwords are hashed; internal creation credentials are random and never disclosed.
- Reset tokens use Laravel's hashed broker storage and are never audited.
- Bulk sources and previews are private, actor-scoped, bounded, and cleaned up.
- XLSX parsing rejects formulas, macros, embedded content, external links, unsafe XML, and oversized archives.
- Private research files, certificates, and payment proofs must never use `public/` storage.

## Auditing

Security-relevant actions record actor, action, subject, bounded metadata, IP address, user agent, and creation time. The RES audit view intentionally omits IP address, user agent, and unrestricted metadata. Authorization denials are captured globally after Laravel converts policy failures to 403 responses.

## Response Protection

No-store caching, `nosniff`, same-origin framing, strict referrer behavior, restricted browser features, and production HTTPS HSTS are applied by shared middleware.

## Known Limits

The repository does not yet provide malware scanning, production object storage, CSP nonces, queue delivery reconciliation, full certificate authorization, or complete blind-review workflows. Deployment controls still matter even when application tests pass.
