# Dropdown Option Management

## Managed Fields

RES Lead manages four shared catalogs: Year Level, Institute, Program, and Reviewer Classification. Reviewer Classification starts with Expedited, Full Board, and Exempted. The nine client-approved Institutes, four Year Levels, and ten Programs are installed as editable defaults, so configuration does not start empty.

## Lifecycle

RES Lead can add, rename, deactivate, and restore options from User Management or an individual account form. Values are whitespace-normalized and duplicates are rejected case-insensitively within each field. Usage totals are loaded in grouped queries rather than one query per option.

`profile_options.id` is the immutable backend identity. When a visible label changes, ECRATS stores the prior normalized label in `profile_option_aliases` against the same option ID. A current label, historical alias, or numeric option ID can resolve to that active identity before validation. The canonical current label is stored on a newly created/imported account.

Inactive options disappear from new account forms and newly generated workbooks. Their IDs and aliases also stop resolving for new account writes. Restoring an option makes its current label and historical aliases available again. Renaming or deactivating an option does not rewrite existing user profile/application snapshot text, so historical records remain readable. Usage totals include the current label and labels retained for the same identity.

Current and historical labels are unique case-insensitively within each field. ECRATS rejects adding or renaming an option to a label already owned by another current or historical identity.

## Authorization and Validation

Only RES Lead can open or mutate the option catalog. Adviser forms consume active values but cannot manage the catalog. Each Institute also has a required, unique 2-to-12-character acronym that RES Lead can edit beside its visible label. Add, update, deactivate, and restore requests use dedicated Form Requests, authorization checks, normalized duplicate validation, rate limiting, and audit events.

The Institute acronym is resolved through the same immutable option identity and historical aliases. New Application IDs use the current configured acronym, while already-issued IDs remain unchanged. Official certificates show the Institute label together with its configured acronym.

Required controlled fields fail server-side validation when no accepted options are active. Newly downloaded Excel templates also add an Instructions warning. Previously downloaded templates do not update their visible dropdown list automatically, but a selected old label may remain valid when it resolves to an active option's server-owned alias.

## Workbook Integration

The hidden, protected `Options` worksheet contains current active readable values. Defined names supply dropdown validation on controlled `Accounts` columns. Excel list-validation cells store the selected visible label, not a separate hidden identity, and the approved Accounts columns contain no option-ID fields. ECRATS therefore resolves uploaded labels through `profile_options.id` and `profile_option_aliases` on the server. Dropdowns improve data entry but are never trusted, and unknown or inactive identities fail validation.
