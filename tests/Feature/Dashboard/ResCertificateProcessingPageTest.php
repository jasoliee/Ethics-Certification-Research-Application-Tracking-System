<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
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

        $response = $this->actingAs($resLead)->get(route('res.certificates.index'));

        $response->assertOk()
            ->assertViewHas('queueMetrics', [
                'pending_decision_release' => 1,
                'pending_certificate_release' => 1,
                'certificates_released' => 0,
            ])
            ->assertSee('Decision &amp; Certificates', false)
            ->assertSee('Pending Decision Release')
            ->assertSee('Pending Certificate Release')
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
            ->assertSee($pending->research_title);
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
