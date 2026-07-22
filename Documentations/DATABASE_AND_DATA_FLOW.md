# Database and Data Flow

## Account Data

`users` retains split names plus a generated compatibility `name`. Account identity includes unique username, email, institutional identifier, role, optional applicant type, role-specific fields, creator, status, setup/onboarding timestamps, email-delivery state, and soft deletion.

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
private upload -> parser -> header/content validation -> batched conflict lookup
-> generated username preview -> actor-scoped JSON token
-> atomic single-use confirmation -> transaction -> setup notifications
```

No account rows are written during preview. Source uploads are deleted after parsing. Preview and error payloads contain no password or reset token.

## Application Submission Flow

```text
owner policy -> draft/incomplete check -> active requirement query
-> current completed-document query -> transaction
-> submitted_to_adviser + timestamps -> audit
```

## Audit Data

`audit_logs` stores nullable actor/subject references, action, JSON metadata, IP, user agent, and creation timestamp. Metadata must stay bounded and exclude credentials, reset tokens, private file contents, and raw import rows.

Refer to `docs/architecture/database-design.md` for the broader planned ERD. The current migrations implement only part of that design.
