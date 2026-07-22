# ECRATS Documentation

This folder documents the implemented ECRATS system contract and clearly identifies unfinished workflows. Read the overview and security boundaries before changing code.

## Reading Order

1. [System overview](SYSTEM_OVERVIEW.md)
2. [Authentication and login](AUTHENTICATION_AND_LOGIN.md)
3. [Email and password setup](EMAIL_AND_PASSWORD_SETUP.md)
4. [First login onboarding](FIRST_LOGIN_ONBOARDING.md)
5. [Account creation and user management](ACCOUNT_CREATION_AND_USER_MANAGEMENT.md)
6. [Username generation](USERNAME_GENERATION.md)
7. [Bulk account import](BULK_ACCOUNT_IMPORT.md)
8. [Application and requirements workflow](APPLICATION_AND_REQUIREMENTS_WORKFLOW.md)
9. [Document and certificate generation](DOCUMENT_AND_CERTIFICATE_GENERATION.md)
10. [Security implementation](SECURITY_IMPLEMENTATION.md)
11. [Performance and scalability](PERFORMANCE_AND_SCALABILITY.md)
12. [Database and data flow](DATABASE_AND_DATA_FLOW.md)
13. [Deployment security checklist](DEPLOYMENT_SECURITY_CHECKLIST.md)
14. [Testing guide](TESTING_GUIDE.md)
15. [Changelog](CHANGELOG.md)

## Interface References

- [Dashboard implementation](DASHBOARD_IMPLEMENTATION.md)
- [Routes and navigation](ROUTES_AND_NAVIGATION.md)
- [Components and layouts](COMPONENTS_AND_LAYOUTS.md)
- [Roles and authorization](ROLES_AND_AUTHORIZATION.md)
- [Populated and empty states](POPULATED_AND_EMPTY_STATES.md)
- [Notifications and profile menu](NOTIFICATIONS_AND_PROFILE_MENU.md)
- [Original performance optimizations](PERFORMANCE_OPTIMIZATIONS.md)
- [Legacy user management summary](USER_MANAGEMENT.md)

## Scope

The documented implementation includes authentication, account setup, role onboarding, account administration, safe CSV/XLSX preview/confirmation, the canonical `/dashboard`, role data, applicant submission guards, shared navigation, notifications, profile/settings access, audit records, and local verification. Incomplete workflow and certificate-generation areas are explicitly identified.

Keep these files synchronized whenever dashboard routes, role rules, data queries, or shared components change.
