# ECRATS Dashboard Documentation

This folder documents the authenticated dashboard foundation and the shared interface used by applicants, advisers, reviewers, and the RES Lead. Begin with `DASHBOARD_IMPLEMENTATION.md`, then use the topic-specific guides below.

## Reading Order

1. [Dashboard implementation](DASHBOARD_IMPLEMENTATION.md)
2. [Routes and navigation](ROUTES_AND_NAVIGATION.md)
3. [Components and layouts](COMPONENTS_AND_LAYOUTS.md)
4. [Roles and authorization](ROLES_AND_AUTHORIZATION.md)
5. [Populated and empty states](POPULATED_AND_EMPTY_STATES.md)
6. [Notifications and profile menu](NOTIFICATIONS_AND_PROFILE_MENU.md)
7. [Performance optimizations](PERFORMANCE_OPTIMIZATIONS.md)
8. [Testing guide](TESTING_GUIDE.md)
9. [Changelog](CHANGELOG.md)

## Scope

The documented implementation includes the canonical `/dashboard` entry point, role-specific dashboard data, shared navigation, breadcrumbs, notification access, profile pages, academic-cycle milestones, reusable table and tooltip behavior, the KLD footer, and local verification. Module pages that still display a temporary workspace are identified as limitations in the implementation guide.

Keep these files synchronized whenever dashboard routes, role rules, data queries, or shared components change.
