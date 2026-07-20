# User Management

## Implemented Surfaces

RES Lead User Management and Adviser Applicant Accounts now provide:

- Search by name, email, institutional identifier, institution, or unit.
- Role, institution, and account-status filters.
- Ten-record pagination with populated and empty states.
- Individual Student Researcher, Faculty Researcher, Adviser, and Reviewer creation according to actor permissions.
- Profile details and updates without editable username, role, join date, status, or password fields.
- RES Lead activation/deactivation and secure reset-link actions.
- Header-only CSV template download and bounded bulk import.

## Identity Rules

New accounts require first name, last name, email, and institutional identifier. Middle name, suffix, phone, institution, department, and position are optional. Student researchers use Student Number; every other implemented account type uses Employee ID.

`users.name` remains a generated compatibility display field. `UsernameGenerator` builds a normalized readable username from the first name, last name, and account type, limits it to 30 characters, and adds a numeric collision suffix.

## CSV Import

Required columns are:

```text
account_type,first_name,last_name,email,institutional_identifier,password
```

Optional columns are `middle_name`, `suffix`, `phone_number`, `institution`, `department`, and `position_title`. Valid account types are `student_researcher`, `faculty_researcher`, `adviser`, and `reviewer`, further restricted by the signed-in creator.

Imports are limited to 250 rows and 2 MB. The complete file is validated before a transaction creates any account. Files are stored temporarily on the private local disk and deleted in a `finally` cleanup after success or failure.

## Password Handling

Initial passwords require at least eight characters and are hashed before storage. User-management pages never display a password. RES Lead sends a time-limited Laravel password-reset link to the account email; advisers cannot reset applicant passwords. Reset request and completion events are audited without token values.

## Audit Actions

- `user.created`
- `user.profile_updated`
- `user.status_changed`
- `user.bulk_imported`
- `user.password_reset_requested`
- `user.password_reset_completed`
