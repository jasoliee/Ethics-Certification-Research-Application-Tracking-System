# Dashboard Implementation

## Purpose

The dashboard foundation gives each authenticated role a database-backed landing page at `/dashboard` while sharing one consistent application shell. It preserves authentication, role middleware, record policies, populated states, and empty states.

## Role Dashboards

- Student Researcher or Faculty Researcher: newest-created owned non-archived application, shared mandatory-requirement completion, configured submission-period state, and milestone timeline.
- Adviser: formally submitted assigned-application counts and the five most recently submitted assigned applications.
- Reviewer: scoped assignment counts, nearest review deadline, and the five most recent assignments.
- RES Lead: administrative queue counts, five pending applications, active deadlines, and milestones.

`App\Http\Controllers\Dashboard\DashboardController` selects the role view. `App\Services\Dashboard\DashboardDataService` owns all dashboard queries; Blade templates do not query the database.

Applicant, Adviser, and RES application queries retain relevant stored records even when an older application has no current academic-term link. Applicant selection uses application creation order so a later edit to an older record cannot replace a newer application. Adviser scope still requires assignment and formal submission; RES scope still requires an administrative queue status. Reviewer assignments, deadlines, and timeline events remain current-term aware.

## Main Files

Created or substantially updated areas include:

- `app/Enums/` for workflow status and applicant type values.
- `app/Http/Controllers/Dashboard/` for dashboard, profile, notification, application, assignment, and temporary module pages.
- `app/Http/Middleware/ShareDashboardContext.php` for shared navigation, notification, profile, and role-label data.
- `app/Models/`, `app/Policies/`, and `app/Services/Dashboard/` for records, authorization, and query composition.
- `database/migrations/2026_07_18_*` for dashboard records, notifications, deadlines, timelines, and applicant category.
- `database/migrations/2026_07_27_000000_complete_initial_application_submission_schema.php` for the unique draft slot, application information/stage, and requirement applicability.
- `database/migrations/2026_07_28_*` for academic terms, deadline linkage, application code sequencing, and Adviser endorsement history.
- `database/migrations/2026_07_29_*` for dated expected duration and historical profile-option aliases.
- `database/migrations/2026_08_02_000000_create_application_screenings_table.php` for the single initial RES decision and classification history.
- `resources/views/layouts/dashboard.blade.php` and `resources/views/components/dashboard/` for the shared interface.
- `resources/css/dashboard.css` and `resources/js/dashboard.js` for responsive layout and interactions.
- `tests/Feature/Dashboard/` for role, route, authorization, notification, state, and query-bound coverage.

## Academic Cycle Source

The timeline reads active `timeline_calendar_events` records for the current active term, ordered by `sort_order` and `starts_at`. Saving RES Lead deadline settings updates the matching timeline events in the same transaction. The semester and academic-year label comes from the configured term/event data; no semester is hardcoded in the view.

## Known Limitations

- RES screening/correction, exact initial reviewer assignment, and the Reviewer Assigned Applications list/details are implemented. Reviewer evaluation, later review/revision forms, reports, result release, and certificates remain temporary or partial module pages.
- The profile page is read-only and links to the current settings workspace.
- Existing applicant accounts created before the applicant category migration default to Student Researcher and should be reviewed if they represent faculty.
- This implementation provides authorized private application-document preview/download and account import, but it does not add certificate generation or review-form workflows.

## Maintenance

Use additive migrations. Keep data access in controllers or services, retain role middleware and policies, and add tests whenever route names or status groups change.
