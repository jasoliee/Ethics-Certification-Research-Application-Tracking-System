<?php

namespace Tests\Feature\Settings;

use App\Enums\RequirementStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDocument;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResLeadRequirementsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_res_settings_show_the_required_tab_order_and_active_term_lock_state(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->activeTerm();
        $this->requirement('RESEARCH-PROPOSAL', 'Research Proposal');

        $response = $this->actingAs($resLead)
            ->get(route('res.settings.index', ['tab' => 'requirements']))
            ->assertOk()
            ->assertSee('Requirements Configuration')
            ->assertSee('Structural changes are locked for 1st Semester, A.Y. 2026-2027.')
            ->assertSee('Research Proposal')
            ->assertSee(route('res.settings.requirements.store'), false);
        $html = $response->getContent();
        $labels = [
            'Profile',
            'Requirements Configuration',
            'Deadline Configuration',
            'Dropdown Options',
            'Background Management',
            'Certificate Configuration',
            'Security and Privacy',
        ];
        $positions = array_map(fn (string $label): int|false => strpos($html, '<span>'.$label.'</span>'), $labels);

        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
        $this->assertMatchesRegularExpression('/<fieldset[^>]*disabled/s', $html);
        $this->assertMatchesRegularExpression('/class="settings-requirement-delete"[^>]*disabled/s', $html);
    }

    public function test_res_can_edit_requirement_text_during_an_active_term_and_applicant_views_receive_it(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $this->activeTerm();
        $requirement = $this->requirement('RESEARCH-PROPOSAL', 'Research Proposal');

        $this->actingAs($resLead)
            ->put(route('res.settings.requirements.update', $requirement), [
                'name' => 'Complete Research Protocol',
                'description' => 'Upload the final protocol specification selected by the RES Lead.',
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'requirements']))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('document_requirements', [
            'id' => $requirement->id,
            'name' => 'Complete Research Protocol',
            'description' => 'Upload the final protocol specification selected by the RES Lead.',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'settings.document_requirement_updated',
            'subject_id' => $requirement->id,
        ]);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('Complete Research Protocol')
            ->assertSee('Upload the final protocol specification selected by the RES Lead.')
            ->assertDontSee('>Research Proposal<', false);
    }

    public function test_active_term_blocks_requirement_addition_and_deletion_on_the_server(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->activeTerm();
        $requirement = $this->requirement('RESEARCH-PROPOSAL', 'Research Proposal');

        $this->actingAs($resLead)
            ->post(route('res.settings.requirements.store'), [
                'name' => 'Data Management Plan',
                'description' => 'Required data-handling specification.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['requirements'], null, 'requirementConfiguration');
        $this->actingAs($resLead)
            ->delete(route('res.settings.requirements.destroy', $requirement))
            ->assertRedirect()
            ->assertSessionHasErrors(['requirements'], null, 'requirementConfiguration');

        $this->assertSame(1, DocumentRequirement::query()->count());
        $this->assertTrue($requirement->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.document_requirement_deactivated']);
    }

    public function test_structural_changes_between_terms_propagate_without_deleting_historical_documents(): void
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        AcademicTerm::create([
            'semester' => 'Next Semester',
            'academic_year' => '2027-2028',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addMonths(5),
            'is_active' => true,
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $this->requirement('req-data-privacy-protocol', 'Legacy Privacy Protocol');

        $this->actingAs($resLead)
            ->post(route('res.settings.requirements.store'), [
                'name' => 'Data Privacy Protocol',
                'description' => 'Explain participant-data safeguards.',
            ])
            ->assertRedirect(route('res.settings.index', ['tab' => 'requirements']))
            ->assertSessionDoesntHaveErrors();
        $requirement = DocumentRequirement::query()->where('name', 'Data Privacy Protocol')->firstOrFail();
        $this->assertSame('REQ-DATA-PRIVACY-PROTOCOL-2', $requirement->code);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('Data Privacy Protocol');
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'privacy-protocol.pdf',
            'stored_file_path' => 'applications/test/privacy-protocol.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 100,
            'file_sha256' => str_repeat('a', 64),
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);

        $this->actingAs($resLead)
            ->delete(route('res.settings.requirements.destroy', $requirement))
            ->assertRedirect(route('res.settings.index', ['tab' => 'requirements']))
            ->assertSessionDoesntHaveErrors();

        $this->assertFalse($requirement->fresh()->is_active);
        $this->assertDatabaseHas('application_documents', ['id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $resLead->id,
            'action' => 'settings.document_requirement_deactivated',
            'subject_id' => $requirement->id,
        ]);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertDontSee('Data Privacy Protocol');
        $this->actingAs($resLead)
            ->put(route('res.settings.requirements.update', $requirement), [
                'name' => 'Attempted Restore by Editing',
                'description' => 'Inactive requirements are immutable through the active catalogue.',
            ])
            ->assertNotFound();
        $this->assertSame('Data Privacy Protocol', $requirement->fresh()->name);
    }

    private function activeTerm(): AcademicTerm
    {
        return AcademicTerm::create([
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonths(4),
            'is_active' => true,
        ]);
    }

    private function requirement(string $code, string $name): DocumentRequirement
    {
        return DocumentRequirement::create([
            'code' => $code,
            'name' => $name,
            'description' => 'Baseline requirement specification.',
            'is_mandatory' => true,
            'research_types' => null,
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }
}
