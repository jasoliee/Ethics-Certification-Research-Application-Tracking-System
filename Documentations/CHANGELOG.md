# Changelog

## 2026-07-22

### Added

- Pending account setup with one-time seven-day password links and delivery state.
- Role-specific onboarding guides with permanent Guide access.
- Role-specific CSV/XLSX templates, preview/confirm import, error reports, and spreadsheet safety checks.
- Mass account deactivate, archive, selected resend, and all-pending resend actions.
- Confirmed surname/identifier correction with username regeneration and notification.
- Applicant initial-submission guard requiring all active documents to be completed.
- MIME-based document icons, authorization-denial auditing, and RES audit history.
- Maintained OVPRII background asset and complete implementation/security documentation set.

### Changed

- Account creators no longer enter usernames, passwords, password confirmation, or Date Joined.
- New accounts remain pending until the account holder chooses a password.
- Footer identifies ECRATS and RES, removes KLD Login, and links the address to Maps.
- Settings is removed from the sidebar but remains in the profile menu.
- Draft Application Status and My Application cards remain empty until accepted submission.

### Known Limitations

- Official document/certificate generation, QR verification, and the later review lifecycle remain incomplete.

## 2026-07-20

### Added

- RES Lead and Adviser user-management workflows.
- Separate account identity fields and institutional identifiers.
- Server-generated usernames and creator tracking.
- CSV account template/import with bounded validation and private cleanup.
- Account status controls, password-reset links, and security audit logs.

### Changed

- Login field validation is separate from generic credential mismatch errors.
- RES Lead can create researcher, adviser, and reviewer accounts but never RES Lead accounts.
- Adviser can create and manage only allowed student and faculty researcher accounts.
- Date created comes from `created_at`; passwords and usernames are not directly edited.

## 2026-07-18

### Added

- Canonical role-aware `/dashboard` route.
- Role-specific profile pages and clickable sidebar profile area.
- Student Researcher and Faculty Researcher account categories.
- Combined applicant Revision and Certificates navigation.
- Header breadcrumb placement and timeline term metadata.
- Reusable delayed research-title tooltip.
- Responsive global KLD footer.
- Dashboard query-count coverage and implementation documentation.

### Changed

- Reduced sidebar width and preserved full role labels.
- Moved notifications out of sidebar navigation.
- Repositioned notification and profile menus.
- Centered Status and Action table columns and normalized status badges.
- Increased login form-panel opacity slightly.
- Reduced dashboard logo transfer size.
- Consolidated repeated dashboard count queries and paginated notification history.

### Preserved

- Authentication, CSRF logout, role middleware, record policies, route authorization, database-driven populated states, and empty states.
