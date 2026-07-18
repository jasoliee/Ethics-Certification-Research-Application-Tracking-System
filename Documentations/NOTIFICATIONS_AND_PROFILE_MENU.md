# Notifications and Profile Menu

## Notification Access

Notifications are not sidebar navigation items. Users reach them through:

1. The header notification bell.
2. View all notifications in the dropdown.

Every role retains a valid named notification route. The shared Mark all as read action updates only the authenticated user's unread database notifications.

## Header Query

`ShareDashboardContext` loads the four newest notification records using only display columns and performs a separate unread count. Notification destinations are validated against named routes; missing routes or parameters fall back to the current role's notification page.

The full page uses 20-record pagination instead of loading an unbounded history.

## Dropdown Behavior

`resources/js/dashboard.js` keeps notification and profile menus mutually exclusive, closes them on outside click or Escape, and synchronizes `aria-expanded`. Mobile notification positioning uses fixed viewport margins.

## Profile Access

The sidebar profile area and the Profile item in the top-right menu use the role-specific named profile route:

- `applicant.profile.show`
- `adviser.profile.show`
- `reviewer.profile.show`
- `res.profile.show`

The profile page displays account identity, username, email, full role label, and account status. It does not currently edit those values.

## Logout

Logout remains a POST form with a CSRF token. The session is invalidated and its CSRF token is regenerated before redirecting to login.
