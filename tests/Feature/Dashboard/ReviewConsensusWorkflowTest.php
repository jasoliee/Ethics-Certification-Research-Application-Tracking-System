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
