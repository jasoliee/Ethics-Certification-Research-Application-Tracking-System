# Excel Bulk Account Import

CSV was replaced as the active account-import format because the approved workflow requires role-specific formatting, Excel dropdowns, protected option data, preserved text identifiers, and in-workbook instructions. Only a current ECRATS `.xlsx` template is accepted.

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
3. Keep Row 1 and all worksheet names unchanged; leave the marked Row 2 example intact.
4. Enter accounts on Row 3 onward, save as `.xlsx`, and upload one file up to 2 MB.
5. Review valid, invalid, duplicate, existing-account, and warning categories plus generated usernames.
6. Confirm once to create only the valid preview rows. Invalid rows remain uncreated and can be corrected in a later workbook.
7. ECRATS sends setup links after all account database writes finish and reports delivery separately.

No account is created during validation. The actor-scoped preview expires after 30 minutes. Confirmation atomically renames the preview file, making it single-use across refreshes and double clicks. Sources are deleted after parsing and preview files are deleted after confirmation or expiry cleanup.

## Workbook Contract

Every official workbook contains exactly three worksheets in this order:

- `Accounts`: visible data-entry sheet, frozen header, filter, text-formatted cells, fixed widths, and dropdowns where current options exist.
- `Options`: hidden and protected sheet containing active Year Level, Institution, Department, Program, and Reviewer Classification values used by defined names.
- `Instructions`: visible contract, accepted values, limits, warnings, and upload steps.

Row 2 is ignored only when its identifier is exactly `EXAMPLE-ROW-DO-NOT-IMPORT`. If edited, it becomes ordinary account data and must validate.

## Role Columns

- Student Researcher: First Name, Middle Name, Last Name, Suffix, Email, Student Number, Phone Number, Year Level, Institution, Department, Program.
- Faculty Researcher: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institution, Department, Program, Position / Designation.
- Research Adviser: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institution, Department, Position / Designation.
- Ethics Reviewer: First Name, Middle Name, Last Name, Suffix, Email, Employee ID, Phone Number, Institution, Department, Position / Designation, Reviewer Classification.

Headers, order, account type, worksheet names, visibility, and worksheet count must match exactly. Username, password, role override, account status, setup status, and Date Joined are never import columns.

## Validation and Duplicate Handling

Server validation is authoritative even when Excel shows a dropdown. It checks required fields, valid email syntax without domain allow-listing, controlled values against active database options, reviewer limits, exact identifiers, row and column bounds, and uniqueness.

The first valid occurrence of an email or institutional identifier is eligible. Later identical workbook rows are categorized as duplicates. Existing active or archived identities are categorized as existing and never overwritten. Conflicting email/identifier combinations are invalid. Database unique indexes remain the final concurrency defense during confirmation.

## Workbook Safety

The parser accepts the ZIP-based OOXML contract only. It rejects CSV, XLS, XLSM, XLSB, renamed non-XLSX files, corrupt/encrypted/password-protected workbooks, formulas, unexpected sheets, changed headers, external relationships, macros, ActiveX, OLE, custom UI, query connections, embedded files, extra columns, and excessive rows.

Limits include 2 MB compressed upload size, 250 account rows, 150 archive entries, 20 MB total expanded archive bytes, 1,000 option rows per field, and 10,000 shared strings. XML is parsed only after these archive and structural checks. This is bounded in-memory OOXML handling, not a general-purpose streaming spreadsheet reader.

## Failure Safety

Each account uses a short transaction. An unexpected failure is reported for that row without holding mail delivery or unrelated rows inside one long transaction. Setup email runs only after database writes finish. A delivery failure leaves the account pending and available for controlled resend.
