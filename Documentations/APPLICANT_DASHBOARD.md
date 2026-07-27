# Applicant Dashboard

## Implemented Experience

Student and Faculty Researchers use the canonical `/dashboard` and the Applicant route prefix `/student-faculty-researcher`. The dashboard reads only the signed-in Applicant's records and links to the complete initial application workflow.

The Application area supports:

- applicant-owned paginated application history;
- one database-enforced editable draft slot per Applicant;
- create, continue, and update actions;
- Student- and Faculty-specific information validation;
- active database-backed Institution, Department, and Program options;
- selection of an active Research Adviser whose account setup is complete;
- private requirement upload, replacement, preview, and download;
- a shared mandatory-requirement completion percentage;
- server-enforced formal submission during the configured period; and
- read-only application details after submission.

## Application Information

The Applicant supplies research title, Thesis or Capstone type, research category, institution, department, program when required, Research Adviser, abstract, target participants, and expected duration. The server validates the same persisted fields again at final submission.

Repeated Start or Save requests converge on the Applicant's existing editable draft. A draft receives a non-sequential public application code, remains private to its owner, and is not visible to the Adviser until formal submission succeeds.

## Requirements

The requirement checklist contains active requirements applicable to the selected research type. Completion counts only applicable mandatory requirements whose current document version has `completed` status. Optional requirements can appear without changing submission readiness.

Accepted files are PDF, DOC, DOCX, JPEG, and PNG up to 10 MB each. Files use randomized paths on Laravel's private `local` disk. Replacing a requirement creates a new current version while preserving prior database and file history.

## Submission

Formal submission fails closed when no approved application-submission deadline is configured, before the opening date, or after the due date. It also rechecks ownership, editable status, complete persisted information, active Adviser eligibility, and every mandatory requirement.

On success, the application moves to `submitted_to_adviser`, its stage becomes `adviser_review`, its draft slot is released, the submission time is recorded, the assigned Adviser receives a database notification, and both actions are audited. A repeated owner request after success is idempotent.

## Pending Workflow

Adviser endorsement or return decisions, Applicant revision cycles, results, certificates, and final archival are separate modules and are not implemented by this initial-submission slice.
