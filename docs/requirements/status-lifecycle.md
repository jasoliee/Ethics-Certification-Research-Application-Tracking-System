# Application Status Lifecycle

Use this as the working lifecycle until implementation plans refine status enums.

## Draft and Submission

1. `draft`
2. `incomplete`
3. `submitted_to_adviser`
4. `returned_by_adviser`
5. `adviser_endorsed`

## RES Screening and Assignment

6. `under_res_screening`
7. `awaiting_reviewer_assignment`
8. `under_expedited_review`
9. `under_full_board_review`
10. `exempted` (branch to the direct documentation/release boundary)

## Decision Hold and Release

11. `review_submitted_pending_release`
12. `result_released_accepted`
13. `result_released_minor_revision`
14. `result_released_major_revision`
15. `result_released_disapproved`

## Revision

16. `revision_window_open`
17. `revision_submitted`
18. `under_re_review`

## Certificate

19. `feedback_required`
20. `certificate_released`
21. `archived`

## Exempted Path

Exempted applications pass through RES screening, persist the classification, advance to `exempted`, and bypass standard reviewer assignment/review. The later direct documentation, release, and certificate controls are not yet implemented.

## Status Rules

- `draft` and `incomplete` are not official RES queue states.
- Only `adviser_endorsed` or `under_res_screening` may receive the single initial RES classification.
- `awaiting_reviewer_assignment` may advance only after exactly one Expedited or three Full Board reviewers pass locked eligibility/capacity checks.
- Reviewer comments must not be visible before a result release state.
- Certificate generation is blocked until the application is accepted and required feedback is complete.
- Use `disapproved` as the preferred system wording for rejected/disapproved outcomes because the official reviewer forms use "Disapproved."
- Public-safe certificate verification must not expose private files or internal workflow details.
