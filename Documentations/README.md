# ECRATS Documentation

This folder documents the implemented ECRATS system contract and clearly identifies unfinished workflows. Read the overview and security boundaries before changing code.

## Reading Order

1. [System overview](SYSTEM_OVERVIEW.md)
2. [Authentication and login](AUTHENTICATION_AND_LOGIN.md)
3. [Email and password setup](EMAIL_AND_PASSWORD_SETUP.md)
4. [First login onboarding](FIRST_LOGIN_ONBOARDING.md)
5. [Account creation and user management](ACCOUNT_CREATION_AND_USER_MANAGEMENT.md)
6. [Adviser user management](ADVISER_USER_MANAGEMENT.md)
7. [Username generation](USERNAME_GENERATION.md)
8. [Excel bulk account import](BULK_ACCOUNT_IMPORT.md)
9. [Dropdown option management](DROPDOWN_OPTION_MANAGEMENT.md)
10. [Audit log](AUDIT_LOG.md)
11. [Applicant dashboard and application flow](APPLICANT_DASHBOARD.md)
12. [Adviser application visibility](ADVISER_APPLICATION_VISIBILITY.md)
13. [Application and requirements workflow](APPLICATION_AND_REQUIREMENTS_WORKFLOW.md)
14. [Document and certificate generation](DOCUMENT_AND_CERTIFICATE_GENERATION.md)
15. [Security implementation](SECURITY_IMPLEMENTATION.md)
16. [Performance and scalability](PERFORMANCE_AND_SCALABILITY.md)
17. [Database and data flow](DATABASE_AND_DATA_FLOW.md)
18. [Deployment security checklist](DEPLOYMENT_SECURITY_CHECKLIST.md)
19. [Testing guide](TESTING_GUIDE.md)
20. [Manual visual validation](MANUAL_VISUAL_VALIDATION.md)
21. [Known issues and pending verification](KNOWN_ISSUES.md)
22. [Changelog](CHANGELOG.md)

## Interface References

- [Dashboard implementation](DASHBOARD_IMPLEMENTATION.md)
- [Routes and navigation](ROUTES_AND_NAVIGATION.md)
- [Components and layouts](COMPONENTS_AND_LAYOUTS.md)
- [UI and responsive design](UI_AND_RESPONSIVE_DESIGN.md)
- [Roles and authorization](ROLES_AND_AUTHORIZATION.md)
- [Populated and empty states](POPULATED_AND_EMPTY_STATES.md)
- [Notifications and profile menu](NOTIFICATIONS_AND_PROFILE_MENU.md)
- [Original performance optimizations](PERFORMANCE_OPTIMIZATIONS.md)
- [Legacy user management summary](USER_MANAGEMENT.md)

## Scope

The documented implementation includes authentication, account setup, role onboarding, account administration, verified Excel-only `.xlsx` generation and preview/confirmation, RES-only preview-based archived-account restoration, shared dropdown catalogs, the canonical `/dashboard`, Applicant draft and document workflows, configured initial submission, Adviser-scoped visibility, shared navigation, notifications, profile/settings access, audit records, and local verification. Later review lifecycle, certificate-generation areas, Microsoft Excel confirmation, and manual responsive checks are explicitly identified.

Keep these files synchronized whenever dashboard routes, role rules, data queries, or shared components change.
