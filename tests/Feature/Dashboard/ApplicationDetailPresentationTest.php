<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationDetailPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adviser_detail_combines_application_and_applicant_information_in_one_panel(): void
    {
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Combined Panel Applicant',
            'institutional_identifier' => 'KLD-COMB-001',
        ]);
        $application = ResearchApplication::factory()->submittedToAdviser($adviser)->create([
            'applicant_user_id' => $applicant,
            'research_title' => 'Cohesive Adviser Detail Study',
        ]);

        $response = $this->actingAs($adviser)
            ->get(route('adviser.applications.show', $application))
            ->assertOk()
            ->assertSee('data-application-combined-information', false)
            ->assertSeeInOrder([
                'Application and Applicant Information',
                'Application Information',
                'Applicant Information',
                'Combined Panel Applicant',
            ]);

        $this->assertSame(1, substr_count($response->getContent(), 'data-application-combined-information'));
        $this->assertSame(2, substr_count($response->getContent(), 'data-application-information-group'));
    }

    public function test_returned_applicant_detail_is_view_only_and_routes_edits_and_uploads_to_their_workspaces(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'draft_owner_user_id' => null,
            'application_status' => ApplicationStatus::ReturnedByAdviser,
            'submitted_at' => now()->subDay(),
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'RETURNED-PROTOCOL',
            'name' => 'Returned Research Protocol',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'returned-protocol.pdf',
            'stored_file_path' => "applications/{$application->id}/returned-protocol.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 24,
            'file_sha256' => hash('sha256', '%PDF-1.4 returned'),
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now()->subDay(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, '%PDF-1.4 returned');

        $response = $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertSee('data-application-combined-information', false)
            ->assertSeeInOrder(['Application Information', 'Edit Information', 'Requirements', 'Re-upload Documents'])
            ->assertSee(route('applicant.applications.edit', $application), false)
            ->assertSee(route('applicant.applications.requirements', $application), false)
            ->assertSee(route('applicant.applications.documents.preview', [$application, $document]), false)
            ->assertSee(route('applicant.applications.documents.download', [$application, $document]), false)
            ->assertDontSee('Continue Document Submission')
            ->assertDontSee('application-document-remove', false)
            ->assertDontSee('data-document-replace-file', false);

        $this->assertSame(1, substr_count($response->getContent(), 'Edit Information'));
    }
}
