# RES Screening and Reviewer Assignment

## Implemented Boundary

The initial RES workflow is implemented from the adviser-endorsed queue through editable screening/classification and, when required, initial reviewer assignment. The protected interface follows the supplied `ECRATS High Fidelity (6).pdf` and `ECRATS High Fidelity (7).pdf` references while reusing the existing dashboard shell, table, badge, empty-state, pagination, modal, and private-document components.

The Reviewer Assigned Applications list, details, and assignment-gated private document access are implemented. This slice does not implement the reviewer worksheet, anonymized document package, conflict declaration, reviewer decision, official result release, certificate generation, or QR verification.

## Applications Queue

`GET /res-lead/applications` lists only formally submitted applications in an adviser-endorsed or later RES status. It supports:

- search by application code, research title, applicant, adviser, institute, or program;
- status, Applicant category, research type, review type, and institute/program filters;
- an endorsement date range;
- 15-row pagination, a filtered empty state, and query-string preservation; and
- an internal horizontal overflow region for the eight-column table.

Queue queries are parameterized Eloquent queries. Draft, incomplete, submitted-to-adviser, and returned-to-adviser records remain outside this surface.

## Screening and Classification

Only a RES Lead may open the write routes. `ClassifyResearchApplicationRequest` validates the administrative decision before the workflow service executes it. Classification requires:

- `complete` completeness status;
- `accepted` receipt-check status;
- confirmation that required documents were verified;
- confirmation that receipt status was recorded;
- confirmation that basic eligibility was checked;
- one of `expedited`, `full_board`, or `exempted`; and
- a 15-to-2,000-character reason or basis.

The service locks the application row, repeats policy authorization, rejects an existing screening, and recalculates mandatory-document readiness from persisted current documents. The browser confirmation and checkboxes do not replace these server checks.

One `application_screenings` row preserves the actor, administrative states, confirmations, optional notes, classification, reason, and timestamp. A unique application foreign key prevents two initial classifications.

## Screening Corrections

`PUT /res-lead/applications/{researchApplication}/classification` allows an authorized RES Lead to correct completeness, receipt status, the three administrative confirmations, notes, review type, and reason during screening, reviewer assignment, active initial review, or the Exempted boundary. Later result, revision, certificate, and archive states are immutable from this surface. The workflow locks the application, screening, and initial assignments before it reconciles status and stage.

- An unchanged complete decision with either zero assignments or the exact required count preserves the assignment set and current workflow projection.
- Revoking administrative readiness or changing to an incompatible classification removes only `pending`, unsubmitted assignments.
- Any `in_review`, `revision_review`, submitted, or otherwise started assignment blocks an incompatible correction.
- Exempted requires zero reviewers; Expedited requires one; Full Board requires three.
- The correction writes `application.res_screening_updated` without notes or reasons and sends neutral notifications.

## Reviewer Eligibility and Assignment

Expedited review requires exactly one reviewer. Full Board review requires exactly three distinct reviewers. Candidate and final-write checks require:

- an active, unarchived Reviewer account with completed setup;
- a classification matching Expedited or Full Board;
- no Applicant or assigned-Adviser identity conflict; and
- an active assignment count below the configured reviewer capacity.

All active classification-matched candidates are visible by default. Exact application-department matches appear first, institution matches next, and other eligible candidates follow. Search covers name, position, and department, while Department provides an optional exact filter. Full-load rows remain visible with `current load / capacity` and disabled selection. Inactive, archived, setup-incomplete, classification-mismatched, and known-conflict accounts are omitted.

The confirmation dialog requires the exact count. The transaction then locks the application and selected reviewer rows, repeats every eligibility and capacity check, rejects an existing initial assignment set, and creates existing `reviewer_assignments` records. Sorted reviewer locking limits deadlock risk during concurrent capacity checks.

Successful assignment advances the application to:

- `under_expedited_review` and `ethics_review` for Expedited; or
- `under_full_board_review` and `ethics_review` for Full Board.

The saved result screen is read-only and displays the persisted reviewers, workload, assignment time, and assignment status.

## Exempted Path

Exempted classification writes the screening, sets `application_status` to `exempted`, advances the stage to `decision_release`, and bypasses reviewer assignment. The detail page states that direct documentation and certificate release remain a later RES process; it does not expose a non-functional release control.

## Security, Audit, and Notifications

- Role middleware and `ResearchApplicationPolicy` protect every RES route and record write.
- Private files remain outside `public/` and stream only through nested, policy-authorized preview/download routes.
- Classification, correction, and assignment use transactions, row locks, validation, unique database constraints, and request throttling.
- Audit actions are `application.res_classified`, `application.res_screening_updated`, and `application.reviewers_assigned`.
- Audit metadata contains only review type, reviewer count, and resulting status. It excludes screening notes, classification reasons, document contents, private paths, and reviewer comments.
- Applicant notifications describe only a neutral workflow update. Reviewer notifications disclose only that an assigned application is available.

## Routes

| Method | URI | Route name |
| --- | --- | --- |
| GET | `/res-lead/applications` | `res.applications.index` |
| GET | `/res-lead/applications/{researchApplication}` | `res.applications.show` |
| POST | `/res-lead/applications/{researchApplication}/classification` | `res.applications.classification.store` |
| PUT | `/res-lead/applications/{researchApplication}/classification` | `res.applications.classification.update` |
| GET | `/res-lead/applications/{researchApplication}/reviewers` | `res.applications.reviewers.index` |
| POST | `/res-lead/applications/{researchApplication}/reviewers` | `res.applications.reviewers.store` |

The existing RES private document preview/download routes remain unchanged.

## Local Demo Data

`DashboardDemoSeeder` creates the prerequisite endorsement and screening histories represented by later demo statuses. The maintained `reviewertest` account is an active Expedited reviewer in Computer Studies with available capacity. Run the normal local migrations and demo seeder after pulling schema changes; database rows are not transferred by Git.

## Remaining Limitations

- Reviewer-declared conflicts and automated blind/anonymized document generation are not implemented.
- There is no availability calendar. Capacity is displayed and enforced, but it is not presented as a separate availability column or filter.
- Reviewer deadlines are not selected during initial assignment.
- Reassignment, withdrawal, replacement, and assignment history controls are not implemented.
- Reviewer assignment list/details are implemented; evaluation forms, held decisions, Exempted direct release, official result release, certificates, and QR verification remain pending.
