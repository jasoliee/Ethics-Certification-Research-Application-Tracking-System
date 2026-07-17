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

## Decision Hold and Release

10. `review_submitted_pending_release`
11. `result_released_accepted`
12. `result_released_minor_revision`
13. `result_released_major_revision`
14. `result_released_disapproved`

## Revision

15. `revision_window_open`
16. `revision_submitted`
17. `under_re_review`

## Certificate

18. `feedback_required`
19. `certificate_released`
20. `archived`

## Exempted Path

Exempted applications are a confirmed addition. They still pass through RES screening and documentation, but bypass standard reviewer assignment and review. The implementation should include an Exempted status path during the database/workflow feature plan.

## Status Rules

- `draft` and `incomplete` are not official RES queue states.
- Reviewer comments must not be visible before a result release state.
- Certificate generation is blocked until the application is accepted and required feedback is complete.
- Use `disapproved` as the preferred system wording for rejected/disapproved outcomes because the official reviewer forms use "Disapproved."
- Public-safe certificate verification must not expose private files or internal workflow details.
