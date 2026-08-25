# RES Screening and Reviewer Assignment

## Implemented Boundary

The RES workflow is implemented from the adviser-endorsed queue through classification and, when required, initial assignment or non-destructive reviewer reassignment.

The Reviewer task list, blind workspace, assignment-gated private document access, official forms, comments, initial recommendation submission, and reassignment history are implemented. The continuation now also implements explicit result release, revision re-review, and private certificate generation/claim. Document-content redaction and public QR verification remain outside the current scope.

## Applications Queue

`GET /res-lead/applications` lists only formally submitted applications in an adviser-endorsed or later RES status. It supports:

- search by application code, research title, applicant, adviser, institute, or program;
- status, Applicant category, research type, review type, and institute/program filters;
- an endorsement date range;
- 15-row pagination, a filtered empty state, and query-string preservation; and
- an internal horizontal overflow region for the eight-column table.

Queue queries are parameterized Eloquent queries. Draft, incomplete, submitted-to-adviser, and returned-to-adviser records remain outside this surface.

## Screening and Classification

Only a RES Lead may open the write routes. `ClassifyResearchApplicationRequest` requires one of `expedited`, `full_board`, or `exempted` plus a 15-to-2,000-character reason or basis. Administrative completeness, receipt, eligibility, confirmation, and notes fields are retired.

The service locks the application row, repeats policy authorization, rejects an existing classification, and recalculates mandatory-document readiness from persisted current documents.

One `application_screenings` row preserves the actor, classification, reason, and timestamp. A unique application foreign key prevents two initial classifications.

The classification editor uses the full available content width. Its desktop layout places Select Review Type and the three existing choices in the left column and Reason / Basis for Classification in the right column. The centered helper spans both columns, and the controls collapse to one column on smaller viewports. Saved Screening and Classification content also occupies the available width. When an application is already under review, Re-edit Decision is grouped beside View Assignment rather than separated from the related workflow action.

## Screening Corrections

`PUT /res-lead/applications/{researchApplication}/classification` allows an authorized RES Lead to correct review type and reason during screening, reviewer assignment, active initial review, or the Exempted boundary. Later result, revision, certificate, and archive states are immutable from this surface. The workflow locks the application, classification, and current initial assignments before it reconciles status and stage.

- An unchanged classification with either zero assignments or the exact required count preserves the assignment set and current workflow projection.
- Changing to an incompatible classification supersedes current assignments without deleting submitted work or history.
- Exempted requires zero reviewers; Expedited requires one; Full Board requires three.
- The correction writes `application.res_screening_updated` without notes or reasons and sends neutral notifications.

## Reviewer Eligibility and Assignment

Expedited review requires exactly one reviewer. Full Board review requires exactly three distinct reviewers. Candidate and final-write checks require:

- an active, unarchived Reviewer account with completed setup;
- a classification matching Expedited or Full Board;
- no Applicant or assigned-Adviser identity conflict; and
- an active assignment count below the configured reviewer capacity.

All active classification-matched candidates are visible by default. Exact application-Institute matches appear first and other eligible candidates follow. Search covers name, position, and Institute, while Institute provides an optional exact filter. Full-load rows remain visible with `current load / capacity` and disabled selection. Inactive, archived, setup-incomplete, classification-mismatched, and known-conflict accounts are omitted.

The confirmation dialog requires the exact count. On reassignment, the Reason for Reassignment field is shown inside Selected Reviewer immediately above Save Reviewer Set. The existing Form Request still requires 10 to 1,000 characters when the selected set changes, and the value remains part of the locked supersession/audit workflow. The redundant candidate-list message about known Applicant/Adviser conflicts is not shown; server-side conflict exclusion and final-write revalidation remain unchanged.

The transaction locks the application and selected reviewer rows and repeats every eligibility and capacity check. An unchanged set is idempotent. Removed assignments are superseded with actor, timestamp, reason, and prior status; retained assignments keep their identity; new rows link to replacements and receive the next sequence. Sorted reviewer locking limits deadlock risk during concurrent capacity checks.

Successful assignment advances the application to:

- `under_expedited_review` and `ethics_review` for Expedited; or
- `under_full_board_review` and `ethics_review` for Full Board.

The saved set remains editable until final release. New current assignments copy the configured Reviewer Submission deadline when available and immediately grant their owner access to the protected workflow documented in [Reviewer Workflow](REVIEWER_WORKFLOW.md).

## Exempted Path

Exempted classification writes the screening, sets `application_status` to `exempted`, advances the stage to `decision_release`, and bypasses reviewer assignment. The detail page states that direct documentation and certificate release remain a later RES process; it does not expose a non-functional release control.

## Security, Audit, and Notifications

- Role middleware and `ResearchApplicationPolicy` protect every RES route and record write.
- Private files remain outside `public/` and stream only through nested, policy-authorized preview/download routes. The RES checklist provides separate protected View and direct Download actions for every current document.
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

`DashboardDemoSeeder` creates the prerequisite endorsement and screening histories represented by later demo statuses. The maintained `reviewertest` account is an active Expedited reviewer in the Institute of Computing and Digital Innovation with available capacity. Run the normal local migrations and demo seeder after pulling schema changes; database rows are not transferred by Git.

## Remaining Limitations

- Automated blind/anonymized document generation and content-level identity redaction are not implemented.
- There is no availability calendar. Capacity is displayed and enforced, but it is not presented as a separate availability column or filter.
- Reviewer deadlines come from the active Reviewer Submission configuration; there is no per-assignment deadline picker.
- Reviewer evaluation forms, held initial decisions, non-destructive replacement, Exempted certificate eligibility, explicit official result release, and certificates are implemented; public QR verification remains pending.
