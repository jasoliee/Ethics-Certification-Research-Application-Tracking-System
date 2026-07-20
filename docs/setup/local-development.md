# Local Development Setup

This project uses Windows, Laragon, PowerShell, Laravel, Composer, Node, npm, and Vite.

## Baseline Commands

Run from the repository root:

```powershell
php -v
composer --version
node --version
npm.cmd --version
git --version
php artisan --version
```

Use `npm.cmd` in PowerShell. Calling `npm` may resolve to `npm.ps1` and fail under the default execution policy.

## Verification Commands

```powershell
composer validate --strict
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
php artisan test
npm.cmd run build
```

## Environment Rules

- Do not commit `.env`.
- Do not print secrets from `.env`.
- Do not modify `.env` automatically.
- `.env.example` should eventually reflect MySQL defaults for ECRATS, but actual local credentials stay private.
- The target local database is MySQL, approximately:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecrats_db
DB_USERNAME=root
DB_PASSWORD=
```

## Development Server

Run a server only when needed for a UI or integration task. Prefer the Composer dev script once environment setup is stable:

```powershell
composer run dev
```

Do not leave long-running servers in shared development sessions without telling the team.

## Account Management Setup

Apply the account and audit migrations after pulling:

```powershell
php artisan migrate
npm.cmd run build
```

Password-reset notifications use `MAIL_MAILER`. Local development defaults to `log`, so reset links are written to `storage/logs/laravel.log`. Configure and test an authenticated production mail provider before school-facing use; never commit mail credentials.
