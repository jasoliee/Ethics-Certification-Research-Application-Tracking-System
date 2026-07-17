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

No active implementation plans.

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
