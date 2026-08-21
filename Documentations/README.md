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
18. [Reviewer-owned decisions, releases, and certificate continuity](REVIEW_RELEASE_CERTIFICATE_GUIDELINE_2026-08-13.md)
19. [Security implementation](SECURITY_IMPLEMENTATION.md)
20. [Performance and scalability](PERFORMANCE_AND_SCALABILITY.md)
21. [Database and data flow](DATABASE_AND_DATA_FLOW.md)
22. [Deployment security checklist](DEPLOYMENT_SECURITY_CHECKLIST.md)
23. [Testing guide](TESTING_GUIDE.md)
24. [Manual visual validation](MANUAL_VISUAL_VALIDATION.md)
25. [Known issues and pending verification](KNOWN_ISSUES.md)
26. [August 21, 2026 DOOMSDAY requirements traceability](DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md)
27. [August 21, 2026 DOOMSDAY implementation status](DOOMSDAY_IMPLEMENTATION_STATUS_2026-08-21.md)
28. [August 17, 2026 Finale implementation record](THE_FINALE_IMPLEMENTATION_2026-08-17.md)
29. [Role settings and managed assets](ROLE_SETTINGS_AND_ASSET_MANAGEMENT_2026-08-17.md)
30. [August 10, 2026 historical implementation status](IMPLEMENTATION_STATUS_2026-08-10.md)
31. [Changelog](CHANGELOG.md)

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

The documented implementation includes authenticated account setup/onboarding; role-scoped account administration; structurally flexible but bounded `.xlsx` preview/confirmation; private Applicant documents; Adviser endorsement; RES screening; Adviser-owned Reviewer entitlement; current-cycle assignment/reassignment; versioned private Reviewer evidence; application-level Full Board consensus/release; requirement/version Applicant revisions; personalized multi-recipient certificates; immutable validity/signatory/background/QR configuration; release/evaluation/claim; role Profile/Security & Privacy; anonymous survey aggregates; Adviser/Reviewer monitoring; term-scoped dashboards/reports; notification inbox/Bin workflows; deadlines; and audit records. `DOOMSDAY_REQUIREMENTS_TRACEABILITY_2026-08-21.md` is authoritative for the August 21 audit. Uploaded-document content redaction, automated side-by-side comparison, public QR verification, production acceptance, connected-browser acceptance, certificate pixel/reference acceptance, and manual Microsoft Excel acceptance remain explicitly incomplete or pending.

Keep these files synchronized whenever dashboard routes, role rules, data queries, or shared components change.
