<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\BulkReleaseType;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Services\Applications\ReviewConsensusService;
use App\Services\Certificates\BulkReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResCertificateProcessingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_queue_uses_summary_table_and_focused_dialogs_without_losing_workflow_routes(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $eligible = $this->application('ELIGIBLE', ApplicationStatus::ResultReleasedAccepted, $resLead);
        $pending = $this->application('PENDING', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);
        $failed = $this->application('FAILED-FIRST', ApplicationStatus::Failed, $resLead);
        $failed->update(['status_updated_at' => now()->subYear()]);
        $released = $this->application('ALREADY-RELEASED', ApplicationStatus::CertificateReleased, $resLead);

        $response = $this->actingAs($resLead)->get(route('res.certificates.index'));

        $response->assertOk()
            ->assertViewHas('queueMetrics', [
                'pending_decision_release' => 1,
                'pending_certificate_release' => 1,
                'final_revision_failed' => 1,
            ])
            ->assertSee('Decision &amp; Certificates', false)
            ->assertSee('Pending Decision Release')
            ->assertSee('Pending Certificate Release')
            ->assertSee('Failed After Final Revision')
            ->assertSeeInOrder([$failed->application_code, $pending->application_code])
            ->assertSee('is-final-review-failed', false)
            ->assertSee('Decision &amp; Certificate Queue', false)
            ->assertSeeInOrder(['Status', 'Decision', 'Claim', 'Last Updated', 'Action'])
            ->assertDontSee('Manage Certificate Background')
            ->assertSee('Release All')
            ->assertSeeInOrder(['Certificate', 'Decision', 'Both Certificate and Decision'])
            ->assertSee('name="release_type" value="certificate"', false)
            ->assertSee('name="release_type" value="decision"', false)
            ->assertSee('name="release_type" value="both"', false)
            ->assertSee('data-certificate-bulk-dialog', false)
            ->assertDontSee('data-certificate-background-dialog', false)
            ->assertSee('data-certificate-application-dialog="'.$eligible->id.'"', false)
            ->assertSee('data-certificate-application-dialog="'.$pending->id.'"', false)
            ->assertSee('data-certificate-row-number="1"', false)
            ->assertSee('data-certificate-row-number="2"', false)
            ->assertDontSee('certificate-row-state', false)
            ->assertSee(route('res.certificates.release', $eligible), false)
            ->assertSee(route('res.certificates.workspace', $pending), false)
            ->assertDontSee(route('res.certificates.decisions.release', $pending), false)
            ->assertDontSee('Documents requiring revision')
            ->assertDontSee('Official released decision')
            ->assertDontSee($eligible->applicant->name)
            ->assertDontSee($pending->applicant->name)
            ->assertSee($eligible->research_title)
            ->assertSee($pending->research_title)
            ->assertDontSee($released->research_title)
            ->assertDontSee('data-certificate-application-dialog="'.$released->id.'"', false);

        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $this->assertMatchesRegularExpression(
            '/\.certificate-metric-strip\s*\{[^}]*grid-template-columns:\s*repeat\(3,\s*minmax\(0,\s*1fr\)\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.certificate-metric-strip\s+article\s*\{[^}]*justify-content:\s*center;/s',
            $css,
        );
    }

    public function test_queue_filters_still_scope_the_table_while_metrics_remain_global(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $eligible = $this->application('FILTER-ELIGIBLE', ApplicationStatus::ResultReleasedAccepted, $resLead);
        $pending = $this->application('FILTER-PENDING', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);

        $response = $this->actingAs($resLead)->get(route('res.certificates.index', [
            'status' => ApplicationStatus::ResultReleasedAccepted->value,
        ]));

        $response->assertOk()
            ->assertViewHas('queueMetrics', fn (array $metrics): bool => array_sum($metrics) === 2)
            ->assertSee($eligible->research_title)
            ->assertDontSee($pending->research_title)
            ->assertSee('(filtered)');
    }

    public function test_queue_numbers_continue_across_pagination(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);

        foreach (range(1, 16) as $number) {
            $this->application('NUMBER-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT), ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);
        }

        $this->actingAs($resLead)
            ->get(route('res.certificates.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('data-certificate-row-number="16"', false)
            ->assertDontSee('data-certificate-row-number="1"', false);
    }

    public function test_decision_release_ignores_forged_submission_identifiers_and_revalidates_application_consensus(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $pending = $this->application('REOPEN', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);

        $response = $this->actingAs($resLead)
            ->from(route('res.certificates.index'))
            ->post(route('res.certificates.decisions.release', $pending), [
                'application_id' => $pending->id,
                'review_submission_id' => 999999,
                'decision' => ReviewDecision::Approved->value,
            ]);

        $response
            ->assertRedirect(route('res.certificates.index'))
            ->assertSessionHasErrorsIn('decisionRelease', ['review_submission_id']);
        $this->assertDatabaseMissing('application_decision_releases', [
            'research_application_id' => $pending->id,
        ]);
    }

    public function test_bulk_decision_release_skips_final_revision_failures_but_releases_every_valid_application(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $failed = $this->application('FINAL-FAILED', ApplicationStatus::Failed, $resLead);
        $failed->update([
            'current_stage' => ApplicationStage::Completed,
            'current_revision_cycle' => 4,
            'review_consensus_cycle' => 3,
            'review_consensus_decision' => ReviewDecision::MajorRevision,
        ]);
        $valid = $this->application('VALID-DECISION', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);
        $valid->update([
            'review_type' => 'expedited',
            'current_revision_cycle' => 1,
        ]);
        $reviewer = User::factory()->reviewer(['Expedited'])->create();
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $valid->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'review_cycle' => 0,
            'assignment_status' => ReviewerAssignmentStatus::DecisionSubmitted,
            'submitted_at' => now(),
        ]);
        $assignment->reviewSubmission()->create([
            'status' => ReviewSubmissionStatus::Submitted,
            'decision' => ReviewDecision::Disapproved,
            'decision_comment' => 'This valid pending decision should still be released.',
            'submitted_at' => now(),
        ]);
        app(ReviewConsensusService::class)->evaluate($valid);

        $summary = app(BulkReleaseService::class)->release($resLead, BulkReleaseType::Decision);

        $this->assertSame(1, $summary['eligible']);
        $this->assertSame(1, $summary['successfully_released']);
        $this->assertSame(1, $summary['max_revision_failed']);
        $this->assertSame([$failed->application_code], $summary['max_revision_failed_application_codes']);
        $this->assertSame(ApplicationStatus::ResultReleasedDisapproved, $valid->refresh()->application_status);
        $this->assertSame(ApplicationStatus::Failed, $failed->refresh()->application_status);
        $this->assertDatabaseHas('application_decision_releases', [
            'research_application_id' => $valid->id,
            'decision' => ReviewDecision::Disapproved->value,
        ]);
        $this->assertDatabaseMissing('application_decision_releases', [
            'research_application_id' => $failed->id,
            'review_cycle' => 3,
        ]);

        $this->actingAs($resLead)
            ->withSession(['bulk_certificate_summary' => $summary])
            ->get(route('res.certificates.index'))
            ->assertOk()
            ->assertSee('Failed after final revision (skipped)')
            ->assertSee($failed->application_code)
            ->assertSee('These applications are already marked Failed.');
    }

    public function test_non_res_user_cannot_open_certificate_processing(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($applicant)
            ->get(route('res.certificates.index'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_res_workspace_is_read_only_and_cross_role_certificate_or_reviewer_actions_are_blocked(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $pending = $this->application('READ-ONLY', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $pending->id,
            'reviewer_user_id' => $reviewer->id,
            'review_cycle' => 0,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.certificates.workspace', $pending))
            ->assertOk()
            ->assertSee('Read-only Review Workspace')
            ->assertSee('RES read-only access')
            ->assertDontSee('Add Comment')
            ->assertDontSee('Open Review Worksheet')
            ->assertDontSee('Submit Review');

        $this->actingAs($resLead)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'category' => 'general',
                'body' => 'RES must not be allowed to write this Reviewer comment.',
            ])
            ->assertForbidden();

        $this->actingAs($applicant)
            ->get(route('res.certificates.workspace', $pending))
            ->assertRedirect(route('dashboard'));
        $this->actingAs($reviewer)
            ->post(route('res.certificates.release', $pending))
            ->assertRedirect(route('dashboard'));
    }

    private function application(string $suffix, ApplicationStatus $status, User $resLead): ResearchApplication
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $application = ResearchApplication::factory()->create([
            'application_code' => 'RES-2026-S-ICDI-08122026-'.$suffix,
            'applicant_user_id' => $applicant->id,
            'research_title' => 'Certification Queue Study '.$suffix,
            'application_status' => $status,
            'current_stage' => $status === ApplicationStatus::ReviewSubmittedPendingRelease
                ? ApplicationStage::EthicsReview
                : ApplicationStage::DecisionRelease,
            'submitted_at' => now()->subWeek(),
        ]);

        if ($status === ApplicationStatus::ResultReleasedAccepted) {
            ApplicationDecisionRelease::create([
                'research_application_id' => $application->id,
                'review_cycle' => 0,
                'source_review_type' => 'initial_review',
                'decision' => ReviewDecision::Approved,
                'released_by_user_id' => $resLead->id,
                'released_at' => now()->subDay(),
            ]);
        }

        return $application;
    }
}
