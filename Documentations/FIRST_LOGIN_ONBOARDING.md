# First Login Onboarding

## Trigger

An onboarding guide is required when `password_setup_completed_at` exists and `onboarding_completed_at` is null. This makes the guide appear after the user completes password setup and signs in for the first time.

The overlay occupies the main workspace while keeping the sidebar available. The permanent Guide control becomes available after initial completion, allowing the same instructions to be reopened later.

## Role Content

- Student/Faculty Researcher: setup, profile review, application preparation, requirements, submission, revisions, and released results.
- Research Adviser: applicant account management, complete-submission review, return/endorse decisions, and deadlines.
- Ethics Reviewer: assignments, conflict declaration, worksheet completion, deadlines, and revision review with confidentiality reminders.
- RES Lead: screening, reviewer management, authorized outcome release, and account administration.

## Completion

`POST /onboarding/complete` may update only the authenticated user. The operation is idempotent and records `user.onboarding_completed` only on the first completion. It is rate-limited and unavailable to guests.
