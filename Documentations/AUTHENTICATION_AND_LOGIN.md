# Authentication and Login

## Login Contract

ECRATS has no public registration. Users sign in with a generated username and their own password. `LoginRequest` trims the username and validates required fields separately from credential failure.

- Missing username: `Enter your username.`
- Missing password: `Enter your password.`
- Invalid username/password or non-active account: one generic credential error.
- Login does not enforce the password setup minimum; it only bounds input length.
- Five failed attempts per username/IP throttle key trigger lockout.

Successful login regenerates the session and records `auth.login_succeeded`. Repeated failures are audited with a SHA-256 username hash, not the entered username or password.

## Account States

- `pending_setup`: cannot sign in and must use the emailed setup link.
- `active`: may sign in when credentials are valid.
- `inactive`: cannot sign in.

Successful initial password setup changes `pending_setup` to `active`. Password reset regenerates the remember token so remembered sessions no longer rely on the old credential.

## Reset Form

The password setup/reset form requires 8 to 64 characters and matching confirmation. Laravel's password broker stores a hashed, single-use token. The default token expiry is 10,080 minutes, or seven days, through `AUTH_PASSWORD_RESET_EXPIRE`.

## Response Security

Login and authenticated pages use no-store headers. Responses also set MIME sniffing, frame, referrer, and permissions protections. Production HTTPS responses receive HSTS. Authorization failures keep the normal 403 behavior and are audited without request payloads.
