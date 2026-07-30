# Populated and Empty States

## General Rule

Dashboard state is based on database records. Views do not replace missing data with production-like hardcoded values. Empty-state assets are local files under `public/assets/empty-states/`.

## Applicant

Populated when the user has an owned non-archived research application, including an editable draft. The newest-created record is selected; later edits to an older application do not displace it. Applicable active mandatory requirements and current document versions determine completion. Without an application, the dashboard shows application, requirements, deadline, and timeline empty states as applicable.

## Adviser

Populated only from formally submitted, non-archived applications whose `adviser_user_id` matches the authenticated user. The dashboard shows grouped status counts and up to five recent submissions. Relevant records are not hidden solely because their academic-term link is absent or historical. Drafts, unsubmitted records, and another Adviser's records never populate the view.

## Reviewer

Populated from assignments whose `reviewer_user_id` matches the authenticated user. Counts cover pending, near-deadline, revision, and completed work. With no assignments, the assigned-reviews section shows its empty state.

## RES Lead

Populated from applications in active administrative statuses. Counts and the action table cover screening, assignment, review, and result-release queues, including relevant stored records without a current term link. With no matching applications, the administrative-action section shows its empty state.

## Timeline and Deadline States

For Applicants, the highest-priority active current-term deadline whose key ends in `application-submission` and whose audience is Applicant or all roles drives upcoming, open, manual-open, and closed states. Other role alerts continue to use their scoped active current-term deadline queries. Current-term active `timeline_calendar_events` records drive milestones. Missing records render purpose-built empty states rather than fabricated dates.

## Local Demo Data

Normal database seeding retains the intended baseline. Populated reference states are optional and local/testing only:

```powershell
php artisan db:seed --class=DashboardDemoSeeder
```

The demo seeder is idempotent and refuses to run outside local or testing environments.
