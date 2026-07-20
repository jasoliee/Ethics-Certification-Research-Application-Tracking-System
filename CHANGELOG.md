# Changelog

All notable project changes should be documented here.

## Unreleased

### Added

- Role-authorized user-management pages for RES Lead and Research Adviser accounts.
- Separate account name fields, institutional identifiers, creator tracking, and generated usernames.
- Search, role/institution/status filters, pagination, populated states, and empty states for account records.
- Private, bounded CSV account imports with downloadable formatting headers and all-or-nothing processing.
- RES Lead account activation/deactivation and one-time email password-reset links.
- Security audit records for account creation, profile changes, status changes, imports, and password resets.
- Repository-wide project guidelines.
- Team contribution workflow.
- Planning template for large changes.
- Requirements, architecture, database, setup, and security/deployment documentation.
- GitHub pull request and issue templates.

### Changed

- Login validation now keeps missing or malformed field errors separate from generic credential mismatches.
- Adviser account authority now includes Student Researcher and Faculty Researcher creation only.
- RES Lead account authority includes researchers, advisers, and reviewers while prohibiting RES Lead creation.
- Replaced unresolved Laravel README conflict with ECRATS project README.
- Reclassified Exempted workflow, disapproved decisions, QR verification, anonymization approval, reviewer conflict declaration, and no-hard-delete/soft-delete handling as confirmed team/client additions instead of unresolved questions.
