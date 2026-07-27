# UI and Responsive Design

## Shared Account Layout

The Account Information header keeps three distinct regions in the approved order: identity information on the left, application count in the center, and Back to User Management on the right. The grid stacks these regions at smaller widths without changing account data or application-count queries.

Password reset and setup resend actions reuse `identity-button-secondary`. The default state is a white or transparent surface with green border, text, and icon. Hover and keyboard focus use a green fill with readable white content and the existing focus indicator.

Individual account forms reuse `identity-form-section-title` for consistent space between dividers, section titles, and the first field row.

## Responsive Tables

User Management, Adviser User Management, Dropdown Option Management, Audit Log, import preview, Applicant application, Adviser application, and requirement tables use the shared `dashboard-overflow-region` behavior. Identity tables retain `identity-table-scroll` alongside it. The wrapper stays within the main-content width and provides internal horizontal scrolling for mouse, trackpad, keyboard, and touch input. Tables retain practical minimum widths; important columns and row actions are not hidden merely to avoid scrolling.

Filters remain outside the scrolling table and pagination remains in the same management panel. The page shell itself must not gain horizontal overflow from a wide table.

## Dashboard Summary Cards

Adviser, Reviewer, and RES Lead dashboards reuse `x-dashboard.summary-card`. Its content order is:

1. Icon
2. Database-derived numerical count
3. Status label

The card content is vertically centered. Counts remain directly above centered labels, including wrapped labels and zero states. Shared grid classes reduce column counts at responsive breakpoints while preserving equal card heights, existing tones, role-specific labels, authorization, and query scopes.

The icon occupies a stable left column. The count and label form a centered right column, with the count directly above its label. The two-column layout collapses responsively without changing the data or card height.

The Student Researcher and Faculty Researcher dashboard currently uses application, requirement, deadline, and milestone panels rather than summary-count cards, so the shared summary-card alignment does not apply there.

## Application Surfaces

Applicant information, requirements, detail, and Adviser submitted-application views reuse the existing quiet dashboard shell. Fixed action areas wrap at narrow widths, modal content scrolls internally, file controls preserve their labels, and application tables keep their columns inside the shared overflow region.

## Import Error Presentation

The import form displays only `An error occurred.` above its actions when validation fails. Complete safe details remain inside the keyboard-accessible Show Errors modal, separated into request errors, invalid rows, warnings, duplicate rows, and existing accounts.

The Show Errors control uses a persistent red exclamation badge and accessible `Validation errors available` text while blocking errors remain. A short pulse stops automatically, stops when the modal opens, and is disabled by reduced-motion preferences. Opening the modal does not clear errors. Choosing another file clears the client-visible result state; a successful server validation renders a fresh state without the badge.

## Validation Boundary

Code-level rendering and frontend build checks do not replace visual acceptance. Complete the checks in [Manual Visual Validation](MANUAL_VISUAL_VALIDATION.md) at approximately 1440, 1280, 1024, 768, and 390 pixels before marking the presentation accepted.
