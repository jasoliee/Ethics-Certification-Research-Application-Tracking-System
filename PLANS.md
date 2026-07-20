# ECRATS Implementation Plans

Use this file for large or risky work. Small isolated fixes do not need a formal plan.

## When a Plan Is Required

Create or update a plan before:

- Database schema changes
- Authentication or account-management changes
- Role, permission, middleware, or policy changes
- File upload, private storage, certificate, or QR access changes
- Reviewer anonymity or double-blind workflow changes
- Cross-module workflow changes
- Package installation
- Large UI layout changes shared by multiple roles
- Deployment, backup, or production configuration changes

## Plan Template

```markdown
## Plan: <short title>

### Goal
What user or team outcome this work should achieve.

### Source Documents
- Primary requirement:
- Supporting diagrams/forms:
- Conflicts or missing decisions:

### Scope
Included:
- 

Excluded:
- 

### Implementation Approach
- Backend:
- Frontend:
- Database:
- Authorization:
- Files/storage:
- Notifications/audit:

### Files Expected to Change
- 

### Tests and Verification
- 

### Risks and Rollback
- 

### Approval Notes
Approved by:
Date:
```

## Active Plans

## Plan: Account management and secure onboarding

### Goal
Replace the temporary user-management modules with a complete, role-authorized account workflow for RES Lead and Research Adviser users, while correcting login validation behavior.

### Source Documents
- Primary requirement: attached account-management request, July 20, 2026 (sections 1-5 and the available opening of section 6).
- Supporting designs: `ECRATS High Fidelity (5).pdf`, pages 1-8, plus the confirmed supervisor requirement for formatted CSV account imports.
- Conflicts or missing decisions: the attached text ends mid-sentence in section 6. The newer written requirements override PDF fields that use one full-name input, an editable date joined, RES/Admin account creation, or direct password editing.

### Scope
Included:
- Separate account name fields, institutional identifiers, creator tracking, profile details, and system-generated usernames.
- Searchable, filterable, paginated populated and empty user-management states.
- Server-enforced role creation, record visibility, editing, account status, and password-reset permissions.
- Individual account creation and bounded CSV imports with private temporary storage and audit records.
- Secure email reset links without exposing or directly editing existing passwords.
- Field-specific login validation and generic credential mismatch errors only after required inputs pass validation.

Excluded:
- Technical Admin onboarding until that role is formally added to the implemented role enum.
- Profile photo uploads and two-factor authentication.
- Infrastructure controls such as a WAF, TLS termination, database encryption, and production mail delivery configuration.

### Implementation Approach
- Backend: thin controllers, Form Requests, policies, identity services, Laravel password broker, and parameterized Eloquent queries.
- Frontend: shared responsive Blade views following the approved user-management table, role selection, account form, success, profile, and edit states.
- Database: additive user-profile columns plus audit logs; retain `users.name` as a generated compatibility display value.
- Authorization: RES Lead can manage non-RES-Lead accounts; advisers can create and manage only applicants within their allowed relationship scope.
- Files/storage: validate CSV extension, MIME type, headers, row count, and row values; process from private local storage and delete every temporary file in `finally` cleanup.
- Notifications/audit: use one-time Laravel password-reset notifications and record security-relevant account actions without secrets or reset tokens.

### Files Expected to Change
- `app/Enums`, `app/Models`, `app/Policies`, `app/Services/Identity`, and `app/Http/*`
- `database/migrations`, factories, and seeders
- `routes/web.php`
- `resources/views`, `resources/css/dashboard.css`, and `resources/js/dashboard.js`
- Account, authentication, navigation, and authorization tests
- Account-management and deployment documentation

### Tests and Verification
- Focused account-management, authorization, import, password-reset, and login tests.
- Full `php artisan test`, Pint, route list, migration status, Composer validation/platform checks, and `npm.cmd run build`.
- Desktop and mobile browser screenshots when the in-app browser connection is available.

### Risks and Rollback
- Existing users are backfilled conservatively from `users.name`; new fields remain additive so rollback does not discard the compatibility name or authentication records.
- User creation uses unique database constraints and transactions to protect generated usernames and institutional identifiers.
- CSV uploads are capped to keep synchronous imports practical for the current deployment and are removed after success or failure.

### Approval Notes
Approved by: User request
Date: 2026-07-20

## Completed Plans

## Plan: Login authentication and role landing pages

### Goal
Implement the first working authentication slice for ECRATS: username/password login, seeded testing accounts, role-based temporary landing pages, logout, and backend role-access guards.

### Source Documents
- Primary requirement: attached login functionality request, July 17, 2026
- Supporting diagrams/forms: high-fidelity login page PDF, page 7; design guide pages 1-6
- Conflicts or missing decisions: Account creation screens are not part of this slice, so account-creation role rules will be implemented as backend service logic and tests for future controllers to reuse.

### Scope
Included:
- Proportional login UI scaling so the connected `1040 x 650` container fits common laptop/desktop viewports
- Stable inline login errors with username/password validation
- Laravel session authentication
- User role fields and seed/test accounts
- Temporary role landing routes
- Logout
- Role access middleware
- Authenticated-login redirect and browser-history cache protection
- Backend account-creation authorization service and tests

Excluded:
- Finished dashboards
- Full account creation UI
- Password reset
- Email verification
- Production account onboarding workflow

### Implementation Approach
- Backend: Laravel session guard with a focused auth controller and form request.
- Frontend: Preserve the previous login design ratio, scale the complete desktop container when necessary, and reserve space for inline validation errors.
- Database: Add username, role, and account status columns to `users`, with a unique username supporting up to 30 characters.
- Authorization: Add role middleware for temporary landing pages and account creation service rules.
- Files/storage: No file storage changes.
- Notifications/audit: No notification or audit changes in this slice.

### Files Expected to Change
- `routes/web.php`
- `app/Models/User.php`
- `app/Enums/UserRole.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Middleware/EnsureUserHasRole.php`
- `app/Http/Middleware/PreventBrowserHistory.php`
- `app/Http/Middleware/RedirectAuthenticatedUser.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Services/Identity/AccountCreationAuthorizationService.php`
- `app/Services/Identity/UserAccountService.php`
- `app/Support/RoleHome.php`
- `bootstrap/app.php`
- `database/migrations/*_add_login_fields_to_users_table.php`
- `database/seeders/*`
- `database/factories/UserFactory.php`
- `resources/views/auth/login.blade.php`
- `resources/views/landing/role.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `tests/Feature/Auth/*`
- `tests/Unit/Services/*`

### Tests and Verification
- `php artisan test`
- `php artisan route:list`
- `php artisan migrate`
- `php artisan db:seed`
- `npm.cmd run build`

### Risks and Rollback
- Existing users without usernames receive a unique `user-{id}` fallback before the database makes the username column required.
- Rollback removes the new user login columns and temporary auth routes/pages.

### Approval Notes
Approved by: User request
Date: 2026-07-17
