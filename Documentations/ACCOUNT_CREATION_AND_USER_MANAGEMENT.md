# Account Creation and User Management

## Authority

RES Lead may create and manage non-RES accounts: Student Researcher, Faculty Researcher, Research Adviser, and Ethics Reviewer. RES Lead creation is intentionally unavailable.

Research Adviser may create and manage Student or Faculty Researcher accounts only. Existing applicant access is limited to accounts created by that adviser or applicants assigned through a research application.

## Individual Creation

The full-page account selector opens a choice between Individual and Bulk creation. Individual forms collect split names and role-specific profile fields. Username, password, password confirmation, and Date Joined are not creator inputs. Form sections reuse one spacing class so divider lines, titles, and first field rows remain consistently separated for every account type.

Year Level, Institute, Program, and Reviewer Classification use shared database-backed options. RES Lead can add, rename, deactivate, or restore an option from User Management or the account form; advisers can select active options but cannot modify the shared catalog. Existing profile strings remain unchanged when catalog entries are renamed or deactivated. Prior labels are retained as aliases of one immutable option ID so older official workbooks can resolve an active renamed option and store its current label.

Required role fields include:

- Student Researcher: student number and year level.
- Faculty Researcher: employee ID.
- Research Adviser: employee ID and position.
- Ethics Reviewer: employee ID, classification, and capacity from 1 to 30.

Created accounts remain pending until password setup succeeds. Email failure never activates the account.

Phone Number is optional, digits-only, and limited to 11 digits in individual and workbook creation. Student Number and Employee ID permit letters, numbers, periods, underscores, and hyphens so valid alphanumeric institutional identifiers are retained.

## Excel Creation

Bulk creation accepts only official `.xlsx` workbooks. Each role has exact headers, a realistic Row 2 example controlled by an exact visible Instructions-sheet marker, a hidden protected Options worksheet, an Instructions worksheet, and database-backed dropdowns. Preview separates valid, invalid, duplicate, active-existing, archived, restored, conflict, and warning rows. Confirmation creates only valid preview rows and never overwrites or recreates an existing account. Only RES Lead can restore a preview-listed original archived row; Advisers receive guidance instead. See [Bulk Account Import](BULK_ACCOUNT_IMPORT.md).

When validation fails, the upload surface shows only `An error occurred.` Complete safe details are available in the scrollable Show Errors modal. A red exclamation badge remains until the file changes or later validation succeeds; opening the modal stops only the brief attention animation.

## Profile and Identity Changes

Ordinary profile edits cannot change surname or institutional identifier because both determine the username. A separate confirmed identity-correction action changes those values, regenerates the username, audits old/new usernames, and notifies the account email.

The account-information header preserves three responsive regions: identity on the left, centered application metrics, and Back to User Management on the right. Adviser/Reviewer details keep Advised Applications and Active Review Assignments as one centered horizontal pair on supported widths. Deactivate or Reactivate stays beside Delete Account as one horizontal lifecycle group. Reset/setup resend actions reuse the shared green-outline button and retain authorization, CSRF, rate limiting, and neutral delivery responses. Edit Profile Information aligns to the same form width and no longer duplicates the Dropdown Options shortcut.

## Status and Mass Actions

RES Lead can select multiple accounts to deactivate, archive by soft deletion, or resend setup links. A separate action resends all pending setup emails. Activation is blocked until password setup is complete. Actor self-deactivation/deletion and RES account targets remain prohibited.

## Audit Events

Creation, profile updates, shared-option lifecycle changes, identity correction, status changes, archives, setup-link generation, email outcomes, import phases, password completion, login outcomes, onboarding, application submission, and authorization denials are recorded without passwords or reset tokens. Metadata is recursively sanitized for secret-bearing keys. The RES Lead audit report supports search, role, action, result, target type, and date filters while intentionally hiding onboarding-completion and initial password-setup-completion events. See [Audit Log](AUDIT_LOG.md).
