# Revised Detailed ECRATS Flowchart Specification

This document describes the current ECRATS workflow as a text-based flowchart. It preserves the original flow numbers for traceability, retires functions that are no longer implemented, and adds flows required by the current system.

## Flowchart notation

- `(START)` or `(END)` — terminator
- `/ INPUT /` — user-entered or system-received data
- `[PROCESS]` — system or user action
- `{DECISION?}` — conditional branch
- `[(ENTITY)]` — database entity or datastore
- `> OUTPUT <` — displayed, generated, or transmitted result
- `! ERROR !` — validation or authorization failure
- `A → B` — control or data connection
- `1:N`, `1:1`, or `N:1` — relationship cardinality

## Master workflow

~~~text
Account Provisioning
    ↓
Authentication and Authorization
    ↓
Application Information Draft
    ↓
Requirement Document Upload
    ↓
Formal Submission
    ↓
Adviser Review
    ├─ Returned → Applicant Correction → Formal Resubmission
    └─ Endorsed
          ↓
      REU Screening
          ├─ Exempted ───────────────────────────────┐
          ├─ Expedited → 1 Reviewer                  │
          └─ Full Board → 3 Reviewers                │
                         ↓                           │
                  Reviewer Evaluation                │
                         ↓                           │
                  Consensus Evaluation               │
                         ├─ Conflicted → Resubmission│
                         ├─ Minor/Major Revision     │
                         │       ↓                   │
                         │ Applicant Revision        │
                         │       ↓                   │
                         │ Same Reviewers Re-review  │
                         │       └──── Loop ≤ 3 ─────┤
                         ├─ Disapproved → Completed  │
                         └─ Approved ────────────────┤
                                                   ↓
                                     Certificate Generation
                                                   ↓
                                        REU Certificate Release
                                                   ↓
                                        Applicant Evaluation
                                                   ↓
                                         Certificate Claim
                                                   ↓
                                                 END
~~~

## Flow No. 1 — Academic Term, Timeline, and Deadline Configuration

1. `(START)` REU Lead opens Settings → Academic Terms and Deadlines.
2. `[PROCESS]` Authenticate and authorize the actor.
3. `{DECISION: actor is an active, setup-complete REU Lead?}`
   - No → `! ACCESS DENIED !` → `(END)`
   - Yes → continue.
4. `/ INPUT /` Select an existing academic term or request a new term.
5. `{DECISION: create a new academic term?}`
   - Yes → enter `semester`, `academic_year`, `starts_at`, `ends_at`, and initial `status`.
   - No → retrieve the selected `academic_terms` record.
6. `[PROCESS]` Validate the academic term.
7. `{DECISION: starts_at ≤ ends_at and term identity is valid?}`
   - No → `! VALIDATION ERROR !` → return to term input.
   - Yes → save or continue.
8. `[(ACADEMIC_TERMS)]` Persist `id`, `semester`, `academic_year`, `starts_at`, `ends_at`, `is_active`, and `status = active | paused | ended`.
9. `/ INPUT /` Configure the six supported processes:
   1. Application Submission
   2. Adviser Endorsement
   3. REU Screening and Classification
   4. Reviewer Submission
   5. Revision Period
   6. Reviewing of Revision Period
10. For each process enter `deadline_key`, `title`, `audience_role`, `starts_at`, `due_at`, optional `manual_status`, `priority`, and `is_active`.
11. `[PROCESS]` Validate each process configuration.
12. `{DECISION: required dates are present and starts_at ≤ due_at?}`
    - No → `! INVALID PROCESS SCHEDULE !` → return to process input.
    - Yes → continue.
13. `{DECISION: dates fall within the selected academic term?}`
    - No → `! TERM-BOUNDARY ERROR !` → correct dates.
    - Yes → continue.
14. `{DECISION: manual override requested?}`
    - No → calculate process state from dates and `is_active`.
    - Yes → set `manual_status = open | closed`.
15. `[(DEADLINE_CONFIGURATIONS)]` Save one record per process.
16. `[PROCESS]` Synchronize the matching calendar milestone.
17. `[(TIMELINE_CALENDAR_EVENTS)]` Save `academic_term_id`, `milestone_key`, `label`, `starts_at`, `ends_at`, `sort_order`, and `is_active`.
18. `[(AUDIT_LOGS)]` Store actor, term, action, affected configuration, sanitized metadata, IP address, user agent, and timestamp.
19. `> OUTPUT <` Display the saved term, process availability, deadline state, and timeline.
20. `(END)` Configuration is available to the corresponding workflows.

### Relationships

~~~text
AcademicTerm 1:N DeadlineConfiguration
AcademicTerm 1:N TimelineCalendarEvent
AcademicTerm 1:N ResearchApplication
AcademicTerm 1:N AuditLog
~~~

### Removed from this flow

- Expedited-only decision deadline
- Full Board deliberation schedule
- Scheduled decision-release date
- Certificate target date
- Duplicate Full Board configuration

## Flow No. 2 — Account Creation and Provisioning

1. `(START)` Authorized REU Lead or Adviser opens Account Management.
2. `[PROCESS]` Validate actor identity, account status, role, and setup completion.
3. `{DECISION: actor role?}`
   - REU Lead → may create Student Applicant, Faculty Applicant, or Adviser.
   - Adviser → may create Student Applicant or Faculty Applicant only.
   - Any other role → `! ACCESS DENIED !` → `(END)`.
4. `/ INPUT /` Select account type.
5. `{DECISION: selected account type is permitted for the actor?}`
   - No → `! UNAUTHORIZED ACCOUNT TYPE !`
   - Yes → continue.
6. `/ INPUT /` Enter applicable variables: `first_name`, `middle_name`, `last_name`, `suffix`, `email`, `institutional_identifier`, `phone_number`, `institution`, `program`, `year_level`, `position_title`, and `expected_endorsement_count`.
7. `{DECISION: creating an Applicant?}`
   - Yes → require `applicant_type = student | faculty`.
   - No → `applicant_type = null`.
8. `{DECISION: creating an Adviser?}`
   - Yes → optionally set `reviewer_enabled`.
   - No → Reviewer capability is unavailable.
9. `[PROCESS]` Validate required fields and managed profile options.
10. `{DECISION: email, institutional identifier, and generated username are unique?}`
    - No → `! DUPLICATE ACCOUNT DATA !` → return to input.
    - Yes → continue.
11. `[PROCESS]` Generate a unique username.
12. `[PROCESS]` Create the user with `role`, `applicant_type`, `account_status = pending_setup`, `created_by_user_id`, a temporary setup credential, and null setup timestamps.
13. `[(USERS)]` Save the account.
14. `[PROCESS]` Send one-time account setup email.
15. `{DECISION: email successfully queued or sent?}`
    - Yes → set `setup_email_status` and `setup_email_sent_at`.
    - No → preserve the pending account, set `setup_email_failed_at`, and show resend option.
16. `[(AUDIT_LOGS)]` Record account creation without passwords or setup tokens.
17. `> OUTPUT <` Display generated username and Pending Setup status.
18. `(END)` Account waits for password setup and onboarding.

### Relationships

~~~text
Creator User 1:N Created Users
Created User N:0..1 Creator User through created_by_user_id
Adviser User 1:N Applicant accounts created by that Adviser
User 1:N AuditLog as actor
~~~

### Restrictions

- No public registration.
- No separate Reviewer account creation.
- No Technical Admin application account.
- No ordinary REU Lead creation from this interface.
- Reviewer access is a capability on an Adviser account.

## Flow No. 3 — Authentication, Setup, and Role-Based Access

1. `(START)` User opens the login page.
2. `/ INPUT /` Enter `username` and `password`.
3. `[PROCESS]` Apply login throttling and validate the request.
4. `{DECISION: credentials valid?}`
   - No → increment failed-attempt counter → generic authentication error → `(END: login failed)`.
   - Yes → continue.
5. `[PROCESS]` Retrieve the `users` record.
6. `{DECISION: account_status?}`
   - `inactive` → deny access → `(END)`.
   - `pending_setup` → redirect to secure password setup.
   - `active` → continue.
7. `{DECISION: password setup completed?}`
   - No → require one-time setup.
   - Yes → continue.
8. `{DECISION: onboarding completed?}`
   - No → redirect to profile/onboarding completion.
   - Yes → continue.
9. `[PROCESS]` Regenerate authenticated session.
10. `[PROCESS]` Resolve role and capabilities.
11. `{DECISION: active academic term is paused?}`
    - Yes and user is not REU Lead → show operational-lock page → `(END: temporarily locked)`.
    - No → continue.
    - REU Lead → continue with administrative access.
12. `{DECISION: role?}`
    - Applicant → Applicant dashboard.
    - Adviser without Reviewer capability → Adviser dashboard.
    - Adviser with `reviewer_enabled = true` → Adviser dashboard plus Reviewer workspace.
    - REU Lead → REU dashboard.
    - Legacy Reviewer record → historical compatibility handling, not a current standalone portal.
13. `> OUTPUT <` Display authorized landing page.
14. `(END)` Authenticated session established.

### Principal variables

~~~text
users.username
users.password
users.role
users.applicant_type
users.account_status
users.reviewer_enabled
users.password_setup_completed_at
users.onboarding_completed_at
academic_terms.status
~~~

## Flow No. 4 — Create and Save Application Draft

1. `(START)` Active Applicant selects Create Application.
2. `[PROCESS]` Authorize Applicant role and ownership scope.
3. `[PROCESS]` Retrieve formal-submission count.
4. `{DECISION: Applicant already has three formally submitted applications?}`
   - Yes → `! APPLICATION LIMIT REACHED !` → `(END)`.
   - No → continue.
5. `{DECISION: another approved application already has a Released or Claimed certificate?}`
   - Yes → `! NEW APPLICATION BLOCKED !` → `(END)`.
   - No → continue.
6. `{DECISION: Applicant already owns an editable draft?}`
   - Yes → open the existing draft.
   - No → create a new draft slot.
7. `[PROCESS]` Resolve the applicable academic term.
8. `[PROCESS]` Generate a unique application code using the current code rules and Institute acronym.
9. `[(RESEARCH_APPLICATIONS)]` Create `academic_term_id`, `application_code`, `applicant_user_id`, `draft_owner_user_id`, `applicant_type`, `application_status = draft`, and `current_stage = application_information`.
10. `/ INPUT /` Enter `research_title`, `research_type = thesis | capstone`, `research_category`, `institution`, `program`, `abstract`, `target_participants`, `expected_duration`, `expected_start_date`, `expected_end_date`, and `adviser_user_id`.
11. `[PROCESS]` Validate information.
12. `{DECISION: Adviser is active, setup-complete, and eligible?}`
    - No → show Adviser validation error.
    - Yes → continue.
13. `{DECISION: expected_start_date ≤ expected_end_date?}`
    - No → show duration validation error.
    - Yes → continue.
14. `/ INPUT /` Enter certificate-recipient names.
15. `[PROCESS]` Normalize recipient names.
16. `{DECISION: recipient count is between 1 and 30?}`
    - No → validation error.
    - Yes → continue.
17. `{DECISION: normalized recipient names are unique within the application?}`
    - No → duplicate-recipient error.
    - Yes → save.
18. `[(APPLICATION_CERTIFICATE_RECIPIENTS)]` Save `research_application_id`, `recipient_name`, `normalized_name`, and `sort_order`.
19. `[PROCESS]` Save application information.
20. `{DECISION: all information fields complete?}`
    - No → `application_status = incomplete`; retain the information stage.
    - Yes → keep editable and set `current_stage = document_submission`.
21. `[PROCESS]` Resolve active requirements applicable to Thesis or Capstone.
22. `[(DOCUMENT_REQUIREMENTS)]` Return active, mandatory, research-type-scoped requirements.
23. `> OUTPUT <` Display requirement checklist.
24. `(END)` Draft saved and ready for document uploads.

### Relationships

~~~text
Applicant User 1:N ResearchApplication
Adviser User 1:N ResearchApplication
AcademicTerm 1:N ResearchApplication
ResearchApplication 1:N CertificateRecipient
ResearchApplication 1:N ApplicationDocument
~~~

## Flow No. 5 — Requirement Upload, Validation, and Formal Submission

1. `(START)` Applicant opens Document Submission.
2. `[PROCESS]` Authorize application ownership.
3. `[PROCESS]` Retrieve active requirements based on `research_type`.
4. `/ INPUT /` Select a requirement and upload a file.
5. `[PROCESS]` Validate server-detected MIME type, supported new-upload format, maximum 100 MB size, nonempty content, and private destination.
6. `{DECISION: valid format?}`
   - PDF, JPEG, PNG, GIF, or WebP → continue.
   - Otherwise → `! UNSUPPORTED FILE !`.
7. `[PROCESS]` Calculate checksum and next `document_version`.
8. `[PROCESS]` Mark the earlier document for the same requirement `is_current = false`.
9. `[(APPLICATION_DOCUMENTS)]` Save application, requirement, uploader, original name, private path, MIME type, size, checksum, version, validation status, current flag, and upload time.
10. `{DECISION: Save Only or Submit?}`
    - Save Only → show updated checklist → `(END: draft retained)`.
    - Submit → continue.
11. `[PROCESS]` Begin transaction and lock Applicant and application records.
12. `{DECISION: repeated request for an already successful submission?}`
    - Yes → return the original successful state without duplicate notifications.
    - No → continue.
13. `[PROCESS]` Reauthorize ownership.
14. `[PROCESS]` Recheck submission count, certified-application block, open submission process, complete information, mandatory requirements, valid current documents, and eligible Adviser.
15. `{DECISION: every validation passes?}`
    - No → rollback, retain draft, and display exact errors → `(END: rejected)`.
    - Yes → continue.
16. `[PROCESS]` Set `application_status = submitted_to_adviser`, `current_stage = adviser_review`, clear `draft_owner_user_id`, and set submission/status timestamps.
17. `[PROCESS]` Mark every current document with `formally_submitted_at`.
18. `[(NOTIFICATIONS)]` Create an in-app notification for the Adviser.
19. `[(AUDIT_LOGS)]` Record submission and notification.
20. `> OUTPUT <` Applicant sees Pending Adviser Review.
21. `(END)` Connect to Flow No. 6.

## Flow No. 6 — Adviser Review, Return, or Endorsement

1. `(START)` Adviser opens an assigned, formally submitted application.
2. `[PROCESS]` Authorize the assigned Adviser and Adviser-review state.
3. `[PROCESS]` Check Adviser Endorsement process availability.
4. `{DECISION: period open?}`
   - No → read-only record and deadline message → `(END)`.
   - Yes → continue.
5. `[PROCESS]` Retrieve information, recipients, and current submitted documents.
6. `[PROCESS]` Revalidate information and requirements.
7. `/ INPUT /` Select Return for Correction or Endorse to REU.

### Return branch

8. `/ INPUT /` Select `return_reason`: requirement correction, payment-proof correction when configured as a requirement, research-information clarification, or other required correction.
9. `/ INPUT /` Enter required remarks.
10. `[(ENDORSEMENTS)]` Create immutable return action with application, Adviser, status, reason, remarks, and `returned_at`.
11. `[PROCESS]` Set `application_status = returned_by_adviser`, retain Adviser Review stage, and update status time.
12. `[(NOTIFICATIONS)]` Notify Applicant.
13. `[(AUDIT_LOGS)]` Record return.
14. `(END: returned for correction)`.

### Endorse branch

15. `[(ENDORSEMENTS)]` Create immutable endorsement with optional remarks and `endorsed_at`.
16. `[PROCESS]` Set `application_status = adviser_endorsed`, `current_stage = res_screening`, and update status time.
17. `[(NOTIFICATIONS)]` Notify Applicant and active REU Leads.
18. `[(AUDIT_LOGS)]` Record endorsement.
19. `(END)` Connect to Flow No. 8.

### Relationships

~~~text
ResearchApplication 1:N Endorsement
Adviser User 1:N Endorsement
ResearchApplication N:1 Assigned Adviser
~~~

## Flow No. 7 — Adviser Expected-Endorsement Monitoring

1. `(START)` Adviser or REU Lead opens dashboard/monitoring.
2. `[PROCESS]` Authorize role and record scope.
3. `[PROCESS]` Retrieve `users.expected_endorsement_count`.
4. `[PROCESS]` Retrieve advised applications, excluding inappropriate draft/archived records.
5. `[PROCESS]` Calculate expected, pending, endorsed, returned, remaining, overdue, and action-required counts.
6. `{DECISION: viewer is Adviser?}`
   - Yes → show that Adviser only.
   - REU Lead → show all Advisers and filters.
7. `> OUTPUT <` Display monitoring results.
8. `(END)`.

The expected count is a field on the Adviser’s User record, not a separate D11 database.

## Flow No. 8 — REU Screening and Classification

1. `(START)` REU Lead opens an Adviser-endorsed application.
2. `[PROCESS]` Authorize classification access and retrieve current state.
3. `{DECISION: screening exists?}`
   - No → create-classification path.
   - Yes → authorized correction path.
4. `[PROCESS]` Revalidate all mandatory requirements.
5. `{DECISION: ready for classification?}`
   - No → show readiness errors and keep in screening.
   - Yes → continue.
6. `/ INPUT /` Enter `review_type = exempted | expedited | full_board` and `classification_reason`.
7. `[(APPLICATION_SCREENINGS)]` Save application, screening actor, review type, reason, and classification time.
8. `[PROCESS]` Copy classification to the application projection.
9. `{DECISION: review_type?}`
   - Exempted → `application_status = exempted`, `current_stage = decision_release`, Reviewer count 0 → certificate eligibility.
   - Expedited → `application_status = awaiting_reviewer_assignment`, required Reviewer count 1.
   - Full Board → `application_status = awaiting_reviewer_assignment`, required Reviewer count 3.
10. `[(NOTIFICATIONS)]` Send neutral Applicant status update.
11. `[(AUDIT_LOGS)]` Record type and reviewer count without the narrative.
12. `{DECISION: correction changes classification or required Reviewer count?}`
    - No → update screening metadata.
    - Yes → supersede affected assignments, preserve evidence, notify affected Reviewers, and reset the appropriate workflow state.
13. `(END)` Exempted connects to Flow No. 19; other classifications connect to Flow No. 9.

## Flow No. 9 — Reviewer Eligibility, Assignment, and Reassignment

1. `(START)` REU Lead opens Reviewer Assignment.
2. `[PROCESS]` Retrieve classification and required count: Exempted 0, Expedited 1, Full Board 3.
3. `{DECISION: Reviewers required?}`
   - No → assignment prohibited → `(END)`.
   - Yes → continue.
4. `[PROCESS]` Build eligible Reviewer-enabled Adviser list.
5. For each candidate validate active/setup-complete Adviser role, `reviewer_enabled = true`, not the endorsing Adviser, no uncleared conflict, fewer than 30 active assignments, and no duplicate selection.
6. `[PROCESS]` Prioritize exact Institute match without making it mandatory.
7. `{DECISION: enough eligible Reviewers?}`
   - No → capacity/eligibility error → `(END)`.
   - Yes → continue.
8. `/ INPUT /` Select exactly the required unique Reviewer IDs.
9. `[PROCESS]` Lock application and selected Reviewers, then revalidate.
10. `{DECISION: replacing a current Reviewer?}`
    - Yes → require `reassignment_reason`.
    - No → continue.
11. `[PROCESS]` Mark removed assignments `superseded`, record supersession actor, time, reason, and previous status.
12. `[(REVIEWER_ASSIGNMENTS)]` Create application, Reviewer, initial/revision type, cycle, status, sequence, replacement link, assignment time, and deadline.
13. `[PROCESS]` Set the application to Under Expedited Review, Under Full Board Review, or Under Re-review; set Ethics Review stage; reset consensus to Awaiting Submissions.
14. `[(NOTIFICATIONS)]` Notify new and removed Reviewers.
15. `[(AUDIT_LOGS)]` Record assignment counts without comments.
16. `(END)` Connect to Flow No. 10.

### Relationships

~~~text
ResearchApplication 1:N ReviewerAssignment
Reviewer-enabled Adviser 1:N ReviewerAssignment
ReviewerAssignment N:0..1 Replaced ReviewerAssignment
ResearchApplication 1:N ReviewerConflict
Reviewer-enabled Adviser 1:N ReviewerConflict
~~~

## Flow No. 10 — Open Privacy-Limited Reviewer Workspace

1. `(START)` Reviewer-enabled Adviser opens an assignment.
2. `[PROCESS]` Authenticate and authorize assignment ownership, Adviser role, Reviewer capability, non-superseded state, and compatible application state.
3. `{DECISION: authorization passes?}`
   - No → Access Denied → `(END)`.
   - Yes → continue.
4. `[PROCESS]` Check initial-review or revision-review deadline.
5. `[PROCESS]` Retrieve privacy-limited application data.
6. `[PROCESS]` Hide Applicant contact/account identity, Adviser identity where restricted, other Reviewer identities, and unreleased decisions.
7. `[PROCESS]` Retrieve current private documents.
8. `{DECISION: document belongs to the authorized application and assignment?}`
   - No → deny.
   - Yes → issue private preview/download.
9. `> OUTPUT <` Display permitted research details, current documents, prior versions for re-review, two forms, comments, decision draft, and deadline state.
10. `{DECISION: review period open?}`
    - Yes → enable editing/submission.
    - No → permitted read-only history.
11. `(END: workspace opened)`.

The system hides database identity fields but does not automatically redact identifying content embedded inside uploaded files.

## Flow No. 11 — Reviewer Forms, Comments, Draft, and Final Submission

1. `(START)` Reviewer works in the authorized workspace.
2. `[PROCESS]` Retrieve or create a `ReviewSubmission` with `status = draft`.
3. `[PROCESS]` Retrieve or create the Protocol Review Worksheet and Informed Consent Checklist.
4. `[(REVIEW_FORM_SUBMISSIONS)]` Each form stores assignment, form type, state, catalog version/snapshot, responses, consent determination, recommendation, comments, review date, and completion/finalization times.
5. `/ INPUT /` Reviewer answers form items and saves draft responses.
6. `/ INPUT /` Reviewer adds comments with:
   - `scope = overall | document | page`
   - `category = general | clarification | required_revision`
   - optional `application_document_id`
   - optional `page_number`
   - `body`
7. `[(REVIEW_COMMENTS)]` Save comments under the assignment and optional document.
8. `/ INPUT /` Select `decision = approved | minor_revision | major_revision | disapproved` and enter decision comments.
9. `{DECISION: Save Draft or Submit Final?}`

### Save Draft branch

10. `[PROCESS]` Save `review_submissions.status = draft`, draft decision/comment, and `has_unsubmitted_changes = true`.
11. `> OUTPUT <` Display Draft Saved.
12. `(END: Reviewer may return later)`.

### Final submission branch

13. `[PROCESS]` Recheck authorization and deadline.
14. `[PROCESS]` Validate both required forms and final decision.
15. `{DECISION: every required form is complete?}`
    - No → show incomplete-form errors.
    - Yes → continue.
16. `[PROCESS]` Finalize form and context snapshots.
17. `[PROCESS]` Generate immutable private PDF artifact for each form.
18. `[(REVIEW_FORM_ARTIFACTS)]` Store form/submission-version links, background, artifact/business versions, status, private file path, MIME type, size, checksum, template/generator versions, and generation time.
19. `[PROCESS]` Create immutable `ReviewSubmissionVersion` containing version number, submission token, decision, comment, payload snapshot, checksums, submitting user, and time.
20. `[PROCESS]` Set `ReviewSubmission.status = submitted`, point to the current version, copy the final decision, clear unsubmitted changes, and set submission time.
21. `[PROCESS]` Set assignment `assignment_status = decision_submitted` and `submitted_at`.
22. `[(AUDIT_LOGS)]` Record final submission without form answers or comment bodies.
23. `[PROCESS]` Invoke Flow No. 12.
24. `{DECISION: decision already released?}`
    - No → Reviewer may submit a newer immutable version.
    - Yes → released evidence remains frozen.
25. `(END)`.

### Relationships

~~~text
ReviewerAssignment 1:1 ReviewSubmission
ReviewerAssignment 1:N ReviewSubmissionVersion
ReviewerAssignment 1:N ReviewFormSubmission
ReviewerAssignment 1:N ReviewComment
ReviewSubmission 1:N ReviewSubmissionVersion
ReviewSubmission N:1 Current ReviewSubmissionVersion
ReviewSubmissionVersion 1:N ReviewFormArtifact
ReviewFormSubmission 1:N ReviewFormArtifact
~~~

## Flow No. 12 — Reviewer Consensus Evaluation

1. `(START)` Triggered after assignment creation, Reviewer submission, or Reviewer resubmission.
2. `[PROCESS]` Lock Research Application.
3. `[PROCESS]` Determine cycle: initial review 0; revisions 1, 2, and 3.
4. `[PROCESS]` Determine required Reviewer count: Expedited 1; Full Board 3.
5. `[PROCESS]` Retrieve current, non-superseded assignments for the cycle.
6. `{DECISION: exact required assignment count exists?}`
   - No → `review_consensus_status = awaiting_submissions`.
   - Yes → continue.
7. `{DECISION: every assignment has a submitted current immutable version, a decision, and no unsubmitted changes?}`
   - No → `review_consensus_status = awaiting_submissions`.
   - Yes → collect current decisions.
8. `[PROCESS]` Build consensus signature from assignment ID, current version ID, and decision.
9. `{DECISION: all decisions identical?}`

### Conflicted branch

10. Set `review_consensus_status = conflicted`, clear consensus decision, retain signature, and set `review_conflicted_at`.
11. `[(NOTIFICATIONS)]` Notify active REU Leads.
12. `[(AUDIT_LOGS)]` Record conflicted consensus.
13. `> OUTPUT <` Decision release blocked.
14. `(END: wait for Reviewer resubmission)`.

### Consensus branch

15. Set `review_consensus_status = consensus`, consensus decision/cycle/signature, and evaluation time.
16. `{DECISION: cycle = 3 and decision is Minor or Major Revision?}`
    - Yes → set application `failed`, stage `completed`, complete the revision, notify Applicant/Adviser/Reviewers/REU, audit, and `(END: Failed)`.
    - No → continue.
17. Set `application_status = review_submitted_pending_release` and `current_stage = decision_release`.
18. `{DECISION: decision = Approved?}`
    - Yes → automatic release handling through Flow No. 13.
    - No → enter REU Decision queue.
19. `(END)`.

## Flow No. 13 — Decision Release and Bulk Release

1. `(START)` REU Lead opens Decision and Certificates, or Approved automation is triggered.
2. `/ INPUT /` Select application(s) and release operation: Decision, Certificate, or Both.
3. `{DECISION: Decision release included?}`
   - No → proceed to certificate validation.
   - Yes → continue.
4. `[PROCESS]` Lock the application and reevaluate consensus.
5. `{DECISION: consensus is Conflicted?}`
   - Yes → block release and return to Flow No. 12.
   - No → continue.
6. `{DECISION: complete consensus and exact current evidence match the stored signature?}`
   - No → block release.
   - Yes → retrieve all current immutable source versions.
7. `{DECISION: release already exists for this cycle?}`
   - Equivalent source → return existing release idempotently.
   - Conflicting source → reject.
   - None → create release.
8. `[(APPLICATION_DECISION_RELEASES)]` Save application, cycle, review type, source submission/version references, source-version IDs, decision, consensus signature, released-feedback snapshot, releasing user, and time.
9. `[PROCESS]` Associate released comments and freeze source evidence.
10. `{DECISION: released decision?}`
    - Approved → mark eligible for certification → Flow No. 19.
    - Minor/Major Revision → Flow No. 15.
    - Disapproved → set `application_status = result_released_disapproved`, stage `completed`, notify Applicant, audit, and end.
11. `[PROCESS]` In bulk operations, validate each selected application independently and continue after an individual failure.
12. `> OUTPUT <` Return per-item success/failure results.
13. `(END)`.

There is no scheduled result-release date or time gate.

## Flow No. 14 — Regala Assistant

**RETIRED — REMOVE FROM THE REVISED RUNTIME FLOWCHART.**

There is no Regala entity, route, service, template, notification database, or workflow connection in the current system.

## Flow No. 15 — Revision Initialization After Decision Release

1. `(START)` A Minor or Major Revision consensus is being released.
2. `[PROCESS]` Determine current review cycle.
3. `{DECISION: already the third revision?}`
   - Yes → application becomes Failed through Flow No. 12.
   - No → continue.
4. `[PROCESS]` Retrieve Revision Period configuration.
5. `{DECISION: current revision deadline configured and available?}`
   - No → block revision creation/release and show REU configuration error.
   - Yes → continue.
6. `[PROCESS]` Complete any previous active revision.
7. `[PROCESS]` Calculate next `revision_number`.
8. `[(APPLICATION_REVISIONS)]` Create application/release links, revision number, `status = pending_uploads`, due date, and null submission/completion fields.
9. `[PROCESS]` Resolve exact replacement requirements from released required-revision feedback and source documents.
10. `[(APPLICATION_REVISION_REQUIREMENTS)]` Save revision, document requirement, source document, null replacement document, and required flag.
11. `[PROCESS]` Set application to `revision_window_open`, stage `revision`, and update current cycle/status time.
12. `[(NOTIFICATIONS)]` Notify Applicant.
13. `[(AUDIT_LOGS)]` Record revision number, requirement count, due date, and decision.
14. `(END)` Connect to Flow No. 16.

## Flow No. 16 — Applicant Revision Upload and Submission

1. `(START)` Applicant opens a revision-enabled application.
2. `[PROCESS]` Authorize ownership and retrieve current revision.
3. `{DECISION: revision is Pending Uploads and application is Revision Window Open?}`
   - No → read-only/unavailable result.
   - Yes → continue.
4. `[PROCESS]` Check Revision Period deadline.
5. `{DECISION: open?}`
   - No → reject uploads/submission.
   - Yes → display released anonymous feedback, required documents, source versions, replacement state, and due date.
6. `/ INPUT /` Upload replacement for a required document.
7. `[PROCESS]` Apply Flow No. 5 file validation.
8. `[PROCESS]` Create new Application Document version, mark earlier version non-current, and set `replacement_application_document_id`.
9. `{DECISION: Save or Submit Revision?}`
   - Save → remain Pending Uploads → `(END)`.
   - Submit → continue.
10. `[PROCESS]` Lock application, revision, requirements, and replacement documents.
11. `{DECISION: every required item has a valid current replacement?}`
    - No → missing-replacement errors.
    - Yes → continue.
12. `[PROCESS]` Retrieve Reviewing of Revision Period.
13. `{DECISION: deadline exists and is not past?}`
    - No → REU configuration error.
    - Yes → continue.
14. `[PROCESS]` Resolve prior authorized Reviewer set.
15. `{DECISION: prior set resolved?}`
    - No → block for REU correction.
    - Yes → create revision-review assignments for the same Reviewers with incremented cycle, replacement links, and deadline.
16. `[PROCESS]` Set revision `under_review`, submitting Applicant, and submission time.
17. `[PROCESS]` Mark replacement documents formally submitted.
18. `[PROCESS]` Set application `under_re_review`, stage `ethics_review`, consensus `awaiting_submissions`, and clear old consensus values.
19. `[(NOTIFICATIONS)]` Notify Reviewers.
20. `[(AUDIT_LOGS)]` Record revision, replacement count, and Reviewer count.
21. `(END)` Connect to Flow No. 17.

## Flow No. 17 — Reviewer Re-review and Maximum Revision Handling

1. `(START)` Reviewer opens a Revision Review assignment.
2. `[PROCESS]` Apply Flow No. 10 authorization/privacy controls.
3. `[PROCESS]` Display prior/current document versions and released anonymous comments.
4. `[PROCESS]` Reviewer manually compares source and replacements.
5. `[PROCESS]` Complete the same two forms and add comments.
6. `/ INPUT /` Select Approved, Minor Revision, Major Revision, or Disapproved.
7. `[PROCESS]` Submit immutable version through Flow No. 11.
8. `[PROCESS]` Evaluate consensus through Flow No. 12.
9. `{DECISION: consensus result?}`
   - Awaiting → wait for others.
   - Conflicted → release blocked until resubmission.
   - Approved → automatic release and certification.
   - Disapproved → REU release and terminal completion.
   - Minor/Major → continue.
10. `{DECISION: revision_number < 3?}`
    - Yes → release decision and create the next revision.
    - No → set application `failed`, stage `completed`, complete revision, notify all parties, and end.
11. `(END)`.

### Document business versions

~~~text
Initial submitted documents = V1
First revised submission    = V2
Second revised submission   = V3
Third revised submission    = V4
~~~

## Flow No. 18 — Applicant Evaluation and Certificate Claim

**Execution order: Flow No. 18 occurs after Flow No. 19.**

1. `(START)` Applicant opens Certificates.
2. `[PROCESS]` Authorize ownership, count recipients, and retrieve all certificates/current versions.
3. `{DECISION: every recipient has a Released or Claimed certificate with a Ready current version?}`
   - No → evaluation unavailable → `(END)`.
   - Yes → continue.
4. `{DECISION: survey already exists?}`
   - Yes → skip to Claim.
   - No → display evaluation.
5. `/ INPUT /` Submit exactly 10 ratings, each integer 1–5, plus optional suggestions/comments up to 2,000 characters.
6. `[PROCESS]` Validate exact questionnaire keys, ratings, and length.
7. `[(APPLICANT_SURVEY_RESPONSES)]` Save application, Applicant, questionnaire version, ratings, optional comments, and completion time.
8. `[(AUDIT_LOGS)]` Record completion without answers.
9. `/ INPUT /` Select Claim Certificates.
10. `[PROCESS]` Lock application, survey, certificates, and versions.
11. `{DECISION: survey exists and all certificates remain claimable?}`
    - No → reject.
    - Yes → continue.
12. `[PROCESS]` For every certificate, set `status = claimed`, Applicant/claimed-version references, and claim time; update the version claim fields.
13. `[(AUDIT_LOGS)]` Record certificate IDs, version IDs, and recipient count.
14. `> OUTPUT <` Enable private preview/download for all personalized certificates.
15. `(END: Claimed)`.

## Flow No. 19 — Personalized Certificate Generation and REU Release

1. `(START)` Application becomes eligible through Exempted status or a released Approved decision.
2. `[PROCESS]` Retrieve all Certificate Recipients.
3. `{DECISION: at least one valid recipient?}`
   - No → block generation and show data-quality error.
   - Yes → continue.
4. `[PROCESS]` Retrieve active certificate background, signatory/signature, validity, and QR configuration/default.
5. `{DECISION: generation configuration complete?}`
   - No → mark generation failure and show corrective action.
   - Yes → continue.
6. For each recipient, create/retrieve one Certificate, use the application code as certificate number, snapshot the recipient, generate PDF/checksum, create immutable version, and set current-version pointer.
7. `[(CERTIFICATES)]` Store application, recipient, Applicant, name, number, status, failure code, current version, release fields, and claim fields.
8. `[(CERTIFICATE_VERSIONS)]` Store version number/state, private file data, checksums, template/background/generator snapshots, signatory/signature/QR snapshots, dates, and actor/time fields.
9. `{DECISION: generation successful for every recipient?}`
   - No → affected certificate `generation_failed`, preserve successes, expose retry, and audit.
   - Yes → certificates `pending_release`.
10. `> OUTPUT <` REU sees private previews.
11. `/ INPUT /` Select individual release, bulk Certificate, or Both.
12. `[PROCESS]` Revalidate eligibility and Ready current version.
13. `[PROCESS]` Set certificate `released`, release actor/time, issued date, and validity; update version release fields.
14. `{DECISION: all recipients now Released or Claimed with Ready versions?}`
    - No → application remains `for_certificate_release`; show Partial Release.
    - Yes → `application_status = certificate_released`, `current_stage = completed`.
15. `[(NOTIFICATIONS)]` Notify Applicant.
16. `[(AUDIT_LOGS)]` Record generation/release, recipients, and versions.
17. `(END)` Connect to Flow No. 18.

### Relationships

~~~text
ResearchApplication 1:N CertificateRecipient
CertificateRecipient 1:1 Certificate
ResearchApplication 1:N Certificate
Certificate 1:N CertificateVersion
Certificate N:1 Current CertificateVersion
Certificate N:0..1 Claimed CertificateVersion
CertificateVersion N:1 CertificateBackground
~~~

## Flow No. 20 — Public QR Verification

**RETIRED — REMOVE AS A PUBLIC ECRATS FLOW.**

Actual connection:

~~~text
Certificate configuration
    → QR image or default institutional URL
    → snapshot stored in CertificateVersion
    → QR rendered in generated certificate
~~~

There is no public ECRATS route, per-certificate token, public lookup entity, or verification-result page.

## Flow No. 21 — Workflow Status and In-App Notification Creation

1. `(START)` A workflow service completes an authorized event.
2. `/ INPUT /` Event supplies actor, subject, result/status, intended recipients, safe route/parameters, and academic term.
3. `[PROCESS]` Persist the authoritative workflow record first.
4. `[PROCESS]` Update applicable application projection variables: `application_status`, `current_stage`, `status_updated_at`, consensus, revision, or certificate variables.
5. `{DECISION: event requires an in-app notification?}`
   - No → proceed to audit.
   - Yes → resolve authorized recipients.
6. `[PROCESS]` Build neutral data and exclude unreleased decisions, Reviewer identities, confidential comments, document contents, credentials, and private tokens.
7. `[(NOTIFICATIONS)]` Save UUID, type, notifiable type/ID, safe data, null `read_at`, null `deleted_at`, and timestamps.
8. `[(AUDIT_LOGS)]` Record the sanitized event where required.
9. `> OUTPUT <` Recipient sees the item in the in-app Inbox.
10. `(END)`.

Workflow status notifications use the database/in-app channel. Account setup, password reset, and username-related messages may use email separately.

## Flow No. 22 — View Application Status and Authorized Details

1. `(START)` User opens an application status/detail page.
2. `[PROCESS]` Authenticate user and retrieve Application ID.
3. `{DECISION: application exists?}`
   - No → Not Found → `(END)`.
   - Yes → continue.
4. `{DECISION: role and relationship authorize the record?}`
   - Applicant → only when `applicant_user_id = current_user.id`.
   - Adviser → assigned Adviser and application is in an Adviser-visible formal state.
   - Reviewer-enabled Adviser → current/permitted historical assignment belongs to user.
   - REU Lead → institution-wide administrative scope.
   - Otherwise → Access Denied.
5. `[PROCESS]` Retrieve role-permitted status, stage, timeline/deadline, required action, documents, released feedback, revision state, and certificate state.
6. `[PROCESS]` Apply identity and unreleased-decision visibility rules.
7. `{DECISION: requesting a private document or artifact?}`
   - No → render page.
   - Yes → perform separate nested resource authorization.
8. `{DECISION: nested resource belongs to the authorized application/assignment/certificate?}`
   - No → deny.
   - Yes → stream private content with no-store protections.
9. `> OUTPUT <` Display only permitted details.
10. `(END)`.

## Flow No. 23 — REU Reports, Monitoring, and Timeline Dashboard

1. `(START)` REU Lead opens Reports or Review Monitoring.
2. `[PROCESS]` Authorize REU-only access.
3. `/ INPUT /` Select academic term, date range, Institute, Program, applicant type, research type, classification, application status, decision, certificate status, Adviser, or Reviewer filters.
4. `[PROCESS]` Retrieve application-level data from the single application database.
5. `[PROCESS]` Aggregate:
   - application counts and status distribution
   - submission trend
   - Exempted/Expedited/Full Board classification
   - decision distribution
   - average/median turnaround
   - Adviser expected/submitted/endorsed/remaining values
   - Reviewer active assignments and remaining capacity
   - pending/completed revisions
   - certificates pending, failed, released, claimed, and ageing
   - unresolved conflicts
   - unassigned applications
   - data-quality and action-required records
   - anonymous survey results
   - audit activity
6. `[PROCESS]` Build application, certification, Institute, workload, survey, and audit drilldowns.
7. `> OUTPUT <` Display cards, charts, tables, and timeline.
8. `{DECISION: View All?}`
   - Yes → display matching detailed dataset.
   - No → remain on dashboard.
9. `{DECISION: export?}`
   - No → `(END)`.
   - Excel → generate local workbook.
   - PDF → generate local PDF with active worksheet/report background.
   - Print → generate print view.
10. `[PROCESS]` Apply the same filters and authorization to export.
11. `[(AUDIT_LOGS)]` Record applicable export action.
12. `> OUTPUT <` Deliver report.
13. `(END)`.

The reporting layer reads related tables in one database. D2–D13 are not independent databases or external analytics services.

## Flow No. 24 — System and Self-Service Configuration

1. `(START)` User opens Settings.
2. `[PROCESS]` Resolve role and category.
3. `{DECISION: REU-managed or self-service?}`

### REU-managed branches

4. Academic Terms/Lifecycle → Flow Nos. 1 and 27.
5. Deadlines/Timeline → Flow No. 1.
6. `/ INPUT /` Document Requirement: code, name, description, mandatory flag, applicable research types, sort order, active state.
7. `[PROCESS]` Validate unique code and allowed research types.
8. `[(DOCUMENT_REQUIREMENTS)]` Save.
9. `/ INPUT /` Profile Option: Year Level, Institute/acronym, or Program.
10. `[PROCESS]` Normalize value and aliases.
11. `[(PROFILE_OPTIONS)]` and `[(PROFILE_OPTION_ALIASES)]` Save.
12. `/ INPUT /` Certificate or Reviewer Worksheet Background.
13. `[PROCESS]` Validate format, dimensions, checksum, and type.
14. `[(CERTIFICATE_BACKGROUNDS)]` Create asset version and supersede the earlier active version of the same type.
15. `/ INPUT /` Certificate signatory, signature, validity, and QR settings.
16. `[PROCESS]` Validate and save for future generated versions.

### Self-service branches

17. User may update authorized account/profile fields, password, and profile image.
18. Reviewer-enabled Adviser may manage worksheet signatory name/signature.
19. `{DECISION: changes valid and authorized?}`
    - No → validation error.
    - Yes → save.
20. `[(AUDIT_LOGS)]` Record configuration without sensitive asset contents.
21. `> OUTPUT <` Display updated settings.
22. `(END)`.

### Not configurable

- Application status definitions
- Research types beyond fixed Thesis/Capstone
- Reviewer classifications as active assignment controls
- Notification templates
- Regala templates
- Public verification tokens

## Flow No. 25 — Audit Log Recording and Viewing

### Recording

1. `(START)` User or system performs an auditable action.
2. `/ INPUT /` Optional actor, action code, polymorphic subject, optional term, bounded metadata, IP address, and user agent.
3. `[PROCESS]` Sanitize metadata.
4. `[PROCESS]` Exclude passwords, tokens, document contents, survey/form answers, confidential comment bodies, and unnecessary personal data.
5. `[(AUDIT_LOGS)]` Save `academic_term_id`, `actor_user_id`, `action`, `subject_type`, `subject_id`, `metadata`, `ip_address`, `user_agent`, and `created_at`.
6. `(END: logged)`.

### Viewing

7. `(START)` REU Lead opens Audit Log.
8. `[PROCESS]` Authorize REU access.
9. `/ INPUT /` Search/filter by actor, subject, action, term, or date.
10. `[PROCESS]` Retrieve permitted entries.
11. `> OUTPUT <` Display actor, action, subject, sanitized metadata, and time.
12. `{DECISION: export?}`
    - No → `(END)`.
    - Yes → generate authorized report/export.
13. `(END)`.

There is no automatic critical-action branch that alerts a Technical Admin.

## Flow No. 26 — Account Bulk Import and Lifecycle Management

### Bulk import

1. `(START)` Authorized account manager opens Import Accounts.
2. `/ INPUT /` Upload `.xlsx`.
3. `[PROCESS]` Validate workbook type, structure, size, and headers.
4. `[PROCESS]` Parse rows without creating accounts.
5. `[PROCESS]` Normalize names and profile-option aliases.
6. For each row validate permitted account type, required identity fields, applicant type, Institute/Program/Year Level, duplicates, and Adviser-only Reviewer capability.
7. `> OUTPUT <` Display preview with valid rows, warnings, and errors.
8. `{DECISION: blocking errors?}`
   - Yes → correct workbook or exclude rows.
   - No → enable confirmation.
9. `/ INPUT /` Confirm import.
10. `[PROCESS]` Revalidate selected rows and create accounts through Flow No. 2.
11. `[PROCESS]` Queue setup messages.
12. `[(AUDIT_LOGS)]` Record batch size/outcome without passwords.
13. `> OUTPUT <` Display created, skipped, and failed counts.
14. `(END)`.

### Account lifecycle

15. `(START)` Authorized manager selects Activate, Deactivate, Archive, Restore, Resend Setup, Managed Password Reset, or Enable/Disable Reviewer.
16. `[PROCESS]` Check authority and protected-account rules.
17. `{DECISION: active workflow responsibilities prevent action?}`
    - Yes → block or require reassignment.
    - No → continue.
18. `[(USERS)]` Update status, capability, setup fields, or soft-delete fields.
19. `[(NOTIFICATIONS / EMAIL)]` Send applicable account message.
20. `[(AUDIT_LOGS)]` Record action.
21. `(END)`.

## Flow No. 27 — Academic Term Pause, End, and Reactivation

1. `(START)` REU Lead opens lifecycle controls.
2. `[PROCESS]` Authorize and lock the selected term.
3. `/ INPUT /` Select Pause, End, or Reactivate.
4. `{DECISION: transition valid from current state?}`
   - No → validation error.
   - Yes → continue.
5. Pause → `status = paused`; preserve records; lock non-REU operational access.
6. End → `status = ended`; disable current operational use; preserve history.
7. Reactivate → validate dates/conflicts; set `status = active`; restore permitted workflow availability.
8. `[(AUDIT_LOGS)]` Record old/new state, actor, and term.
9. `> OUTPUT <` Display result.
10. `(END)`.

## Flow No. 28 — Notification Inbox, Read State, and Bin

1. `(START)` User opens Notifications.
2. `[PROCESS]` Retrieve only records belonging to the current notifiable User.
3. `/ INPUT /` Apply unread/read/status filter.
4. `> OUTPUT <` Display paginated notifications.
5. `{DECISION: action?}`
   - Open → validate route, mark read, navigate to authorized destination.
   - Mark read/unread → update `read_at`.
   - Bulk mark read → update selected owned records.
   - Move to Bin → set `deleted_at`.
   - Restore → clear `deleted_at` within retention.
   - Permanently delete → remove authorized Bin record.
6. `[PROCESS]` Bin represents the seven-day recovery period.
7. `(END)`.

## Flow No. 29 — Certificate Failure Retry and Regeneration

### Generation retry

1. `(START)` REU opens a `generation_failed` certificate.
2. `[PROCESS]` Authorize and retrieve failure/configuration.
3. `{DECISION: blocking issue resolved?}`
   - No → show unresolved requirement.
   - Yes → retry Flow No. 19 generation.
4. `{DECISION: retry successful?}`
   - No → retain `generation_failed`.
   - Yes → create Ready version, set current pointer, and set certificate `pending_release`.
5. `[(AUDIT_LOGS)]` Record retry.
6. `(END)`.

### Regeneration

7. `(START)` REU requests regeneration.
8. `/ INPUT /` Enter reason.
9. `[PROCESS]` Lock certificate/current version and generate a new immutable version from current approved configuration.
10. `{DECISION: generation successful?}`
    - No → retain existing Ready version.
    - Yes → mark prior current version Superseded, make new version Ready, and update current pointer.
11. `{DECISION: certificate already Claimed?}`
    - Yes → preserve claim state under regeneration rules and record new current version.
    - No → preserve applicable Pending Release or Released state.
12. `[(AUDIT_LOGS)]` Record old/new versions, reason, and actor.
13. `(END)`.

## Flow No. 30 — Private File and Generated Artifact Access

1. `(START)` Authenticated user requests private resource.
2. `/ INPUT /` Resource type/ID: Application Document, Review Form Artifact, Certificate Version, or authorized administrative asset.
3. `[PROCESS]` Retrieve resource and complete parent ownership chain.
4. `{DECISION: Application Document?}`
   - Validate application ownership or Applicant/Adviser/Reviewer/REU relationship.
5. `{DECISION: Review Form Artifact?}`
   - Validate form, assignment, Reviewer relationship, and release/privacy rules.
6. `{DECISION: Certificate Version?}`
   - Validate certificate/application ownership and release/claim or REU administrative access.
7. `{DECISION: full nested authorization passes?}`
   - No → deny without revealing storage path.
   - Yes → resolve private file.
8. `{DECISION: file exists and state/checksum is acceptable?}`
   - No → safe Not Found/generation error.
   - Yes → stream inline or download with private/no-store controls.
9. `(END)`.

## Flow No. 31 — Legacy Reviewer Identity Reconciliation

1. `(START)` REU opens a reconciliation record.
2. `[PROCESS]` Retrieve source legacy Reviewer, candidate Adviser, matched fields, and historical references.
3. `{DECISION: valid Adviser match?}`
   - No → keep separate.
   - Yes → continue.
4. `/ INPUT /` Select Merge or Keep Separate and enter reason/notes.
5. `{DECISION: Merge?}`
   - Yes → reconcile supported references, enable Reviewer capability where appropriate, preserve source history, set `status = merged`.
   - No → preserve both and set `status = kept_separate`.
6. `[(REVIEWER_IDENTITY_RECONCILIATIONS)]` Save source, target Adviser, status, matched fields, reason, notes, resolver, and time.
7. `[(AUDIT_LOGS)]` Record non-destructive reconciliation.
8. `(END)`.

## Principal application-status transition map

~~~text
draft
  → incomplete
  → submitted_to_adviser
      ├─ returned_by_adviser → incomplete → submitted_to_adviser
      └─ adviser_endorsed
           → under_res_screening
               ├─ exempted → certificate generation/release
               ├─ awaiting_reviewer_assignment → under_expedited_review
               └─ awaiting_reviewer_assignment → under_full_board_review

under_expedited_review / under_full_board_review
  → review_submitted_pending_release
      ├─ Approved → result_released_accepted / for_certificate_release
      │             → certificate_released
      ├─ Minor Revision → result_released_minor_revision
      │                    → revision_window_open → under_re_review
      ├─ Major Revision → result_released_major_revision
      │                    → revision_window_open → under_re_review
      └─ Disapproved → result_released_disapproved

under_re_review
  ├─ Approved → certificate path
  ├─ Disapproved → completed
  ├─ Minor/Major and cycle < 3 → next revision
  └─ Minor/Major and cycle = 3 → failed

certificate
  pending_release
      ├─ generation_failed → retry → pending_release
      └─ released → survey completed → claimed
~~~

## Principal consensus-status transition map

~~~text
awaiting_submissions
  ├─ not all current Reviewers submitted → awaiting_submissions
  ├─ submitted decisions differ → conflicted
  └─ all submitted decisions match → consensus

conflicted
  ├─ newer decisions still differ → conflicted
  └─ newer decisions match → consensus
~~~

## Core entity relationship summary

~~~text
User 1:N ResearchApplication as Applicant
User 1:N ResearchApplication as Adviser
User 1:N ReviewerAssignment as Reviewer-enabled Adviser
User 1:N Endorsement
User 1:N Notification
User 1:N AuditLog
User 1:N WorkflowDraft
User 1:N User through created_by_user_id

AcademicTerm 1:N ResearchApplication
AcademicTerm 1:N DeadlineConfiguration
AcademicTerm 1:N TimelineCalendarEvent
AcademicTerm 1:N AuditLog

ResearchApplication 1:N ApplicationDocument
ResearchApplication 1:N CertificateRecipient
ResearchApplication 1:N Endorsement
ResearchApplication 1:1 ApplicationScreening
ResearchApplication 1:N ReviewerAssignment
ResearchApplication 1:N DecisionRelease
ResearchApplication 1:N ApplicationRevision
ResearchApplication 1:1 ApplicantSurveyResponse
ResearchApplication 1:N Certificate

DocumentRequirement 1:N ApplicationDocument
DocumentRequirement 1:N ApplicationRevisionRequirement

ReviewerAssignment 1:1 ReviewSubmission
ReviewerAssignment 1:N ReviewSubmissionVersion
ReviewerAssignment 1:N ReviewFormSubmission
ReviewerAssignment 1:N ReviewComment
ReviewerAssignment N:0..1 Previous ReviewerAssignment

ReviewSubmission 1:N ReviewSubmissionVersion
ReviewSubmission N:1 Current ReviewSubmissionVersion
ReviewSubmissionVersion 1:N ReviewFormArtifact
ReviewFormSubmission 1:N ReviewFormArtifact
CertificateBackground 1:N ReviewFormArtifact
CertificateBackground 1:N CertificateVersion

DecisionRelease 1:N Released ReviewComment
DecisionRelease 1:0..1 ApplicationRevision

ApplicationRevision 1:N ApplicationRevisionRequirement
ApplicationRevisionRequirement N:1 Source ApplicationDocument
ApplicationRevisionRequirement N:0..1 Replacement ApplicationDocument

CertificateRecipient 1:1 Certificate
Certificate 1:N CertificateVersion
Certificate N:1 Current CertificateVersion
Certificate N:0..1 Claimed CertificateVersion

ProfileOption 1:N ProfileOptionAlias

AuditLog N:1 Actor User
AuditLog N:0..1 AcademicTerm
AuditLog N:1 Polymorphic Subject
~~~

## Principal variables by entity

### User

`role`, `applicant_type`, `account_status`, `reviewer_enabled`, `created_by_user_id`, identity/profile fields, setup/onboarding timestamps, expected endorsement count, certificate settings, and worksheet settings.

### Academic Term and deadlines

`semester`, `academic_year`, `starts_at`, `ends_at`, `is_active`, `status`, `deadline_key`, `audience_role`, `due_at`, and `manual_status`.

### Research Application

`application_code`, Applicant/Adviser/term references, Applicant/research types, research information, `application_status`, `current_stage`, `review_type`, `current_revision_cycle`, consensus status/cycle/decision/signature, and submission/status timestamps.

### Application Document

Application, requirement, and uploader references; private file metadata; checksum; `document_version`; `validation_status`; `is_current`; upload and formal-submission times.

### Reviewer Assignment and submission

Application/Reviewer references, review type/cycle, assignment status/sequence, replacement/supersession data, deadlines, submission state, decision, immutable version number, snapshots, checksums, and timestamps.

### Revision

Application/release references, `revision_number`, `status`, `due_at`, submitting Applicant/time, completion time, source document, and replacement document.

### Certificate

Application/recipient/Applicant references, recipient name, certificate number, status/failure code, current/claimed version pointers, generation/release/claim actors and times, issued date, and validity.

### Notification and audit

Notification UUID/type/notifiable/data/read/bin timestamps; audit term/actor/action/polymorphic subject/sanitized metadata/IP/user agent/time.
