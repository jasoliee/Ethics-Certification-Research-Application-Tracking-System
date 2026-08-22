<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\BulkReleaseType;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Services\Applications\ApplicationRevisionWorkflowService;
use App\Services\Applications\ReviewConsensusService;
use App\Services\Applications\ReviewSubmissionVersionService;
use App\Services\Certificates\BulkReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReviewConsensusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_full_board_release_includes_all_three_feedback_sets_and_actionable_requirements(): void
    {
        Notification::fake();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => 'full_board',
            'current_revision_cycle' => 1,
            'submitted_at' => now()->subWeek(),
        ]);
        DeadlineConfiguration::create([
            'deadline_key' => 'test-revision-period',
            'title' => 'Applicant revision period',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addWeek(),
            'priority' => 10,
            'is_active' => true,
        ]);

        $comments = collect(range(1, 3))->map(function (int $sequence) use ($application) {
            $reviewer = User::factory()->reviewer()->create();
            $assignment = ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'review_type' => 'initial_review',
                'review_cycle' => 0,
                'assignment_sequence' => $sequence,
                'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
                'submitted_at' => now()->subDay(),
            ]);
            $requirement = DocumentRequirement::create([
                'code' => 'FULL-BOARD-'.$sequence,
                'name' => 'Full Board Requirement '.$sequence,
                'is_mandatory' => true,
                'sort_order' => $sequence,
                'is_active' => true,
            ]);
            $document = ApplicationDocument::create([
                'research_application_id' => $application->id,
                'document_requirement_id' => $requirement->id,
                'uploaded_by_user_id' => $application->applicant_user_id,
                'original_file_name' => "reviewed-{$sequence}.pdf",
                'stored_file_path' => "applications/tests/{$application->id}/reviewed-{$sequence}.pdf",
                'mime_type' => 'application/pdf',
                'file_size_bytes' => 100,
                'file_sha256' => str_repeat((string) $sequence, 64),
                'document_version' => 1,
                'validation_status' => 'completed',
                'is_current' => true,
                'uploaded_at' => now()->subDays(2),
            ]);
            $comment = $assignment->comments()->create([
                'application_document_id' => $document->id,
                'scope' => 'document',
                'category' => 'required_revision',
                'body' => "FULL-BOARD-ACTIONABLE-FEEDBACK-{$sequence}",
                'status' => 'open',
            ]);
            $assignment->reviewSubmission()->create([
                'status' => ReviewSubmissionStatus::Submitted,
                'decision' => ReviewDecision::MinorRevision,
                'decision_comment' => "Reviewer {$sequence} requires revision.",
                'submitted_at' => now()->subDay(),
            ]);

            return $comment;
        });

        $evaluated = app(ReviewConsensusService::class)->evaluate($application);
        $this->assertSame(ReviewConsensusStatus::Consensus, $evaluated->review_consensus_status);
        $this->actingAs($resLead)
            ->get(route('res.certificates.workspace', $application))
            ->assertOk()
            ->assertSeeInOrder(['Supporting Documents', 'Release Decision', 'Reviewer 1'])
            ->assertDontSee('Application Decision');
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $this->assertMatchesRegularExpression(
            '/\.res-application-release-panel\s*>\s*form\s*>\s*\.dashboard-primary-action\s*\{[^}]*width:\s*100%;/s',
            $css,
        );

        $release = app(ApplicationRevisionWorkflowService::class)->releaseDecision(
            $resLead,
            $application->refresh(),
        );

        $this->assertCount(3, $release->source_review_submission_version_ids);
        $this->assertCount(3, $release->released_feedback_snapshot);
        $this->assertSame([1, 2, 3], collect($release->released_feedback_snapshot)->pluck('reviewer_sequence')->all());
        $this->assertSame(3, $release->releasedComments()->count());
        $this->assertSame(3, $application->revisions()->firstOrFail()->requirements()->count());
        $comments->each(fn ($comment) => $this->assertSame($release->id, $comment->refresh()->application_decision_release_id));

        $response = $this->actingAs($application->applicant)
            ->get(route('applicant.revision-certificates.index', ['application' => $application->id]))
            ->assertOk()
            ->assertSee('Reviewer 1')
            ->assertSee('Reviewer 2')
            ->assertSee('Reviewer 3');
        foreach (range(1, 3) as $sequence) {
            $response->assertSee("FULL-BOARD-ACTIONABLE-FEEDBACK-{$sequence}");
        }
    }

    public function test_full_board_conflict_blocks_every_release_path_until_an_immutable_resubmission_restores_consensus(): void
    {
        Notification::fake();
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => 'full_board',
            'current_revision_cycle' => 1,
            'submitted_at' => now()->subWeek(),
        ]);
        $assignments = collect([
            ReviewDecision::Approved,
            ReviewDecision::Approved,
            ReviewDecision::Disapproved,
        ])->map(function (ReviewDecision $decision, int $index) use ($application): ReviewerAssignment {
            $reviewer = User::factory()->reviewer(['Full Board'])->create();
            $assignment = ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'review_type' => 'initial_review',
                'review_cycle' => 0,
                'assignment_sequence' => $index + 1,
                'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
                'submitted_at' => now()->subDay(),
            ]);
            $assignment->reviewSubmission()->create([
                'status' => ReviewSubmissionStatus::Submitted,
                'decision' => $decision,
                'decision_comment' => 'Submitted Full Board decision '.$decision->value.'.',
                'submitted_at' => now()->subDay(),
            ]);

            return $assignment;
        });

        $evaluated = app(ReviewConsensusService::class)->evaluate($application);
        $this->assertSame(ReviewConsensusStatus::Conflicted, $evaluated->review_consensus_status);
        $this->assertNotNull($evaluated->review_consensus_signature);
        Notification::assertSentToTimes($resLead, DashboardUpdateNotification::class, 1);
        $this->actingAs($resLead)
            ->get(route('res.certificates.index'))
            ->assertOk()
            ->assertSee('Conflicted Decisions')
            ->assertSee('is-review-conflicted', false)
            ->assertSee('Decision release blocked.')
            ->assertDontSee($application->applicant->name);
        $this->actingAs($resLead)
            ->get(route('res.certificates.workspace', $application))
            ->assertOk()
            ->assertSeeInOrder(['Supporting Documents', 'Decision release blocked.', 'Reviewer 1'])
            ->assertDontSee('Application Decision')
            ->assertSee('The three current Full Board submissions do not agree. A Reviewer must re-submit before RES can release a result.')
            ->assertDontSee('Release Decision');

        try {
            app(ApplicationRevisionWorkflowService::class)->releaseDecision(
                $resLead,
                $application->refresh(),
                $assignments->first()->reviewSubmission,
            );
            $this->fail('A conflicted Full Board result must never be released manually.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('conflicted', $exception->errors()['review_submission_id'][0]);
        }
        $bulk = app(BulkReleaseService::class)->release($resLead, BulkReleaseType::Decision);
        $this->assertSame(0, $bulk['successfully_released']);
        $this->assertSame(1, $bulk['conflicted']);
        $this->assertDatabaseCount('application_decision_releases', 0);
        Notification::assertSentToTimes($resLead, DashboardUpdateNotification::class, 1);

        $disagreeingAssignment = $assignments->last();
        $disagreeingSubmission = $disagreeingAssignment->reviewSubmission()->firstOrFail();
        $firstVersion = $disagreeingSubmission->currentVersion()->firstOrFail();
        $this->assertSame(ReviewDecision::Disapproved, $firstVersion->decision);
        $disagreeingSubmission->update([
            'decision' => ReviewDecision::Approved->value,
            'decision_comment' => 'Reconsidered Full Board decision after deliberation.',
        ]);
        $secondVersion = app(ReviewSubmissionVersionService::class)->create(
            $disagreeingAssignment->reviewer,
            $disagreeingAssignment,
            $disagreeingSubmission->refresh(),
            collect(),
            collect(),
            collect(),
            now(),
        );
        $resolved = app(ReviewConsensusService::class)->evaluate($application->refresh());

        $this->assertSame(2, $secondVersion->version_number);
        $this->assertSame(ReviewDecision::Disapproved, $firstVersion->refresh()->decision);
        $this->assertSame(ReviewConsensusStatus::Consensus, $resolved->review_consensus_status);
        $this->assertSame(ReviewDecision::Approved, $resolved->review_consensus_decision);

        $this->actingAs($resLead)
            ->get(route('res.certificates.workspace', $application))
            ->assertOk()
            ->assertDontSee('Release Decision');

        $release = app(ApplicationRevisionWorkflowService::class)->releaseDecision(
            $resLead,
            $application->refresh(),
            $assignments->first()->reviewSubmission,
        );
        $this->assertSame(ReviewDecision::Approved, $release->decision);
        $this->assertNotNull($release->source_review_submission_version_id);
        $this->assertSame($resolved->review_consensus_signature, $release->review_consensus_signature);
        $this->assertSame(ApplicationStatus::ForCertificateRelease, $application->refresh()->application_status);
        $certificate = $application->certificate()->with('currentVersion')->firstOrFail();
        $this->assertSame('pending_release', $certificate->status->value);
        $this->assertSame($certificate->issued_date->addYearNoOverflow()->toDateString(), $certificate->valid_until->toDateString());
        $this->assertSame($certificate->issued_date->toDateString(), $certificate->currentVersion->issued_date->toDateString());

        $this->actingAs($disagreeingAssignment->reviewer)
            ->post(route('reviewer.assignments.comments.store', $disagreeingAssignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'A forged post-release edit must be rejected immediately.',
            ])
            ->assertForbidden();
    }
}
