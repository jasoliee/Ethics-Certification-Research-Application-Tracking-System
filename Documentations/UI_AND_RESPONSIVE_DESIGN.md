# UI and Responsive Design

## Shared Account Layout

The Account Information header keeps three distinct regions in the approved order: identity information on the left, application count in the center, and Back to User Management on the right. The grid stacks these regions at smaller widths without changing account data or application-count queries.

Password reset and setup resend actions reuse `identity-button-secondary`. The default state is a white or transparent surface with green border, text, and icon. Hover and keyboard focus use a green fill with readable white content and the existing focus indicator.

Individual account forms reuse `identity-form-section-title` for consistent space between dividers, section titles, and the first field row.

## Settings Alignment

RES Lead Profile and Security and Privacy use the same left-aligned section heading: icon first, then title and supporting copy. The shared alignment remains unchanged across desktop and mobile breakpoints.

## Application Form Sections

Applicant create and edit forms use three separate operational sections: Research Information, Institutional Information, and Study Scope. Each section has a compact domain icon header and bordered field surface; the outer form and final action row remain unframed so cards are not nested. Research Adviser spans the Research Information width, Study Scope keeps the abstract full-width, and participant/date fields collapse without horizontal overflow on phone layouts.

## Responsive Tables

User Management, Adviser User Management, Dropdown Option Management, Audit Log, import preview, Applicant application, Adviser application, RES Applications Queue/screening/assignment, Reviewer Assigned Applications/details/workspace, deadline configuration, and requirement tables use the shared `dashboard-overflow-region` behavior. Identity tables retain `identity-table-scroll` alongside it. The wrapper stays within the main-content width and provides internal horizontal scrolling for mouse, trackpad, keyboard, and touch input. The scrollbar sits immediately below the table with zero extra bottom padding. Tables retain practical minimum widths; important columns and row actions are not hidden merely to avoid scrolling.

Filters remain outside the scrolling table and pagination remains in the same management panel, except Reviewer candidate filters intentionally sit inside Eligible Reviewers as one unified panel. The RES Applications Queue uses only its current screening-filter grid: the desktop arrangement places Apply Filters directly above Reset Filters in the rightmost of five tracks, then reduces to two-column and single-column phone arrangements. Superseded term-filter tracks cannot overlap search, status, Applicant category, research type, review type, affiliation, date range, or actions. Screening overview, decision panels, reviewer context, selection, confirmation, conflict, form, comment, and decision content reduce from multi-column desktop layouts to bounded single-column phone layouts. The application workspace uses a zero-minimum grid track, and each panel/table wrapper is independently width-bounded so a wide table cannot enlarge the page. Standard browser zoom remains available for accessibility, but the layout is designed for the default `initial-scale=1` viewport.

Requested column alignment is explicit: RES Applicant Category, Applicant Submitted, Reviewer Application Code/Status/Deadline, User Management Name, and Applicant requirement Document/Version/Uploaded use centered headers and values. Reviewer deadline text remains dark even when overdue. RES detail actions keep matched 44-pixel minimum heights, screening notes stay left-aligned, direct downloads use an icon action beside View Document, and phone heading actions stack at normal button height rather than inheriting a large flex basis.

## Dashboard Summary Cards

Adviser, Reviewer, and RES Lead dashboards reuse `x-dashboard.summary-card`. Its content order is:

1. Icon
2. Database-derived numerical count
3. Status label

The card content is vertically centered. Counts remain directly above centered labels, including wrapped labels and zero states. Shared grid classes reduce column counts at responsive breakpoints while preserving equal card heights, existing tones, role-specific labels, authorization, and query scopes.

The icon occupies a stable left column. The count and label form a centered right column, with the count directly above its label. The two-column layout collapses responsively without changing the data or card height.

The Student Researcher and Faculty Researcher dashboard currently uses application, requirement, deadline, and milestone panels rather than summary-count cards, so the shared summary-card alignment does not apply there.

## Application Surfaces

Applicant information, requirements, detail, and Adviser submitted-application views reuse the existing quiet dashboard shell. The Applicant Application page keeps its Open/Closed state beside Create/Resume on wide screens and stacks the controls without overlap on narrow screens. The requirements page combines application identity and completion in one section, keeps upload controls stable, and stacks requirement actions at the mobile breakpoint.

The final-submission confirmation and private document viewer use focus-restoring dialogs. Long document values are constrained with ellipsis and use the shared delayed tooltip for the complete value. Requirement progress and the final checklist update after each asynchronous upload; refresh occurs only when completion is final and no selected file would be discarded. Office formats retain the viewer frame and show a secure same-origin fallback when native rendering is unavailable. Submit Application is aligned with the Submission Checklist heading. Target Participants occupies the left study-scope column while Starting Date and Ending Date stack in the right column. The Adviser Decision section aligns red Return and green Endorse controls on desktop and stacks full-width actions on smartphones. Application detail section headings use compact top/bottom spacing rather than oversized gaps.

Deadline Configuration aligns its page heading, tab strip, term summary, two summary areas, and process table to one workspace width. The seven phases remain inside a bordered table with a bottom horizontal scrollbar. Date/time fields use stable spacing and manual switches label their stored override as `On` or `Auto`; no Manual Toggles On summary is rendered.

## Import Error Presentation

The import form displays only `An error occurred.` above its actions when validation fails. Complete safe details remain inside the keyboard-accessible Show Errors modal, separated into request errors, invalid rows, warnings, duplicate rows, and existing accounts.

The Show Errors control uses a persistent red exclamation badge and accessible `Validation errors available` text while blocking errors remain. A short pulse stops automatically, stops when the modal opens, and is disabled by reduced-motion preferences. Opening the modal does not clear errors. Choosing another file clears the client-visible result state; a successful server validation renders a fresh state without the badge.

## Validation Boundary

Code-level rendering and frontend build checks do not replace visual acceptance. Complete the checks in [Manual Visual Validation](MANUAL_VISUAL_VALIDATION.md) at approximately 1440, 1280, 1024, 768, and 390 pixels before marking the presentation accepted.
