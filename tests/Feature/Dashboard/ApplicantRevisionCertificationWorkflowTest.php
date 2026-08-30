<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationRevisionStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ApplicationDocument;
use App\Models\ApplicationRevision;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Services\Applications\ApplicationDocumentService;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Applications\ReviewConsensusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantRevisionCertificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_document_first_replaced_in_the_second_revision_cycle_becomes_version_two(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, , $resLead, $application, , $document] = $this->reviewedApplication();
        $this->openWindow('revision-period', UserRole::Applicant);
        $application->update([
            'application_status' => ApplicationStatus::RevisionWindowOpen,
            'current_stage' => ApplicationStage::Revision,
            'current_revision_cycle' => 3,
        ]);
        $release = ApplicationDecisionRelease::create([
            'research_application_id' => $application->id,
            'review_cycle' => 1,
            'source_review_type' => 'revision_review',
            'decision' => ReviewDecision::MinorRevision,
            'released_by_user_id' => $resLead->id,
            'released_at' => now(),
        ]);
        $revision = ApplicationRevision::create([
            'research_application_id' => $application->id,
            'application_decision_release_id' => $release->id,
            'revision_number' => 2,
            'status' => ApplicationRevisionStatus::PendingUploads,
            'due_at' => now()->addWeek(),
        ]);
        $revisionRequirement = $revision->requirements()->create([
            'document_requirement_id' => $document->document_requirement_id,
            'source_application_document_id' => $document->id,
            'is_required' => true,
        ]);

        $replacement = app(ApplicationDocumentService::class)->uploadRevision(
            $applicant,
            $application->refresh(),
            $revision,
            $revisionRequirement,
            UploadedFile::fake()->createWithContent('first-cycle-two-replacement.pdf', '%PDF-1.4 cycle two'),
        );

        $this->assertSame(2, $replacement->document_version);
        $this->assertSame(1, $document->document_version);
        $this->assertFalse($document->refresh()->is_current);
        $this->assertTrue($replacement->is_current);
    }

    public function test_res_releases_the_exact_reviewer_submission_and_routes_a_versioned_revision_to_the_same_reviewer(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $reviewer, $resLead, $application, $assignment, $document] = $this->reviewedApplication();
        $this->openWindow('revision-period', UserRole::Applicant);
        $this->openWindow('reviewing-revision-period', UserRole::Reviewer);

        $releasedComment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'application_document_id' => $document->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => 'Replace the participant consent procedure.',
        ]);
        $generalComment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'scope' => ReviewCommentScope::Overall,
            'category' => ReviewCommentCategory::General,
            'body' => 'General reviewer guidance released with the submitted decision.',
        ]);

        $release = app(ApplicationRevisionWorkflowService::class)->releaseDecision(
            $resLead,
            $application,
            $assignment->reviewSubmission,
        );

        $application->refresh();
        $revision = $application->revisions()->with('requirements')->firstOrFail();
        $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->application_status);
        $this->assertSame(2, $application->current_revision_cycle);
        $this->assertSame($release->id, $releasedComment->refresh()->application_decision_release_id);
        $this->assertNotNull($releasedComment->released_at);
        $this->assertSame($release->id, $generalComment->refresh()->application_decision_release_id);
        $this->assertNotNull($generalComment->released_at);
        $this->assertCount(1, $revision->requirements);

        $page = $this->actingAs($applicant)->get(route('applicant.revision-certificates.index', [
            'application' => $application->id,
        ]));
        $page->assertOk()
            ->assertSee('Replace the participant consent procedure.')
            ->assertSee('General reviewer guidance released with the submitted decision.')
            ->assertDontSee($reviewer->name);

        $replacement = app(ApplicationDocumentService::class)->uploadRevision(
            $applicant,
            $application,
            $revision,
            $revision->requirements->first(),
            UploadedFile::fake()->createWithContent('revised-protocol.pdf', '%PDF-1.4 revised protocol'),
        );

        $this->assertSame(2, $replacement->document_version);
        $this->assertTrue($replacement->is_current);
        $this->assertFalse($document->refresh()->is_current);
        $this->assertNotNull($replacement->file_sha256);

        app(ApplicationRevisionWorkflowService::class)->submitRevision($applicant, $application, $revision);

        $application->refresh();
        $revisionAssignment = ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', 1)
            ->firstOrFail();
        $this->assertSame(ApplicationStatus::UnderReReview, $application->application_status);
        $this->assertSame($reviewer->id, $revisionAssignment->reviewer_user_id);
        $this->assertSame('revision_review', $revisionAssignment->review_type);
        $this->assertSame(ReviewerAssignmentStatus::RevisionReview, $revisionAssignment->assignment_status);
        $this->assertSame($assignment->id, $revisionAssignment->replaces_assignment_id);
        $this->assertSame(UserRole::Applicant, $application->applicant->role);

        $workspace = $this->actingAs($reviewer)->get(route('reviewer.assignments.workspace', $revisionAssignment));
        $workspace->assertOk()
            ->assertSee('Previous Versions and Comments')
            ->assertSee('Replace the participant consent procedure.')
            ->assertSee('Version 1');

        $unrelatedReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $this->actingAs($unrelatedReviewer)
            ->get(route('reviewer.assignments.workspace', $revisionAssignment))
            ->assertForbidden();
        $this->actingAs($unrelatedReviewer)
            ->get(route('reviewer.applications.documents.preview', [$application, $document]))
            ->assertForbidden();
    }

    public function test_two_revision_cycles_preserve_version_feedback_and_remain_submittable_through_approval(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $reviewer, $resLead, $application, $initialAssignment, $versionOne] = $this->reviewedApplication();
        $this->openWindow('revision-period', UserRole::Applicant);
        $this->openWindow('reviewing-revision-period', UserRole::Reviewer);
        $workflow = app(ApplicationRevisionWorkflowService::class);
        $documents = app(ApplicationDocumentService::class);
        $consensus = app(ReviewConsensusService::class);
        $versionOneComment = 'VERSION-ONE-REVISION-FEEDBACK';
        $versionTwoComment = 'VERSION-TWO-REVISION-FEEDBACK';
        $versionThreeComment = 'VERSION-THREE-APPROVAL-FEEDBACK';

        $initialAssignment->comments()->create([
            'application_document_id' => $versionOne->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => $versionOneComment,
        ]);
        $workflow->releaseDecision($resLead, $application, $initialAssignment->reviewSubmission);

        $application->refresh();
        $revisionOne = $application->revisions()
            ->with('requirements')
            ->where('revision_number', 1)
            ->firstOrFail();
        $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->application_status);
        $this->assertCount(1, $revisionOne->requirements);
        $this->assertSame($versionOne->id, $revisionOne->requirements->first()->source_application_document_id);
        $this->actingAs($resLead)
            ->get(route('res.certificates.index'))
            ->assertOk()
            ->assertDontSee($application->application_code);
        $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('data-revision-upload-form', false)
            ->assertSee('Replacement required before submission')
            ->assertSee('Submit Revision for Re-review');

        $versionTwo = $documents->uploadRevision(
            $applicant,
            $application,
            $revisionOne,
            $revisionOne->requirements->first(),
            UploadedFile::fake()->createWithContent('protocol-c1.pdf', '%PDF-1.4 cycle one'),
        );
        $workflow->submitRevision($applicant, $application->refresh(), $revisionOne->refresh());
        $cycleOneAssignment = ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', 1)
            ->firstOrFail();
        $cycleOneAssignment->comments()->create([
            'application_document_id' => $versionTwo->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => $versionTwoComment,
        ]);
        $cycleOneSubmission = $cycleOneAssignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::MinorRevision,
            'decision_comment' => 'The first revised version still needs correction.',
            'submitted_at' => now(),
        ]);
        $cycleOneAssignment->update([
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);
        $consensus->evaluate($application->refresh());
        $workflow->releaseDecision($resLead, $application->refresh(), $cycleOneSubmission);

        $application->refresh();
        $revisionTwo = $application->revisions()
            ->with('requirements')
            ->where('revision_number', 2)
            ->firstOrFail();
        $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->application_status);
        $this->assertSame(3, $application->current_revision_cycle);
        $this->assertCount(1, $revisionTwo->requirements);
        $this->assertSame($versionTwo->id, $revisionTwo->requirements->first()->source_application_document_id);

        $applicantHistory = $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('Version 2')
            ->assertSee('Version 1')
            ->assertSee('The first revised version still needs correction.')
            ->assertSee('data-revision-upload-form', false)
            ->assertSee('Replacement required before submission')
            ->getContent();
        $versionTwoPanel = $this->versionPanel($applicantHistory, 'data-revision-version-panel', $versionTwo->id);
        $versionOnePanel = $this->versionPanel($applicantHistory, 'data-revision-version-panel', $versionOne->id);
        $this->assertStringContainsString($versionTwoComment, $versionTwoPanel);
        $this->assertStringNotContainsString($versionOneComment, $versionTwoPanel);
        $this->assertStringContainsString($versionOneComment, $versionOnePanel);
        $this->assertStringNotContainsString($versionTwoComment, $versionOnePanel);

        $versionThree = $documents->uploadRevision(
            $applicant,
            $application,
            $revisionTwo,
            $revisionTwo->requirements->first(),
            UploadedFile::fake()->createWithContent('protocol-c2.pdf', '%PDF-1.4 cycle two'),
        );
        $workflow->submitRevision($applicant, $application->refresh(), $revisionTwo->refresh());
        $cycleTwoAssignment = ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', 2)
            ->firstOrFail();
        $cycleTwoAssignment->comments()->create([
            'application_document_id' => $versionThree->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::General,
            'body' => $versionThreeComment,
        ]);
        $cycleTwoSubmission = $cycleTwoAssignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::Approved,
            'decision_comment' => 'The second revised version is acceptable.',
            'submitted_at' => now(),
        ]);
        $cycleTwoAssignment->update([
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);
        $consensus->evaluate($application->refresh());

        $reviewerHistory = $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $cycleTwoAssignment))
            ->assertOk()
            ->assertSee('data-reviewer-history-version-select', false)
            ->assertSee('The first revised version still needs correction.')
            ->assertSee('The second revised version is acceptable.')
            ->getContent();
        $reviewerVersionThree = $this->versionPanel($reviewerHistory, 'data-reviewer-history-version-panel', $versionThree->id);
        $reviewerVersionTwo = $this->versionPanel($reviewerHistory, 'data-reviewer-history-version-panel', $versionTwo->id);
        $reviewerVersionOne = $this->versionPanel($reviewerHistory, 'data-reviewer-history-version-panel', $versionOne->id);
        $this->assertStringContainsString($versionThreeComment, $reviewerVersionThree);
        $this->assertStringNotContainsString($versionTwoComment, $reviewerVersionThree);
        $this->assertStringContainsString($versionTwoComment, $reviewerVersionTwo);
        $this->assertStringNotContainsString($versionOneComment, $reviewerVersionTwo);
        $this->assertStringContainsString($versionOneComment, $reviewerVersionOne);

        $workflow->releaseDecision($resLead, $application->refresh(), $cycleTwoSubmission);

        $application->refresh();
        $this->assertSame(ApplicationStatus::ForCertificateRelease, $application->application_status);
        $this->assertSame(2, $application->revisions()->where('status', ApplicationRevisionStatus::Completed->value)->count());
        $finalApplicantHistory = $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee($versionOneComment)
            ->assertSee($versionTwoComment)
            ->assertSee($versionThreeComment)
            ->assertSee('The first revised version still needs correction.')
            ->assertSee('The second revised version is acceptable.');
        $finalApplicantHistory
            ->assertDontSee('data-revision-upload-form', false)
            ->assertDontSee('id="certification-state-title"', false);
    }

    public function test_res_cannot_override_a_submitted_revision_decision_and_overall_feedback_still_creates_actionable_documents(): void
    {
        Storage::fake('local');
        Notification::fake();
        [, , $resLead, $application, $assignment, $document] = $this->reviewedApplication();
        $this->openWindow('revision-period', UserRole::Applicant);
        $legacyOverallComment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'scope' => ReviewCommentScope::Overall,
            'category' => ReviewCommentCategory::General,
            'body' => 'This submitted comment needs a document recovery mapping.',
        ]);

        $this->actingAs($resLead)
            ->from(route('res.certificates.index'))
            ->post(route('res.certificates.decisions.release', $application), [
                'application_id' => $application->id,
                'review_submission_id' => $assignment->reviewSubmission->id,
                'decision' => ReviewDecision::Approved->value,
                'revision_document_ids' => [$document->id],
            ])
            ->assertRedirect(route('res.certificates.index'))
            ->assertSessionHasNoErrors();

        $release = $application->decisionReleases()->firstOrFail();
        $this->assertSame(ReviewDecision::MinorRevision, $release->decision);
        $this->assertSame($assignment->reviewSubmission->id, $release->source_review_submission_id);
        $generatedRevision = $application->revisions()->with('requirements')->firstOrFail();
        $this->assertCount(1, $generatedRevision->requirements);
        $this->assertSame($document->id, $generatedRevision->requirements->first()->source_application_document_id);
        $this->assertSame($release->id, $legacyOverallComment->refresh()->application_decision_release_id);
        $this->assertSame(ReviewCommentScope::Overall, $legacyOverallComment->scope);
        $this->assertNull($legacyOverallComment->application_document_id);
        $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->refresh()->application_status);
    }

    public function test_revision_upload_is_idempotent_for_the_same_file_and_cross_application_ids_are_rejected(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, , $resLead, $application, $assignment, $document] = $this->reviewedApplication();
        $this->openWindow('revision-period', UserRole::Applicant);
        $comment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'application_document_id' => $document->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => 'Revise this file.',
        ]);
        app(ApplicationRevisionWorkflowService::class)->releaseDecision(
            $resLead,
            $application,
            $assignment->reviewSubmission,
        );
        $revision = $application->revisions()->with('requirements')->firstOrFail();
        $requirement = $revision->requirements->first();
        $contents = '%PDF-1.4 same content';

        $first = app(ApplicationDocumentService::class)->uploadRevision(
            $applicant,
            $application,
            $revision,
            $requirement,
            UploadedFile::fake()->createWithContent('first.pdf', $contents),
        );
        $second = app(ApplicationDocumentService::class)->uploadRevision(
            $applicant,
            $application,
            $revision,
            $requirement->refresh(),
            UploadedFile::fake()->createWithContent('duplicate.pdf', $contents),
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, $application->documents()->count());

        $otherOwner = User::factory()->create(['role' => UserRole::Applicant]);
        $otherApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $otherOwner->id,
            'application_status' => ApplicationStatus::RevisionWindowOpen,
        ]);
        $this->actingAs($applicant)
            ->post(route('applicant.revision-certificates.revisions.submit', [$otherApplication, $revision]))
            ->assertForbidden();
    }

    public function test_the_third_revised_submission_receives_a_final_review_and_another_revision_decision_marks_it_failed(): void
    {
        Storage::fake('local');
        Notification::fake();
        [$applicant, $reviewer, $resLead, $application, $currentAssignment, $currentDocument] = $this->reviewedApplication();
        $reviewer->update([
            'role' => UserRole::Adviser,
            'reviewer_enabled' => true,
            'reviewer_classification' => 'Expedited',
            'reviewer_classifications' => ['Expedited'],
            'reviewer_capacity' => 6,
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application->update([
            'adviser_user_id' => $adviser->id,
            'review_type' => 'expedited',
        ]);
        $this->openWindow('revision-period', UserRole::Applicant);
        $this->openWindow('reviewing-revision-period', UserRole::Reviewer);
        $workflow = app(ApplicationRevisionWorkflowService::class);
        $documents = app(ApplicationDocumentService::class);
        $consensus = app(ReviewConsensusService::class);

        foreach (range(1, ApplicationRevisionWorkflowService::MAX_REVISION_CYCLES) as $revisionNumber) {
            $currentAssignment->comments()->create([
                'application_document_id' => $currentDocument->id,
                'scope' => ReviewCommentScope::Document,
                'category' => ReviewCommentCategory::RequiredRevision,
                'body' => "Revision cycle {$revisionNumber} requires another document version.",
            ]);
            $workflow->releaseDecision(
                $resLead,
                $application->refresh(),
                $currentAssignment->reviewSubmission,
            );
            $revision = $application->revisions()
                ->with('requirements')
                ->where('revision_number', $revisionNumber)
                ->firstOrFail();
            $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->refresh()->application_status);

            $currentDocument = $documents->uploadRevision(
                $applicant,
                $application,
                $revision,
                $revision->requirements->firstOrFail(),
                UploadedFile::fake()->createWithContent(
                    "protocol-revision-{$revisionNumber}.pdf",
                    "%PDF-1.4 revision {$revisionNumber}",
                ),
            );
            $workflow->submitRevision($applicant, $application->refresh(), $revision->refresh());
            $currentAssignment = ReviewerAssignment::query()
                ->where('research_application_id', $application->id)
                ->where('review_cycle', $revisionNumber)
                ->firstOrFail();

            if ($revisionNumber < ApplicationRevisionWorkflowService::MAX_REVISION_CYCLES) {
                $currentAssignment->reviewSubmission()->create([
                    'status' => ReviewSubmissionStatus::Submitted,
                    'decision' => ReviewDecision::MinorRevision,
                    'decision_comment' => "Revision {$revisionNumber} still requires correction.",
                    'submitted_at' => now(),
                ]);
                $currentAssignment->update([
                    'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
                    'submitted_at' => now(),
                ]);
                $consensus->evaluate($application->refresh());
            }
        }

        $this->assertSame(4, $application->refresh()->current_revision_cycle);
        $this->assertSame(ApplicationStatus::UnderReReview, $application->application_status);
        $finalComment = 'The third revised document still requires a major revision.';
        $currentAssignment->comments()->create([
            'application_document_id' => $currentDocument->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => $finalComment,
        ]);
        $currentAssignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::MajorRevision,
            'decision_comment' => 'The final review did not meet the approval criteria.',
            'submitted_at' => now(),
        ]);
        $currentAssignment->update([
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);

        $evaluated = $consensus->evaluate($application->refresh());

        $this->assertSame(ApplicationStatus::Failed, $evaluated->application_status);
        $this->assertSame(ApplicationStage::Completed, $evaluated->current_stage);
        $this->assertSame(3, $evaluated->review_consensus_cycle);
        $this->assertSame(ReviewDecision::MajorRevision, $evaluated->review_consensus_decision);
        $this->assertSame(3, $application->decisionReleases()->count());
        $this->assertDatabaseMissing('application_decision_releases', [
            'research_application_id' => $application->id,
            'review_cycle' => 3,
        ]);
        $this->assertSame(3, $application->revisions()
            ->where('status', ApplicationRevisionStatus::Completed->value)
            ->count());

        $this->actingAs($resLead)
            ->get(route('res.certificates.index'))
            ->assertOk()
            ->assertSeeInOrder([$application->application_code, 'Failed - Maximum Revisions'])
            ->assertSee('is-final-review-failed', false)
            ->assertSee('Application already failed.');
        $this->actingAs($resLead)
            ->from(route('res.certificates.index'))
            ->post(route('res.certificates.decisions.release', $application), [
                'application_id' => $application->id,
            ])
            ->assertRedirect(route('res.certificates.index'))
            ->assertSessionHasErrorsIn('decisionRelease', ['review_submission_id']);
        $this->actingAs($applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('Failed')
            ->assertSee($finalComment)
            ->assertSee('The final review did not meet the approval criteria.');
        $this->actingAs($adviser)
            ->get(route('adviser.applications.index'))
            ->assertOk()
            ->assertSee($application->application_code)
            ->assertSee('Failed');
        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.index', ['tab' => 'completed']))
            ->assertOk()
            ->assertSee($application->application_code)
            ->assertSee('Failed');
    }

    /** @return array{User, User, User, ResearchApplication, ReviewerAssignment, ApplicationDocument} */
    private function reviewedApplication(): array
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-2026-S-ICDI-08112026-REV001',
            'applicant_user_id' => $applicant->id,
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::EthicsReview,
            'current_revision_cycle' => 1,
            'submitted_at' => now()->subWeek(),
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'REVISION-PROTOCOL',
            'name' => 'Research Protocol',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'protocol.pdf',
            'stored_file_path' => "applications/{$application->id}/protocol.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'file_sha256' => hash('sha256', '%PDF-1.4 original'),
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now()->subWeek(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, '%PDF-1.4 original');
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'review_cycle' => 0,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now()->subDay(),
        ]);
        $assignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::MinorRevision,
            'decision_comment' => 'Revision is required.',
            'submitted_at' => now()->subDay(),
        ]);

        return [$applicant, $reviewer, $resLead, $application, $assignment, $document];
    }

    private function openWindow(string $key, UserRole $role): DeadlineConfiguration
    {
        return DeadlineConfiguration::create([
            'deadline_key' => 'test-'.$key,
            'title' => $key,
            'audience_role' => $role,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDays(7),
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    private function versionPanel(string $html, string $attribute, int $documentId): string
    {
        $marker = $attribute.'="'.$documentId.'"';
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, "Expected {$marker} in rendered revision history.");
        $next = strpos($html, $attribute.'="', $start + strlen($marker));

        return substr($html, $start, $next === false ? null : $next - $start);
    }
}
