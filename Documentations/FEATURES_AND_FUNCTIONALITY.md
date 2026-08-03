# Features and Functionality

This file is the current implemented-feature catalog for ECRATS as of August 3, 2026. A feature is listed as implemented only when the repository contains its authorization, validation, persistence, interface, and relevant automated coverage. Later ethics-review stages remain listed separately so dashboard placeholders are not mistaken for complete workflows.

## Authentication and Account Access

- Users sign in with their generated username rather than email.
- Public self-registration is disabled.
- New managed accounts begin in pending setup and receive a one-time password setup link.
- Password setup and password reset links use Laravel's password broker, expire, and cannot be reused after completion.
- Login enforces active-account state, request throttling, generic credential failures, no-store browser history protection, and role-aware redirection.
- Password fields provide one ECRATS visibility control while suppressing the browser's duplicate reveal control where supported.
- Student Researcher, Faculty Researcher, Research Adviser, Ethics Reviewer, and RES Lead accounts receive role-specific onboarding and navigation.

## User Management

- RES Lead can create and manage Applicant, Adviser, and Reviewer accounts but cannot create another RES Lead through User Management.
- Advisers can create and manage only Student and Faculty Researcher accounts within their authorized routes.
- Usernames are generated from institutional identity and are not chosen by the creator.
- Individual profile updates, confirmed identity correction, username regeneration, account activation/deactivation, soft deletion, mass actions, setup-link resend, and filtered audit history are implemented.
- Account Information keeps Deactivate/Reactivate and Delete Account actions aligned as one lifecycle action group.
- Phone Number accepts digits only and is limited to 11 digits. Student Number and Employee ID accept approved alphanumeric identifiers.
- Archived accounts are preserved through soft deletion and can be restored only by a RES Lead from an actor-owned, unexpired import preview.

## Excel Bulk Account Import

- The active import format is the official macro-free `.xlsx` workbook; CSV, XLS, XLSM, XLSB, and renamed non-XLSX files are rejected.
- Role-specific workbooks contain `Accounts`, hidden `Options`, and `Instructions` worksheets with exact headers and controlled dropdowns.
- Uploads are limited to 2 MB and 250 account rows and undergo bounded ZIP/OOXML validation before account data is accepted.
- Preview separates valid, invalid, duplicate, active-existing, archived, restored, conflict, and warning rows.
- Invalid workbook rows identify the Excel row, field, safe submitted value, reason, and expected format.
- No account is created during preview. Confirmation is single-use and creates only valid rows.
- Current option labels, immutable numeric option IDs, and historical labels are resolved to one active `profile_options.id`.
- Renaming an option records its prior label in `profile_option_aliases`; an older workbook can therefore resolve the old label and store the current canonical label.
- Deactivated options and their aliases remain invalid for new accounts and imports.
- The workbook still displays labels because a macro-free Excel dropdown cell cannot safely retain a hidden stable ID without changing the approved Accounts columns. Identity resolution is therefore server-owned.

## Applicant Application Workflow

- Applicants have paginated application history with semester and academic-year filters and internal horizontal table scrolling.
- Each Applicant can have only one editable draft slot at a time. Repeated create requests reuse that draft.
- New application codes use the approved year, Applicant type, institution acronym, date, and collision-checked random suffix.
- Application information validates research title, type, category, institution, department, Student program, eligible Adviser, abstract, target participants, Starting Date, and Ending Date.
- Ending Date must be on or after Starting Date. Historical records with the legacy duration text remain readable.
- The Application page shows whether formal submission is Open or Closed beside Create/Resume Application.
- The maximum is three formally submitted applications per Applicant. Drafts do not consume a slot, and resubmitting the same returned application reuses its original slot.
- When the limit is reached, Create Application is server-blocked and rendered disabled with the shared red warning treatment.

## Requirement Documents and Submission

- Active requirements are filtered by research type and summarized as Completed, Missing, Pending, and Rejected.
- PDF, DOC, DOCX, XLS, XLSX, JPEG, and PNG files up to 100 MB are accepted after server-side MIME inspection.
- Files use randomized private-storage paths. Original names are display metadata only.
- Applicants can choose files per requirement, upload individually, upload selected requirements together, replace a current document, remove its current pointer, preview browser-supported content, and download authorized files.
- Upload responses immediately update completion counts, the final checklist, and submit readiness. A completed upload set refreshes only when no selected browser file would be lost.
- Word and Excel files open an authorized no-store viewer fallback with a secure download when native browser rendering is unavailable.
- Replacement history remains private and retained. Versions follow the application's revision cycle.
- The document viewer constrains long titles/filenames with ellipsis and exposes the full value through a delayed accessible tooltip.
- Application identity and requirement completion share one responsive overview.
- The final checklist follows the authoritative order: submission open, formal slot available, information complete, Adviser eligible, mandatory documents complete.
- Clicking Submit Application opens a confirmation dialog. The server still repeats every authorization and readiness check before the formal transition.
- Successful submission records the timestamp, releases the draft slot, moves the application to Adviser Review, notifies the assigned Adviser, and writes audit events.

## Adviser Initial Decision

- Adviser dashboard and application list include only formally submitted records assigned to the signed-in Adviser.
- Relevant assigned submissions remain visible even when older records have no current academic-term link; deadline and timeline data remain current-term aware.
- The Adviser can inspect application information and current authorized private documents.
- During an available Adviser Endorsement period, the assigned Adviser can return a complete initial submission with a required reason and correction instructions or endorse it with optional remarks.
- A return records an endorsement-history row, preserves the original formal submission timestamp and slot, notifies the Applicant, and reopens the same application for correction.
- An endorsement records the decision once, advances the application to RES Screening, notifies the Applicant, and makes repeated Adviser decisions unavailable.
- An endorsement also sends a neutral database notification to every active RES Lead and places the record on the RES Applications Queue.
- Decisions require the initial revision cycle, current assignment, complete information/documents, an available deadline process, authorization, transaction locking, notification, and audit logging.

## Dashboards and Deadline Configuration

- Applicant dashboard shows the newest created non-archived owned application, its current status, requirement completion, deadline alert, and configured timeline.
- Adviser dashboard shows assigned formal-submission counts and five recent assigned submissions.
- Reviewer dashboard shows current-term assignment counts, near deadlines, revisions, and recent assignments.
- RES Lead dashboard shows stored administrative queue counts, recent pending actions, active deadlines, and timeline data.
- RES Lead Applications Queue lists all formally submitted records that have entered the RES workflow, with bounded pagination, full approved search/filter coverage, protected detail links, and an internal horizontal-scroll table.
- Application dashboard queries do not hide relevant Applicant, Adviser, or RES records solely because a term link is absent or historical. Deadline and timeline queries remain tied to the current active term.
- RES Lead configures Semester, Academic Year, term dates, and seven process rows; the result/certificate release row uses one exact date/time.
- ECRATS evaluates workflow dates in `Asia/Manila`.
- A toggle set to `On` manually opens that process outside its date range while the configured term remains active. `Auto` removes the override and returns the process to automatic date evaluation.
- Automatic mode is open only from the configured opening time through its deadline; missing, future, expired, inactive, or outside-term configurations fail closed.
- Term dates and every process opening, deadline, or release value reject past values in both browser and server validation, including manually open rows.
- Saving deadline settings also synchronizes role timeline events used by dashboards.

## RES Screening and Initial Reviewer Assignment

- RES Lead sees the application overview, research information, current private requirement checklist, administrative checks, screening notes, and classification controls on one protected detail page.
- The server requires current mandatory-document readiness, complete/accepted administrative states, three affirmative eligibility checks, and a classification reason.
- One `application_screenings` row preserves the initial Expedited, Full Board, or Exempted decision and actor.
- RES Leads can correct persisted screening details; compatible assignments remain, incompatible pending assignments are removed, and started review work blocks destructive correction.
- Expedited requires exactly one eligible reviewer; Full Board requires exactly three distinct eligible reviewers.
- Eligibility is repeated inside the locked assignment transaction and includes active/setup-complete status, matching classification, Applicant/Adviser exclusion, and remaining active capacity.
- All eligible classification-matched reviewers are listed, with department and institution matches prioritized. Department filtering and name/position/department search are available. Full-load reviewers are visible but disabled.
- Assignment uses the existing `reviewer_assignments` table and advances to `under_expedited_review` or `under_full_board_review`.
- Exempted bypasses assignment and advances to the additive `exempted` status at the direct-release boundary.
- Classification and assignment produce bounded audit events and neutral Applicant/Reviewer notifications.

## Reviewer Assigned Applications

- Reviewers receive an owner-scoped Assigned Applications page with search, review type, status, research type, and deadline filters.
- The six-column table uses 15-row pagination, an empty state, and contained responsive overflow.
- Assignment details omit Applicant and Adviser profile identities, show research context, and provide assignment-gated current-document preview/download.
- PDF/images stream inline. Word/Excel use the authorized fallback. Automated document redaction, conflict declaration, and decision forms remain pending.

## Interface and Accessibility

- All Blade tables use the reusable `dashboard-overflow-region` boundary so wide columns scroll inside their container rather than widening the page.
- Applicant list, details, requirements, Adviser and RES lists/decision controls, the seven-row deadline table, account actions, and import surfaces include desktop, tablet, and narrow-screen layouts.
- Modals restore focus, close by explicit control, backdrop, or Escape, and keep private content bounded.
- Buttons, status badges, tooltips, pagination, empty states, and validation summaries use shared components and accessible labels.

## Security and Audit Boundaries

- Policies enforce Applicant ownership, Adviser assignment, RES Lead administration, and nested application/document relationships.
- Research uploads, import previews, and generated account workbooks are not served from `public/`.
- Passwords, reset tokens, credentials, raw private documents, and private file paths are excluded from audit metadata.
- Major account, import, option, application, document, submission, Adviser decision, deadline, and authorization-denial actions are audited.

## Not Yet Complete End to End

- Reviewer conflict declaration, anonymized review worksheet, reviewer decisions, reassignment, and the full blind-review lifecycle.
- Applicant revision workflow after official RES result release.
- Final result release, Exempted direct release, feedback gate, certificate rendering, protected certificate download, and QR verification.
- Production deployment, production mail provider configuration, backup/restore operations, and final browser acceptance evidence.

Update this catalog and the affected topic guide in the same change whenever behavior, validation, role access, workflow status, storage, or user-visible functionality changes.
