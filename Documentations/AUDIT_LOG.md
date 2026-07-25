# Audit Log

## Recorded Data

Security and account-management events record the actor when available, action, optional subject reference, bounded metadata, IP address, user agent, and timestamp. Authorization denials, option changes, import phases, account creation, status changes, identity correction, setup delivery, and password-reset requests are included.

`AuditLogService` recursively removes metadata whose keys indicate passwords, credentials, secrets, tokens, authorization headers, cookies, sessions, CSRF values, or API keys. Long metadata strings are bounded. Raw workbooks, complete imported rows, passwords, setup/reset tokens, and SMTP credentials must never be recorded.

## RES Lead Report

The report supports search plus actor role, action, result, target type, and inclusive date filters. Pagination preserves all active filters. Normal reporting hides onboarding-completion and initial password-setup-completion events while retaining the underlying records.

No token filter is provided. The current schema has no separate non-secret request, trace, or correlation identifier, and authentication/setup tokens are secrets. A correlation filter should be added only after a safe public identifier is designed and stored.

## Authorization

Only RES Lead can access the account audit report. The rendered table omits unrestricted metadata, IP addresses, user agents, and subject details that are not required for the administrative view.
