# Audit Log

## Recorded Data

Security, account-management, and implemented workflow events record the actor when available, action, optional subject reference, bounded metadata, IP address, user agent, and timestamp. Authorization denials, option/alias changes, import phases, account creation, status changes, identity correction, setup delivery, password-reset requests, application drafts/information/documents/submission, deadline updates, Adviser return/endorsement, RES classification, initial reviewer assignment, and Reviewer conflict/form/comment/decision actions are included.

`AuditLogService` recursively removes metadata whose keys indicate passwords, credentials, secrets, tokens, authorization headers, cookies, sessions, CSRF values, or API keys. Long metadata strings are bounded. Raw workbooks, complete imported rows, passwords, setup/reset tokens, and SMTP credentials must never be recorded.

`application.res_classified` stores only the review type, required reviewer count, and resulting status. `application.res_screening_updated` stores the prior/new review type, removed pending-assignment count, and resulting status. `application.reviewers_assigned` stores only the review type, assignment count, and resulting status. Screening notes, classification reasons, reviewer identities/comments, private filenames/paths, and document contents are deliberately absent.

Reviewer actions are `review.conflict_declared`, `review.form_draft_saved`, `review.form_finalized`, `review.comment_added`, `review.comment_removed`, `review.decision_draft_saved`, and `review.decision_submitted`. Their metadata is limited to identifiers, state codes, form type/status, comment scope, decision code, all-submitted state, and resulting application status. Form answers, comment bodies, decision rationale, filenames, private paths, and document contents are deliberately absent.

## RES Lead Report

The report supports search plus actor role, action, result, target type, and inclusive date filters. Pagination preserves all active filters. Normal reporting hides onboarding-completion and initial password-setup-completion events while retaining the underlying records.

No token filter is provided. The current schema has no separate non-secret request, trace, or correlation identifier, and authentication/setup tokens are secrets. A correlation filter should be added only after a safe public identifier is designed and stored.

## Authorization

Only RES Lead can access the account audit report. The rendered table omits unrestricted metadata, IP addresses, user agents, and subject details that are not required for the administrative view.
