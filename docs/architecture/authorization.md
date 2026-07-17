# Authorization Design

ECRATS uses three authorization layers.

## Layer 1: Middleware

Middleware checks broad access:

- User is authenticated.
- Account is active.
- User has the required role.
- Submission or revision windows are open when applicable.

Planned middleware examples:

- `EnsureAccountIsActive`
- `EnsureUserHasRole`
- `EnsureApplicantRole`
- `EnsureAdviserRole`
- `EnsureReviewerRole`
- `EnsureResRole`
- `EnsureTechnicalAdminRole`
- `EnsureReviewerAssignmentAccess`
- `EnsureCertificateAccess`

## Layer 2: Policies

Policies check record-level access:

- Applicant owns this application.
- Adviser is assigned to this application for initial review.
- Reviewer is assigned to this anonymized application.
- RES user can screen or release this record.
- Certificate belongs to the requesting applicant or is being managed by RES.

## Layer 3: Workflow Guards

Services check whether an action is valid for the current workflow state:

- Application can be submitted only when complete and inside the submission window.
- Adviser can endorse only submitted complete applications.
- Reviewer can submit only assigned active reviews.
- RES can release only held decisions.
- Applicant can submit revisions only during an open revision window.
- Certificate can be released only after approval and required feedback.

## Confidentiality Rules

- Applicants never see reviewer identity.
- Reviewers never see applicant names, group members, adviser names, or identifying profile data in blind-review screens.
- Reviewer comments and decisions are hidden until official RES release.
- Regala and notifications must not reveal unreleased decisions.
- Private file routes must authorize every request.
