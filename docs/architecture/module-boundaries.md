# Module Boundaries

## Identity and Access

Owns users, role profiles, account status, login, role middleware, and active-account checks.

Do not add public registration. Accounts are created by authorized users.

## Academic Reference

Owns institutes, programs, research types, and requirement definitions.

## Applicant Application

Owns application drafts, group members, completeness validation, submission, applicant status, revision upload, feedback submission, and applicant certificate access.

## Documents and Storage

Owns private upload validation, document versioning, anonymized copies, file paths, private disks, and secure downloads.

## Adviser Review

Owns receipt verification, initial return/endorsement, and adviser expected-count monitoring.

## RES Screening and Assignment

Owns eligibility screening, review-type classification, reviewer filtering, capacity snapshots, and assignment records.

## Blind Review

Owns reviewer workspace access, worksheet/checklist responses, comments, decisions, and re-review.

## Decision Release and Revision

Owns held decisions, batch release, revision windows, revision cycle limits, and result visibility.

## Feedback, Certificates, and QR

Owns feedback forms/responses, certificate generation, control numbers, QR tokens, protected certificate viewing, and verification logs.

## Communication and Audit

Owns notifications, Regala message templates/logs, system configuration records, and audit logs.

## Shared Boundary Rules

- A role module may call shared services, but must not bypass policies.
- Direct model access is acceptable for simple reads in services; complex reusable queries may move to repositories.
- Only document/storage services should build private file paths.
- Only certificate services should issue control numbers or QR tokens.
- Only status/workflow services should transition application statuses.
