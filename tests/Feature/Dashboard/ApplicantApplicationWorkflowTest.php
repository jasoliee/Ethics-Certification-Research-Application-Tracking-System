<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_start_submissions_create_one_editable_draft_and_audit_creation_once(): void
    {
        // Arrange an eligible Student applicant and active Research Adviser.
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $payload = $this->applicationPayload($adviser);

        // Act twice through the same create endpoint to model repeated Start Application actions.
        $first = $this->actingAs($applicant)->post(route('applicant.applications.store'), $payload);
        $application = ResearchApplication::where('applicant_user_id', $applicant->id)->firstOrFail();
        $first->assertRedirect(route('applicant.applications.requirements', $application));
        $this->actingAs($applicant)
            ->post(route('applicant.applications.store'), [
                ...$payload,
                'research_title' => 'Updated through the same draft slot',
            ])
            ->assertRedirect(route('applicant.applications.requirements', $application));

        // Assert one database-enforced draft slot was reused and only draft creation was single-shot.
        $this->assertSame(1, ResearchApplication::where('applicant_user_id', $applicant->id)->count());
        $application->refresh();
        $this->assertSame($applicant->id, $application->draft_owner_user_id);
        $this->assertSame('Updated through the same draft slot', $application->research_title);
        $this->assertSame(ApplicationStage::DocumentSubmission, $application->current_stage);
        $this->assertSame(1, $this->auditCount('application.draft_created', $application));
        $this->assertSame(2, $this->auditCount('application.information_updated', $application));

        // Assert the applicant list renders its complete table inside the shared horizontal overflow region.
        $this->actingAs($applicant)
            ->get(route('applicant.applications.index'))
            ->assertOk()
            ->assertSee('dashboard-overflow-region', false);
    }

    public function test_information_validation_preserves_student_and_faculty_differences_and_adviser_eligibility(): void
    {
        // Arrange Student and Faculty applicants plus one eligible and one ineligible Adviser record.
        $student = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ]);
        $faculty = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Faculty,
        ]);
        $eligibleAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $inactiveAdviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'account_status' => 'inactive',
        ]);

        // Act as a Student without a program and with an inactive Adviser.
        $this->actingAs($student)
            ->from(route('applicant.applications.create'))
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($inactiveAdviser),
                'program' => null,
            ])
            ->assertRedirect(route('applicant.applications.create'))
            ->assertSessionHasErrors(['program', 'adviser_user_id'])
            ->assertSessionHasInput('research_title', 'Community Data Privacy Study');

        // Act as Faculty with no program and assert the optional field is accepted.
        $this->actingAs($faculty)
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($eligibleAdviser),
                'program' => null,
            ])
            ->assertRedirect();
        $facultyApplication = ResearchApplication::where('applicant_user_id', $faculty->id)->firstOrFail();
        $this->assertNull($facultyApplication->program);
        $this->assertSame(ApplicantType::Faculty->value, $facultyApplication->applicant_type);
    }

    public function test_applicant_can_update_only_their_own_eligible_draft(): void
    {
        // Arrange an owned draft, another applicant, and an active Adviser.
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $other = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $owner,
            'draft_owner_user_id' => $owner,
            'adviser_user_id' => $adviser,
        ]);

        // Act as the non-owner and assert policy authorization fails before mutation.
        $this->actingAs($other)
            ->put(route('applicant.applications.update', $application), $this->applicationPayload($adviser))
            ->assertForbidden();
        $this->assertNotSame('Owner Revised Title', $application->fresh()->research_title);

        // Act as the owner and assert the same route updates the existing draft in place.
        $this->actingAs($owner)
            ->put(route('applicant.applications.update', $application), [
                ...$this->applicationPayload($adviser),
                'research_title' => 'Owner Revised Title',
            ])
            ->assertRedirect(route('applicant.applications.requirements', $application));
        $this->assertSame('Owner Revised Title', $application->fresh()->research_title);
    }

    public function test_create_prefers_the_unique_draft_and_returned_record_cannot_replace_it(): void
    {
        // Arrange an older unique draft and a newer returned record for the same applicant.
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);
        $draft = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::Draft,
            'updated_at' => now()->subDay(),
        ]);
        $returned = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => null,
            'adviser_user_id' => $adviser,
            'application_status' => ApplicationStatus::ReturnedByAdviser,
            'updated_at' => now(),
        ]);

        // Act through the create page and assert it resumes the database-enforced draft slot.
        $this->actingAs($applicant)
            ->get(route('applicant.applications.create'))
            ->assertOk()
            ->assertViewHas('application', fn (ResearchApplication $selected): bool => $selected->is($draft));

        // Act through the returned record's direct edit route and assert a friendly validation boundary.
        $this->actingAs($applicant)
            ->from(route('applicant.applications.edit', $returned))
            ->put(route('applicant.applications.update', $returned), $this->applicationPayload($adviser))
            ->assertRedirect(route('applicant.applications.edit', $returned))
            ->assertSessionHasErrors('application');

        // Assert neither record was displaced or silently repurposed.
        $this->assertSame($applicant->id, $draft->fresh()->draft_owner_user_id);
        $this->assertNull($returned->fresh()->draft_owner_user_id);
        $this->assertSame(ApplicationStatus::ReturnedByAdviser, $returned->fresh()->application_status);
    }

    public function test_private_document_upload_replacement_and_validation_preserve_version_history(): void
    {
        // Arrange an editable owned draft and one active Thesis requirement on a fake private disk.
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $requirement = $this->requirement();

        // Act by uploading and replacing the same requirement with accepted PDF content.
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('proposal.pdf', 20, 'application/pdf')],
        )->assertRedirect();
        $first = ApplicationDocument::firstOrFail();
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('proposal-revised.pdf', 24, 'application/pdf')],
        )->assertRedirect();

        // Assert both private files and metadata versions remain while only the latest is current.
        $documents = ApplicationDocument::orderBy('document_version')->get();
        $this->assertCount(2, $documents);
        $this->assertFalse($documents[0]->is_current);
        $this->assertTrue($documents[1]->is_current);
        $this->assertSame(2, $documents[1]->document_version);
        $this->assertSame(RequirementStatus::Completed, $documents[1]->validation_status);
        Storage::disk('local')->assertExists($documents[0]->stored_file_path);
        Storage::disk('local')->assertExists($documents[1]->stored_file_path);
        $this->assertStringNotContainsString('proposal-revised.pdf', $documents[1]->stored_file_path);
        $this->assertSame(1, $this->auditCount('application.requirement_uploaded', $documents[0]));
        $this->assertSame(1, $this->auditCount('application.requirement_replaced', $documents[1]));

        // Act with an executable extension and assert validation prevents a third stored version.
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('payload.php', 1, 'text/x-php')],
        )->assertSessionHasErrors('document');

        // Act with an oversized otherwise valid PDF and assert the request limit rejects it before storage.
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('oversized.pdf', 10241, 'application/pdf')],
        )->assertSessionHasErrors('document');

        // Act against an active requirement configured only for the other research type.
        $capstoneRequirement = DocumentRequirement::create([
            'code' => 'CAPSTONE-ONLY',
            'name' => 'Capstone-only Requirement',
            'description' => 'Applies only to Capstone applications.',
            'is_mandatory' => true,
            'research_types' => [ResearchType::Capstone->value],
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $capstoneRequirement]),
            ['document' => UploadedFile::fake()->create('wrong-category.pdf', 4, 'application/pdf')],
        )->assertSessionHasErrors('document');

        // Assert rejected type, size, and category attempts never created a third document version.
        $this->assertSame(2, ApplicationDocument::count());
    }

    public function test_document_routes_enforce_owner_and_formal_assigned_adviser_access(): void
    {
        // Arrange one private PDF owned by an applicant and two Research Advisers.
        Storage::fake('local');
        $owner = User::factory()->create(['role' => UserRole::Applicant]);
        $otherApplicant = User::factory()->create(['role' => UserRole::Applicant]);
        $assignedAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $otherAdviser = User::factory()->create(['role' => UserRole::Adviser]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $owner,
            'draft_owner_user_id' => $owner,
            'adviser_user_id' => $assignedAdviser,
        ]);
        $requirement = $this->requirement();
        $this->actingAs($owner)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('private.pdf', 8, 'application/pdf')],
        )->assertRedirect();
        $document = ApplicationDocument::firstOrFail();

        // Act and assert the owner receives a protected inline response with defensive headers.
        $previewResponse = $this->actingAs($owner)
            ->get(route('applicant.applications.documents.preview', [$application, $document]))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $cacheControl = (string) $previewResponse->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);

        // Act and assert another applicant and the assigned Adviser cannot inspect a draft document.
        $this->actingAs($otherApplicant)
            ->get(route('applicant.applications.documents.download', [$application, $document]))
            ->assertForbidden();
        $this->actingAs($assignedAdviser)
            ->get(route('adviser.applications.documents.download', [$application, $document]))
            ->assertForbidden();

        // Cross the formal boundary and assert only the assigned Adviser gains secure access.
        $application->update([
            'application_status' => ApplicationStatus::SubmittedToAdviser,
            'current_stage' => ApplicationStage::AdviserReview,
            'draft_owner_user_id' => null,
            'submitted_at' => now(),
        ]);
        $this->actingAs($assignedAdviser)
            ->get(route('adviser.applications.documents.download', [$application, $document]))
            ->assertOk()
            ->assertDownload('private.pdf');
        $this->actingAs($otherAdviser)
            ->get(route('adviser.applications.documents.download', [$application, $document]))
            ->assertForbidden();
    }

    /**
     * Return one valid information payload using migration-backed dropdown values.
     *
     * @return array<string, mixed>
     */
    private function applicationPayload(User $adviser): array
    {
        return [
            'research_title' => 'Community Data Privacy Study',
            'research_type' => ResearchType::Thesis->value,
            'research_category' => 'Social and Behavioral Research',
            'institution' => 'Institute of Computing and Digital Innovation',
            'department' => 'Computer Studies',
            'program' => 'Bachelor of Science in Computer Science',
            'adviser_user_id' => $adviser->id,
            'abstract' => 'This study examines privacy expectations in community-facing digital research.',
            'target_participants' => 'Adult KLD students who provide informed consent.',
            'expected_duration' => 'August 2026 to May 2027',
        ];
    }

    private function requirement(): DocumentRequirement
    {
        // Create one active mandatory requirement applicable to both approved research types.
        return DocumentRequirement::create([
            'code' => 'TEST-PROPOSAL',
            'name' => 'Research Proposal',
            'description' => 'Complete proposal',
            'is_mandatory' => true,
            'research_types' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function auditCount(string $action, ResearchApplication|ApplicationDocument $subject): int
    {
        // Count one specific subject/action pair without depending on unrelated authorization audits.
        return AuditLog::query()
            ->where('action', $action)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->id)
            ->count();
    }
}
