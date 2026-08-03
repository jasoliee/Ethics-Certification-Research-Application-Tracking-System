# Applicant Dashboard

## Implemented Experience

Student and Faculty Researchers use the canonical `/dashboard` and the Applicant route prefix `/student-faculty-researcher`. The dashboard reads only the signed-in Applicant's records and links to the complete initial application workflow.

The Application area supports:

- applicant-owned paginated application history;
- semester and academic-year history filters plus internal horizontal table scrolling;
- one database-enforced editable draft slot per Applicant;
- create, continue, and update actions;
- Student- and Faculty-specific information validation;
- active database-backed Institution, Department, and Program options;
- selection of an active Research Adviser whose account setup is complete;
- private individual/batch requirement upload, replacement, removal, preview, and download;
- a shared mandatory-requirement completion percentage;
- an Open/Closed formal-submission label sourced from RES Lead configuration;
- a maximum of three formally submitted applications, excluding drafts;
- server-enforced formal submission during the configured period; and
- read-only application details after submission.

## Application Information

The Applicant supplies research title, Thesis or Capstone type, research category, institution, department, program when required, Research Adviser, abstract, target participants, Starting Date, and Ending Date. Ending Date must be on or after Starting Date. The server validates the same persisted fields again at final submission. Historical records that only contain the earlier duration text remain readable without guessed date conversion.

Repeated Start or Save requests converge on the Applicant's existing editable draft. A draft receives a non-sequential public application code, remains private to its owner, and is not visible to the Adviser until formal submission succeeds.

The dashboard selects the newest created non-archived application owned by the signed-in Applicant. Editing an older record later does not make it replace a newer application. Application data is not hidden solely because an older record lacks the current term link; deadline alerts and timeline events remain tied to the active configured term.

## Requirements

The requirement checklist contains active requirements applicable to the selected research type. Completion counts only applicable mandatory requirements whose current document version has `completed` status. Optional requirements can appear without changing submission readiness.

Accepted files are PDF, DOC, DOCX, XLS, XLSX, JPEG, and PNG up to 100 MB each. Files use randomized paths on Laravel's private `local` disk. Replacing a requirement creates a new current record while preserving prior database/file history and the version assigned to the current revision cycle. Removing a current document detaches it from readiness without deleting its private history. Individual and Upload All responses update completion and checklist state immediately; a final refresh occurs only when no selected browser file can be lost. Word and Excel files use the authorized viewer fallback when native browser rendering is unavailable.

The requirement workspace combines application identity and completion in one responsive overview. File controls keep a stable button position, show `No file selected` or the selected filename, and support Upload All for selected requirements. Browser-supported content opens in an authorized modal; other Office files retain a protected download fallback. Long modal values use ellipsis and expose the full value through the shared 0.5-second tooltip.

## Submission

Formal submission fails closed when no approved application-submission deadline is configured, before the opening date, or after the due date. A manual `On` override can open submission outside those dates while the configured term remains active; `Off` returns the process to automatic date evaluation. It also rechecks ownership, editable status, an available formal-application slot, complete persisted information, active Adviser eligibility, and every mandatory requirement.

The maximum is three records with a non-null formal `submitted_at` timestamp. Unsubmitted drafts do not count. Returning and resubmitting the same application does not consume another slot. When no editable draft exists and the limit is reached, Create Application is disabled and the red warning explains the formal count.

The checklist always appears in this order: submission open, formal slot available, information complete, Adviser eligible, and mandatory requirements complete. Clicking Submit Application opens a confirmation dialog; only Confirm Submission sends the request.

On success, the application moves to `submitted_to_adviser`, its stage becomes `adviser_review`, its draft slot is released, the submission time is recorded, the assigned Adviser receives a database notification, and both actions are audited. A repeated owner request after success is idempotent.

## Pending Workflow

Adviser return/endorsement, RES administrative screening/classification, and exact initial reviewer assignment are implemented for the complete initial submission. Blind reviewer evaluation, later revision handling after result release, direct Exempted release, certificates, QR access, and final archival remain incomplete end to end.
