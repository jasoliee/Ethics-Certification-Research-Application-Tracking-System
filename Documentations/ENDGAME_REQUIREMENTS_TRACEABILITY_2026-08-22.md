# ENDGAME and DOOMSDAY Requirements Traceability - 2026-08-22

This is the current evidence ledger for the complete original `DOOMSDAY-INPUT.txt` requirements and the August 22 `ENDGAME.txt` additions. Direct user instructions and `SAFETY NET.txt` control safety and scope. `DOOMSDAY-HANDOFF.txt` is historical context only and is not evidence.

Status meanings:

- **Verified complete**: current source/data inspection plus current-session automated, structural, or generated-artifact evidence passed.
- **Incomplete/incorrect**: a verified defect remains.
- **Not yet verified**: the implementation and automated evidence exist, but required authenticated browser, visual, native-viewer, or scanner acceptance could not be executed.

## Safety and execution

| ID | Requirement | Status | Evidence |
| --- | --- | --- | --- |
| SAFE-01 | Confirm repository boundary and inspect Git before edits | Verified complete | Work began in the confirmed ECRATS Git root with a clean working tree. All changes are ECRATS-only; unrelated/untracked work was preserved. |
| SAFE-02 | No internet/external transfer, `.env` change, commit, push, deploy, tunnel, or unrelated access | Verified complete | No external site or service was contacted; no data or source was transmitted. No prohibited configuration or Git/deployment operation was performed. |
| SAFE-03 | Preserve MySQL data and use safe forward migrations only | Verified complete | `migrate:status` showed only the additive August 22 migration pending. It was applied as batch 6 without reset, rollback, truncate, reseed, or destructive command; the ledger is current. |
| SAFE-04 | Automated tests use isolated SQLite memory | Verified complete | The Laravel test environment used its configured SQLite in-memory database. No automated test command targeted local MySQL. |
| SAFE-05 | Authorization, validation, ownership, privacy, workflow, and filters are server-side | Verified complete | Focused forged-request, cross-role, cross-owner, cross-term, private-file, workflow-state, and filter tests pass. |
| SAFE-06 | Never expose SQL, stack traces, paths, or raw technical errors | Verified complete | Notification MySQL regression and global `QueryException` rendering are covered by tests asserting a generic response and absence of SQL/trace details. |
| PROC-01 | Inspect code/routes/migrations/tests/UI before changes and maintain evidence | Verified complete | Controllers, services, policies/routes, models, migrations, Blade, JS/CSS, tests, database ledger, supplied screenshots, and certificate reference were audited. Browser discovery was also attempted and recorded. |
| PROC-02 | Authenticated localhost checks for all roles and desktop/tablet/mobile | Not yet verified | The approved in-app browser returned zero available browser sessions on both attempts. No manual UI result is claimed. |

## Original DOOMSDAY product requirements

| ID | Original requirement area | Status | Evidence or remaining acceptance |
| --- | --- | --- | --- |
| D-GR-01 | Adviser is the primary role; Reviewer is an RES-controlled capability; review type classifies applications | Verified complete | Reviewer entitlement, eligibility, canonical routes, navigation, assignment, import, and account tests pass; historical Reviewer classifications are not current eligibility controls. |
| D-GR-02 | Eligible reviewer-enabled Advisers; endorser, status, capacity, conflict, self, and assignment enforcement | Verified complete | Shared locked eligibility/assignment services enforce every rule for UI and forged writes. Expedited-one and Full-Board-three tests pass. |
| D-RL-A | Adviser creation Reviewer Capability/Capacity alignment, accessibility, conditional validation, optional position | Not yet verified | Blade/Form Request and forged-request tests prove disabled/required semantics and optional Position. Authenticated responsive visual acceptance remains blocked. |
| D-RL-B | Adviser XLSX import/template parity and actionable row errors | Verified complete | Offline bounded workbook validation, conditional capacity, preview/no-write, row errors, and valid-only confirmation tests pass. |
| D-RL-C | Decision & Certificates three-card summary and server filters | Not yet verified | Metrics/filter validation, role/term scope, clickable card contracts, and rendered structure tests pass; viewport/pointer acceptance remains blocked. |
| D-RL-D | Selected-application modal summary/decisions/actions and `Certificate` wording | Not yet verified | Required labels, wrapping CSS, action placement, and response contracts pass; live modal/focus/responsive acceptance remains blocked. |
| D-RL-E | Full Board conflict block; one application-level release of all three reviewers; Approved automation | Verified complete | Combined release freezes all three source versions and releases all anonymous feedback/actionable requirements. Split decisions block; Approved prepares certificates. Consensus/release tests pass. |
| D-RL-F | Centered, responsive RES Version History | Not yet verified | Centered table/source contracts pass; responsive modal/table inspection remains blocked. |
| D-RL-G | Certificate/worksheet backgrounds version independently with safe failures | Verified complete | Typed asset uniqueness, future-only activation, immutable provenance, failure retention, settings load, and artifact generation tests pass. |
| D-RL-H1 | Document business versions increment only for reviewed replacements | Verified complete | Initial draft reuploads remain V1; actual reviewed replacements become V2/V3; duplicate submission does not increment; history remains authorized. |
| D-RL-H2 | Worksheet business versions initial V1, C1 V2, C2 V3 with immutable internal artifacts | Verified complete | `business_version = review_cycle + 1`; internal versions remain append-only. V1/V2/V3 and historical-access tests pass. |
| D-RL-I | Certificate quoted title/document list and fixed lower-left immutable QR | Not yet verified | Generated-PDF tests and source coordinates verify title/list, Payment Proof exclusion, 30 mm square lower-left placement, separation, and provenance. Independent raster/scanner acceptance remains blocked. |
| D-RL-J | RES certificate signatory/signature/QR/validity settings and immutable snapshots | Not yet verified | Server validation, private previews, default/replacement QR, provenance, and unauthorized/tamper tests pass; responsive upload-state acceptance remains blocked. |
| D-RL-K | Review Monitoring reviewer/adviser workloads, filters, secure drill-downs, empty states, no legacy progress | Not yet verified | Exact queries/calculations, filters, role/nested authorization, links, empty states, and legacy-section absence pass tests; target-width UI inspection remains blocked. |
| D-AP-A | Single-document auto-upload, no Upload/Upload All, safe states | Not yet verified | JS/Blade and HTTP tests verify one-file automatic requests, validation, ownership, and no legacy controls. Live progress/error/retry acceptance remains blocked. |
| D-AP-B | Revision status/nav wording and application-specific equal actions | Not yet verified | Status projection and rendered-view tests pass; final viewport alignment remains blocked. |
| D-AP-C | Centered responsive Revision application selector | Not yet verified | Ownership-scoped selector and centered/equal responsive CSS contracts pass; browser acceptance remains blocked. |
| D-AP-D | Exactly three Revision/Certificates progress steps | Verified complete | Rendered Applicant response contains only Revision Submission, Evaluation Form, and Certification. |
| D-AP-E | Six-cell Application Status Overview | Not yet verified | Six required values and alignment CSS pass source/render tests; long-title target-width inspection remains blocked. |
| D-AP-F | Feedback/History and Revision Submission heading/body/badge alignment | Not yet verified | DOM/CSS contracts place titles left and badges/actions right; browser acceptance remains blocked. |
| D-AP-G | Revision warning behavior without prohibited redundant subtext | Verified complete | Server readiness still blocks incomplete revision; the later explicit subtext-removal instruction is honored. |
| D-AP-H | Anonymous multi-reviewer feedback, versions, dates/tooltips, no separate history container | Not yet verified | All three Full Board groups and requirements, real filenames, current/date state, secure actions, and tooltip hooks pass tests; responsive behavior remains blocked. |
| D-AP-I | Revision replacement auto-upload and secure source actions | Not yet verified | Filename replacement/preview/Download/Replace and no separate legacy link are verified in source/tests; live upload/viewer acceptance remains blocked. |
| D-AP-J | Minor/Major Revision needs at least one actionable comment | Verified complete | Current submission evidence is checked server-side for actionable overall/document comments; allow/block tests pass. |
| D-AP-K | Status, Feedback/History, Certification collapsibles | Not yet verified | Semantic controls/hooks and rendered tests pass; keyboard/focus/browser acceptance remains blocked. |
| D-AP-L | Ordered recipient names and personalized certificate per recipient | Verified complete | Normalization, duplicate rejection, plural generation, metrics, queue, eligibility, bulk release, preview/download, survey and claim tests pass. |
| D-AP-M | Orange Review Worksheet panel, two worksheet types, available versions, secure actions | Verified complete | Applicant view order, business-version selector, both types, and nested owner-authorized routes pass artifact/access tests. |
| D-RV-A | Reviewer worksheet modal, async save, close only on success, no erased panels | Not yet verified | Per-worksheet dialogs and async success/error DOM contracts pass; live focus/state-preservation acceptance remains blocked. |
| D-RV-B | Consent necessity gate and Yes/No conditional validation | Verified complete | Client and server normalization/validation agree; dependent answers hide/disable/clear correctly; artifact tests pass. |
| D-RV-C | Reviewer sees only own prior comments across versions | Verified complete | Assignment/reviewer-scoped history and cross-reviewer denial tests pass. |
| D-ALL-A | Editable profile controls remain contained/responsive | Not yet verified | Shared grid/ellipsis/select CSS and profile tests pass; live open-dropdown/viewport overlap inspection remains blocked. |
| D-ALL-B | Username changes only through explicit Security & Privacy action | Verified complete | Profile allowlists ignore forged username/identifier changes; separate current-password/rate-limited username action passes cross-role tests. |
| D-ALL-C | Profile Summary displays actual Institute/contact/position data | Verified complete | Role summary responses render stored phone, Institute/program, and Adviser designation. |
| D-ALL-D | Authorized same-origin private previews/downloads with defensive headers/fallbacks | Not yet verified | Nested policies and header tests cover PDF/image/Office, certificates, worksheets, signature/background/QR. Native browser viewer/new-tab/download acceptance remains blocked. |
| D-ALL-E | Search icons inside-left and vertically centered | Not yet verified | Search surfaces use the shared positioned wrapper and padding; responsive browser inspection remains blocked. |
| D-ALL-F | Notifications filters/actions/pagination/Bin/purge/reusable confirmation and MySQL 3065 fix | Not yet verified | `reorder()->select('type')->distinct()->orderBy('type')`, owner scope, 20/page, Bin/purge, individual/selected/all actions, reusable modal, and safe-error tests pass; live UI acceptance remains blocked. |
| D-TXT | Remove every explicitly listed redundant subtext while retaining functional messages | Verified complete | Exact Blade/source tests confirm prohibited copy is absent while validation, status, warnings, and required instructions remain. |
| D-TERM | Academic-term filtering without cross-term or role/ownership leakage | Verified complete | Remaining filters validate and apply term after role/owner scope; current-first/Current label, All, inactive terms, invalid IDs, drill-downs, reports, queues, and dashboards pass tests. Dashboard term query is intentionally ignored. |

## ENDGAME additions and refinements

| ID | ENDGAME requirement | Status | Evidence or remaining acceptance |
| --- | --- | --- | --- |
| E-SET-01 | RES settings tabs full-width/equally spaced; remove heading subtext; profile fields contained | Not yet verified | Tab/grid/profile CSS and prohibited-copy tests pass; desktop/tablet/mobile visual inspection remains blocked. |
| E-SET-02 | Term dates may be historical; process Open/Deadline cannot be past; Deadline >= Open | Verified complete | Form Request/browser attributes distinguish term boundaries from workflow dates and enforce server dates/order. Settings tests pass. |
| E-SET-03 | Deadline headers/controls/toggles centered; rename Deadline; calendar hover/pointer; success boundaries | Not yet verified | Blade/CSS contracts and settings response tests pass; native date-control hover/target-width acceptance remains blocked. |
| E-SET-04 | Dropdown Options button placement, outlined header, row hover | Not yet verified | DOM/CSS layout and hover contracts pass source checks; responsive visual acceptance remains blocked. |
| E-SET-05 | Background Reset beside Preview, exact label, equal orange control, green Preview; remove subtext | Not yet verified | Required labels/classes and absence tests pass; live hover/alignment remains blocked. |
| E-SET-06 | Default deterministic QR for exact KLD destination; replacement and immutable provenance | Verified complete | Offline fallback asset is 296x296 PNG with SHA-256 `2e8081ab...e9d117`; generation tests assert exact payload metadata, fallback/replacement behavior, dimensions/hash/config snapshots, and immutability. |
| E-SET-07 | Certificate configuration two-column/vertical controls, equal buttons, save right | Not yet verified | Blade/CSS structure and settings tests pass; responsive/file-selection visual acceptance remains blocked. |
| E-SET-08 | Three stacked Security panels, centered labels/full actions, centered confirmations, remove subtext | Not yet verified | Shared cross-role partial and exact-copy tests pass; keyboard/collapse/modal acceptance remains blocked. |
| E-DASH-01 | RES Deadline Alerts match other roles; current six-process timeline; no dashboard term filter | Verified complete | Current-term-only dashboard service/view and deadline process tests pass; dashboard query parameters cannot switch terms. |
| E-SCREEN-01 | Four screening detail containers are hoverable collapsibles | Not yet verified | Semantic Blade/CSS contracts pass; live keyboard/pointer acceptance remains blocked. |
| E-ASSIGN-01 | Current Load centered; assignment modal copy/alignment; redirect to Application | Not yet verified | Required source structure, exact removed copy, server redirect, and assignment regression tests pass; modal visual acceptance remains blocked. |
| E-MON-01 | Monitoring search/filter alignment, equal full-width tables, centered Action, green `View` links | Not yet verified | Controller/view/filter tests and CSS contracts pass; target-width populated/empty inspection remains blocked. |
| E-MON-02 | Reviewer and endorsed drill-down filters, long-title ellipsis/0.5s tooltip, no Completion, secure `View` | Not yet verified | Server filter/authorization tests, delayed tooltip hooks, columns, and secure routes pass; actual hover positioning and overflow remain blocked. |
| E-CERT-01 | Clickable/equal/color-matched certificate hero filters and Applications-style filters | Not yet verified | Query/state/metrics tests and card DOM/CSS pass; live pointer/focus/responsive acceptance remains blocked. |
| E-CERT-02 | Queue `View`, long-title-safe modal, read-only workspace column alignment/eye-only actions | Not yet verified | Blade/CSS and private-action tests pass; live modal and long-title viewport inspection remains blocked. |
| E-CERT-03 | Full Board Reviewer collapsibles expose identities to RES/Adviser but not Applicant | Not yet verified | Role-scoped rendered responses and privacy tests pass; live collapse behavior remains blocked. |
| E-CERT-04 | Release All centered, redundant note removed, result columns aligned | Not yet verified | Exact-copy absence and layout contracts pass; modal/result visual acceptance remains blocked. |
| E-CERT-05 | Green certificate actions and Preview All Certificate for every recipient/page | Verified complete | FPDI combines every ready recipient PDF after authorization/hash checks; page count equals recipient count; tamper/unauthorized tests pass. |
| E-APP-01 | Applicant timeline before an application and no Applicant list filter | Verified complete | Current-term timeline is independent of application existence; list renders ownership scope without term/filter input. Dashboard/list tests pass. |
| E-APP-02 | Applicant profile dropdown containment/ellipsis, expanded summary, remove role subtext | Not yet verified | Shared CSS and stored-value render tests pass; live long dropdown/open-state acceptance remains blocked. |
| E-APP-03 | Create Application four collapsibles; past start allowed; ending not before start | Verified complete | Form structure/hover contracts and server date-order validation pass; historical start is accepted. |
| E-UPLOAD-01 | Equal empty/completed controls, animated Uploading dots, no success copy, centered row controls | Not yet verified | JS state machine, absence assertions, safe upload/replace/retention tests, and layout CSS pass; live progress timing/error recovery remains blocked. |
| E-UPLOAD-02 | Submit Application confirmation centered with exact messages/actions | Not yet verified | Exact copy and dialog layout contracts pass; live keyboard/focus acceptance remains blocked. |
| E-REV-01 | No term filter; top version controls; worksheet controls above tabs; no arrows; revision collapsible | Not yet verified | Rendered/source tests verify order, labels, three steps, and removed arrows; target-width interaction remains blocked. |
| E-REV-02 | Replacement filename opens embedded preview with Download/Replace; no separate link | Not yet verified | Nested preview/download routes and DOM tests pass; native embedded viewer remains blocked. |
| E-REV-03 | Revision resubmission confirmation centered without redundant copy | Not yet verified | Reusable confirmation markup and safe server transition pass; live modal/focus acceptance remains blocked. |
| E-ROLE-DASH | Adviser and Reviewer dashboards have no term filter and use active term | Verified complete | Dashboard service ignores term query across all roles and filters current term after ownership. Regression tests pass. |
| E-RV-01 | Reviewer workspace top values align; exact actionable-comment error; centered final modal | Not yet verified | Server actionable requirement, exact rendered message with emphasis, and modal/source tests pass; live layout/focus acceptance remains blocked. |
| E-RV-02 | Reviewer Worksheet Configuration with name/signature and corrected generated layout | Verified complete | Settings service/request/private preview and artifact snapshot tests pass; renderer supports multiline titles, centered larger review date/name, signature above name, and no page-three overlap. |
| E-SEED | Consistent Institute/program and safe mock profile data | Verified complete | All three development/demo seeders use the specified Institute, BSCS, and populated mock contact/designation fields. |
| E-DRAFT | Automatic safe draft saving for draft-enabled workflows | Verified complete | Application changes save on debounce/pagehide after form validation; screening uses owner/workflow-scoped persisted drafts with reauthorization, validation, privacy-limited audit metadata, restore, and final-transition cleanup. Reviewer worksheet and final-decision changes also submit draft intent on pagehide. No finalized state is bypassed; tests pass. |
| E-TERM | Remaining term filters first/current-labelled; no dashboard filters | Verified complete | `AcademicTerm` current-first label and filter components are shared; server scopes are validated. Dashboard term query is ignored. Tests pass. |
| E-ENDORSE | Full-width application title and centered endorsement confirmation except Remarks | Not yet verified | Blade/CSS and workflow tests pass; long-title/modal viewport acceptance remains blocked. |
| E-NOTIFY | Exact notification/Bin order, labels, danger styles, term filter, left bulk controls, enlarged icons | Not yet verified | Blade/CSS and notification feature tests verify all requested labels/order/actions/term scope; live hover/modal/responsive acceptance remains blocked. |
| E-HOVER | Hover effects on all filters/collapsibles | Not yet verified | Shared hover/focus CSS applies to filter controls and collapsible triggers; actual pointer/focus inspection remains blocked. |
| E-PREVIEW | Fix browser-blocked previews with same-origin embedded/native/fallback actions | Not yet verified | CSP sandbox removal, same-origin framing, no-store/nosniff/referrer/permissions headers, nested policies, and supported-type/fallback tests pass. Actual native PDF/image viewer acceptance remains blocked. |
| E-RETENTION | Delete never-submitted replacements; retain max three submitted business versions | Verified complete | `formally_submitted_at` distinguishes business evidence; pre-submission replacement removes row/private bytes; formal initial/C1/C2 history is retained and a fourth business version is blocked. Filesystem and version tests pass. |
| E-REPORT-01 | Reports filters and eight summary cards | Verified complete | Authorized Form Request/controller/service implement term-first plus date/type/category/classification/Institute/status filters and all requested cards from stored data. |
| E-REPORT-02 | Pipeline, trend, classification, decision, turnaround, reviewer, adviser, certificate analytics | Verified complete | Operational report service computes all requested real-data aggregates including average/median durations; accessible tables accompany every visual treatment. Tests cover values and filter scope. |
| E-REPORT-03 | Action Required, Reviewer Capacity/Delay, Certificate Follow-up, Data Quality tables | Verified complete | Secure View links, recipient-aware certificate states/ageing, deadline responsibility, workload/delay, and current-term data-quality checks pass focused tests. |
| E-REPORT-04 | RES-only, server revalidation, aggregate/privacy, no external analytics, responsive/empty/N+1-safe | Not yet verified | RES middleware, validated filters, first-party CSS/Blade only, fixed aggregate query count, privacy/empty-state tests, and overflow boundaries pass. Responsive browser acceptance remains blocked. No export was added. |

No item is currently classified **Incomplete/incorrect** after the final regression run. Rows marked **Not yet verified** are deliberately open because the requested browser/native-viewer/visual/scanner evidence could not be collected.

## Current verification log

| Check | Result |
| --- | --- |
| Focused repaired-dashboard/static regression | 4 tests, 73 assertions, passed |
| Final complete Laravel suite | 329 tests, 4,545 assertions, passed |
| Repository-wide `vendor/bin/pint --test` | Passed |
| `npm run build` | Passed; optional Fontaine optimization notice only |
| `php artisan view:cache` | Passed |
| `php artisan route:list --except-vendor` | Passed; 172 routes |
| `git diff --check` before documentation | Passed |
| Local MySQL `migrate:status` before/after | Sole pending additive migration applied as batch 6; all migrations now Ran |
| Representative certificate PDF generation/text extraction | Passed; expected content present, Payment Proof absent, one local PDF generated |
| Reference review | Supplied PDF inspected; its QR occupies the lower-left reference zone. Source generation constants use x=24 mm, y=237 mm, 30 mm square and a non-overlapping signature zone. |
| Independent QR decode and pixel/raster comparison | Not yet verified; installed local tools provide neither a PDF rasterizer nor a QR decoder/scanner |
| Authenticated desktop/tablet/mobile browser checks | Not yet verified; approved in-app browser reports zero sessions |

## Remaining manual acceptance

1. Attach/connect an authenticated in-app browser and execute the role/viewport checklist in `MANUAL_VISUAL_VALIDATION.md`, including long values, filters, collapsibles, modals, uploads, and private native previews.
2. Rasterize a newly generated representative certificate and compare it side by side with the supplied certificate PDF; scan the generated fallback QR and a configured replacement QR using an approved local decoder/scanner.
3. Retain the existing separate Microsoft Excel workbook manual check.
