# Manual Visual Validation

Use this checklist in a working browser and record evidence. Use `Pass`, `Fail`, or `Pending` in Result. Microsoft Excel checks require the desktop application; browser spreadsheet previews are not equivalent.

Current status for the 2026-07-30 implementation: **Implemented in code but pending manual visual verification.** Automated rendering/build checks do not replace the viewport acceptance below. Record the browser, viewport, date, result, and evidence when the team completes each check.

The RES queue, screening, and reviewer-assignment slice was interactively verified in a local browser on 2026-08-02. The August 3 correction, private-preview, Reviewer assignment-list/detail, and submitted Applicant checklist states were rechecked at 1280px and 390px on 2026-08-03. This does not change the pending status of unrelated screens or replace stakeholder acceptance. The populated Applicant account had no editable draft, so a real post-upload checklist transition remains a manual check even though its feature tests pass.

| Area | Check | Result | Notes | Screenshot Evidence |
| --- | --- | --- | --- | --- |
| Account Information | Identity appears left, application count is centered, and Back to User Management appears right. | Pending | | |
| Account Information | Approved hierarchy is preserved and all three regions stack without overlap on smaller screens. | Pending | | |
| Reset Link | Default button is hollow green with green text, border, and icon. | Pending | | |
| Reset Link | Hover fills green, text/icon remain readable, and keyboard focus is visible. | Pending | | |
| User Management | Wide tables stay inside their panel and do not create whole-page horizontal overflow. | Pending | | |
| User Management | Internal horizontal scrolling works by mouse, trackpad, keyboard, and touch. | Pending | | |
| User Management | Filters, row actions, and pagination remain usable. | Pending | | |
| Individual Creation | Personal Information and Institutional Information have space below the divider, compact space below the title, and visually connected first rows. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Dashboard Cards | Icon remains in the left column; count is centered directly above its label in the right column. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Dashboard Cards | The icon and count/label group are vertically centered and multiline labels remain balanced. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Dashboard Cards | Adviser, Reviewer, and RES Lead cards keep equal heights, correct colors/counts, zero states, and responsive wrapping. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Dashboard Cards | Card grids do not create whole-page horizontal overflow. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Empty State | Application Status, My Application, Requirements, deadline, and unavailable timeline follow the reference hierarchy; both start actions open the same flow. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Form | Research Information, Institutional Information, and Study Scope render as separate icon-headed sections; required markers, Student/Faculty differences, Cancel, and Save and Continue match the supplied reference without overlap. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Requirements | Progress updates from current documents; Upload, View, Download, and Replace remain usable at every target width. | Pending | The populated submitted state rendered correctly at 390px on 2026-08-03. A live upload transition still needs an editable draft; automated coverage verifies the immediate checklist hooks and safe refresh conditions. | Interactive local browser, 2026-08-03 |
| Applicant Requirements | Application identity and completion share one section; file controls stay fixed; Completed/Missing states and Upload All remain aligned. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Submission | Final action is disabled while incomplete/closed, and success updates status, submission date, Adviser, and timeline presentation. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Submission | Open/Closed label, red limit warning, exact checklist order, and confirmation dialog fit without overlap. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Dialogs | Application Details and Requirements Completion remain within the viewport, scroll internally, close by keyboard, and display the correct record. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Document Dialog | Long document values use ellipsis, do not resize the modal, and reveal the full value after the delayed tooltip. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Adviser Applications | Assigned submission appears on dashboard/list; search/filter controls, pagination, details, and private document actions remain readable. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Adviser Decision | Decision copy and Return/Endorse actions share one row on desktop and stack cleanly on tablet/mobile. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Deadline Configuration | The term summary, Upcoming Deadline, Active Date Range, and seven process rows align to one width; date/time values have space; On/Auto labels update; the bottom scrollbar and narrow-screen stacking remain usable. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| RES Applications Queue | At default 100% phone and desktop zoom, all filters and actions stay inside the viewport; heading copy wraps; only formally submitted RES-flow records appear; the bottom table scrollbar reaches every column without adding a second vertical scrollbar; pagination and detail links remain usable. | Pass | Verified with populated demo data at the target widths; the table scrolls internally and the page has no horizontal overflow. | Interactive local browser, 2026-08-02 |
| RES Screening Details | Overview, research information, requirement checklist, administrative checks, classification choices, notes, and private document dialog remain readable without overlap or clipped actions. | Pass | Rechecked at 1280px and 390px on 2026-08-03. Review Type, Reviewers Required, and long rationale content remained separate; the correction action appeared only in an editable workflow state. | Interactive local browser, 2026-08-03 |
| RES Reviewer Assignment | Candidate search/filter, disabled full-load rows, exact selection count, confirmation dialog, and read-only result remain usable for one and three reviewers. | Pass | Department replaced Availability; the Expedited candidate showed classification, institution, and `4 / 6` load. Selection updated to `1 / 1` and enabled confirmation without submitting it. Automated tests cover exact three-reviewer assignment and full-capacity rows. | Interactive local browser, 2026-08-03 |
| Reviewer Assigned Applications | Owner-scoped filters, populated table, protected detail, private documents, internal scrolling, and identity omission remain usable on desktop and phone. | Pass | The six approved columns, five filters, populated records, current document versions, and assignment detail rendered at 1280px and 390px without page-level horizontal overflow. Applicant and Adviser identity labels were absent. | Interactive local browser, 2026-08-03 |
| Private Office Preview | Word and Excel files open a bounded authorized fallback with neutral guidance and secure Download actions; no private storage path appears. | Pass | The Excel fallback opened inside the existing document dialog at 390px, remained within the viewport, and exposed only authorized download routes. | Interactive local browser, 2026-08-03 |
| RES Workflow Mobile | Queue filters stack, wide tables scroll internally, decision panels become one column, and modal content remains bounded at 768px and 390px. | Pass | No page-level horizontal overflow was found. At 390px the assignment dialog remained fully bounded with both actions visible. | Interactive local browser, 2026-08-02 |
| User Account Detail | The application metric is centered; Deactivate or Reactivate stays horizontally aligned with Delete Account; the edit page has no Dropdown Options shortcut. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Application Controls | Three detail actions, landing status/action, checklist heading/action, and form actions remain horizontal with equal heights; labels wrap inside controls and document upload fields stay bounded. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Adviser Scope | Drafts and another Adviser's submissions remain absent from every visible Adviser surface. | Pending | Authorization tests pass; visual verification remains pending. | Not captured |
| Import Categories | Active Existing Accounts, Archived Accounts Found, Restored Accounts, and restoration conflicts render as distinct containers with different descriptions. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Import Restoration | Individual Restore aligns with archived rows; Restore All stays in the archived heading, shows the exact count, and moves successes to Restored Accounts. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Import Restoration | RES controls wrap cleanly; Adviser guidance contains no individual or bulk restore control. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Excel Template | Workbook opens in Microsoft Excel without corruption, repair, recovery, or removed-content warning. | Pending | Automated checks pass; desktop Excel confirmation is still required. | |
| Excel Template | Accounts, hidden Options, and Instructions exist in that order. | Pending | | |
| Excel Template | Student Row 2 contains the approved Juan Dela Cruz values and Student Number/Phone remain text. | Pending | Automated workbook tests pass; desktop Excel confirmation is still required. | Not captured |
| Excel Template | Dropdowns work, long option values remain readable, and a populated workbook uploads successfully. | Pending | Automated upload tests pass; desktop Excel confirmation is still required. | Not captured |
| Show Errors | Only `An error occurred.` appears above Validate and Show Errors. | Pending | | |
| Show Errors | Full categorized details appear only in the scrollable modal and remain usable on mobile. | Pending | | |
| Show Errors | Red exclamation badge appears, short pulse stops, and reduced-motion disables animation. | Pending | | |
| Show Errors | Badge persists after opening the modal and clears after successful validation or file change. | Pending | | |

## RES Workflow Viewport Runs

These results apply to the Applications Queue, Screening Details, and Reviewer Assignment pages only.

| Width | Result | Notes | Evidence |
| --- | --- | --- | --- |
| 1440px | Pass | Desktop queue, screening, selection, and confirmation states rendered without page-level horizontal overflow. | Interactive local browser, 2026-08-02 |
| 1280px | Pass | All three RES pages remained within the document width. | Interactive local browser, 2026-08-02 |
| 1024px | Pass | Tablet layouts remained bounded and controls stayed usable. | Interactive local browser, 2026-08-02 |
| 768px | Pass | Compact tablet layouts remained bounded; wide data stayed in internal scrollers. | Interactive local browser, 2026-08-02 |
| 390px | Pass | Filters and decision panels stacked; the reviewer confirmation dialog fit with both actions visible. | Interactive local browser, 2026-08-02 |

The browser console reported no errors or warnings during the final reviewer-assignment check.

## August 3 Cross-role Viewport Runs

These results apply to RES Screening Details, RES Reviewer Assignment, Reviewer Assigned Applications, Reviewer Assignment Detail, the private Office fallback, and the populated submitted Applicant requirements state.

| Width | Result | Notes | Evidence |
| --- | --- | --- | --- |
| 1280px | Pass | Classification fields remained separate; Reviewer filters, tables, detail values, and private document actions stayed within the dashboard content width. | Interactive local browser, 2026-08-03 |
| 390px | Pass | Cards and filters stacked, long breadcrumbs reduced to the current page, and wide tables scrolled only inside their labeled regions. | Interactive local browser, 2026-08-03 |

Manual-only remainder: repeat an actual successful and partial/error upload with an editable Applicant draft to observe the immediate checklist transition and selected-file preservation in a live browser.

## Viewport Runs

Repeat applicable browser rows at each width.

| Width | Result | Notes | Screenshot Evidence |
| --- | --- | --- | --- |
| 1440px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 1280px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 1024px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 768px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 390px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
