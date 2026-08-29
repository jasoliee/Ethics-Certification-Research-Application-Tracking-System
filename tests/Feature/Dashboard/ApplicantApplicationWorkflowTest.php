<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicantType;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ProfileOptionField;
use App\Enums\RequirementStatus;
use App\Enums\ResearchType;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ProfileOption;
use App\Models\ResearchApplication;
use App\Models\User;
use App\Services\Identity\ProfileOptionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicantApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DeadlineConfiguration::create([
            'deadline_key' => 'workflow-application-submission',
            'title' => 'Application submission deadline',
            'audience_role' => UserRole::Applicant,
            'starts_at' => Carbon::parse('2020-01-01 00:00:00'),
            'due_at' => Carbon::parse('2035-01-01 00:00:00'),
            'priority' => 100,
            'is_active' => true,
        ]);
    }

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
            ->assertSee('application-heading-actions', false)
            ->assertSee('dashboard-overflow-region', false)
            ->assertSee('>View<', false)
            ->assertDontSee('>Edit<', false);
    }

    public function test_new_application_codes_use_approved_type_institution_date_and_unique_suffixes(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10 09:30:00'));

        try {
            $adviser = User::factory()->create(['role' => UserRole::Adviser]);
            $institutions = [
                'Institute of Behavioral Sciences' => 'IBS',
                'Institute of Computing and Digital Innovation' => 'ICDI',
                'Institute of Engineering' => 'IOE',
                'Institute of Foundational Studies' => 'IFS',
                'Institute of Governance and Development Studies' => 'IGDS',
                'Institute of Medical Laboratory Science' => 'IMLS',
                'Institute of Midwifery' => 'IOM',
                'Institute of Nursing' => 'ION',
                'Institute of Science and Mathematics' => 'ISM',
            ];

            foreach ($institutions as $institution => $acronym) {
                $applicant = User::factory()->create([
                    'role' => UserRole::Applicant,
                    'applicant_type' => ApplicantType::Student,
                ]);
                $this->actingAs($applicant)
                    ->post(route('applicant.applications.store'), [
                        ...$this->applicationPayload($adviser),
                        'institution' => $institution,
                    ])
                    ->assertRedirect();
                $code = ResearchApplication::where('applicant_user_id', $applicant->id)
                    ->value('application_code');
                $this->assertMatchesRegularExpression(
                    "/^RES-2026-S-{$acronym}-08102026-(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*\\d)[A-Z0-9]{6}$/",
                    $code,
                );
            }

            $faculty = User::factory()->create([
                'role' => UserRole::Applicant,
                'applicant_type' => ApplicantType::Faculty,
            ]);
            $this->actingAs($faculty)
                ->post(route('applicant.applications.store'), $this->applicationPayload($adviser))
                ->assertRedirect();
            $this->assertMatchesRegularExpression(
                '/^RES-2026-F-ICDI-08102026-(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*\d)[A-Z0-9]{6}$/',
                ResearchApplication::where('applicant_user_id', $faculty->id)->value('application_code'),
            );

            $codes = ResearchApplication::query()->pluck('application_code');
            $this->assertSame($codes->count(), $codes->unique()->count());
        } finally {
            $this->travelBack();
        }
    }

    public function test_application_codes_use_the_current_editable_institute_acronym(): void
    {
        $this->travelTo(Carbon::parse('2026-08-10 09:30:00'));

        try {
            $resLead = User::factory()->create(['role' => UserRole::ResLead]);
            $institute = ProfileOption::query()
                ->where('field', ProfileOptionField::Institute->value)
                ->where('value', 'Institute of Computing and Digital Innovation')
                ->firstOrFail();
            app(ProfileOptionCatalog::class)->update($resLead, $institute, $institute->value, 'DIGI');
            $applicant = User::factory()->create([
                'role' => UserRole::Applicant,
                'applicant_type' => ApplicantType::Student,
            ]);
            $adviser = User::factory()->create(['role' => UserRole::Adviser]);

            $this->actingAs($applicant)
                ->post(route('applicant.applications.store'), $this->applicationPayload($adviser))
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();

            $this->assertMatchesRegularExpression(
                '/^RES-2026-S-DIGI-08102026-(?=[A-Z0-9]*[A-Z])(?=[A-Z0-9]*\d)[A-Z0-9]{6}$/',
                (string) ResearchApplication::query()
                    ->where('applicant_user_id', $applicant->id)
                    ->value('application_code'),
            );
        } finally {
            $this->travelBack();
        }
    }

    public function test_applicant_application_list_has_no_term_filter_and_shows_all_owned_terms(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $firstTerm = AcademicTerm::create([
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->addMonth(),
            'is_active' => true,
        ]);
        $secondTerm = AcademicTerm::create([
            'semester' => '2nd Semester',
            'academic_year' => '2026-2027',
            'starts_at' => now()->addMonths(2),
            'ends_at' => now()->addMonths(5),
            'is_active' => true,
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'academic_term_id' => $firstTerm,
            'research_title' => 'Visible First Term Study',
        ]);
        ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'academic_term_id' => $secondTerm,
            'research_title' => 'Hidden Second Term Study',
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.index', [
                'academic_term_id' => $firstTerm->id,
            ]))
            ->assertOk()
            ->assertSee('Visible First Term Study')
            ->assertSee('Hidden Second Term Study')
            ->assertDontSee('name="academic_term_id"', false)
            ->assertViewHas('applications', fn ($applications): bool => $applications->total() === 2);
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

    public function test_application_collects_one_unique_certificate_recipient_at_a_time_and_persists_the_order(): void
    {
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'name' => 'John S. Doe',
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.create'))
            ->assertOk()
            ->assertSee('Enter the name of each member one at a time.')
            ->assertSee('data-certificate-recipient-add', false)
            ->assertSee('data-certificate-recipient-list', false)
            ->assertSee('Add Name');

        $names = ['John S. Doe', 'Maria L. Cruz', 'Paolo R. Santos'];
        $this->actingAs($applicant)
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($adviser),
                'certificate_recipients' => $names,
            ])
            ->assertRedirect();
        $application = ResearchApplication::query()->where('applicant_user_id', $applicant->id)->firstOrFail();
        $this->assertSame($names, $application->certificateRecipients()->orderBy('sort_order')->pluck('recipient_name')->all());

        $otherApplicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ]);
        $this->actingAs($otherApplicant)
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($adviser),
                'certificate_recipients' => ['Duplicate A. Name', ' duplicate a. name '],
            ])
            ->assertSessionHasErrors(['certificate_recipients.0', 'certificate_recipients.1']);
        $this->assertDatabaseMissing('research_applications', ['applicant_user_id' => $otherApplicant->id]);
    }

    public function test_student_advisers_are_institute_scoped_while_faculty_advisers_are_not(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
            'institution' => 'Institute of Computing and Digital Innovation',
        ]);
        $faculty = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Faculty,
            'institution' => 'Institute of Computing and Digital Innovation',
        ]);
        $sameInstitute = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Eligible Computing Adviser',
            'institution' => 'Institute of Computing and Digital Innovation',
        ]);
        $otherInstitute = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Eligible Nursing Adviser',
            'institution' => 'Institute of Nursing',
        ]);
        $inactiveSameInstitute = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Inactive Computing Adviser',
            'institution' => 'Institute of Computing and Digital Innovation',
            'account_status' => 'inactive',
        ]);

        $this->actingAs($student)
            ->get(route('applicant.applications.create'))
            ->assertOk()
            ->assertSee('Eligible Computing Adviser')
            ->assertDontSee('Eligible Nursing Adviser')
            ->assertDontSee('Inactive Computing Adviser');

        $this->actingAs($student)
            ->from(route('applicant.applications.create'))
            ->post(route('applicant.applications.store'), $this->applicationPayload($otherInstitute))
            ->assertRedirect(route('applicant.applications.create'))
            ->assertSessionHasErrors('adviser_user_id');

        $this->actingAs($faculty)
            ->get(route('applicant.applications.create'))
            ->assertOk()
            ->assertSee('Eligible Computing Adviser')
            ->assertSee('Eligible Nursing Adviser')
            ->assertDontSee('Inactive Computing Adviser');

        $this->actingAs($faculty)
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($otherInstitute),
                'program' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('research_applications', [
            'applicant_user_id' => $faculty->id,
            'adviser_user_id' => $otherInstitute->id,
        ]);
        $this->assertNotNull($sameInstitute->id);
    }

    public function test_expected_duration_requires_an_ordered_date_pair_and_displays_the_saved_range(): void
    {
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'applicant_type' => ApplicantType::Student,
        ]);
        $adviser = User::factory()->create(['role' => UserRole::Adviser]);

        $this->actingAs($applicant)
            ->from(route('applicant.applications.create'))
            ->post(route('applicant.applications.store'), [
                ...$this->applicationPayload($adviser),
                'expected_start_date' => '2027-05-31',
                'expected_end_date' => '2026-08-01',
            ])
            ->assertRedirect(route('applicant.applications.create'))
            ->assertSessionHasErrors('expected_end_date');

        $this->actingAs($applicant)
            ->post(route('applicant.applications.store'), $this->applicationPayload($adviser))
            ->assertRedirect();

        $application = ResearchApplication::where('applicant_user_id', $applicant->id)->firstOrFail();
        $this->assertSame('2026-08-01', $application->expected_start_date?->format('Y-m-d'));
        $this->assertSame('2027-05-31', $application->expected_end_date?->format('Y-m-d'));
        $this->assertNull($application->expected_duration);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertSee('Aug 1, 2026 to May 31, 2027');

        $this->actingAs($applicant)
            ->get(route('applicant.applications.edit', $application))
            ->assertOk()
            ->assertSee('application-information-form', false)
            ->assertSee('application-form-section-heading', false)
            ->assertSee('institute-information-title', false)
            ->assertSee('study-scope-title', false)
            ->assertSee('application-duration-fields', false)
            ->assertSeeInOrder(['Target Participants', 'Starting Date', 'Ending Date'])
            ->assertSeeInOrder(['>Cancel<', '>Save and Continue<'], false);

        $formView = (string) file_get_contents(resource_path('views/dashboard/applications/form.blade.php'));
        $this->assertStringContainsString('name="building-2"', $formView);
        $this->assertStringContainsString('name="target"', $formView);

        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $this->assertMatchesRegularExpression(
            '/\.application-form\.application-information-form\s*\{[^}]*display:\s*grid;[^}]*border:\s*0;[^}]*background:\s*transparent;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.application-form-section\s*\{[^}]*border:\s*1px solid #dce5e0;[^}]*border-radius:\s*8px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.application-form-actions\s*\{\s*padding:\s*4px 0 0;\s*\}/s',
            $css,
        );
    }

    public function test_requirement_workspace_combines_progress_and_requires_submit_confirmation_in_the_approved_order(): void
    {
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $this->requirement();
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_title' => 'Submission Confirmation Study',
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('application-submission-overview', false)
            ->assertSee('application-record-header-integrated', false)
            ->assertSee('application-submit-heading', false)
            ->assertSeeInOrder([
                'Submission Checklist',
                'data-final-submit-open',
                'Application submission is open by the REU Lead.',
                'A formal application slot is available.',
                'All required application information is complete.',
                'An eligible Research Adviser is assigned.',
                'Every mandatory requirement is uploaded and complete.',
            ])
            ->assertSee('data-final-submit-open', false)
            ->assertSee('data-final-submit-dialog', false)
            ->assertSee('data-requirement-readiness', false)
            ->assertSee('data-application-upload-form', false)
            ->assertSee('data-requirement-file', false)
            ->assertSee('Choose File')
            ->assertDontSee('data-upload-all', false)
            ->assertDontSee('Upload All')
            ->assertSee('up to 100 MB per file')
            ->assertSee('Confirm Submission');

        $javascript = (string) file_get_contents(resource_path('js/dashboard.js'));
        $this->assertMatchesRegularExpression(
            '/if\s*\(input\.files\?\.length\)\s*\{.*?uploadOne\(form\);/s',
            $javascript,
        );
    }

    public function test_requested_application_action_groups_remain_horizontal_at_narrow_widths(): void
    {
        $css = file_get_contents(resource_path('css/dashboard.css'));

        $this->assertIsString($css);
        $this->assertMatchesRegularExpression(
            '/\.application-record-actions,\s*\.application-heading-actions,\s*\.application-adviser-decision-actions\s*\{[^}]*flex-direction:\s*row;[^}]*flex-wrap:\s*nowrap;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.application-form-actions\s*\{[^}]*flex-direction:\s*row;[^}]*flex-wrap:\s*nowrap;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.application-submit-heading\s*\{[^}]*flex-direction:\s*row;[^}]*flex-wrap:\s*nowrap;/s',
            $css,
        );
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

    public function test_reopening_an_adviser_returned_application_preserves_initial_submission_and_cycle(): void
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
            'current_revision_cycle' => 1,
        ]);
        $originalSubmittedAt = $application->submitted_at;
        $requirement = $this->requirement();

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('too-early.pdf', 20, 'application/pdf')],
        )->assertForbidden();
        $this->actingAs($applicant)
            ->post(route('applicant.applications.submit', $application))
            ->assertForbidden();

        $this->actingAs($applicant)
            ->put(route('applicant.applications.update', $application), $this->applicationPayload($adviser))
            ->assertRedirect(route('applicant.applications.requirements', $application));

        $application->refresh();
        $this->assertSame(ApplicationStatus::Incomplete, $application->application_status);
        $this->assertTrue($application->submitted_at->equalTo($originalSubmittedAt));
        $this->assertSame($applicant->id, $application->draft_owner_user_id);
        $this->assertSame(1, $application->current_revision_cycle);

        $this->actingAs($applicant)
            ->put(route('applicant.applications.update', $application), $this->applicationPayload($adviser))
            ->assertRedirect(route('applicant.applications.requirements', $application));
        $this->assertSame(1, $application->fresh()->current_revision_cycle);

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('revision.pdf', 20)],
        )->assertRedirect();
        $this->assertSame(1, ApplicationDocument::firstOrFail()->document_version);
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
            ['document' => $this->pdfUpload('proposal.pdf', 20)],
        )->assertRedirect();
        $first = ApplicationDocument::firstOrFail();
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('proposal-revised.pdf', 24)],
        )->assertRedirect();

        // Assert replacements before formal submission remain Version 1 and erase the obsolete draft.
        $documents = ApplicationDocument::query()->orderBy('id')->get();
        $this->assertCount(1, $documents);
        $this->assertTrue($documents[0]->is_current);
        $this->assertSame(1, $documents[0]->document_version);
        $this->assertSame(RequirementStatus::Completed, $documents[0]->validation_status);
        $this->assertDatabaseMissing('application_documents', ['id' => $first->id]);
        Storage::disk('local')->assertMissing($first->stored_file_path);
        Storage::disk('local')->assertExists($documents[0]->stored_file_path);
        $this->assertStringNotContainsString('proposal-revised.pdf', $documents[0]->stored_file_path);
        $this->assertSame(1, $this->auditCount('application.requirement_uploaded', $first));
        $this->assertSame(1, $this->auditCount('application.requirement_replaced', $documents[0]));

        // A cycle counter alone does not advance a document that was not reviewed and replaced.
        $application->update(['current_revision_cycle' => 2]);
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('proposal-cycle-two.pdf', 24)],
        )->assertRedirect();
        $cycleTwoDocument = ApplicationDocument::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $cycleTwoDocument->document_version);
        $this->assertTrue($cycleTwoDocument->is_current);

        // Act with an executable extension and assert validation prevents another stored record.
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('payload.php', 1, 'text/x-php')],
        )->assertSessionHasErrors('document');

        // Act above the one-hundred-megabyte boundary and assert validation rejects it before storage.
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => UploadedFile::fake()->create('oversized.pdf', 102401, 'application/pdf')],
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

        // Assert rejected type, size, and category attempts never created another document record.
        $this->assertSame(1, ApplicationDocument::count());
    }

    public function test_private_document_upload_accepts_the_one_hundred_megabyte_boundary(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
        ]);
        $requirement = $this->requirement();

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('boundary.pdf', 102400)],
        )->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(1, ApplicationDocument::count());
    }

    public function test_private_document_upload_requires_matching_extension_mime_and_signature_for_every_allowed_format(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
        ]);
        $requirement = $this->requirement();
        $allowedFiles = [
            $this->pdfUpload('proposal.pdf'),
            UploadedFile::fake()->image('photo.jpg'),
            UploadedFile::fake()->image('photo.jpeg'),
            UploadedFile::fake()->image('diagram.png'),
            $this->signedUpload('animation.gif', 'image/gif', 'GIF89a'.str_repeat("\0", 20)),
            $this->signedUpload('figure.webp', 'image/webp', 'RIFF'.pack('V', 4).'WEBPVP8 '),
        ];

        foreach ($allowedFiles as $index => $file) {
            $uploadRequirement = $index === 0 ? $requirement : DocumentRequirement::create([
                'code' => "ALLOWED-FORMAT-{$index}",
                'name' => "Allowed Format {$index}",
                'description' => 'Format validation fixture.',
                'is_mandatory' => true,
                'research_types' => [ResearchType::Thesis->value],
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
            $this->actingAs($applicant)->post(
                route('applicant.applications.documents.store', [$application, $uploadRequirement]),
                ['document' => $file],
            )->assertRedirect()->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(6, ApplicationDocument::count());
        $this->assertSame(
            ['application/pdf', 'image/jpeg', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            ApplicationDocument::query()->orderBy('id')->pluck('mime_type')->all(),
        );

        $extensionMismatch = $this->signedUpload('disguised.jpg', 'application/pdf', "%PDF-1.7\n");
        $invalidSignature = UploadedFile::fake()->create('forged.pdf', 1, 'application/pdf');

        foreach ([$extensionMismatch, $invalidSignature] as $file) {
            $this->actingAs($applicant)->post(
                route('applicant.applications.documents.store', [$application, $requirement]),
                ['document' => $file],
            )->assertRedirect()->assertSessionHasErrors('document');
        }

        $this->assertSame(6, ApplicationDocument::count());
    }

    public function test_upload_all_processes_each_selected_requirement_independently(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $proposal = DocumentRequirement::create([
            'code' => 'BATCH-PROPOSAL',
            'name' => 'Research Proposal',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $consent = DocumentRequirement::create([
            'code' => 'BATCH-CONSENT',
            'name' => 'Informed Consent',
            'is_mandatory' => true,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $response = $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store-all', $application),
            [
                'documents' => [
                    $proposal->id => $this->pdfUpload('proposal.pdf', 20),
                    $consent->id => UploadedFile::fake()->create('unsafe.php', 1, 'text/x-php'),
                ],
            ],
            $headers,
        );

        $response->assertOk()
            ->assertJsonPath("successes.{$proposal->id}.message", 'Document uploaded.')
            ->assertJsonPath('progress.completed_count', 1);
        $this->assertNotEmpty($response->json("errors.{$consent->id}"));
        $this->assertDatabaseHas('application_documents', [
            'research_application_id' => $application->id,
            'document_requirement_id' => $proposal->id,
            'is_current' => true,
        ]);
        $this->assertDatabaseMissing('application_documents', [
            'research_application_id' => $application->id,
            'document_requirement_id' => $consent->id,
        ]);

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store-all', $application),
            [
                'documents' => [
                    $consent->id => $this->pdfUpload('consent.pdf', 18),
                ],
            ],
            $headers,
        )->assertOk()
            ->assertJsonPath("successes.{$consent->id}.message", 'Document uploaded.')
            ->assertJsonPath('progress.completed_count', 2)
            ->assertJsonPath('progress.ready', true);

        $this->assertSame(2, ApplicationDocument::query()->count());
    }

    public function test_unsubmitted_document_removal_updates_the_checklist_and_erases_private_draft(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $requirement = $this->requirement();

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('remove-me.pdf', 10)],
        )->assertRedirect();
        $document = ApplicationDocument::firstOrFail();
        Storage::disk('local')->assertExists($document->stored_file_path);

        $this->actingAs($applicant)
            ->delete(route('applicant.applications.documents.destroy', [$application, $document]))
            ->assertRedirect();

        $this->assertDatabaseMissing('application_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->stored_file_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.requirement_removed',
            'subject_id' => $document->id,
        ]);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('0 of 1 mandatory requirements completed')
            ->assertSee('Missing')
            ->assertDontSee('remove-me.pdf');
    }

    public function test_formally_submitted_document_removal_detaches_but_preserves_immutable_history(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $requirement = $this->requirement();
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('submitted-history.pdf', 10)],
        )->assertRedirect();
        $document = ApplicationDocument::firstOrFail();
        $document->update(['formally_submitted_at' => now()->subMinute()]);

        $this->actingAs($applicant)
            ->delete(route('applicant.applications.documents.destroy', [$application, $document]))
            ->assertRedirect();

        $this->assertFalse($document->refresh()->is_current);
        $this->assertNotNull($document->formally_submitted_at);
        Storage::disk('local')->assertExists($document->stored_file_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.requirement_removed',
            'subject_id' => $document->id,
        ]);
    }

    public function test_document_workspace_uses_the_filename_modal_and_authorized_office_fallback(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_type' => ResearchType::Thesis,
        ]);
        $requirement = $this->requirement();
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'participant-data.xlsx',
            'stored_file_path' => 'applications/private/participant-data.xlsx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'file_size_bytes' => 2048,
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, 'private workbook');

        $this->actingAs($applicant)
            ->get(route('applicant.applications.requirements', $application))
            ->assertOk()
            ->assertSee('participant-data.xlsx')
            ->assertSee('data-document-open', false)
            ->assertSee('data-document-preview-kind="office"', false)
            ->assertSee('data-document-type="Microsoft Excel workbook (.xlsx)"', false)
            ->assertSee('data-document-replace', false)
            ->assertSee('Preview unavailable')
            ->assertSee(route('applicant.applications.documents.preview', [$application, $document]), false)
            ->assertSee('accept=".pdf,.jpg,.jpeg,.png,.gif,.webp"', false)
            ->assertDontSee('Choose Replacement');

        $this->actingAs($applicant)
            ->get(route('applicant.applications.documents.preview', [$application, $document]))
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertSee('Secure inline preview unavailable')
            ->assertSee('Microsoft Excel workbook (.xlsx)')
            ->assertSee(route('applicant.applications.documents.download', [$application, $document]), false);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.documents.download', [$application, $document]))
            ->assertOk()
            ->assertDownload('participant-data.xlsx');
    }

    public function test_new_office_upload_is_rejected_while_historical_office_files_remain_streamable(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
        ]);
        $requirement = $this->requirement();

        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            [
                'document' => UploadedFile::fake()->create(
                    'research-data.xlsx',
                    12,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ),
            ],
        )->assertRedirect()->assertSessionHasErrors('document');

        $this->assertSame(0, ApplicationDocument::count());
    }

    public function test_applicant_can_permanently_discard_only_their_own_unsubmitted_draft(): void
    {
        Storage::fake('local');
        $applicant = User::factory()->create(['role' => UserRole::Applicant]);
        $otherApplicant = User::factory()->create(['role' => UserRole::Applicant]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant,
            'draft_owner_user_id' => $applicant,
            'research_title' => 'Draft To Discard',
            'application_status' => ApplicationStatus::Incomplete,
        ]);
        $requirement = $this->requirement();
        $this->actingAs($applicant)->post(
            route('applicant.applications.documents.store', [$application, $requirement]),
            ['document' => $this->pdfUpload('preserved.pdf', 5)],
        )->assertRedirect();
        $document = ApplicationDocument::firstOrFail();

        $this->actingAs($otherApplicant)
            ->delete(route('applicant.applications.destroy', $application))
            ->assertForbidden();
        $this->actingAs($applicant)
            ->delete(route('applicant.applications.destroy', $application))
            ->assertRedirect(route('applicant.applications.index'));

        $this->assertDatabaseMissing('research_applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('application_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->stored_file_path);
        $audit = AuditLog::query()
            ->where('action', 'application.draft_discarded')
            ->firstOrFail();
        $this->assertNull($audit->subject_id);
        $this->assertSame($application->id, $audit->metadata['application_id']);
        $this->assertSame('deleted', $audit->metadata['result']);
        $this->actingAs($applicant)
            ->get(route('applicant.applications.index'))
            ->assertOk()
            ->assertDontSee('Draft To Discard');

        $submitted = ResearchApplication::factory()->submittedToAdviser()->create([
            'applicant_user_id' => $applicant,
        ]);
        $this->actingAs($applicant)
            ->delete(route('applicant.applications.destroy', $submitted))
            ->assertForbidden();
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
            ['document' => $this->pdfUpload('private.pdf', 8)],
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
            'program' => 'Bachelor of Science in Computer Science',
            'adviser_user_id' => $adviser->id,
            'abstract' => 'This study examines privacy expectations in community-facing digital research.',
            'target_participants' => 'Adult KLD students who provide informed consent.',
            'certificate_recipients' => ['John S. Doe'],
            'expected_start_date' => '2026-08-01',
            'expected_end_date' => '2027-05-31',
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

    private function pdfUpload(string $name, int $kilobytes = 1): UploadedFile
    {
        return $this->signedUpload($name, 'application/pdf', "%PDF-1.7\n% ECRATS test upload\n", $kilobytes);
    }

    private function signedUpload(
        string $name,
        string $mimeType,
        string $contents,
        int $kilobytes = 1,
    ): UploadedFile {
        $file = UploadedFile::fake()->create($name, $kilobytes, $mimeType);
        file_put_contents((string) $file->getRealPath(), $contents);

        return $file;
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
