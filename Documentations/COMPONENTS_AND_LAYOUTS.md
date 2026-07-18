# Components and Layouts

## Shared Layout

`resources/views/layouts/dashboard.blade.php` composes:

1. `x-dashboard.sidebar`
2. `x-dashboard.topbar`
3. Main page content
4. `x-dashboard.footer`

The workspace is a full-height flex column. Main content grows on short pages, so the footer stays at the viewport bottom; long content pushes the footer below the page naturally.

## Sidebar

The desktop sidebar is 264 pixels wide and becomes an off-canvas panel below the responsive breakpoint. Labels may wrap when needed. The bottom profile link shows initials, the full user name where space permits, and the complete role label.

## Header and Menus

The header contains the mobile navigation control, breadcrumbs, notification bell, avatar or initials, user name, and profile menu arrow. Notification and profile menus are mutually exclusive. The notification panel is aligned to the bell and remains within the viewport on mobile.

## Footer

`resources/views/components/dashboard/footer.blade.php` contains About KLD, Contact, Quick Links, Helpful Links, social links, and dynamic copyright text. External links use the official KLD website destinations available at implementation time.

## Timeline Header

`x-dashboard.section` accepts optional `headerMeta` and `headerMetaIcon` values. Timeline sections place Application Timeline on the left and the database-provided term label with calendar icon on the right.

## Research Title Tooltip

`x-dashboard.research-title` renders truncated titles with `data-research-title-tooltip`. `resources/js/dashboard.js` waits one second before showing the complete title, positions the tooltip within the viewport, and hides it immediately on pointer leave or blur. Focused title links and spans receive the same behavior, and Escape closes the tooltip.

## Tables

All headers and cells are left-aligned except `.dashboard-table-status` and `.dashboard-table-action`, which are centered. Table status badges use a shared minimum width and centered placement. View remains a green hyperlink-style action.
