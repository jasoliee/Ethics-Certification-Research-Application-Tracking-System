<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewDecision;
use App\Enums\UserRole;
use App\Models\ApplicationDecisionRelease;
use App\Models\ResearchApplication;
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
                'relevant' => 2,
                'released' => 0,
                'pending_final_approval' => 1,
                'survey_required' => 0,
            ])
            ->assertSee('Certificate Processing')
            ->assertSee('Relevant Applications')
            ->assertSee('Certification Queue')
            ->assertSee('Final Review')
            ->assertSee('Manage Certificate Background')
            ->assertSee('Release All Eligible')
            ->assertSee('data-certificate-bulk-dialog', false)
            ->assertSee('data-certificate-background-dialog', false)
            ->assertSee('data-certificate-application-dialog="'.$eligible->id.'"', false)
            ->assertSee('data-certificate-application-dialog="'.$pending->id.'"', false)
            ->assertSee(route('res.certificates.release', $eligible), false)
            ->assertSee(route('res.certificates.decisions.release', $pending), false)
            ->assertSee(route('res.certificate-backgrounds.store'), false)
            ->assertSee($eligible->research_title)
            ->assertSee($pending->research_title);
    }

    public function test_queue_filters_still_scope_the_table_while_metrics_remain_global(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $eligible = $this->application('FILTER-ELIGIBLE', ApplicationStatus::ResultReleasedAccepted, $resLead);
        $pending = $this->application('FILTER-PENDING', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);

        $response = $this->actingAs($resLead)->get(route('res.certificates.index', ['state' => 'eligible']));

        $response->assertOk()
            ->assertViewHas('queueMetrics', fn (array $metrics): bool => $metrics['relevant'] === 2)
            ->assertSee($eligible->research_title)
            ->assertDontSee($pending->research_title)
            ->assertSee('(filtered)');
    }

    public function test_decision_service_validation_reopens_the_correct_application_dialog(): void
    {
        Storage::fake('local');
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $pending = $this->application('REOPEN', ApplicationStatus::ReviewSubmittedPendingRelease, $resLead);

        $response = $this->actingAs($resLead)
            ->from(route('res.certificates.index'))
            ->post(route('res.certificates.decisions.release', $pending), [
                'application_id' => $pending->id,
                'decision' => ReviewDecision::Approved->value,
            ]);

        $response->assertRedirect(route('res.certificates.index'));
        $this->followRedirects($response)
            ->assertOk()
            ->assertSee('data-certificate-application-dialog="'.$pending->id.'"', false)
            ->assertSee('data-open-on-load', false)
            ->assertSee('Every required Reviewer must submit this review cycle');
    }

    public function test_non_res_user_cannot_open_certificate_processing(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);

        $this->actingAs($applicant)
            ->get(route('res.certificates.index'))
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
