# RES Operational Reports

The RES Lead Reports page is an authorized, read-only operational dashboard built only from stored ECRATS data. It uses no third-party analytics, external chart CDN, or export service.

## Filters and scope

Academic Term is first; the active term is first in the list and labelled `Current`. Date range, research type, Applicant category, review classification, Institute, and workflow status are validated on the server. Every query remains inside RES authorization and the selected term/filter scope.

## Report contents

- Eight summary cards: submitted, awaiting screening, awaiting assignment, under review, awaiting decision release, for certificate release, certificates released, and overdue/due-soon.
- Workflow pipeline, submission trend, classification distribution, decision distribution, stage/end-to-end average and median turnaround, Reviewer workload, Adviser endorsement workload, and certificate operations.
- Action Required, Reviewer Capacity and Delay, Certificate Follow-up, and Operations/Data Quality management tables.
- Recipient-aware certificate status: an application is fully released only when the complete configured certificate set is released/claimed. Missing initial generation and partial recipient sets remain visible as pending/partial states.

Every chart-like section has a textual/table equivalent, labels do not rely on color alone, wide tables use the shared contained overflow boundary, and empty selected scopes render explicit empty states. Workload aggregates use fixed grouped queries rather than per-user N+1 queries. No private document, Reviewer comment, credential, or unnecessary Applicant detail is included.

## Verification

`ResOperationalReportTest` covers authorization, filter validation/scope, stored-data aggregates, average/median calculations, recipient-aware certificate follow-up, current-term data-quality checks, accessible output, and query-count stability as Reviewer/Adviser rows grow. It is included in the final 329-test, 4,545-assertion suite.

Manual responsive/browser acceptance remains tracked in `MANUAL_VISUAL_VALIDATION.md`.
