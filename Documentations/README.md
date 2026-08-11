# ECRATS Documentation

This folder documents the implemented ECRATS system contract and clearly identifies unfinished workflows. Read the overview and security boundaries before changing code.

## Reading Order

1. [System overview](SYSTEM_OVERVIEW.md)
2. [Current features and functionality](FEATURES_AND_FUNCTIONALITY.md)
3. [Authentication and login](AUTHENTICATION_AND_LOGIN.md)
4. [Email and password setup](EMAIL_AND_PASSWORD_SETUP.md)
5. [First login onboarding](FIRST_LOGIN_ONBOARDING.md)
6. [Account creation and user management](ACCOUNT_CREATION_AND_USER_MANAGEMENT.md)
7. [Adviser user management](ADVISER_USER_MANAGEMENT.md)
8. [Username generation](USERNAME_GENERATION.md)
9. [Excel bulk account import](BULK_ACCOUNT_IMPORT.md)
10. [Dropdown option management](DROPDOWN_OPTION_MANAGEMENT.md)
11. [Audit log](AUDIT_LOG.md)
12. [Applicant dashboard and application flow](APPLICANT_DASHBOARD.md)
13. [Adviser application visibility and decisions](ADVISER_APPLICATION_VISIBILITY.md)
14. [Application and requirements workflow](APPLICATION_AND_REQUIREMENTS_WORKFLOW.md)
15. [RES screening and reviewer assignment](RES_SCREENING_AND_REVIEWER_ASSIGNMENT.md)
16. [Reviewer workflow](REVIEWER_WORKFLOW.md)
17. [Document and certificate generation](DOCUMENT_AND_CERTIFICATE_GENERATION.md)
18. [Security implementation](SECURITY_IMPLEMENTATION.md)
19. [Performance and scalability](PERFORMANCE_AND_SCALABILITY.md)
20. [Database and data flow](DATABASE_AND_DATA_FLOW.md)
21. [Deployment security checklist](DEPLOYMENT_SECURITY_CHECKLIST.md)
22. [Testing guide](TESTING_GUIDE.md)
23. [Manual visual validation](MANUAL_VISUAL_VALIDATION.md)
24. [Known issues and pending verification](KNOWN_ISSUES.md)
25. [August 10, 2026 implementation status](IMPLEMENTATION_STATUS_2026-08-10.md)
26. [Changelog](CHANGELOG.md)

## Interface References

- [Dashboard implementation](DASHBOARD_IMPLEMENTATION.md)
- [Deadline configuration](DEADLINE_CONFIGURATION.md)
- [Routes and navigation](ROUTES_AND_NAVIGATION.md)
- [Components and layouts](COMPONENTS_AND_LAYOUTS.md)
- [UI and responsive design](UI_AND_RESPONSIVE_DESIGN.md)
- [Roles and authorization](ROLES_AND_AUTHORIZATION.md)
- [Populated and empty states](POPULATED_AND_EMPTY_STATES.md)
- [Notifications and profile menu](NOTIFICATIONS_AND_PROFILE_MENU.md)
- [Original performance optimizations](PERFORMANCE_OPTIMIZATIONS.md)
- [Legacy user management summary](USER_MANAGEMENT.md)

## Scope

The documented implementation includes authentication, account setup, role onboarding, account administration, guarded Excel-only `.xlsx` generation and preview/confirmation, RES-only archived-account restoration, immutable dropdown identities with historical-label aliases, the canonical `/dashboard`, Applicant drafts and private documents, date-based research duration, configured initial submission, the three-formal-application limit, Adviser return/endorsement decisions, the RES queue, Expedited/Full Board/Exempted classification, non-destructive reviewer assignment/reassignment, current-assignment Reviewer workspaces, the two official Reviewer forms, asynchronous private comment CRUD/resolution with incremental history, initial review decisions, versioned private official-form PDFs, explicit result release, two-cycle Applicant revisions and re-review, official-template private certificate generation/release/versioning, post-release evaluation and claim, deadline/timeline configuration, shared navigation, notifications, functional Reviewer/RES account settings, and audit records. Uploaded-document content redaction, automated side-by-side revision comparison, public QR verification, manual Microsoft Excel acceptance, and final responsive acceptance remain explicitly identified as incomplete or pending.

Keep these files synchronized whenever dashboard routes, role rules, data queries, or shared components change.
