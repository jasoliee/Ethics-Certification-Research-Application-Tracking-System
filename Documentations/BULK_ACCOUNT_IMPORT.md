# Excel Bulk Account Import

CSV was replaced as the active account-import format because the approved workflow requires role-specific formatting, Excel dropdowns, protected option data, preserved text identifiers, and in-workbook instructions. Only an official ECRATS `.xlsx` workbook contract is accepted. Downloading a fresh template remains recommended, but a prior readable option label can survive a later rename when its active option identity is still known.

## Prerequisite

PHP `ext-zip` is required. Confirm the active CLI configuration with:

```powershell
php --ini
php -m | Select-String zip
composer check-platform-reqs
```

## Workflow

1. Select an account type authorized for the signed-in RES Lead or Adviser.
2. Download a new `.xlsx` template so it contains the latest active options.
3. Keep Row 1 and all worksheet names unchanged; leave the Instructions-sheet `Example Row Marker` unchanged if Row 2 should remain an example.
4. Enter accounts on Row 3 onward, save as `.xlsx`, and upload one file up to 2 MB.
5. Review valid, invalid, duplicate, active-existing, archived-account, restored-account, conflict, and warning categories plus generated usernames.
6. Confirm once to create only the valid preview rows. Invalid rows remain uncreated and can be corrected in a later workbook.
7. ECRATS sends setup links after all account database writes finish and reports delivery separately.

No account is created during validation. The actor-scoped preview expires after 30 minutes. Confirmation atomically renames the preview file, making it single-use across refreshes and double clicks. Sources are deleted after parsing and preview files are deleted after confirmation or expiry cleanup.

## Workbook Contract

Every official workbook contains exactly three worksheets in this order:

- `Accounts`: visible data-entry sheet, frozen header, filter, text-formatted cells, fixed widths, and dropdowns where current options exist.
- `Options`: hidden and protected sheet containing active Year Level, Institute, Program, and Reviewer Classification values used by defined names.
- `Instructions`: visible contract, accepted values, limits, warnings, and upload steps.

The Student Researcher example row is:

| First Name | Middle Name | Last Name | Suffix | Email | Student Number | Phone Number | Year Level | Institute | Program |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Juan | Dela | Cruz | Jr. | juandelacruz@example.com | 20260000 | 09999999999 | 4th Year | Institute of Computing and Digital Innovation | Bachelor of Science in Computer Science |

The sentinel `EXAMPLE-ROW-DO-NOT-IMPORT` is stored as `Example Row Marker` in the visible Instructions sheet. Physical Row 2 is skipped only while that exact sentinel remains intact. Removing or changing the marker makes Row 2 ordinary account data that must pass every normal validation rule. The example row itself contains no hidden marker in an account field.

## Role Columns

- Student Researcher: First Name, Middle Name, Last Name, Suffix, Email, Student Number, Phone Number, Year Level, Institute, Program.
- Faculty Researcher: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institute, Program, Position / Designation.
- Research Adviser: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institute, Position / Designation.
- Ethics Reviewer: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institute, Position / Designation, Reviewer Classification.

Headers, order, account type, worksheet names, visibility, and worksheet count must match exactly. Username, password, role override, account status, setup status, and Date Joined are never import columns.

## Validation and Duplicate Handling

Server validation is authoritative even when Excel shows a dropdown. It checks required fields, valid email syntax without domain allow-listing, controlled values against active database option identities, reviewer limits, row and column bounds, and uniqueness. Phone Number accepts digits only with a maximum of 11 digits. Student Number and Employee ID accept letters, numbers, periods, underscores, and hyphens.

Each invalid row is reported with its Excel row number and, for every failed value, the field, safe submitted value, reason, and expected format. Valid alphanumeric institutional identifiers are not converted to numbers or rejected.

## Stable Dropdown Identity

The official workbook deliberately displays readable option labels. A standard macro-free Excel list-validation cell does not preserve a hidden database ID beside its selected label, and adding ID columns would break the approved role contracts.

ECRATS uses this server-owned compatibility layer:

- `profile_options.id` remains fixed when RES Lead corrects a visible label;
- the outgoing label is recorded in `profile_option_aliases`;
- preview resolves a current label, historical alias, or explicit numeric ID to one active option;
- validation then stores the option's current canonical label; and
- deactivated options and aliases fail new-import validation.

This prevents ordinary label corrections from immediately invalidating an older workbook without trusting workbook-maintained mappings or rewriting historical user records.

The first valid occurrence of an email or institutional identifier is eligible. Later identical workbook rows are categorized as duplicates. Active existing and soft-deleted archived identities appear in separate containers and are never overwritten. Conflicting email/identifier combinations are invalid. Database unique indexes remain the final concurrency defense during confirmation.

## Archived Account Restoration

An archived-account match means the original user row still exists through soft deletion. Import must never create a replacement identity for it.

Only a RES Lead sees Restore and Restore All controls. An Adviser sees guidance to contact the RES Lead and has no restoration route in the Adviser group. Restoration is limited to archived IDs already present in the signed-in actor's unexpired 30-minute preview.

Before restoring, the server:

- locks and reloads the original row with archived records included;
- verifies that its normalized email, institutional identifier, username, and expected account type still match the preview;
- checks for active conflicts on email, identifier, and username; and
- preserves the original user ID, relationships, timestamps, and history.

The restored account becomes active when password setup was previously completed and pending setup otherwise. Individual restorations write `user.archived_account_restored`; Restore All also writes `user.bulk_archived_accounts_restored`. A conflict leaves the original record archived and reports it in the preview.

Restoration refreshes the same preview. A restored row moves to Restored Accounts and is excluded from later import confirmation, so it cannot be recreated.

## Workbook Safety

The parser accepts the ZIP-based OOXML contract only. It rejects CSV, XLS, XLSM, XLSB, renamed non-XLSX files, corrupt/encrypted/password-protected workbooks, formulas, unexpected sheets, changed headers, external relationships, macros, ActiveX, OLE, custom UI, query connections, embedded files, extra columns, and excessive rows.

Limits include 2 MB compressed upload size, 250 account rows, 150 archive entries, 20 MB total expanded archive bytes, 1,000 option rows per field, and 10,000 shared strings. XML is parsed only after these archive and structural checks. This is bounded in-memory OOXML handling, not a general-purpose streaming spreadsheet reader.

## Failure Safety

Each account uses a short transaction. An unexpected failure is reported for that row without holding mail delivery or unrelated rows inside one long transaction. Setup email runs only after database writes finish. A delivery failure leaves the account pending and available for controlled resend.
