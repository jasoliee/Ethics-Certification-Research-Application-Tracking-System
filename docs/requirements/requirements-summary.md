# Requirements Summary

Primary source: `context_files/[DRAFT] ECRATS_System_Project_Documentation.docx`.

## Purpose

ECRATS centralizes the KLD ethics certification process from application submission through certificate release. It replaces scattered links, spreadsheets, file folders, and separate communication channels with a role-based Laravel system.

## User Roles

- Student or Faculty Researcher / Group Leader
- Research Adviser
- Ethics Reviewer
- RES Lead/Admin
- Technical Admin

## Major Modules

- Authentication and controlled account creation
- Applicant application draft, submission, documents, receipt upload, revisions, feedback, certificate access
- Research Adviser review, receipt verification, return, endorsement, expected-group monitoring
- RES screening, review classification, reviewer assignment, release control, certificates, reports, configuration
- Ethics Reviewer blind workspace, official worksheets, comments, decisions, re-review
- Notifications, Regala neutral guidance, and audit logs

## Core Workflow

1. Applicant creates a draft.
2. Applicant uploads required documents and payment proof.
3. System validates completeness before formal submission.
4. Applicant submits to adviser.
5. Adviser reviews, verifies receipt image, returns or endorses.
6. RES screens and classifies the application.
7. RES assigns reviewers by review type, discipline, availability, and capacity.
8. Reviewer works only in an anonymized assigned workspace.
9. Reviewer submits decision and comments.
10. RES releases held results.
11. Applicant submits revisions if required, routed directly to assigned reviewer or reviewers.
12. Accepted applications complete required feedback.
13. RES generates and releases certificate with control number and QR access.

## Non-Negotiable Requirements

- No public self-registration.
- Incomplete applications must not enter official tracking or review.
- Adviser handles only initial endorsement.
- Revisions do not return to adviser.
- Reviewer identity is hidden from applicants.
- Applicant identity is hidden from reviewers.
- No direct applicant-reviewer chat or negotiation.
- Reviewer comments are hidden until official release.
- Private files and certificates require authenticated authorization.
- Audit logs are required for major workflow actions.
