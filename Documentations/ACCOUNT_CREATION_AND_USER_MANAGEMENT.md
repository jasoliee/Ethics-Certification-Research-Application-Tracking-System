# Account Creation and User Management

## Authority

RES Lead may create and manage non-RES accounts: Student Researcher, Faculty Researcher, Research Adviser, and Ethics Reviewer. RES Lead creation is intentionally unavailable.

Research Adviser may create and manage Student or Faculty Researcher accounts only. Existing applicant access is limited to accounts created by that adviser or applicants assigned through a research application.

## Individual Creation

The full-page account selector opens a choice between Individual and Bulk creation. Individual forms collect split names and role-specific profile fields. Username, password, password confirmation, and Date Joined are not creator inputs.

Year Level, Institution, Department, and Program use shared database-backed options. RES Lead can add an option from User Management or the account form; advisers can select active options but cannot modify the shared catalog. Department and Program intentionally begin without guessed values.

Required role fields include:

- Student Researcher: student number and year level.
- Faculty Researcher: employee ID.
- Research Adviser: employee ID and position.
- Ethics Reviewer: employee ID, classification, and capacity from 1 to 30.

Created accounts remain pending until password setup succeeds. Email failure never activates the account.

## Profile and Identity Changes

Ordinary profile edits cannot change surname or institutional identifier because both determine the username. A separate confirmed identity-correction action changes those values, regenerates the username, audits old/new usernames, and notifies the account email.

## Status and Mass Actions

RES Lead can select multiple accounts to deactivate, archive by soft deletion, or resend setup links. A separate action resends all pending setup emails. Activation is blocked until password setup is complete. Actor self-deactivation/deletion and RES account targets remain prohibited.

## Audit Events

Creation, profile updates, shared-option creation, identity correction, status changes, archives, setup-link generation, email outcomes, import phases, password completion, login outcomes, onboarding, application submission, and authorization denials are recorded without passwords or reset tokens. The RES Lead audit report intentionally hides onboarding-completion and initial password-setup-completion events while retaining those records in the database.
