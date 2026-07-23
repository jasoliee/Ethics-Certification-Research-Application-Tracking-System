# Bulk Account Import

## Workflow

1. Select one authorized account type.
2. Download that role's CSV template.
3. Upload at most 2 MB and 250 non-empty account rows.
4. Review generated usernames, skipped duplicates/existing accounts, and row-level errors.
5. Correct the source file until every row is valid.
6. Confirm the preview once to create all new accounts in a transaction.
7. ECRATS sends setup links after the transaction commits.

No user is created during preview. Confirmation uses an atomic file rename, making the preview token single-use across refreshes and double clicks. Preview/error files expire after 30 minutes and are scoped to the actor under private local storage.

## Templates

The visible template is UTF-8 CSV with role-specific headers and one realistic example row. The example row is ignored automatically during validation. Templates never contain username, password, password confirmation, role override, account status, setup status, or Date Joined columns.

The selected account type is server-owned. A row cannot use a template to create a more privileged role.

CSV does not support cell dropdowns, widths, or wrap-text formatting. ECRATS therefore validates CSV values against the same database-backed Year Level, Institution, Department, and Program options used by account forms. The retained internal XLSX generator applies those spreadsheet-only features for compatibility, but XLSX is not shown as an upload/template choice in the interface.

## Validation

- Exact known headers with required fields and no duplicates.
- Valid role-specific institutional identifier and fields.
- Student number or employee ID remains the primary identity key.
- The first valid occurrence of repeated credentials is kept; later occurrences are skipped.
- Rows matching an existing email or identifier, including archived accounts, are skipped without becoming validation errors.
- New-row emails and identifiers remain protected by database uniqueness.
- Maximum reviewer capacity of 30.
- UTF-8 CSV content in the visible workflow; safe XLSX parsing remains available for backward compatibility.
- No spreadsheet formulas, formula-prefixed cells, HTML, unsupported control characters, macros, embedded objects, or external links.
- Archive entry and expanded-size limits to reduce decompression abuse.

Real format or field errors block confirmation and appear in a scrollable error dialog with their row number and accepted format/value. An invalid import creates no accounts and can provide a CSV error report. Error-report cells are escaped before download to avoid spreadsheet formula injection.

## Failure Safety

Uploaded sources are removed in a `finally` block. Account creation is transactional. Email failure after commit leaves the affected account pending and visible for a controlled resend.
