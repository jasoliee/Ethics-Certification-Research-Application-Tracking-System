# Security and Deployment Checklist

Use this before production deployment and before any school-facing pilot.

## Environment and Secrets

- `.env` is not committed.
- Production `APP_ENV=production`.
- Production `APP_DEBUG=false`.
- `APP_KEY` exists and is secret.
- Database credentials are not in tracked files.
- Mail credentials are not in tracked files.
- File storage credentials are not in tracked files.

## Database

- MySQL database exists.
- Migrations run successfully.
- Backups are configured.
- Restore process is documented and tested.
- No destructive database command is run without approval.

## File Uploads

- Upload file types are allowlisted.
- File sizes are limited.
- Private documents are stored outside `public/`.
- Certificate files are stored privately.
- Download routes enforce authorization.

## Authentication and Authorization

- No public registration.
- Disabled accounts cannot log in.
- Role middleware is active.
- Policies enforce record ownership and assignments.
- Reviewer workspace hides applicant identity.
- Applicant views hide reviewer identity.
- Certificate access is protected.

## Workflow Security

- Incomplete applications cannot enter official review.
- Reviewer comments are held until official release.
- Revision cycle limits are enforced.
- Certificate generation requires accepted status and feedback when enabled.
- Regala and notifications do not leak unreleased decisions.

## Audit and Logging

- Major workflow actions create audit records.
- Login/account changes are logged where appropriate.
- Certificate verification attempts are logged.
- Logs do not contain secrets or private file contents.

## Deployment Verification

- `composer install --no-dev --optimize-autoloader` has run in production deployment.
- `php artisan config:cache` has run after environment setup.
- `php artisan route:cache` is safe for current routes.
- `npm.cmd run build` or equivalent asset build has completed.
- HTTPS is enabled.
- Queue worker strategy is defined.
- Scheduler strategy is defined.

## Rollback

- Previous release is available.
- Database backup exists before migration.
- File storage backup exists.
- Rollback steps are documented.
