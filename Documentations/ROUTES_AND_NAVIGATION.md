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

Both authorized surfaces provide list, create, store, `.xlsx` import form/upload/confirmation/template, show, edit, update, username correction, and setup/reset-link actions. RES Lead additionally provides mass actions, status changes, and `/profile-options` management. The Audit Log is under `/res-lead/reports/audit-log`; `/res-lead/users/audit-log` only redirects old bookmarks. Import templates no longer use a format route parameter; the only active format is `.xlsx`.

Profile-option writes use POST for add, PUT for rename, and PATCH for deactivate/restore. All write routes retain CSRF protection, role authorization, Form Request validation, and named rate limits.

## Applicant Navigation

The applicant sidebar contains Home, Application, and Revision and Certificates. Applicant Reports routes are not registered; Reports and Audit Log are RES-only. Settings remains available through the profile menu. The combined destination is:

- URI: `/student-faculty-researcher/revision-certificates`
- Name: `applicant.revision-certificates.index`

The old applicant Reviewer and Certificates URLs redirect to the combined page for compatibility but are not sidebar items.

## Application Routes

Applicant routes under `/student-faculty-researcher/applications` provide index, create, store, edit, update, detail, draft discard, requirements, individual/batch private requirement upload, current-document removal, authorized preview/download, and formal submission. Application writes, uploads, and submissions have named throttles in addition to CSRF, role middleware, Form Requests, and record policies.

Adviser routes under `/adviser/applications` provide a scoped submitted-application list, authorized detail, private current-document preview/download, `POST /{researchApplication}/return`, and `POST /{researchApplication}/endorse`. Decision routes require dedicated Form Requests, assignment policy checks, a complete initial submission, and an available Adviser Endorsement deadline.

RES application routes implement the post-endorsement queue through initial reviewer assignment:

| Method | URI | Route name | Purpose |
| --- | --- | --- | --- |
| GET | `/res-lead/applications` | `res.applications.index` | Searchable/filterable post-endorsement queue |
| GET | `/res-lead/applications/{researchApplication}` | `res.applications.show` | Screening details, requirement checklist, and saved classification |
| POST | `/res-lead/applications/{researchApplication}/classification` | `res.applications.classification.store` | Save one validated RES classification |
| PUT | `/res-lead/applications/{researchApplication}/classification` | `res.applications.classification.update` | Correct the saved screening under started-work safeguards |
| GET | `/res-lead/applications/{researchApplication}/reviewers` | `res.applications.reviewers.index` | Select eligible reviewers or view saved assignment result |
| POST | `/res-lead/applications/{researchApplication}/reviewers` | `res.applications.reviewers.store` | Save the exact required initial reviewer set |

The queue lists 15 records per page only after they enter an Adviser-endorsed or later RES status. Classification, correction, and assignment writes use the named `res-workflow` throttle, RES role middleware, Form Requests, policy checks, and locked workflow services. Existing nested RES private-document preview/download routes remain policy-authorized.

Reviewer routes now provide the real assigned-work surface:

| Method | URI | Route name | Purpose |
| --- | --- | --- | --- |
| GET | `/reviewer/assignments` | `reviewer.assignments.index` | Owner-scoped searchable/filterable assigned applications |
| GET | `/reviewer/assignments/{reviewerAssignment}` | `reviewer.assignments.show` | Policy-authorized assigned application details |
| GET | `/reviewer/assignments/{reviewerAssignment}/workspace` | `reviewer.assignments.workspace` | Current-assignment blind review workspace |
| PUT | `/reviewer/assignments/{reviewerAssignment}/forms/{reviewFormType}` | `reviewer.assignments.forms.update` | Save or finalize one official form |
| POST | `/reviewer/assignments/{reviewerAssignment}/comments` | `reviewer.assignments.comments.store` | Add an assignment-owned comment; returns JSON for asynchronous requests |
| PUT | `/reviewer/assignments/{reviewerAssignment}/comments/{reviewComment}` | `reviewer.assignments.comments.update` | Edit an owned unsubmitted comment; returns JSON for asynchronous requests |
| PATCH | `/reviewer/assignments/{reviewerAssignment}/comments/{reviewComment}/status` | `reviewer.assignments.comments.status` | Resolve or reopen an owned comment; returns JSON for asynchronous requests |
| DELETE | `/reviewer/assignments/{reviewerAssignment}/comments/{reviewComment}` | `reviewer.assignments.comments.destroy` | Remove an owned unsubmitted comment; returns `204` for asynchronous requests |
| POST | `/reviewer/assignments/{reviewerAssignment}/review` | `reviewer.assignments.review.store` | Save a decision draft or submit the final review |
| GET | `/reviewer/applications/{researchApplication}/documents/{applicationDocument}/preview` | `reviewer.applications.documents.preview` | Assignment-gated private preview or Office fallback |
| GET | `/reviewer/applications/{researchApplication}/documents/{applicationDocument}/download` | `reviewer.applications.documents.download` | Assignment-gated private download |
| GET | `/reviewer/reviews` | `reviewer.reviews.index` | Owner-scoped assigned/revision/completed task tabs |
| GET | `/reviewer/notifications` | `reviewer.notifications.index` | Reviewer notification history opened from the top-bar bell |
| GET | `/reviewer/settings` | `reviewer.settings.index` | Functional profile and account-security settings |

The Reviewer sidebar contains only Home and Assignments. Review remains available to dashboard cards and authorized direct links, Notifications is available through the top-bar bell, and Settings is available through the avatar/profile menu. All listed backend routes remain registered and role-protected.

Reviewer write routes use the named `reviewer-workflow` throttle plus role middleware, Form Requests, policy checks, CSRF protection, the configured Reviewer Submission window, and locked service operations. JSON comment responses contain server-rendered escaped markup so asynchronous updates match the normal Blade fallback. Result-release routes remain future work; Reviewer comments and decisions are not exposed through Applicant routes.

Document preview routes never expose private storage paths. PDF and browser-safe images may stream inline; Word and Excel files stay within the authorized same-origin fallback and protected download flow until a privacy-preserving local renderer is approved. No private document is sent to an external Office viewer.

## Import Restoration Routes

Only the RES Lead user-management group defines:

- `POST /res-lead/users/import/restore-account` as `res.users.import.restore-account`; and
- `POST /res-lead/users/import/restore-all` as `res.users.import.restore-all`.

Both routes require the current actor-owned preview token and use the import-confirm throttle. No equivalent Adviser restore route exists.

## Breadcrumbs

Controllers provide arrays with `label`, named `route`, and optional `parameters`. Previous items render as links. The final item is plain text with `aria-current="page"` and green styling. Breadcrumbs render inside the shared top header.

## External KLD Link

The sidebar logo opens `https://kld.edu.ph/profile.php` in a separate tab with `noopener noreferrer` protection.
