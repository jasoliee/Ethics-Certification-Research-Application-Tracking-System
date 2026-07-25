# Routes and Navigation

## Canonical Entry

`GET /dashboard` is named `dashboard`. The authenticated user's role determines which dashboard view and query set are returned. Login, guest redirects, role middleware redirects, and the Home sidebar item all use this route.

Legacy role landing URLs remain as redirects to `/dashboard` so existing bookmarks do not become 404 responses. There are no role-specific dashboard routes.

## Shared Routes

| Method | URI | Route name | Purpose |
| --- | --- | --- | --- |
| GET | `/dashboard` | `dashboard` | Role-specific authenticated dashboard |
| POST | `/notifications/mark-all-read` | `notifications.mark-all-read` | Mark the current user's notifications read |
| POST | `/logout` | `logout` | End the authenticated session |

## Role Prefixes

- Applicant: `/student-faculty-researcher`, route prefix `applicant.`
- Adviser: `/adviser`, route prefix `adviser.`
- Reviewer: `/reviewer`, route prefix `reviewer.`
- RES Lead: `/res-lead`, route prefix `res.`

Each role owns named notification, profile, settings, and module routes. Direct access to another role's prefix is rejected by `role` middleware and redirected to `dashboard`.

## Account Administration Routes

Adviser applicant administration is under `/adviser/applicants` with route prefix `adviser.applicants.`. RES Lead administration is under `/res-lead/users` with route prefix `res.users.`.

Both authorized surfaces provide list, create, store, `.xlsx` import form/upload/confirmation/template, show, edit, update, username correction, and setup/reset-link actions. RES Lead additionally provides mass actions, status changes, `/audit-log`, and `/profile-options` management. Import templates no longer use a format route parameter; the only active format is `.xlsx`.

Profile-option writes use POST for add, PUT for rename, and PATCH for deactivate/restore. All write routes retain CSRF protection, role authorization, Form Request validation, and named rate limits.

## Applicant Navigation

The applicant sidebar contains Home, Application, Revision and Certificates, Reports, and Settings. The combined destination is:

- URI: `/student-faculty-researcher/revision-certificates`
- Name: `applicant.revision-certificates.index`

The old applicant Reviewer and Certificates URLs redirect to the combined page for compatibility but are not sidebar items.

## Breadcrumbs

Controllers provide arrays with `label`, named `route`, and optional `parameters`. Previous items render as links. The final item is plain text with `aria-current="page"` and green styling. Breadcrumbs render inside the shared top header.

## External KLD Link

The sidebar logo opens `https://kld.edu.ph/profile.php` in a separate tab with `noopener noreferrer` protection.
