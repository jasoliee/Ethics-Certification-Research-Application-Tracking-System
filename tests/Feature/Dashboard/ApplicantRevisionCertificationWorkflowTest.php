<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Services\Applications\ApplicationDocumentService;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicantRevisionCertificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_releases_only_selected_comments_and_routes_a_versioned_revision_to_the_same_reviewer(): void
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
        $unreleasedComment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'scope' => ReviewCommentScope::Overall,
            'category' => ReviewCommentCategory::General,
            'body' => 'Internal note that must remain hidden.',
        ]);

        $release = app(ApplicationRevisionWorkflowService::class)->releaseDecision(
            $resLead,
            $application,
            ReviewDecision::MinorRevision,
            [$releasedComment->id],
        );

        $application->refresh();
        $revision = $application->revisions()->with('requirements')->firstOrFail();
        $this->assertSame(ApplicationStatus::RevisionWindowOpen, $application->application_status);
        $this->assertSame(2, $application->current_revision_cycle);
        $this->assertSame($release->id, $releasedComment->refresh()->application_decision_release_id);
        $this->assertNotNull($releasedComment->released_at);
        $this->assertNull($unreleasedComment->refresh()->released_at);
        $this->assertCount(1, $revision->requirements);

        $page = $this->actingAs($applicant)->get(route('applicant.revision-certificates.index', [
            'application' => $application->id,
        ]));
        $page->assertOk()
            ->assertSee('Replace the participant consent procedure.')
            ->assertDontSee('Internal note that must remain hidden.')
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
            ReviewDecision::MajorRevision,
            [$comment->id],
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

    public function test_a_third_revision_cycle_is_rejected_before_any_release_is_persisted(): void
    {
        Storage::fake('local');
        Notification::fake();
        [, $reviewer, $resLead, $application, , $document] = $this->reviewedApplication();
        $application->update(['current_revision_cycle' => 3]);
        $thirdCycleAssignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'revision_review',
            'review_cycle' => 2,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'assignment_sequence' => 2,
            'submitted_at' => now()->subHour(),
        ]);
        $thirdCycleAssignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::MinorRevision,
            'submitted_at' => now()->subHour(),
        ]);
        $comment = ReviewComment::create([
            'reviewer_assignment_id' => $thirdCycleAssignment->id,
            'application_document_id' => $document->id,
            'scope' => ReviewCommentScope::Document,
            'category' => ReviewCommentCategory::RequiredRevision,
            'body' => 'A third revision must not be opened.',
        ]);

        try {
            app(ApplicationRevisionWorkflowService::class)->releaseDecision(
                $resLead,
                $application->refresh(),
                ReviewDecision::MinorRevision,
                [$comment->id],
            );
            $this->fail('The maximum revision cycle boundary must be enforced.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('decision', $exception->errors());
        }

        $this->assertSame(0, $application->decisionReleases()->count());
        $this->assertNull($comment->refresh()->released_at);
        $this->assertSame(ApplicationStatus::ReviewSubmittedPendingRelease, $application->refresh()->application_status);
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
}
