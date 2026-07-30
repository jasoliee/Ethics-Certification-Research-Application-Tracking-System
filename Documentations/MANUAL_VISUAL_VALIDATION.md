# Manual Visual Validation

Use this checklist in a working browser and record evidence. Use `Pass`, `Fail`, or `Pending` in Result. Microsoft Excel checks require the desktop application; browser spreadsheet previews are not equivalent.

Current status for the 2026-07-30 implementation: **Implemented in code but pending manual visual verification.** Automated rendering/build checks do not replace the viewport acceptance below. Record the browser, viewport, date, result, and evidence when the team completes each check.

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
| Applicant Form | Information hierarchy, required markers, Student/Faculty differences, Cancel, and Save and Continue match the supplied reference without overlap. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Requirements | Progress updates from current documents; Upload, View, Download, and Replace remain usable at every target width. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Requirements | Application identity and completion share one section; file controls stay fixed; Completed/Missing states and Upload All remain aligned. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Submission | Final action is disabled while incomplete/closed, and success updates status, submission date, Adviser, and timeline presentation. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Submission | Open/Closed label, red limit warning, exact checklist order, and confirmation dialog fit without overlap. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Dialogs | Application Details and Requirements Completion remain within the viewport, scroll internally, close by keyboard, and display the correct record. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Applicant Document Dialog | Long document values use ellipsis, do not resize the modal, and reveal the full value after the delayed tooltip. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Adviser Applications | Assigned submission appears on dashboard/list; search/filter controls, pagination, details, and private document actions remain readable. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Adviser Decision | Decision copy and Return/Endorse actions share one row on desktop and stack cleanly on tablet/mobile. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| Deadline Configuration | The term summary, Upcoming Deadline, Active Date Range, and seven process rows align to one width; date/time values have space; On/Auto labels update; the bottom scrollbar and narrow-screen stacking remain usable. | Pending | Implemented in code but pending manual visual verification. | Not captured |
| RES Endorsed Applications | Only formally submitted applications that reached the RES flow appear; filters, 15-row pagination, detail links, and the bottom horizontal scrollbar remain usable. | Pending | Authorization and visibility tests pass; visual verification remains pending. | Not captured |
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

## Viewport Runs

Repeat applicable browser rows at each width.

| Width | Result | Notes | Screenshot Evidence |
| --- | --- | --- | --- |
| 1440px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 1280px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 1024px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 768px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
| 390px | Pending | Browser runtime unavailable; manual visual verification required. | Not captured |
