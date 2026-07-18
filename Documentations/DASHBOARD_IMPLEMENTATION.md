# Dashboard Implementation

## Purpose

The dashboard foundation gives each authenticated role a database-backed landing page at `/dashboard` while sharing one consistent application shell. It preserves authentication, role middleware, record policies, populated states, and empty states.

## Role Dashboards

- Student Researcher or Faculty Researcher: active application, requirements, deadline, and milestone timeline.
- Adviser: scoped application counts and the five most recently submitted advised applications.
- Reviewer: scoped assignment counts, nearest review deadline, and the five most recent assignments.
- RES Lead: administrative queue counts, five pending applications, active deadlines, and milestones.

`App\Http\Controllers\Dashboard\DashboardController` selects the role view. `App\Services\Dashboard\DashboardDataService` owns all dashboard queries; Blade templates do not query the database.

## Main Files

Created or substantially updated areas include:

- `app/Enums/` for workflow status and applicant type values.
- `app/Http/Controllers/Dashboard/` for dashboard, profile, notification, application, assignment, and temporary module pages.
- `app/Http/Middleware/ShareDashboardContext.php` for shared navigation, notification, profile, and role-label data.
- `app/Models/`, `app/Policies/`, and `app/Services/Dashboard/` for records, authorization, and query composition.
- `database/migrations/2026_07_18_*` for dashboard records, notifications, deadlines, timelines, and applicant category.
- `resources/views/layouts/dashboard.blade.php` and `resources/views/components/dashboard/` for the shared interface.
- `resources/css/dashboard.css` and `resources/js/dashboard.js` for responsive layout and interactions.
- `tests/Feature/Dashboard/` for role, route, authorization, notification, state, and query-bound coverage.

## Academic Cycle Source

The timeline reads active `timeline_calendar_events` records ordered by `sort_order` and `starts_at`. The semester and academic-year label comes from the first active event's `term_label`. No semester is hardcoded in the view.

## Known Limitations

- Application creation, review workspaces, user management, reports, certificates, and settings remain temporary module pages where their full workflows are not yet implemented.
- The profile page is read-only and links to the current settings workspace.
- Existing applicant accounts created before the applicant category migration default to Student Researcher and should be reviewed if they represent faculty.
- This implementation does not add document preview, certificate generation, account-import, or review-form workflows.

## Maintenance

Use additive migrations. Keep data access in controllers or services, retain role middleware and policies, and add tests whenever route names or status groups change.
