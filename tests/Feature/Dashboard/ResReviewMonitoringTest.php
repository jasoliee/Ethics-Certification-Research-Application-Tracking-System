<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\EndorsementStatus;
use App\Enums\ReviewConsensusStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResReviewMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_res_lead_can_open_review_monitoring(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->get(route('res.review-monitoring.index'))
            ->assertRedirect(route('login'));
        $this->actingAs($applicant)
            ->get(route('res.review-monitoring.index'))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($adviser)
            ->get(route('res.review-monitoring.index'))
            ->assertRedirect(route('dashboard'));

        $this->actingAs($resLead)
            ->get(route('res.review-monitoring.index'))
            ->assertOk()
            ->assertSee('Review Monitoring')
            ->assertSee('No reviewer-enabled Advisers')
            ->assertDontSee('Assignment progress and deadlines');
    }

    public function test_full_board_conflict_is_prominent_anonymous_and_excludes_applicant_and_comment_data(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Sensitive Applicant Identity',
            'email' => 'sensitive-applicant@example.test',
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'application_code' => 'RES-FULLBOARD-CONFLICT-001',
            'research_title' => 'Anonymous Full Board Conflict Study',
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => 'full_board',
            'current_revision_cycle' => 1,
            'review_consensus_status' => ReviewConsensusStatus::Conflicted,
            'review_consensus_cycle' => 0,
            'review_conflicted_at' => now()->subHour(),
            'submitted_at' => now()->subWeek(),
        ]);

        $reviewers = collect([
            ['name' => 'Capacity Adviser Alpha', 'decision' => ReviewDecision::Approved],
            ['name' => 'Capacity Adviser Beta', 'decision' => ReviewDecision::MinorRevision],
            ['name' => 'Capacity Adviser Gamma', 'decision' => ReviewDecision::Disapproved],
        ])->map(function (array $fixture, int $index) use ($application): User {
            $reviewer = User::factory()->reviewer(['Full Board'])->create([
                'name' => $fixture['name'],
            ]);
            $assignment = $this->createAssignment(
                $application,
                $reviewer,
                ReviewerAssignmentStatus::DecisionSubmitted,
                $index + 1,
                now()->subDay(),
            );
            $assignment->reviewSubmission()->create([
                'status' => ReviewSubmissionStatus::Submitted,
                'decision' => $fixture['decision'],
                'decision_comment' => 'CONFIDENTIAL INTERNAL COMMENT '.$index,
                'submitted_at' => now()->subDay(),
            ]);

            return $reviewer;
        });

        DB::enableQueryLog();
        $response = $this->actingAs($resLead)
            ->get(route('res.review-monitoring.index'))
            ->assertOk()
            ->assertSee('Full Board decision conflicts require RES attention')
            ->assertSee('RES-FULLBOARD-CONFLICT-001')
            ->assertSeeInOrder(['Reviewer 1', 'Approved', 'Reviewer 2', 'Minor Revision', 'Reviewer 3', 'Disapproved'])
            ->assertSee(route('res.applications.show', $application), false)
            ->assertSee(route('res.certificates.workspace', $application), false)
            ->assertDontSee('Sensitive Applicant Identity')
            ->assertDontSee('sensitive-applicant@example.test')
            ->assertDontSee('CONFIDENTIAL INTERNAL COMMENT');

        $this->assertMatchesRegularExpression(
            '/<section[^>]*id="review-monitoring-conflicts".*?<\/section>/s',
            $response->getContent(),
        );
        preg_match(
            '/<section[^>]*id="review-monitoring-conflicts".*?<\/section>/s',
            $response->getContent(),
            $conflictSection,
        );
        foreach ($reviewers as $reviewer) {
            $this->assertStringNotContainsString($reviewer->name, $conflictSection[0]);
            // Staff names remain available only in the distinct capacity panel.
            $response->assertSee($reviewer->name);
        }

        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        $this->assertStringNotContainsString('review_comments', $queries);
    }

    public function test_metrics_use_current_assignment_state_without_the_removed_assignment_progress_container(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $overdueApplication = ResearchApplication::factory()->create([
            'application_code' => 'RES-MONITOR-ALPHA',
            'research_title' => 'Alpha Deadline Study',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => 'expedited',
            'review_consensus_status' => ReviewConsensusStatus::AwaitingSubmissions,
            'submitted_at' => now()->subWeek(),
        ]);
        $completedApplication = ResearchApplication::factory()->create([
            'application_code' => 'RES-MONITOR-BETA',
            'research_title' => 'Beta Completed Study',
            'application_status' => ApplicationStatus::ReviewSubmittedPendingRelease,
            'current_stage' => ApplicationStage::DecisionRelease,
            'review_type' => 'expedited',
            'review_consensus_status' => ReviewConsensusStatus::Consensus,
            'review_consensus_cycle' => 0,
            'review_consensus_decision' => ReviewDecision::Approved,
            'submitted_at' => now()->subWeek(),
        ]);
        $overdueReviewer = User::factory()->reviewer()->create();
        $completedReviewer = User::factory()->reviewer()->create();

        $this->createAssignment(
            $overdueApplication,
            $overdueReviewer,
            ReviewerAssignmentStatus::Pending,
            1,
            now()->subDay(),
        );
        $this->createAssignment(
            $completedApplication,
            $completedReviewer,
            ReviewerAssignmentStatus::DecisionSubmitted,
            1,
            now()->subDay(),
        );

        $response = $this->actingAs($resLead)
            ->get(route('res.review-monitoring.index'))
            ->assertOk()
            ->assertDontSee('Assignment progress and deadlines')
            ->assertSee('Reviewer-enabled Adviser workload')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics === [
                'active_applications' => 1,
                'active_assignments' => 1,
                'completed_assignments' => 1,
                'total_assignments' => 2,
                'completion_rate' => 50,
                'overdue_assignments' => 1,
                'conflicted_applications' => 0,
            ]);

        $response->assertSee('review-monitoring-reviewer-table', false);
    }

    public function test_reviewer_enabled_adviser_capacity_shows_load_without_comments_or_disabled_accounts(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-CAPACITY-001',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => 'expedited',
            'submitted_at' => now()->subWeek(),
        ]);
        $fullReviewer = User::factory()->reviewer()->create([
            'name' => 'Full Capacity Adviser',
            'reviewer_capacity' => 1,
        ]);
        $availableReviewer = User::factory()->reviewer(['Expedited', 'Full Board'])->create([
            'name' => 'Available Capacity Adviser',
            'reviewer_capacity' => 3,
        ]);
        User::factory()->reviewer()->create([
            'name' => 'Disabled Reviewer Account',
            'reviewer_enabled' => false,
        ]);

        $assignment = $this->createAssignment(
            $application,
            $fullReviewer,
            ReviewerAssignmentStatus::InReview,
            1,
            now()->addDay(),
        );
        $assignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Draft,
            'decision_comment' => 'DRAFT CONFIDENTIAL CAPACITY COMMENT',
        ]);

        $response = $this->actingAs($resLead)
            ->get(route('res.review-monitoring.index'))
            ->assertOk()
            ->assertSee('Full Capacity Adviser')
            ->assertSee('Available Capacity Adviser')
            ->assertSee('Current Number of Applications')
            ->assertSee('Successfully Completed Applications')
            ->assertSee('Remaining Applications to Be Reviewed')
            ->assertSee('>View</a>', false)
            ->assertDontSee('Reviewer classifications')
            ->assertDontSee('DRAFT CONFIDENTIAL CAPACITY COMMENT');

        preg_match(
            '/<section[^>]*id="review-monitoring-capacity".*?<\/section>/s',
            $response->getContent(),
            $reviewerCapacitySection,
        );
        $this->assertNotEmpty($reviewerCapacitySection);
        $this->assertStringNotContainsString('Disabled Reviewer Account', $reviewerCapacitySection[0]);

        $response->assertViewHas('reviewerWorkloads', function ($reviewers) use ($fullReviewer, $availableReviewer): bool {
            return $reviewers->pluck('id')->sort()->values()->all() === collect([$fullReviewer->id, $availableReviewer->id])->sort()->values()->all()
                && (int) $reviewers->firstWhere('id', $fullReviewer->id)->active_assignment_count === 1
                && (int) $reviewers->firstWhere('id', $availableReviewer->id)->active_assignment_count === 0
                && (int) $reviewers->firstWhere('id', $fullReviewer->id)->completed_application_count === 0;
        });
    }

    public function test_adviser_endorsement_workload_uses_live_statistics_and_safe_application_drill_down(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $privateApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Private Unendorsed Applicant',
            'email' => 'private-unendorsed@example.test',
            'username' => 'private-unendorsed-user',
        ]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Endorsement Workload Alpha',
            'position_title' => 'Research Adviser',
            'department' => 'Computer Studies',
            'expected_endorsement_count' => 5,
        ]);
        User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Inactive Workload Alpha',
            'department' => 'Computer Studies',
            'account_status' => 'inactive',
            'expected_endorsement_count' => 8,
        ]);
        User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Different Active Adviser',
            'department' => 'Behavioral Sciences',
            'expected_endorsement_count' => 2,
        ]);

        $endorsedApplication = ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser->id,
            'application_code' => 'RES-ENDORSED-SAFE-001',
            'application_status' => ApplicationStatus::UnderResScreening,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDays(3),
        ]);
        Endorsement::query()->create([
            'research_application_id' => $endorsedApplication->id,
            'adviser_user_id' => $adviser->id,
            'endorsement_status' => EndorsementStatus::Endorsed,
            'endorsed_at' => now()->subDays(2),
        ]);
        $awaitingApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $privateApplicant->id,
            'adviser_user_id' => $adviser->id,
            'application_code' => 'RES-UNENDORSED-SAFE-002',
            'research_title' => 'Private Unendorsed Applicant Credential Study',
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'current_stage' => ApplicationStage::AdviserReview,
            'submitted_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($resLead)
            ->get(route('res.review-monitoring.index', [
                'adviser_q' => 'Endorsement Workload Alpha',
                'adviser_department' => 'Computer Studies',
                'adviser_workload' => 'not_received',
            ]))
            ->assertOk()
            ->assertSee('Adviser endorsement workload')
            ->assertSee('Endorsement Workload Alpha')
            ->assertSee('data-declared="5"', false)
            ->assertSee('data-endorsed="1"', false)
            ->assertSee('data-awaiting="1"', false)
            ->assertSee('data-remaining="4"', false)
            ->assertSee('data-not-received="3"', false)
            ->assertSee('>View</a>', false)
            ->assertSee(route('res.review-monitoring.advisers.applications', $adviser), false)
            ->assertDontSee('RES-UNENDORSED-SAFE-002')
            ->assertDontSee('Private Unendorsed Applicant')
            ->assertDontSee('private-unendorsed@example.test')
            ->assertDontSee('private-unendorsed-user')
            ->assertDontSee('Private Unendorsed Applicant Credential Study')
            ->assertDontSee('Inactive Workload Alpha')
            ->assertDontSee('Different Active Adviser');

        $response->assertViewHas('adviserWorkloads', function ($advisers) use ($adviser): bool {
            $row = $advisers->first();

            return $advisers->total() === 1
                && $row?->is($adviser)
                && $row?->endorsement_statistics === [
                    'declared' => 5,
                    'endorsed' => 1,
                    'awaiting' => 1,
                    'remaining' => 4,
                    'not_received' => 3,
                ];
        });

        $this->actingAs($resLead)
            ->get(route('res.review-monitoring.advisers.applications', $adviser))
            ->assertOk()
            ->assertSee('RES-ENDORSED-SAFE-001')
            ->assertDontSee('RES-UNENDORSED-SAFE-002')
            ->assertDontSee('Private Unendorsed Applicant');
    }

    public function test_monitoring_filters_collapse_responsively_and_the_wide_table_stays_scrollable(): void
    {
        $css = file_get_contents(resource_path('css/review-monitoring.css'));
        $dashboardCss = file_get_contents(resource_path('css/dashboard.css'));

        $this->assertIsString($css);
        $this->assertIsString($dashboardCss);
        $this->assertMatchesRegularExpression(
            '/\.review-monitoring-table\s*\{[^}]*min-width:\s*1220px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.review-monitoring-adviser-table\s*\{[^}]*min-width:\s*980px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.dashboard-overflow-region\s*\{[^}]*overflow-x:\s*auto;/s',
            $dashboardCss,
        );
        $this->assertMatchesRegularExpression(
            '/@container \(max-width:\s*620px\)\s*\{.*?\.review-monitoring-adviser-filters[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\);/s',
            $css,
        );
    }

    private function createAssignment(
        ResearchApplication $application,
        User $reviewer,
        ReviewerAssignmentStatus $status,
        int $sequence,
        CarbonInterface $deadline,
    ): ReviewerAssignment {
        return ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'review_cycle' => 0,
            'assignment_sequence' => $sequence,
            'assignment_status' => $status,
            'review_deadline_at' => $deadline,
            'submitted_at' => $status === ReviewerAssignmentStatus::DecisionSubmitted ? now()->subDay() : null,
        ]);
    }
}
