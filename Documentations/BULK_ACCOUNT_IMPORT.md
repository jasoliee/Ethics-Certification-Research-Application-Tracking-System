# Bulk Account Import

## Workflow

1. Select one authorized account type.
2. Download that role's CSV or XLSX template.
3. Upload at most 2 MB and 250 non-empty account rows.
4. Review generated usernames, valid rows, and row-level errors.
5. Correct the source file until every row is valid.
6. Confirm the preview once to create all accounts in a transaction.
7. ECRATS sends setup links after the transaction commits.

No user is created during preview. Confirmation uses an atomic file rename, making the preview token single-use across refreshes and double clicks. Preview/error files expire after 30 minutes and are scoped to the actor under private local storage.

## Templates

Every template contains `template_version=ECRATS-ACCOUNT-1.0` data and role-specific headers. Templates never contain username, password, password confirmation, role override, account status, or Date Joined columns.

The selected account type is server-owned. A row cannot use a template to create a more privileged role.

## Validation

- Exact known headers with required fields and no duplicates.
- Valid role-specific institutional identifier and fields.
- Unique email and identifier within the file and database.
- Maximum reviewer capacity of 30.
- UTF-8 CSV content or a valid XLSX archive.
- No spreadsheet formulas, formula-prefixed cells, HTML, unsupported control characters, macros, embedded objects, or external links.
- Archive entry and expanded-size limits to reduce decompression abuse.

An invalid import creates no accounts and can provide a CSV error report. Error-report cells are escaped before download to avoid spreadsheet formula injection.

## Failure Safety

Uploaded sources are removed in a `finally` block. Account creation is transactional. Email failure after commit leaves the affected account pending and visible for a controlled resend.
