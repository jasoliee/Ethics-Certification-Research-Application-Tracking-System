<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\RequirementStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\DeadlineConfiguration;
use App\Models\DocumentRequirement;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\User;
use App\Support\ReviewFormCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_owner_can_open_blind_documents_without_a_conflict_declaration(): void
    {
        Storage::fake('local');
        [$reviewer, $applicant, $adviser, $application, $assignment, $document] = $this->assignmentFixture();
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertOk()
            ->assertSee('Assignment ready')
            ->assertSee($document->original_file_name)
            ->assertDontSee($applicant->name)
            ->assertDontSee($adviser->name);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertOk()
            ->assertSee('Confidential blind review')
            ->assertSee($document->original_file_name)
            ->assertSee(route('reviewer.applications.documents.download', [$application, $document]), false)
            ->assertDontSee($applicant->name)
            ->assertDontSee($adviser->name);

        $this->actingAs($reviewer)
            ->get(route('reviewer.applications.documents.download', [$application, $document]))
            ->assertDownload($document->original_file_name);
        $this->actingAs($otherReviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertForbidden();
        $this->actingAs($otherReviewer)
            ->get(route('reviewer.applications.documents.download', [$application, $document]))
            ->assertForbidden();

        $this->assertFalse(AuditLog::query()->where('action', 'review.conflict_declared')->exists());
    }

    public function test_superseded_assignment_immediately_revokes_the_old_reviewer_workspace(): void
    {
        [$reviewer, , , , $assignment] = $this->assignmentFixture();
        $assignment->update([
            'superseded_at' => now(),
            'superseded_by_user_id' => User::factory()->create(['role' => UserRole::ResLead])->id,
            'supersession_reason' => 'Administrative reviewer replacement.',
            'superseded_from_status' => $assignment->assignment_status->value,
            'assignment_status' => ReviewerAssignmentStatus::Superseded,
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.show', $assignment))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertForbidden();
    }

    public function test_comments_are_validated_audited_and_hidden_from_the_applicant(): void
    {
        $this->openReviewWindow();
        [$reviewer, $applicant, , $application, $assignment] = $this->assignmentFixture();
        $comment = 'CONFIDENTIAL-REVISION-NOTE-4821';

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'x',
            ])
            ->assertSessionHasErrorsIn('reviewComment', ['body']);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'required_revision',
                'body' => $comment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('review_comments', [
            'reviewer_assignment_id' => $assignment->id,
            'body' => $comment,
            'released_at' => null,
            'status' => 'open',
        ]);
        $storedComment = $assignment->comments()->firstOrFail();
        $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.comments.update', [$assignment, $storedComment]), [
                'scope' => 'overall',
                'category' => 'required_revision',
                'body' => $comment.' updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.body', $comment.' updated');
        $this->actingAs($reviewer)
            ->patchJson(route('reviewer.assignments.comments.status', [$assignment, $storedComment]), [
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
        $this->assertDatabaseHas('review_comment_status_changes', [
            'review_comment_id' => $storedComment->id,
            'from_status' => 'open',
            'to_status' => 'resolved',
        ]);

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertDontSee($comment)
            ->assertDontSee($reviewer->name);

        $audit = AuditLog::query()->where('action', 'review.comment_added')->firstOrFail();
        $encodedMetadata = json_encode($audit->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString($comment, $encodedMetadata);
        $this->assertArrayNotHasKey('body', $audit->metadata);
    }

    public function test_closed_reviewer_window_keeps_the_workspace_read_only_and_rejects_writes(): void
    {
        DeadlineConfiguration::create([
            'deadline_key' => 'test-reviewer-submission',
            'title' => 'Closed reviewer submission deadline',
            'audience_role' => UserRole::Reviewer,
            'starts_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'priority' => 10,
            'is_active' => true,
        ]);
        [$reviewer, , , , $assignment] = $this->assignmentFixture();

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertOk()
            ->assertSee('Review work is read-only')
            ->assertSee('disabled', false);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'This closed-window comment must not be stored.',
            ])
            ->assertSessionHasErrorsIn('reviewerWorkflow', ['deadline']);

        $this->assertDatabaseCount('review_comments', 0);
    }

    public function test_official_forms_support_drafts_and_require_complete_server_owned_answers_before_finalization(): void
    {
        $this->openReviewWindow();
        [$reviewer, , , , $assignment] = $this->assignmentFixture();

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'draft',
                'responses' => [
                    'protocol_01' => ['answer' => 'yes', 'comment' => 'Initial assessment.'],
                ],
            ])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('review_form_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'form_type' => ReviewFormType::Protocol->value,
            'status' => ReviewFormStatus::Draft->value,
        ]);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'final',
                'responses' => [
                    'protocol_01' => ['answer' => 'yes', 'comment' => null],
                ],
                'recommendation' => ReviewDecision::Approved->value,
            ])
            ->assertSessionHasErrorsIn('reviewerForm', ['responses.protocol_02.answer']);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'final',
                'responses' => $this->completeResponses(ReviewFormType::Protocol),
                'recommendation' => ReviewDecision::Approved->value,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::InformedConsent->value]), [
                'intent' => 'final',
                'consent_required' => '0',
                'consent_not_required_explanation' => 'The approved protocol uses only fully anonymized secondary records.',
                'recommendation' => ReviewDecision::Approved->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            $assignment->formSubmissions()->where('status', ReviewFormStatus::Final->value)->count(),
        );
        $protocol = $assignment->formSubmissions()->where('form_type', ReviewFormType::Protocol->value)->firstOrFail();
        $this->assertSame('2026-08-05', $protocol->catalog_version);
        $this->assertCount(15, $protocol->catalog_snapshot['questions']);
        $this->assertNotNull($protocol->finalized_payload_snapshot);
        $this->assertCount(15, ReviewFormCatalog::questions(ReviewFormType::InformedConsent));
    }

    public function test_final_decision_requires_both_forms_then_freezes_work_and_moves_the_application_for_res_release(): void
    {
        $this->openReviewWindow();
        [$reviewer, $applicant, , $application, $assignment] = $this->assignmentFixture();
        $decisionComment = 'CONFIDENTIAL-FINAL-DECISION-7319';

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertSessionHasErrorsIn('reviewDecision', ['forms']);

        $this->finalizeForms($assignment);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertRedirect(route('reviewer.assignments.show', $assignment));

        $this->assertDatabaseHas('review_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewSubmissionStatus::Submitted->value,
            'decision' => ReviewDecision::Approved->value,
        ]);
        $this->assertSame(ReviewerAssignmentStatus::DecisionSubmitted, $assignment->fresh()->assignment_status);
        $this->assertSame(ApplicationStatus::ReviewSubmittedPendingRelease, $application->fresh()->application_status);
        $this->assertSame(ApplicationStage::DecisionRelease, $application->fresh()->current_stage);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'This should not be accepted after submission.',
            ])
            ->assertForbidden();

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertDontSee($decisionComment);

        $audit = AuditLog::query()->where('action', 'review.decision_submitted')->firstOrFail();
        $this->assertArrayNotHasKey('decision_comment', $audit->metadata);
        $this->assertStringNotContainsString($decisionComment, json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_full_board_application_waits_for_every_assigned_reviewer_before_release_processing(): void
    {
        $this->openReviewWindow();
        $application = ResearchApplication::factory()->create([
            'application_status' => ApplicationStatus::UnderFullBoardReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => 'full_board',
            'submitted_at' => now()->subDays(3),
        ]);
        $assignments = collect(range(1, 3))->map(function () use ($application): ReviewerAssignment {
            $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);

            return ReviewerAssignment::factory()->create([
                'research_application_id' => $application->id,
                'reviewer_user_id' => $reviewer->id,
                'review_type' => 'initial_review',
            ]);
        });

        foreach ($assignments as $index => $assignment) {
            $this->finalizeForms($assignment);
            $this->actingAs($assignment->reviewer)
                ->post(route('reviewer.assignments.review.store', $assignment), [
                    'intent' => 'submit',
                    'decision' => ReviewDecision::Approved->value,
                    'decision_comment' => 'Completed independent full board assessment '.$index.'.',
                ])
                ->assertSessionHasNoErrors();

            $expected = $index === 2
                ? ApplicationStatus::ReviewSubmittedPendingRelease
                : ApplicationStatus::UnderFullBoardReview;
            $this->assertSame($expected, $application->fresh()->application_status);
        }
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: ResearchApplication, 4: ReviewerAssignment, 5: ApplicationDocument}
     */
    private function assignmentFixture(): array
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $applicant = User::factory()->create([
            'role' => UserRole::Applicant,
            'name' => 'Hidden Applicant Identity',
        ]);
        $adviser = User::factory()->create([
            'role' => UserRole::Adviser,
            'name' => 'Hidden Adviser Identity',
        ]);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => 'expedited',
            'submitted_at' => now()->subDays(3),
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
        ]);
        $requirement = DocumentRequirement::create([
            'code' => 'REVIEW-WORKSPACE-PROPOSAL',
            'name' => 'Research Proposal',
            'is_mandatory' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $document = ApplicationDocument::create([
            'research_application_id' => $application->id,
            'document_requirement_id' => $requirement->id,
            'uploaded_by_user_id' => $applicant->id,
            'original_file_name' => 'blind-review-copy.pdf',
            'stored_file_path' => "applications/private/{$application->id}/blind-review-copy.pdf",
            'mime_type' => 'application/pdf',
            'file_size_bytes' => 2048,
            'document_version' => 1,
            'validation_status' => RequirementStatus::Completed,
            'is_current' => true,
            'uploaded_at' => now(),
        ]);
        Storage::disk('local')->put($document->stored_file_path, '%PDF-1.4 private reviewer copy');

        return [$reviewer, $applicant, $adviser, $application, $assignment, $document];
    }

    private function openReviewWindow(): DeadlineConfiguration
    {
        return DeadlineConfiguration::create([
            'deadline_key' => 'test-reviewer-submission',
            'title' => 'Reviewer submission deadline',
            'audience_role' => UserRole::Reviewer,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    /** @return array<string, array{answer: string, comment: null}> */
    private function completeResponses(ReviewFormType $type): array
    {
        return collect(ReviewFormCatalog::questions($type))
            ->mapWithKeys(fn (string $question, string $key): array => [
                $key => ['answer' => 'yes', 'comment' => null],
            ])
            ->all();
    }

    private function finalizeForms(ReviewerAssignment $assignment): void
    {
        foreach (ReviewFormType::cases() as $type) {
            $assignment->formSubmissions()->create([
                'form_type' => $type,
                'status' => ReviewFormStatus::Final,
                'responses' => $this->completeResponses($type),
                'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
                'recommendation' => ReviewDecision::Approved,
                'review_date' => today(),
                'finalized_at' => now(),
            ]);
        }
    }
}
