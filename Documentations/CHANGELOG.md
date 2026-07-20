# Changelog

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
