<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Support\DocumentTypeIcon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_dashboard_cards_remain_empty_until_server_accepts_submission(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Private Draft Research Title',
        ]);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No submitted application')
            ->assertSee('Application not submitted')
            ->assertDontSee('Private Draft Research Title');

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertSee('Submit Application');
    }

    public function test_missing_pending_and_rejected_requirements_block_submission(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create(['applicant_user_id' => $applicant]);
        $protocol = $this->requirement('PROTOCOL', 'Research Protocol');
        $consent = $this->requirement('CONSENT', 'Informed Consent');

        $this->document($application, $protocol, $applicant, RequirementStatus::Pending);
        $this->document($application, $consent, $applicant, RequirementStatus::Rejected);

        $this->actingAs($applicant)
            ->from(route('applicant.applications.show', $application))
            ->post(route('applicant.applications.submit', $application))
            ->assertRedirect(route('applicant.applications.show', $application))
            ->assertSessionHasErrors('requirements');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Draft, $application->application_status);
        $this->assertNull($application->submitted_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'application.submitted']);
    }

    public function test_all_active_requirements_complete_submits_once_and_records_audit(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Submission Ready Research',
        ]);
        $protocol = $this->requirement('PROTOCOL', 'Research Protocol');
        $consent = $this->requirement('CONSENT', 'Informed Consent');
        $inactive = $this->requirement('OLD', 'Retired Requirement', false);

        $this->document($application, $protocol, $applicant, RequirementStatus::Completed, 'application/pdf');
        $this->document($application, $consent, $applicant, RequirementStatus::Completed, 'image/png');
        $this->document($application, $inactive, $applicant, RequirementStatus::Rejected);

        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertRedirect(route('applicant.applications.show', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::SubmittedToAdviser, $application->application_status);
        $this->assertNotNull($application->submitted_at);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $applicant->id,
            'action' => 'application.submitted',
            'subject_id' => $application->id,
        ]);

        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertForbidden();

        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.authorization_denied']);

        $this->actingAs($applicant)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Submission Ready Research');
    }

    public function test_another_applicant_cannot_submit_and_denial_is_audited(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create(['applicant_user_id' => $owner]);

        $this->actingAs($other)
            ->post(route('applicant.applications.submit', $application))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $other->id,
            'action' => 'auth.authorization_denied',
        ]);
    }

    public function test_document_icons_come_from_stored_mime_type(): void
    {
        $this->assertSame('file-pdf', DocumentTypeIcon::fromMimeType('application/pdf'));
        $this->assertSame('file-word', DocumentTypeIcon::fromMimeType('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $this->assertSame('image', DocumentTypeIcon::fromMimeType('image/jpeg'));
        $this->assertSame('file-spreadsheet', DocumentTypeIcon::fromMimeType('text/csv'));
        $this->assertSame('file', DocumentTypeIcon::fromMimeType('application/octet-stream'));
    }

    private function requirement(string $code, string $name, bool $active = true): DocumentRequirement
    {
        return DocumentRequirement::create([
            'code' => $code,
            'name' => $name,
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
}
