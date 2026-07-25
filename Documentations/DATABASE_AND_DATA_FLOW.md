# Database and Data Flow

## Account Data

`users` retains split names plus a generated compatibility `name`. Account identity includes unique username, email, institutional identifier, role, optional applicant type, role-specific fields, creator, status, setup/onboarding timestamps, email-delivery state, and soft deletion.

`profile_options` stores normalized, active/inactive shared values for Year Level, Institution, Department, Program, and Reviewer Classification with ordering and creator attribution. User profile fields remain strings so renamed or deactivated catalog values do not silently rewrite historical records. Reviewer classification is likewise stored as a bounded string rather than a PHP enum.

The additive onboarding migration backfills existing users as already set up so a deployment does not lock out established accounts. Fresh seeders explicitly mark their active test/admin accounts the same way.

## Account Creation Flow

```text
Form Request -> policy/creation authority -> UserAccountService transaction
-> generated username + hashed random credential -> audit
-> password broker token -> notification -> delivery state
-> user sets password -> active account -> onboarding
```

## Bulk Flow

```text
private .xlsx upload -> bounded OOXML and exact-contract validation
-> active-option validation -> batched conflict and username lookup
-> categorized preview with valid rows in actor-scoped JSON
-> atomic single-use confirmation -> short transaction per valid account
-> setup notifications after database writes
```

No account rows are written during preview. Invalid, later duplicate, and existing-account rows are excluded from the confirmation payload. Source uploads are deleted after parsing. Preview payloads contain no password or reset token and expire after 30 minutes.

## Application Submission Flow

```text
owner policy -> draft/incomplete check -> active requirement query
-> current completed-document query -> transaction
-> submitted_to_adviser + timestamps -> audit
```

## Audit Data

`audit_logs` stores nullable actor/subject references, action, JSON metadata, IP, user agent, and creation timestamp. Metadata is recursively sanitized and bounded. It must exclude credentials, reset/setup tokens, private file contents, raw import rows, cookies, sessions, authorization headers, CSRF values, and API keys.

Refer to `docs/architecture/database-design.md` for the broader planned ERD. The current migrations implement only part of that design.
