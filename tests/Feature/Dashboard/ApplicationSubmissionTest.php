<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\DeadlineManualStatus;
use App\Enums\RequirementStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use App\Support\DocumentTypeIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_draft_appears_on_dashboard_without_becoming_formally_submitted(): void
    {
        // Arrange one applicant-owned private draft.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Private Draft Research Title',
        ]);

        // Act on both the dashboard and authorized application-detail routes.
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Private Draft Research Title')
            ->assertSee('Not submitted')
            ->assertSee('Draft');

        // Assert the draft remains editable and exposes the dedicated submission workspace.
        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertSee('Continue Document Submission');
        $this->assertNull($application->submitted_at);
    }

    public function test_missing_pending_and_rejected_requirements_block_submission(): void
    {
        // Arrange a complete information record, eligible Adviser, open window, and incomplete mandatory files.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
        ]);
        $protocol = $this->requirement('PROTOCOL', 'Research Protocol');
        $consent = $this->requirement('CONSENT', 'Informed Consent');
        $this->document($application, $protocol, $applicant, RequirementStatus::Pending);
        $this->document($application, $consent, $applicant, RequirementStatus::Rejected);
        $this->openSubmissionWindow();

        // Act by attempting the dedicated final-submission route.
        $this->actingAs($applicant)
            ->from(route('applicant.applications.show', $application))
            ->post(route('applicant.applications.submit', $application))
            ->assertRedirect(route('applicant.applications.show', $application))
            ->assertSessionHasErrors('requirements');

        // Assert no workflow or audit transition was persisted.
        $application->refresh();
        $this->assertSame(ApplicationStatus::Draft, $application->application_status);
        $this->assertNull($application->submitted_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'application.submitted']);
    }

    public function test_all_active_requirements_complete_submits_once_and_records_audit(): void
    {
        // Arrange a submission-ready application with one inactive requirement that must be ignored.
        Notification::fake();
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'draft_owner_user_id' => $applicant,
            'research_title' => 'Submission Ready Research',
        ]);
        $protocol = $this->requirement('PROTOCOL', 'Research Protocol');
        $consent = $this->requirement('CONSENT', 'Informed Consent');
        $inactive = $this->requirement('OLD', 'Retired Requirement', false);

        $this->document($application, $protocol, $applicant, RequirementStatus::Completed, 'application/pdf');
        $this->document($application, $consent, $applicant, RequirementStatus::Completed, 'image/png');
        $this->document($application, $inactive, $applicant, RequirementStatus::Rejected);
        $this->openSubmissionWindow();

        // Act twice to verify that a repeated owner request is a successful no-op.
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertRedirect(route('applicant.applications.show', $application));
        $submittedAt = $application->fresh()->submitted_at;
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertRedirect(route('applicant.applications.show', $application));

        // Assert one authoritative transition, one Adviser notification, and no retained draft slot.
        $application->refresh();
        $this->assertSame(ApplicationStatus::SubmittedToAdviser, $application->application_status);
        $this->assertTrue($application->submitted_at->equalTo($submittedAt));
        $this->assertNull($application->draft_owner_user_id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $applicant->id,
            'action' => 'application.submitted',
            'subject_id' => $application->id,
        ]);
        $this->assertDatabaseCount('audit_logs', 2);
        Notification::assertSentToTimes($adviser, DashboardUpdateNotification::class, 1);

        // Assert the accepted application becomes visible on the applicant dashboard.
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Submission Ready Research');
    }

    public function test_another_applicant_cannot_submit_and_denial_is_audited(): void
    {
        // Arrange an application and a different authenticated applicant.
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create(['applicant_user_id' => $owner]);

        // Act through the protected final-submission route.
        $this->actingAs($other)
            ->post(route('applicant.applications.submit', $application))
            ->assertForbidden();

        // Assert the authorization denial remains auditable.
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $other->id,
            'action' => 'auth.authorization_denied',
        ]);
    }

    public function test_closed_submission_window_preserves_a_complete_draft(): void
    {
        // Arrange a complete information record and mandatory document after the configured deadline.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'draft_owner_user_id' => $applicant,
        ]);
        $requirement = $this->requirement('PROTOCOL', 'Research Protocol');
        $this->document($application, $requirement, $applicant, RequirementStatus::Completed);
        DeadlineConfiguration::create([
            'deadline_key' => 'closed-application-submission',
            'title' => 'Closed application submission',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDays(4),
            'due_at' => now()->subDay(),
            'priority' => 10,
            'is_active' => true,
        ]);

        // Act at the protected final boundary and assert the server rejects the stale browser action.
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertSessionHasErrors('submission_window');

        // Assert the draft slot, file metadata, and status remain intact.
        $application->refresh();
        $this->assertSame(ApplicationStatus::Draft, $application->application_status);
        $this->assertSame($applicant->id, $application->draft_owner_user_id);
        $this->assertNull($application->submitted_at);
        $this->assertSame(1, $application->documents()->count());
    }

    public function test_res_manual_open_and_closed_states_override_submission_dates(): void
    {
        // Arrange one complete application after its configured date and explicitly force the process open.
        $firstApplicant = User::factory()->create(['role' => UserRole::Applicant]);
        $firstAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $firstApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $firstApplicant,
            'adviser_user_id' => $firstAdviser,
            'draft_owner_user_id' => $firstApplicant,
        ]);
        $firstRequirement = $this->requirement('MANUAL-OPEN', 'Manual Open Protocol');
        $this->document($firstApplication, $firstRequirement, $firstApplicant, RequirementStatus::Completed);
        $deadline = DeadlineConfiguration::create([
            'deadline_key' => 'application-submission',
            'title' => 'Application Submission Deadline',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDays(10),
            'due_at' => now()->subDay(),
            'manual_status' => DeadlineManualStatus::Open,
            'priority' => 100,
            'is_active' => true,
        ]);

        // Assert the explicit RES open state permits the otherwise expired complete submission.
        $this->actingAs($firstApplicant)
            ->post(route('applicant.applications.submit', $firstApplication))
            ->assertRedirect(route('applicant.applications.show', $firstApplication));
        $this->assertSame(ApplicationStatus::SubmittedToAdviser, $firstApplication->fresh()->application_status);

        // Arrange another complete application inside the date range and explicitly close the process.
        $secondApplicant = User::factory()->create(['role' => UserRole::Applicant]);
        $secondAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $secondApplication = ResearchApplication::factory()->create([
            'applicant_user_id' => $secondApplicant,
            'adviser_user_id' => $secondAdviser,
            'draft_owner_user_id' => $secondApplicant,
        ]);
        $secondRequirement = $this->requirement('MANUAL-CLOSED', 'Manual Closed Protocol');
        $this->document($secondApplication, $secondRequirement, $secondApplicant, RequirementStatus::Completed);
        $deadline->update([
            'starts_at' => now()->subDay(),
            'due_at' => now()->addWeek(),
            'manual_status' => DeadlineManualStatus::Closed,
        ]);

        // Assert manual closure remains authoritative while the configured date range is otherwise open.
        $this->actingAs($secondApplicant)
            ->post(route('applicant.applications.submit', $secondApplication))
            ->assertSessionHasErrors('submission_window');
        $this->assertSame(ApplicationStatus::Draft, $secondApplication->fresh()->application_status);
    }

    public function test_missing_information_and_ineligible_adviser_are_revalidated_at_submission(): void
    {
        // Arrange an open window, one complete requirement, and incomplete persisted application information.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'abstract' => null,
        ]);
        $requirement = $this->requirement('PROTOCOL', 'Research Protocol');
        $this->document($application, $requirement, $applicant, RequirementStatus::Completed);
        $this->openSubmissionWindow();

        // Act and assert missing persisted information blocks submission independently of browser validation.
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertSessionHasErrors('abstract');

        // Repair information, invalidate the assigned Adviser, and assert Adviser eligibility is checked again.
        $application->update(['abstract' => 'A complete abstract now exists.']);
        $adviser->update(['account_status' => 'inactive']);
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertSessionHasErrors('adviser_user_id');
        $this->assertNull($application->fresh()->submitted_at);
    }

    public function test_submission_checklist_uses_persisted_information_readiness(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'draft_owner_user_id' => $applicant,
            'abstract' => null,
        ]);
        $requirement = $this->requirement('CHECKLIST', 'Checklist Protocol');
        $this->document($application, $requirement, $applicant, RequirementStatus::Completed);
        $this->openSubmissionWindow();

        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertViewHas('informationSummary', fn (array $summary): bool => ! $summary['complete']
                && in_array('abstract', $summary['invalid_fields'], true))
            ->assertSee('All required application information is complete.')
            ->assertSee('disabled', false);

        $application->update(['abstract' => 'The completed persisted abstract.']);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertViewHas('informationSummary', fn (array $summary): bool => $summary['complete']);
    }

    public function test_requirement_completion_uses_only_active_items_for_selected_research_type(): void
    {
        // Arrange one completed Thesis requirement and one missing Capstone-only requirement.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'research_type' => ResearchType::Thesis,
        ]);
        $thesis = $this->requirement('THESIS', 'Thesis Protocol');
        $thesis->update(['research_types' => [ResearchType::Thesis->value]]);
        $capstone = $this->requirement('CAPSTONE', 'Capstone Protocol');
        $capstone->update(['research_types' => [ResearchType::Capstone->value]]);
        $this->document($application, $thesis, $applicant, RequirementStatus::Completed);

        // Act on the dashboard and requirement workspace that share one completion service.
        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('1 of 1 mandatory completed')
            ->assertSee('Thesis Protocol')
            ->assertDontSee('Capstone Protocol');
        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('1 of 1 mandatory requirements completed')
            ->assertDontSee('Capstone Protocol');
    }

    public function test_document_icons_come_from_stored_mime_type(): void
    {
        // Assert document presentation derives from trusted MIME metadata, not filenames.
        $this->assertSame('file-pdf', DocumentTypeIcon::fromMimeType('application/pdf'));
        $this->assertSame('file-word', DocumentTypeIcon::fromMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('image', DocumentTypeIcon::fromMimeType('image/jpeg'));
        $this->assertSame('file-spreadsheet', DocumentTypeIcon::fromMimeType('text/csv'));
        $this->assertSame('file', DocumentTypeIcon::fromMimeType('application/octet-stream'));
    }

    private function requirement(string $code, string $name, bool $active = true): DocumentRequirement
    {
        // Create one mandatory requirement for a focused submission fixture.
        return DocumentRequirement::create([
            'code' => $code,
            'name' => $name,
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => $active,
        ]);
    }

    private function document(
        ResearchApplication $application,
        DocumentRequirement $requirement,
        User $uploader,
        RequirementStatus $status,
        string $mimeType = 'application/pdf',
    ): ApplicationDocument {
        // Create one current private-document metadata row without requiring a physical file.
        return ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $uploader->id,
            'original_file_name' => strtolower($requirement->code).'.pdf',
            'stored_file_path' => 'applications/private/'.$application->id.'/'.strtolower($requirement->code).'.pdf',
            'mime_type' => $mimeType,
            'file_size_bytes' => 1024,
            'document_version' => 1,
            'validation_status' => $status,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
    }

    private function openSubmissionWindow(): DeadlineConfiguration
    {
        // Configure an active applicant window around the current test clock.
        return DeadlineConfiguration::create([
            'deadline_key' => 'test-application-submission',
            'title' => 'Application submission deadline',
            'audience_role' => UserRole::Applicant,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
        ]);
    }
}
