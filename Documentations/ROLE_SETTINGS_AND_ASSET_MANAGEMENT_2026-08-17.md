# Role Settings and Managed Assets

Last updated: 2026-08-17

## Scope

The Applicant, Adviser, and RES Lead account areas now expose real, authenticated account data through role-owned Profile and Security & Privacy controls. Reviewer identity remains part of the Adviser account; legacy reviewer settings routes remain capability-gated compatibility endpoints.

## Profile authorization

- Self-service writes are authorized by the `UserPolicy` `updateOwnProfile` ability and always target the authenticated user.
- The service applies a server-owned allowlist. Forged role, account-status, institutional-identifier, Reviewer entitlement, classification, or capacity fields are never persisted.
- Applicant fields cover identity, contact, institution, department, program, and year level where applicable.
- Adviser fields cover identity, contact, institution, department, position, and the expected-endorsement declaration. Reviewer eligibility data is read-only on the Adviser profile.
- RES Lead fields cover permitted identity/contact information plus the separately authorized certificate signatory.
- Every accepted profile write creates a `settings.profile_updated` audit record containing only the changed field names.

## Security & Privacy

- Username, email, and password writes are policy-authorized and rate-limited.
- Email and password changes require the current password. Email changes clear the verification timestamp.
- Passwords are hashed; plaintext credentials are never logged.
- Email/password changes rotate the remember token and revoke all other database-backed sessions while retaining and regenerating the current session.
- Audit actions are `settings.username_updated`, `settings.email_updated`, and `settings.password_updated`.

## RES signatory

- RES Lead may set the printed certificate signatory name and upload a PNG signature.
- The server verifies the actual PNG signature, decoded image type, 2 MB size ceiling, safe dimension range, and an alpha channel or transparency chunk.
- Uploaded signatures are stored on the private local disk and served only through the authenticated RES Settings preview route.
- Certificate rendering verifies the stored SHA-256 and PNG decoding before use. The bundled official signature/name remains the fallback until an authorized replacement is configured.
- Changes affect future generated certificates only; previously issued PDF artifacts are not rewritten.

## Dropdown Options

The controlled dropdown-option create/edit/activate functions now live under the RES Settings **Dropdown Options** tab. The old User Management button was removed and its bookmarked index URL redirects to the Settings tab. Existing authorization, aliases, usage counts, historical labels, throttles, and audits remain in force.

## Background Management

RES Settings now has a **Background Management** tab with separate histories for:

- Certificate Background
- Review Worksheet Background

Each type has an independently active asset, private authorized preview, validated PDF/JPEG/PNG upload, official-default reset, traceable version history, and audited activation. Activations are scoped to future output; rows referenced by issued certificate versions and the issued PDF files themselves remain immutable.

The shared `CertificateBackgroundService` accepts a background type. Call `active(CertificateBackground::TYPE_REVIEW_WORKSHEET)` when rendering a future review worksheet and `active(CertificateBackground::TYPE_CERTIFICATE)` for certificates.

## Verification

Focused coverage is in:

- `tests/Feature/Settings/RoleAccountSettingsTest.php`
- `tests/Feature/Settings/ResAssetSettingsTest.php`
- `tests/Feature/Settings/ResLeadSettingsTest.php`

The focused settings run completed with 19 passing tests and 199 assertions. Blade compilation, the production Vite build, the settings route listing, and targeted Pint checks also pass.
