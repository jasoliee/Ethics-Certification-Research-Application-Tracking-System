<?php

namespace Tests\Feature\Dashboard;

use App\Enums\AccountStatus;
use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewType;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Notifications\DashboardUpdateNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResLeadScreeningWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_res_lead_classifies_a_ready_application_and_records_a_bounded_audit_event(): void
    {
        [$resLead, $applicant, , $application] = $this->readyApplication();

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload(ReviewType::Expedited),
            )
            ->assertRedirect(route('res.applications.reviewers.index', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::AwaitingReviewerAssignment, $application->application_status);
        $this->assertSame(ApplicationStage::ResScreening, $application->current_stage);
        $this->assertSame(ReviewType::Expedited->value, $application->review_type);
        $this->assertDatabaseHas('application_screenings', [
            'research_application_id' => $application->id,
            'screened_by_user_id' => $resLead->id,
            'review_type' => ReviewType::Expedited->value,
        ]);

        $audit = AuditLog::query()
            ->where('action', 'application.res_classified')
            ->where('subject_id', $application->id)
            ->firstOrFail();

        $this->assertSame(1, $audit->metadata['reviewer_count']);
        $this->assertArrayNotHasKey('screening_notes', $audit->metadata);
        $this->assertArrayNotHasKey('classification_reason', $audit->metadata);
        Notification::assertSentTo($applicant, DashboardUpdateNotification::class);
    }

    public function test_screening_details_expose_protected_documents_and_all_required_decision_controls(): void
    {
        Storage::fake('local');
        [$resLead, , , $application] = $this->readyApplication();
        $document = $application->documents()->firstOrFail();
        Storage::disk('local')->put($document->stored_file_path, '%PDF-1.4 protected RES copy');

        $this->actingAs($resLead)
            ->get(route('res.applications.show', $application))
            ->assertOk()
            ->assertSeeInOrder(['Requirement Checklist', 'Application Details', 'Research Information'])
            ->assertSee('Research Information')
            ->assertSee('Requirement Checklist')
            ->assertDontSee('Administrative Screening Panel')
            ->assertSee('Review Type Classification')
            ->assertSee(ReviewType::Expedited->label())
            ->assertSee(ReviewType::FullBoard->label())
            ->assertSee(ReviewType::Exempted->label())
            ->assertSee('class="res-classification-fields"', false)
            ->assertSeeInOrder([
                'Select Review Type',
                'Reason / Basis for Classification',
                'Classification determines the reviewer count and next workflow status.',
            ])
            ->assertSee(route('res.applications.documents.preview', [$application, $document]), false)
            ->assertSee(route('res.applications.documents.download', [$application, $document]), false)
            ->assertSee('data-application-submit-once', false);

        $this->actingAs($resLead)
            ->get(route('res.applications.documents.download', [$application, $document]))
            ->assertDownload($document->original_file_name);

        $this->actingAs($application->applicant)
            ->get(route('res.applications.documents.download', [$application, $document]))
            ->assertRedirect(route('dashboard'));
    }

    public function test_classification_rechecks_stale_document_readiness(): void
    {
        [$resLead, , , $application] = $this->readyApplication();
        $application->documents()->update(['validation_status' => RequirementStatus::Rejected->value]);

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload(ReviewType::Expedited),
            )
            ->assertSessionHasErrorsIn('resScreening', ['requirements']);

        $this->assertDatabaseMissing('application_screenings', [
            'research_application_id' => $application->id,
        ]);
    }

    public function test_incomplete_screening_work_is_saved_for_its_res_owner_and_cleared_after_formal_classification(): void
    {
        [$resLead, $applicant, , $application] = $this->readyApplication();

        $this->actingAs($resLead)
            ->postJson(route('res.applications.classification.draft', $application), [
                'review_type' => ReviewType::FullBoard->value,
                'classification_reason' => 'Still reviewing risk.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Screening draft saved.');

        $this->assertDatabaseHas('workflow_drafts', [
            'user_id' => $resLead->id,
            'research_application_id' => $application->id,
            'workflow' => 'res_screening',
        ]);
        $this->actingAs($resLead)
            ->get(route('res.applications.show', $application))
            ->assertOk()
            ->assertSee('Still reviewing risk.')
            ->assertSee('data-partial-auto-save-draft', false);

        $this->actingAs($applicant)
            ->postJson(route('res.applications.classification.draft', $application), [
                'review_type' => ReviewType::Exempted->value,
            ])
            ->assertRedirect(route('dashboard'));
        $this->assertDatabaseCount('workflow_drafts', 1);

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload(ReviewType::Expedited),
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('workflow_drafts', [
            'user_id' => $resLead->id,
            'research_application_id' => $application->id,
            'workflow' => 'res_screening',
        ]);
        $audit = AuditLog::query()
            ->where('action', 'application.res_screening_draft_saved')
            ->firstOrFail();
        $this->assertSame(['review_type', 'classification_reason'], $audit->metadata['fields_present']);
        $this->assertArrayNotHasKey('payload', $audit->metadata);
    }

    public function test_expedited_assignment_requires_exactly_one_eligible_reviewer_and_is_idempotent(): void
    {
        [$resLead, $applicant, , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertRedirect(route('res.applications.index'))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::UnderExpeditedReview, $application->application_status);
        $this->assertSame(ApplicationStage::EthicsReview, $application->current_stage);
        $this->assertDatabaseHas('reviewer_assignments', [
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'review_type' => 'initial_review',
            'assignment_status' => ReviewerAssignmentStatus::Pending->value,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.reviewers_assigned',
            'subject_id' => $application->id,
        ]);
        Notification::assertSentTo($reviewer, DashboardUpdateNotification::class);
        Notification::assertSentTo($applicant, DashboardUpdateNotification::class);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->count());
    }

    public function test_reassignment_controls_are_grouped_with_selected_reviewer_and_decision_actions(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($resLead)
            ->get(route('res.applications.reviewers.index', $application))
            ->assertOk()
            ->assertSeeInOrder(['Selected Reviewer', 'Reason for Reassignment', 'Save Reviewer Set'])
            ->assertSee('form="res-reviewer-assignment-form"', false)
            ->assertDontSee('Known applicant and adviser conflicts are excluded from this list.');

        $this->actingAs($resLead)
            ->get(route('res.applications.show', $application))
            ->assertOk()
            ->assertSee('class="res-workflow-banner-actions"', false)
            ->assertSee('href="'.route('res.applications.reviewers.index', $application).'"', false)
            ->assertSeeInOrder(['Re-edit Decision', 'Re-edit Assignment']);
    }

    public function test_full_board_assignment_rejects_wrong_and_duplicate_counts_then_assigns_exactly_three(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::FullBoard);
        $reviewers = collect(range(1, 3))
            ->map(fn (): User => $this->reviewerFor($application, ReviewType::FullBoard));

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => $reviewers->take(2)->pluck('id')->all(),
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasErrorsIn('reviewerAssignment', ['reviewer_ids']);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => array_fill(0, 3, $reviewers->first()->id),
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasErrorsIn('reviewerAssignment');

        $this->assertSame(0, ReviewerAssignment::count());

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => $reviewers->pluck('id')->all(),
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(ApplicationStatus::UnderFullBoardReview, $application->fresh()->application_status);
        $this->assertSame(3, ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->count());
    }

    public function test_assignment_page_prioritizes_matches_hides_inactive_accounts_and_blocks_a_full_reviewer(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $eligible = $this->reviewerFor($application, ReviewType::Expedited, ['name' => 'Primary Match Reviewer']);
        $full = $this->reviewerFor($application, ReviewType::Expedited, [
            'name' => 'Full Capacity Reviewer',
            'reviewer_capacity' => 1,
        ]);
        $inactive = $this->reviewerFor($application, ReviewType::Expedited, [
            'name' => 'Inactive Reviewer',
            'account_status' => AccountStatus::Inactive->value,
        ]);
        $wrongDiscipline = $this->reviewerFor($application, ReviewType::Expedited, [
            'name' => 'Different Discipline Reviewer',
            'institution' => 'Institute of Governance and Development Studies',
        ]);
        ReviewerAssignment::factory()->count(30)->create([
            'reviewer_user_id' => $full->id,
            'review_type' => 'initial_review',
            'assignment_status' => ReviewerAssignmentStatus::InReview,
            'assigned_at' => now(),
        ]);

        $this->actingAs($resLead)
            ->get(route('res.applications.reviewers.index', $application))
            ->assertOk()
            ->assertSeeInOrder([$eligible->name, $wrongDiscipline->name])
            ->assertSee($full->name)
            ->assertDontSee($inactive->name)
            ->assertSee($wrongDiscipline->name)
            ->assertSee('Capacity reached')
            ->assertDontSee('<th>Availability</th>', false);

        $this->actingAs($resLead)
            ->get(route('res.applications.reviewers.index', [
                $application,
                'institute' => 'Institute of Governance and Development Studies',
            ]))
            ->assertOk()
            ->assertSee($wrongDiscipline->name)
            ->assertDontSee($eligible->name);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$full->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasErrorsIn('reviewerAssignment', ['reviewer_ids']);

        $this->assertSame(0, ReviewerAssignment::query()
            ->where('research_application_id', $application->id)
            ->count());
    }

    public function test_screening_correction_preserves_an_exact_pending_assignment_when_classification_is_compatible(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();
        $assignment = ReviewerAssignment::query()->firstOrFail();
        $payload = $this->classificationPayload(ReviewType::Expedited);
        $payload['classification_reason'] = 'The corrected record still satisfies the expedited review criteria.';

        $this->actingAs($resLead)
            ->put(route('res.applications.classification.update', $application), $payload)
            ->assertRedirect(route('res.applications.show', $application))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviewer_assignments', ['id' => $assignment->id]);
        $this->assertSame(ApplicationStatus::UnderExpeditedReview, $application->fresh()->application_status);
        $this->assertSame($payload['classification_reason'], $application->screening()->firstOrFail()->classification_reason);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.res_screening_updated',
            'subject_id' => $application->id,
        ]);
    }

    public function test_screening_classification_change_supersedes_pending_assignment_without_deleting_it(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($resLead)
            ->put(
                route('res.applications.classification.update', $application),
                $this->classificationPayload(ReviewType::FullBoard),
            )
            ->assertRedirect(route('res.applications.reviewers.index', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::AwaitingReviewerAssignment, $application->application_status);
        $this->assertSame(ReviewType::FullBoard->value, $application->review_type);
        $this->assertSame(1, $application->reviewerAssignments()->count());
        $this->assertSame(0, $application->reviewerAssignments()->current()->count());
        $this->assertSame(ReviewerAssignmentStatus::Superseded, $application->reviewerAssignments()->firstOrFail()->assignment_status);
        Notification::assertSentTo($reviewer, DashboardUpdateNotification::class);
    }

    public function test_screening_correction_preserves_and_supersedes_started_review_work(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Expedited);
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        $this->actingAs($resLead)
            ->post(route('res.applications.reviewers.store', $application), [
                'reviewer_ids' => [$reviewer->id],
                'confirm_assignment' => '1',
            ])
            ->assertSessionHasNoErrors();
        ReviewerAssignment::query()->update([
            'assignment_status' => ReviewerAssignmentStatus::InReview->value,
        ]);

        $this->actingAs($resLead)
            ->from(route('res.applications.show', $application))
            ->put(
                route('res.applications.classification.update', $application),
                $this->classificationPayload(ReviewType::FullBoard),
            )
            ->assertRedirect(route('res.applications.reviewers.index', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::AwaitingReviewerAssignment, $application->application_status);
        $this->assertSame(ReviewType::FullBoard->value, $application->review_type);
        $this->assertSame(1, $application->reviewerAssignments()->count());
        $this->assertSame(ReviewerAssignmentStatus::Superseded, $application->reviewerAssignments()->firstOrFail()->assignment_status);
    }

    public function test_screening_correction_is_unavailable_after_the_initial_review_boundary(): void
    {
        [$resLead, , , $application] = $this->classifiedApplication(ReviewType::Exempted);
        $application->update([
            'application_status' => ApplicationStatus::CertificateReleased->value,
            'current_stage' => ApplicationStage::Completed->value,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.applications.show', [$application, 'edit_screening' => 1]))
            ->assertOk()
            ->assertDontSee('Update Screening Decision');

        $this->actingAs($resLead)
            ->put(
                route('res.applications.classification.update', $application),
                $this->classificationPayload(ReviewType::Expedited),
            )
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::CertificateReleased, $application->fresh()->application_status);
        $this->assertSame(ReviewType::Exempted, $application->screening()->firstOrFail()->review_type);
    }

    public function test_exempted_classification_bypasses_reviewer_assignment(): void
    {
        [$resLead, , , $application] = $this->readyApplication();

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload(ReviewType::Exempted),
            )
            ->assertRedirect(route('res.applications.show', $application))
            ->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(ApplicationStatus::Exempted, $application->application_status);
        $this->assertSame(ApplicationStage::DecisionRelease, $application->current_stage);
        $this->assertSame(0, $application->reviewerAssignments()->count());

        $this->actingAs($resLead)
            ->get(route('res.applications.reviewers.index', $application))
            ->assertNotFound();
    }

    public function test_non_res_roles_cannot_classify_or_assign_reviewers(): void
    {
        [$resLead, $applicant, $adviser, $application] = $this->readyApplication();
        $reviewer = $this->reviewerFor($application, ReviewType::Expedited);

        foreach ([$applicant, $adviser, $reviewer] as $actor) {
            $this->actingAs($actor)
                ->post(
                    route('res.applications.classification.store', $application),
                    $this->classificationPayload(ReviewType::Expedited),
                )
                ->assertRedirect(route('dashboard'));
        }

        $this->assertDatabaseMissing('application_screenings', [
            'research_application_id' => $application->id,
        ]);

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload(ReviewType::Expedited),
            )
            ->assertSessionHasNoErrors();

        foreach ([$applicant, $adviser, $reviewer] as $actor) {
            $this->actingAs($actor)
                ->post(route('res.applications.reviewers.store', $application), [
                    'reviewer_ids' => [$reviewer->id],
                    'confirm_assignment' => '1',
                ])
                ->assertRedirect(route('dashboard'));

            $this->actingAs($actor)
                ->put(
                    route('res.applications.classification.update', $application),
                    $this->classificationPayload(ReviewType::FullBoard),
                )
                ->assertRedirect(route('dashboard'));
        }

        $this->assertSame(0, ReviewerAssignment::count());
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: ResearchApplication}
     */
    private function readyApplication(): array
    {
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'institution' => 'Institute of Computing and Digital Innovation',
            'program' => 'Bachelor of Science in Computer Science',
        ]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'institution' => 'Institute of Computing and Digital Innovation',
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::AdviserEndorsed,
            'current_stage' => ApplicationStage::ResScreening,
            'submitted_at' => now()->subDays(2),
            'status_updated_at' => now(),
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'RES-TEST-PROPOSAL',
            'name' => 'Complete Research Proposal',
            'description' => 'Mandatory screening document.',
            'is_mandatory' => true,
            'research_types' => null,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'research-proposal.pdf',
            'stored_file_path' => "applications/private/{$application->id}/research-proposal.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now()->subDay(),
        ]);

        return [$resLead, $applicant, $adviser, $application];
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: ResearchApplication}
     */
    private function classifiedApplication(ReviewType $reviewType): array
    {
        [$resLead, $applicant, $adviser, $application] = $this->readyApplication();

        $this->actingAs($resLead)
            ->post(
                route('res.applications.classification.store', $application),
                $this->classificationPayload($reviewType),
            )
            ->assertSessionHasNoErrors();

        return [$resLead, $applicant, $adviser, $application->fresh()];
    }

    /** @return array<string, string> */
    private function classificationPayload(ReviewType $reviewType): array
    {
        return [
            'review_type' => $reviewType->value,
            'classification_reason' => 'The submitted protocol meets the documented criteria for this classification.',
        ];
    }

    /** @param array<string, mixed> $overrides */
    private function reviewerFor(
        ResearchApplication $application,
        ReviewType $reviewType,
        array $overrides = [],
    ): User {
        return User::factory()->create(array_merge([
            'role' => UserRole::Reviewer,
            'account_status' => AccountStatus::Active->value,
            'institution' => $application->institution,
            'position_title' => 'Ethics Reviewer',
            'reviewer_classification' => $reviewType->reviewerClassification(),
            'reviewer_capacity' => 3,
            'password_setup_completed_at' => now(),
        ], $overrides));
    }
}
