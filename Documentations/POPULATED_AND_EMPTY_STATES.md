# Populated and Empty States

## General Rule

Dashboard state is based on database records. Views do not replace missing data with production-like hardcoded values. Empty-state assets are local files under `public/assets/empty-states/`.

## Applicant

Populated when the user has a non-archived research application. The newest status update is selected. Active requirements and current document versions determine completion. Without an application, the dashboard shows application, requirements, deadline, and timeline empty states as applicable.

## Adviser

Populated from applications whose `adviser_user_id` matches the authenticated user. The dashboard shows grouped status counts and up to five recent submissions. With no matching applications, the submitted-application section shows its empty state.

## Reviewer

Populated from assignments whose `reviewer_user_id` matches the authenticated user. Counts cover pending, near-deadline, revision, and completed work. With no assignments, the assigned-reviews section shows its empty state.

## RES Lead

Populated from applications in active administrative statuses. Counts and the action table cover screening, assignment, review, and result-release queues. With no matching applications, the administrative-action section shows its empty state.

## Timeline and Deadline States

Active future `deadline_configurations` records drive alerts. Active `timeline_calendar_events` records drive milestones. Missing records render purpose-built empty states rather than fabricated dates.

## Local Demo Data

Normal database seeding retains the intended baseline. Populated reference states are optional and local/testing only:

```powershell
php artisan db:seed --class=DashboardDemoSeeder
```

The demo seeder is idempotent and refuses to run outside local or testing environments.
