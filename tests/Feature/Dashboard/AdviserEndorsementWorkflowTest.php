<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AdviserReturnReason;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\DeadlineManualStatus;
use App\Enums\EndorsementStatus;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\Endorsement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdviserEndorsementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_adviser_can_endorse_a_complete_initial_submission_once(): void
    {
        Notification::fake();
        [$applicant, $adviser, $application] = $this->completeSubmission();
        $activeResLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'account_status' => 'active',
        ]);
        $inactiveResLead = User::factory()->create([
            'role' => UserRole::ResLead,
            'account_status' => 'inactive',
        ]);
        $this->endorsementDeadline();

        $this->actingAs($adviser)
            ->get(route('adviser.applications.show', $application))
            ->assertOk()
            ->assertSee('Adviser Decision')
            ->assertSee('Endorse Application')
            ->assertSee('Return for Correction')
            ->assertSee('class="dashboard-danger-action"', false)
            ->assertDontSee('<span>Adviser Review</span>', false);

        $this->actingAs($adviser)
            ->post(route('adviser.applications.endorse', $application), [
                'endorsement_remarks' => 'Complete and ready for ethics screening.',
            ])
            ->assertRedirect(route('adviser.applications.show', $application))
            ->assertSessionHas('status');

        $application->refresh();
        $this->assertSame(ApplicationStatus::AdviserEndorsed, $application->application_status);
        $this->assertSame(ApplicationStage::ResScreening, $application->current_stage);
        $this->assertNull($application->draft_owner_user_id);
        $endorsement = Endorsement::firstOrFail();
        $this->assertSame(EndorsementStatus::Endorsed, $endorsement->endorsement_status);
        $this->assertNull($endorsement->return_reason);
        $this->assertNotNull($endorsement->endorsed_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $adviser->id,
            'action' => 'application.adviser_endorsed',
            'subject_id' => $application->id,
        ]);
        Notification::assertSentTo($applicant, DashboardUpdateNotification::class);
        Notification::assertSentTo(
            $activeResLead,
            DashboardUpdateNotification::class,
            function (DashboardUpdateNotification $notification) use ($activeResLead, $application): bool {
                $data = $notification->toDatabase($activeResLead);

                return $data['title'] === 'Application ready for REU screening'
                    && $data['route'] === 'res.applications.show'
                    && $data['route_parameters'] === ['researchApplication' => $application->id]
                    && ! str_contains($data['message'], $application->research_title);
            },
        );
        Notification::assertNotSentTo($inactiveResLead, DashboardUpdateNotification::class);

        // The status transition makes a repeated decision request fail authorization.
        $this->actingAs($adviser)
            ->post(route('adviser.applications.endorse', $application))
            ->assertForbidden();
        $this->assertSame(1, Endorsement::count());
    }

    public function test_assigned_adviser_can_return_with_required_reason_and_instructions(): void
    {
        Notification::fake();
        [$applicant, $adviser, $application] = $this->completeSubmission();
        $submittedAt = $application->submitted_at;
        $this->endorsementDeadline();

        $this->actingAs($adviser)
            ->post(route('adviser.applications.return', $application), [])
            ->assertSessionHasErrors(
                ['return_reason', 'endorsement_remarks'],
                null,
                'adviserReturn',
            );

        $this->actingAs($adviser)
            ->post(route('adviser.applications.return', $application), [
                'return_reason' => AdviserReturnReason::PaymentProofCorrection->value,
                'endorsement_remarks' => 'Replace the unreadable payment proof before resubmitting.',
            ])
            ->assertRedirect(route('adviser.applications.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::ReturnedByAdviser, $application->application_status);
        $this->assertSame(ApplicationStage::AdviserReview, $application->current_stage);
        $this->assertSame(1, $application->current_revision_cycle);
        $this->assertTrue($application->submitted_at->equalTo($submittedAt));
        $endorsement = Endorsement::firstOrFail();
        $this->assertSame(EndorsementStatus::Returned, $endorsement->endorsement_status);
        $this->assertSame(AdviserReturnReason::PaymentProofCorrection, $endorsement->return_reason);
        $this->assertNotNull($endorsement->returned_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $adviser->id,
            'action' => 'application.returned_by_adviser',
            'subject_id' => $application->id,
        ]);
        Notification::assertSentTo($applicant, DashboardUpdateNotification::class);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertSee('Payment proof needs correction')
            ->assertSee('Replace the unreadable payment proof before resubmitting.');
        $this->actingAs($applicant)
            ->get(route('applicant.applications.edit', $application))
            ->assertOk();
    }

    public function test_unassigned_incomplete_later_cycle_and_closed_period_decisions_are_blocked(): void
    {
        [, $adviser, $application] = $this->completeSubmission();
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $this->endorsementDeadline(DeadlineManualStatus::Open);

        $this->actingAs($otherAdviser)
            ->post(route('adviser.applications.endorse', $application))
            ->assertForbidden();

        $incomplete = ResearchApplication::factory()->create([
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::Incomplete,
            'submitted_at' => null,
        ]);
        $this->actingAs($adviser)
            ->post(route('adviser.applications.endorse', $incomplete))
            ->assertForbidden();

        $laterCycle = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'current_revision_cycle' => 2,
        ]);
        $this->actingAs($adviser)
            ->post(route('adviser.applications.endorse', $laterCycle))
            ->assertForbidden();

        DeadlineConfiguration::query()->update([
            'manual_status' => DeadlineManualStatus::Closed,
        ]);
        $this->actingAs($adviser)
            ->post(route('adviser.applications.endorse', $application))
            ->assertSessionHasErrors('deadline');

        $this->assertSame(ApplicationStatus::SubmittedToAdviser, $application->fresh()->application_status);
        $this->assertSame(0, Endorsement::count());
    }

    /**
     * @return array{0: User, 1: User, 2: ResearchApplication}
     */
    private function completeSubmission(): array
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => null,
            'current_revision_cycle' => 1,
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'ENDORSE-PROTOCOL',
            'name' => 'Complete Research Protocol',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'complete-protocol.pdf',
            'stored_file_path' => "applications/private/{$application->id}/complete-protocol.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 1024,
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);

        return [$applicant, $adviser, $application];
    }

    private function endorsementDeadline(
        ?DeadlineManualStatus $manualStatus = null,
    ): DeadlineConfiguration {
        return DeadlineConfiguration::create([
            'deadline_key' => 'test-adviser-endorsement',
            'title' => 'Endorsement Period',
            'audience_role' => UserRole::Adviser,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'manual_status' => $manualStatus,
            'priority' => 100,
            'is_active' => true,
        ]);
    }
}
