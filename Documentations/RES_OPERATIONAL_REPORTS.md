# RES Operational Reports

The RES Lead Reports page is an authorized, read-only operational dashboard built only from stored ECRATS data. It uses no third-party analytics, external chart CDN, or export service.

## Filters and scope

Academic Term is first; the active term is first in the list and labelled `Current`. Date range, research type, Applicant category, review classification, Institute, and workflow status are validated on the server. Every query remains inside RES authorization and the selected term/filter scope.

## Report contents

- Eight summary cards: submitted, awaiting screening, awaiting assignment, under review, awaiting decision release, for certificate release, certificates released, and overdue/due-soon.
- Submission trend, classification distribution, decision distribution, stage/end-to-end average and median turnaround, Reviewer workload, Adviser endorsement workload, and certificate operations. The former workflow-pipeline presentation is intentionally absent.
- Action Required, Reviewer Capacity and Delay, Certificate Follow-up, and Operations/Data Quality management tables.
- Application-level Institute drill-down, filtered-application, and certification tables. Recipient certificates are summarized without duplicating applications; the aggregate status distinguishes Claimed, Unclaimed, Partial, and Not issued.
- Dedicated View All pages preserve the active validated filter scope. Each certification action opens the Released Applicant Record for that specific application while retaining identity verification from its complete certificate records.

Every chart-like section has a textual/table equivalent, labels do not rely on color alone, wide tables use the shared contained overflow boundary, and empty selected scopes render explicit empty states. Workload aggregates use fixed grouped queries rather than per-user N+1 queries. No private document, Reviewer comment, credential, or unnecessary Applicant detail is included.

## Downloads and print views

- **Download Report** produces either `.xlsx` or PDF from the current validated filters. The workbook separates operational and anonymous survey data into worksheets and wraps every populated cell.
- **Download Survey** produces a survey-only `.xlsx` or PDF without Applicant identity or free-text responses.
- **Print Report** and **Print Survey** open inline PDFs produced by the same generators as their corresponding PDF downloads. The browser therefore prints the official PDF layout, background, outlines, pagination, and current validated filter scope instead of a separate HTML print view.
- Both download-format dialogs use the shared accessible modal behavior.
- Generated PDFs automatically compose against the active Review Worksheet Background from private managed storage. A translucent white content layer keeps tables readable while preserving the configured institutional design.
- Download responses are RES-authorized, private/no-store, and generated from stored ECRATS records without an external export service.

## Verification

`ResOperationalReportTest` covers authorization, filter validation/scope, stored-data aggregates, average/median calculations, application-level certification aggregation, current-term data-quality checks, accessible output, Excel wrapping, genuine PDF downloads, equivalent inline print PDFs after volatile creation metadata is excluded, and query-count stability as Reviewer/Adviser rows grow.

Manual responsive/browser acceptance remains tracked in `MANUAL_VISUAL_VALIDATION.md`.
