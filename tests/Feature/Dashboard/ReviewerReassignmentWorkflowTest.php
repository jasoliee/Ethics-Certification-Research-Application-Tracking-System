<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewCommentCategory;
use App\Enums\ReviewCommentScope;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ApplicationScreening;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewComment;
use App\Models\ReviewerAssignment;
use App\Models\ReviewerConflict;
use App\Models\ReviewSubmission;
use App\Models\ReviewSubmissionVersion;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\Applications\ReviewConsensusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReviewerReassignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_res_can_replace_a_reviewer_after_initial_work_starts_without_destroying_history(): void
    {
        [$resLead, $application] = $this->reviewApplication();
        $original = $this->reviewer();
        $replacement = $this->reviewer();
        $assignment = $this->assignment($application, $original, 0, ReviewerAssignmentStatus::InReview);
        $draft = ReviewSubmission::create([
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewSubmissionStatus::Draft,
            'draft_decision' => ReviewDecision::MinorRevision,
            'draft_decision_comment' => 'The reviewer has started the assessment.',
        ]);
        $comment = ReviewComment::create([
            'reviewer_assignment_id' => $assignment->id,
            'scope' => ReviewCommentScope::Overall,
            'category' => ReviewCommentCategory::Clarification,
            'body' => 'Started review work that must remain in history.',
        ]);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$replacement->id],
                'confirm_assignment' => '1',
                'reassignment_reason' => 'The original reviewer is no longer available to complete this review.',
            ])
            ->assertRedirect(route('res.applications.reviewers.index', $application))
            ->assertSessionHasNoErrors();

        $assignment->refresh();
        $replacementAssignment = ReviewerAssignment::query()
            ->current()
            ->where('research_application_id', $application->id)
            ->where('reviewer_user_id', $replacement->id)
            ->firstOrFail();

        $this->assertSame(ReviewerAssignmentStatus::Superseded, $assignment->assignment_status);
        $this->assertSame(ReviewerAssignmentStatus::InReview->value, $assignment->superseded_from_status);
        $this->assertSame($resLead->id, $assignment->superseded_by_user_id);
        $this->assertSame($assignment->id, $replacementAssignment->replaces_assignment_id);
        $this->assertSame(ReviewerAssignmentStatus::Pending, $replacementAssignment->assignment_status);
        $this->assertDatabaseHas('review_submissions', ['id' => $draft->id, 'reviewer_assignment_id' => $assignment->id]);
        $this->assertDatabaseHas('review_comments', ['id' => $comment->id, 'reviewer_assignment_id' => $assignment->id]);

        $this->actingAs($original)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertForbidden();
        $this->actingAs($original)
            ->get(route('reviewer.assignments.index'))
            ->assertOk()
            ->assertDontSee($application->application_code);

        Notification::assertSentTo(
            $original,
            DashboardUpdateNotification::class,
            fn (DashboardUpdateNotification $notification): bool => $notification->toDatabase($original)['title'] === 'Ethics review assignment updated',
        );
        Notification::assertSentTo($replacement, DashboardUpdateNotification::class);
    }

    public function test_res_can_replace_a_submitted_revision_reviewer_and_consensus_uses_only_the_replacement(): void
    {
        [$resLead, $application] = $this->reviewApplication(2, ApplicationStatus::UnderReReview);
        $original = $this->reviewer();
        $replacement = $this->reviewer();
        $assignment = $this->assignment($application, $original, 1, ReviewerAssignmentStatus::DecisionSubmitted);
        [$oldSubmission, $oldVersion] = $this->submittedDecision($assignment, $original, ReviewDecision::Approved);

        $application = app(ReviewConsensusService::class)->evaluate($application);
        $this->assertSame(ReviewConsensusStatus::Consensus, $application->review_consensus_status);
        $this->assertSame(ApplicationStatus::ReviewSubmittedPendingRelease, $application->application_status);

        $this->actingAs($resLead)
            ->get(route('res.applications.reviewers.index', $application))
            ->assertOk()
            ->assertSee('Reason for Reassignment');

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$replacement->id],
                'confirm_assignment' => '1',
                'reassignment_reason' => 'A replacement is required for the active revision review cycle.',
            ])
            ->assertSessionHasNoErrors();

        $assignment->refresh();
        $application->refresh();
        $replacementAssignment = ReviewerAssignment::query()
            ->current()
            ->where('research_application_id', $application->id)
            ->where('reviewer_user_id', $replacement->id)
            ->firstOrFail();

        $this->assertSame(ReviewerAssignmentStatus::Superseded, $assignment->assignment_status);
        $this->assertSame('revision_review', $replacementAssignment->review_type);
        $this->assertSame(1, $replacementAssignment->review_cycle);
        $this->assertSame(ReviewerAssignmentStatus::RevisionReview, $replacementAssignment->assignment_status);
        $this->assertSame(ApplicationStatus::UnderReReview, $application->application_status);
        $this->assertSame(ReviewConsensusStatus::AwaitingSubmissions, $application->review_consensus_status);
        $this->assertNull($application->review_consensus_decision);
        $this->assertDatabaseHas('review_submissions', ['id' => $oldSubmission->id]);
        $this->assertDatabaseHas('review_submission_versions', ['id' => $oldVersion->id]);

        $this->submittedDecision($replacementAssignment, $replacement, ReviewDecision::MinorRevision);
        $application = app(ReviewConsensusService::class)->evaluate($application);

        $this->assertSame(ReviewConsensusStatus::Consensus, $application->review_consensus_status);
        $this->assertSame(ReviewDecision::MinorRevision, $application->review_consensus_decision);
        $this->assertSame(1, ReviewerAssignment::query()
            ->current()
            ->where('research_application_id', $application->id)
            ->where('review_cycle', 1)
            ->count());

        $this->actingAs($original)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertForbidden();
        $this->actingAs($original)
            ->get(route('reviewer.assignments.index'))
            ->assertOk()
            ->assertDontSee($application->application_code);
        Notification::assertSentTo($original, DashboardUpdateNotification::class);
    }

    public function test_replacement_ignores_legacy_classification_and_revalidates_other_eligibility_rules_atomically(): void
    {
        [$resLead, $application] = $this->reviewApplication();
        $original = $this->reviewer();
        $assignment = $this->assignment($application, $original, 0, ReviewerAssignmentStatus::InReview);

        $disabled = $this->reviewer(['reviewer_enabled' => false]);
        $wrongClassification = $this->reviewer([
            'reviewer_classification' => ReviewType::FullBoard->reviewerClassification(),
            'reviewer_classifications' => [ReviewType::FullBoard->reviewerClassification()],
        ]);
        $full = $this->reviewer(['reviewer_capacity' => 1]);
        $conflicted = $this->reviewer();
        $endorser = $this->reviewer();

        $otherApplication = ResearchApplication::factory()->create();
        $this->assignment($otherApplication, $full, 0, ReviewerAssignmentStatus::InReview);
        ReviewerConflict::create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $conflicted->id,
            'declared_by_user_id' => $resLead->id,
            'reason' => 'Declared application-specific conflict.',
            'declared_at' => now(),
        ]);
        Endorsement::create([
            'research_application_id' => $application->id,
            'adviser_user_id' => $endorser->id,
            'endorsement_status' => 'endorsed',
            'endorsed_at' => now(),
        ]);

        foreach ([$disabled, $full, $conflicted, $endorser] as $candidate) {
            $this->actingAs($resLead)
                ->post(route('res.applications.reviewers.store', $application), [
                    'reviewer_ids' => [$candidate->id],
                    'confirm_assignment' => '1',
                    'reassignment_reason' => 'Testing authoritative replacement eligibility safeguards.',
                ])
                ->assertSessionHasErrorsIn('reviewerAssignment', ['reviewer_ids']);

            $this->assertDatabaseHas('reviewer_assignments', [
                'id' => $assignment->id,
                'assignment_status' => ReviewerAssignmentStatus::InReview->value,
                'superseded_at' => null,
            ]);
            $this->assertDatabaseMissing('reviewer_assignments', [
                'research_application_id' => $application->id,
                'reviewer_user_id' => $candidate->id,
            ]);
        }

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$wrongClassification->id],
                'confirm_assignment' => '1',
                'reassignment_reason' => 'Legacy classifications do not restrict current assignments.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviewer_assignments', [
            'research_application_id' => $application->id,
            'reviewer_user_id' => $wrongClassification->id,
            'superseded_at' => null,
        ]);
    }

    /** @return array{0: User, 1: ResearchApplication} */
    private function reviewApplication(
        int $currentRevisionCycle = 1,
        ApplicationStatus $status = ApplicationStatus::UnderExpeditedReview,
    ): array {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => $status,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => ReviewType::Expedited,
            'current_revision_cycle' => $currentRevisionCycle,
            'submitted_at' => now()->subDays(5),
        ]);
        ApplicationScreening::create([
            'research_application_id' => $application->id,
            'screened_by_user_id' => $resLead->id,
            'review_type' => ReviewType::Expedited,
            'classification_reason' => 'The application meets the expedited classification criteria.',
            'classified_at' => now()->subDays(4),
        ]);

        return [$resLead, $application];
    }

    /** @param array<string, mixed> $overrides */
    private function reviewer(array $overrides = []): User
    {
        return User::factory()->reviewer([ReviewType::Expedited->reviewerClassification()])->create(array_merge([
            'account_status' => AccountStatus::Active->value,
            'password_setup_completed_at' => now(),
            'reviewer_capacity' => 5,
        ], $overrides));
    }

    private function assignment(
        ResearchApplication $application,
        User $reviewer,
        int $cycle,
        ReviewerAssignmentStatus $status,
    ): ReviewerAssignment {
        return ReviewerAssignment::create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => $cycle === 0 ? 'initial_review' : 'revision_review',
            'review_cycle' => $cycle,
            'assignment_status' => $status,
            'assignment_sequence' => 1,
            'assigned_at' => now()->subDays(2),
            'review_deadline_at' => now()->addDays(5),
            'submitted_at' => $status === ReviewerAssignmentStatus::DecisionSubmitted ? now()->subDay() : null,
        ]);
    }

    /** @return array{0: ReviewSubmission, 1: ReviewSubmissionVersion} */
    private function submittedDecision(
        ReviewerAssignment $assignment,
        User $reviewer,
        ReviewDecision $decision,
    ): array {
        $submittedAt = now();
        $submission = ReviewSubmission::create([
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => $decision,
            'decision_comment' => 'Immutable submitted reviewer decision.',
            'has_unsubmitted_changes' => false,
            'submitted_at' => $submittedAt,
        ]);
        $version = ReviewSubmissionVersion::create([
            'review_submission_id' => $submission->id,
            'reviewer_assignment_id' => $assignment->id,
            'version_number' => 1,
            'submission_token' => (string) Str::uuid(),
            'decision' => $decision,
            'decision_comment' => 'Immutable submitted reviewer decision.',
            'snapshot_schema_version' => 1,
            'payload_snapshot' => [
                'decision' => $decision->value,
                'decision_comment' => 'Immutable submitted reviewer decision.',
                'comments' => [],
                'forms' => [],
            ],
            'payload_sha256' => hash('sha256', 'payload-'.$assignment->id.'-'.$decision->value),
            'request_sha256' => hash('sha256', 'request-'.$assignment->id.'-'.$decision->value),
            'submitted_by_user_id' => $reviewer->id,
            'submitted_at' => $submittedAt,
        ]);
        $submission->update(['current_version_id' => $version->id]);
        $assignment->update([
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => $submittedAt,
        ]);

        return [$submission->refresh(), $version];
    }
}
