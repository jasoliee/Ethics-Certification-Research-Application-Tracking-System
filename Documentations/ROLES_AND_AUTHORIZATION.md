# Roles and Authorization

## Role Values

| Role | Stored value | Display behavior |
| --- | --- | --- |
| Applicant | `student_faculty_researcher` | Student Researcher or Faculty Researcher from `users.applicant_type` |
| Adviser | `adviser` | Adviser |
| Reviewer | `reviewer` | Reviewer |
| RES Lead | `res_lead` | RES Lead |

Applicant categories use `student` and `faculty`. New applicant accounts must provide a category through `UserAccountService`. Existing applicants receive `student` during migration as a conservative compatibility default.

## Route Authorization

Each role prefix uses `EnsureUserHasRole`. A user entering another role area is redirected to the canonical dashboard. Authentication remains required for all dashboard, profile, notification, and module routes.

## Record Authorization

- Applicants may view only their own research applications.
- Advisers may view only applications assigned to them.
- Reviewers may view only their reviewer assignments.
- RES Lead users may view administrative application records.

`ResearchApplicationPolicy` and `ReviewerAssignmentPolicy` enforce these record rules. Controllers call `Gate::authorize` before returning record pages.

## Account Creation

`AccountCreationAuthorizationService` allows:

- RES Lead to create Adviser and Reviewer accounts.
- Adviser to create Applicant accounts.
- Applicant and Reviewer roles to create no accounts.

Account creation validates unique usernames and emails, password boundaries, approved roles, and applicant category when applicable.
