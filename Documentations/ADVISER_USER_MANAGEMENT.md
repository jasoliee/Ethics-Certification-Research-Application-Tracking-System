# Adviser User Management

## Scope

Research Advisers can create, import, view, update, and manage only Student Researcher and Faculty Researcher accounts within their authorized scope. They cannot create advisers, reviewers, or RES Lead accounts and cannot open RES Lead-only profile-option or audit administration routes.

The Adviser interface reuses the same table, status badge, pagination, tooltip, empty-state, form, and Excel-import components as RES Lead User Management. Filters remain in the query string during pagination, and wide tables scroll inside their own container on smaller viewports.

## Account Creation

Individual creation collects split names, email, the correct institutional identifier, and role-specific profile fields. Username and password are server-owned. New accounts remain `pending_setup` until the account holder follows the one-time setup link.

Advisers can select active Year Level, Institute, and Program values. They can see newly restored options immediately but cannot add, rename, deactivate, or restore options.

## Excel Import

Advisers download the current `.xlsx` template for Student Researcher or Faculty Researcher accounts. The selected account type is server-authorized and cannot be overridden by workbook content. Preview, duplicate handling, private storage, confirmation, and setup-email behavior follow [Bulk Account Import](BULK_ACCOUNT_IMPORT.md).

## Authorization Checks

- Route middleware restricts the Adviser prefix to authenticated adviser accounts.
- Policies restrict record access to accounts the adviser created or applicants connected through an authorized research relationship.
- Unauthorized cross-role access is denied and recorded as `auth.authorization_denied` without sensitive metadata.
- Database uniqueness remains authoritative for email, username, and institutional identifier conflicts.
