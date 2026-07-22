# Roles and Authorization

## Role Values

| Role | Stored value | Display behavior |
| --- | --- | --- |
| Applicant | `student_faculty_researcher` | Student Researcher or Faculty Researcher from `users.applicant_type` |
| Adviser | `adviser` | Research Adviser |
| Reviewer | `reviewer` | Ethics Reviewer |
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

- RES Lead to create Student Researcher, Faculty Researcher, Adviser, and Reviewer accounts.
- Adviser to create Student Researcher and Faculty Researcher accounts.
- No role to create another RES Lead account.
- Applicant and Reviewer roles to create no accounts.

`UserPolicy` also limits account records and actions:

- RES Lead can view and edit non-RES-Lead profiles, change status, send reset links, and import approved account types.
- Adviser can view and edit only applicants created by that adviser or linked through an assigned research application.
- Adviser cannot change account status, but may resend setup/reset links for applicants the adviser is authorized to manage.

Account creation validates split names, unique email and institutional identifier, approved roles, applicant category, and role-specific academic/reviewer fields. Username and the initial random internal credential are generated server-side. The account holder chooses an 8 to 64 character password through the one-time setup form; creators never enter or view it.
