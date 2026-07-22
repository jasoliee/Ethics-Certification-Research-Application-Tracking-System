# Email and Password Setup

## New Account Flow

1. An authorized RES Lead or Adviser creates an allowed account.
2. ECRATS generates a username and a random unusable internal credential.
3. The account remains `pending_setup`.
4. Laravel's password broker generates a one-time token.
5. Email contains the username and setup link, never a password.
6. The user chooses a password through the reset form.
7. ECRATS activates the account and records setup completion.

Resending creates or refreshes the broker token and does not create another user. Individual setup-email actions are limited to three requests per minute per signed-in actor. Delivery status and timestamps are stored on the user record. A mail failure leaves the account pending and records a safe failure event.

## Local Mail

The default safe local approach is Laravel's log mailer:

```dotenv
MAIL_MAILER=log
APP_URL=http://127.0.0.1:8000
```

The message appears in `storage/logs/laravel.log`. Never commit real mail credentials.

## Production Mail Example

Use an approved institutional provider. A Gmail-compatible SMTP configuration may use placeholders like these:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=approved-account@example.edu.ph
MAIL_PASSWORD=provider-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=approved-account@example.edu.ph
MAIL_FROM_NAME="ECRATS"
APP_URL=https://ecrats.example.edu.ph
```

Production `APP_URL` must be the real HTTPS origin so emailed links never point to localhost. Store secrets only in the deployment environment.

## Queues

Setup notifications are currently sent synchronously so the request can record immediate delivery failure. If the team later makes them queued, run a supervised worker such as `php artisan queue:work --tries=3 --timeout=90` and redesign delivery status to distinguish queued, sent, and failed states.
