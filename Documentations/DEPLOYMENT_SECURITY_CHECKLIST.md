# Deployment Security Checklist

## Before Deployment

- [ ] Use PHP 8.3 and satisfy `composer check-platform-reqs`.
- [ ] Keep `.env` outside version control and generate a production `APP_KEY` securely.
- [ ] Set `APP_ENV=production`, `APP_DEBUG=false`, and a real HTTPS `APP_URL`.
- [ ] Configure secure database credentials with least privilege.
- [ ] Configure an approved institutional SMTP account; do not use localhost links.
- [ ] Keep password reset expiry at the approved seven-day value unless policy changes.
- [ ] Set secure session cookies and an appropriate trusted proxy configuration.
- [ ] Store uploads, previews, certificates, and payment proofs on private storage.
- [ ] Restrict filesystem permissions for `.env`, storage, logs, and generated files.
- [ ] Confirm no credentials, tokens, private paths, or reference documents are in Git history.

## Release Checks

- [ ] Run `composer validate --strict`.
- [ ] Run `composer check-platform-reqs`.
- [ ] Run `composer audit --locked` and resolve approved advisories. As of 2026-07-22, locked `guzzlehttp/guzzle 7.14.1` has four medium advisories fixed by `7.15.1` or later.
- [ ] Run `npm.cmd audit --audit-level=high`.
- [ ] Run `php artisan route:list` and review public/authenticated boundaries.
- [ ] Run `php artisan migrate:status`, back up the database, then run approved additive migrations.
- [ ] Run `php artisan test`.
- [ ] Run `npm.cmd run build` and deploy the generated Vite assets.
- [ ] Run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` after environment configuration.
- [ ] Verify setup emails use HTTPS links and contain no password.
- [ ] Verify pending/inactive accounts cannot log in.
- [ ] Verify role and record-level forbidden cases in the deployed environment.
- [ ] Verify private documents cannot be fetched directly from the web root.

## Operations

- [ ] Use HTTPS redirects, HSTS, current TLS, and secure headers at the application/proxy layers.
- [ ] Monitor failed login, authorization denial, setup email failure, and import audit events.
- [ ] Rotate mail/database credentials and app secrets under an approved process.
- [ ] Back up and restore-test the database and private storage together.
- [ ] If queues are enabled later, supervise `php artisan queue:work` and monitor failed jobs.
- [ ] Schedule retention/cleanup for expired imports, logs, private documents, and certificates according to approved policy.

Do not run destructive migrations, `migrate:fresh`, `db:wipe`, or unreviewed certificate-release changes in production.
