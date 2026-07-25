# Dropdown Option Management

## Managed Fields

RES Lead manages five shared catalogs: Year Level, Institution, Department, Program, and Reviewer Classification. Reviewer Classification starts with Expedited, Full Board, and Exempted. Department and Program are intentionally not populated with guessed institutional values.

## Lifecycle

RES Lead can add, rename, deactivate, and restore options from User Management or an individual account form. Values are whitespace-normalized and duplicates are rejected case-insensitively within each field. Usage totals are loaded in grouped queries rather than one query per option.

Inactive options disappear from new account forms and newly generated workbooks. Restoring an option makes it available again. Renaming or deactivating an option does not rewrite existing user profile text, so historical records remain readable. Because profiles currently store strings rather than option foreign keys, usage counts reflect exact current values.

## Authorization and Validation

Only RES Lead can open or mutate the option catalog. Adviser forms consume active values but cannot manage the catalog. Add, update, deactivate, and restore requests use dedicated Form Requests, authorization checks, normalized duplicate validation, rate limiting, and audit events.

Required controlled fields fail server-side validation when no accepted options are active. Newly downloaded Excel templates also add an Instructions warning. Previously downloaded templates do not update automatically.

## Workbook Integration

The hidden, protected `Options` worksheet contains current active values. Defined names supply dropdown validation on controlled `Accounts` columns. These dropdowns improve data entry but are never trusted: uploads are checked against current active database values again.
