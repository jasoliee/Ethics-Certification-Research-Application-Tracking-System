<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Enums\ReviewSubmissionStatus;
use App\Enums\UserRole;
use App\Exceptions\OfficialReviewFormGenerationException;
use App\Models\DeadlineConfiguration;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewFormArtifact;
use App\Models\ReviewFormSubmission;
use App\Models\User;
use App\Services\Applications\OfficialReviewFormArtifactService;
use App\Support\ReviewFormCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Mockery;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class OfficialReviewFormArtifactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        DeadlineConfiguration::create([
            'deadline_key' => 'test-reviewer-submission',
            'title' => 'Open reviewer submission deadline',
            'audience_role' => UserRole::Reviewer,
            'starts_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'priority' => 10,
            'is_active' => true,
        ]);
    }

    public function test_worksheet_submission_marks_it_completed_and_editable_without_an_artifact(): void
    {
        [$reviewer, $applicant, $adviser, $application, $assignment] = $this->fixture();
        $payload = $this->finalPayload(ReviewFormType::Protocol);

        $first = $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', ReviewFormStatus::Completed->value)
            ->assertJsonPath('data.artifact', null);

        $form = $assignment->formSubmissions()
            ->where('form_type', ReviewFormType::Protocol)
            ->firstOrFail();

        $this->assertSame($form->id, $first->json('data.id'));
        $this->assertNull($form->catalog_version);
        $this->assertNull($form->finalized_payload_snapshot);
        $this->assertNull($form->finalized_context_snapshot);
        $this->assertNotNull($form->completed_at);
        $this->assertDatabaseCount('review_form_artifacts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        // A repeated worksheet submission updates the same editable completed record.
        $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $payload)
            ->assertOk()
            ->assertJsonPath('data.id', $form->id)
            ->assertJsonPath('data.artifact', null);

        $this->assertDatabaseCount('review_form_submissions', 1);
        $this->assertDatabaseCount('review_form_artifacts', 0);
    }

    public function test_overall_submission_generates_both_ready_pdfs_from_persisted_decision_and_comments(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $this->finalizeForms($reviewer, $assignment);
        $firstComment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'general',
            'body' => 'The protocol and consent materials are internally consistent.',
            'status' => 'open',
        ]);
        $secondComment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'required_revision',
            'body' => 'Clarify the participant withdrawal language before release.',
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => $reviewer->id,
        ]);
        $decisionComment = 'Approve after preserving the resolved withdrawal-language clarification.';

        $this->actingAs($reviewer)
            ->postJson(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::MinorRevision->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReviewSubmissionStatus::Submitted->value)
            ->assertJsonPath('data.decision', ReviewDecision::MinorRevision->value);

        $review = $assignment->reviewSubmission()->firstOrFail();
        $this->assertSame(ReviewSubmissionStatus::Submitted, $review->status);
        $this->assertSame(ReviewDecision::MinorRevision, $review->decision);
        $this->assertSame($decisionComment, $review->decision_comment);
        $this->assertNotNull($review->submitted_at);
        $this->assertDatabaseHas('review_comments', ['id' => $firstComment->id, 'body' => $firstComment->body]);
        $this->assertDatabaseHas('review_comments', ['id' => $secondComment->id, 'body' => $secondComment->body]);

        $artifacts = ReviewFormArtifact::query()
            ->with('formSubmission')
            ->orderBy('review_form_submission_id')
            ->get();

        $this->assertCount(2, $artifacts);
        $this->assertEqualsCanonicalizing(
            [ReviewFormType::Protocol->value, ReviewFormType::InformedConsent->value],
            $artifacts->map(
                fn (ReviewFormArtifact $artifact): string => $artifact->formSubmission->form_type->value,
            )->all(),
        );

        foreach ($artifacts as $artifact) {
            $bytes = Storage::disk('local')->get($artifact->stored_file_path);
            $this->assertSame(ReviewFormArtifactStatus::Ready, $artifact->status);
            $this->assertSame(1, $artifact->artifact_version);
            $this->assertSame(strlen($bytes), $artifact->file_size_bytes);
            $this->assertSame(hash('sha256', $bytes), $artifact->sha256);
            $this->assertSame(ReviewFormCatalog::TEMPLATE_SHA256, $artifact->template_sha256);
            $this->assertSame(ReviewFormCatalog::GENERATOR_VERSION, $artifact->generator_version);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertStringNotContainsString('/AcroForm', $bytes);
            $this->assertStringNotContainsString('/Widget', $bytes);
            $this->assertStringNotContainsString('/JavaScript', $bytes);

            $parser = new Fpdi;
            $expectedPages = $artifact->formSubmission->form_type === ReviewFormType::Protocol ? 4 : 3;
            $this->assertSame(
                $expectedPages,
                $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)),
            );
        }
    }

    public function test_submission_passes_persisted_decision_and_assignment_comments_to_both_renderers(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $this->finalizeForms($reviewer, $assignment);
        $firstComment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'general',
            'body' => 'FIRST-PERSISTED-ARTIFACT-COMMENT',
            'status' => 'open',
        ]);
        $secondComment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'clarification',
            'body' => 'SECOND-PERSISTED-ARTIFACT-COMMENT',
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_user_id' => $reviewer->id,
        ]);
        $capturedContexts = [];
        $renderer = Mockery::mock(OfficialReviewFormArtifactService::class);
        $renderer->shouldReceive('renderAndStore')
            ->twice()
            ->andReturnUsing(function (
                ReviewFormType $type,
                array $payload,
                array $context,
                int $version,
            ) use (&$capturedContexts): array {
                $capturedContexts[$type->value] = [
                    'payload' => $payload,
                    'context' => $context,
                    'version' => $version,
                ];
                $bytes = "%PDF-1.4\n% ECRATS persisted-context fixture {$type->value}\n%%EOF";
                $path = "review-form-artifacts/tests/persisted-{$type->value}-v{$version}.pdf";
                Storage::disk('local')->put($path, $bytes);

                return $this->artifactData($type, $path, $bytes);
            });
        $this->app->instance(OfficialReviewFormArtifactService::class, $renderer);
        $decisionComment = 'PERSISTED-FINAL-DECISION-COMMENT-4827';

        $this->actingAs($reviewer)
            ->postJson(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::MajorRevision->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertOk();

        $this->assertCount(2, $capturedContexts);

        foreach (ReviewFormType::cases() as $type) {
            $captured = $capturedContexts[$type->value];
            $finalReview = $captured['context']['final_review'];
            $this->assertSame(1, $captured['version']);
            $this->assertSame(ReviewDecision::MajorRevision->value, $finalReview['decision']);
            $this->assertSame(ReviewDecision::MajorRevision->label(), $finalReview['decision_label']);
            $this->assertSame($decisionComment, $finalReview['decision_comment']);
            $this->assertNotNull($finalReview['review_submission_id']);
            $this->assertNotNull($finalReview['submitted_at']);
            $this->assertSame(
                [$firstComment->id, $secondComment->id],
                array_column($finalReview['assignment_comments'], 'id'),
            );
            $this->assertSame(
                [$firstComment->body, $secondComment->body],
                array_column($finalReview['assignment_comments'], 'body'),
            );
            $this->assertSame(
                ['open', 'resolved'],
                array_column($finalReview['assignment_comments'], 'status'),
            );
        }

        $this->assertDatabaseHas('review_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewSubmissionStatus::Submitted->value,
            'decision' => ReviewDecision::MajorRevision->value,
            'decision_comment' => $decisionComment,
        ]);
        $this->assertDatabaseCount('review_form_artifacts', 2);
    }

    public function test_consent_artifact_uses_official_pages_seven_and_eight_plus_the_submission_continuation(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $this->finalizeForms($reviewer, $assignment);
        $this->submitReview($reviewer, $assignment);

        $artifact = ReviewFormArtifact::query()
            ->where('template_code', ReviewFormType::InformedConsent->code())
            ->firstOrFail();
        $parser = new Fpdi;

        $this->assertSame(3, $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)));
        $this->assertSame('KLD-RES-04-002', $artifact->template_code);
    }

    public function test_long_form_decision_and_assignment_comments_are_preserved_on_continuation_pages(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $protocolPayload = $this->finalPayload(ReviewFormType::Protocol);
        $questionComment = trim('QUESTION-CONTINUATION-'.str_repeat('Complete source response ', 35));
        $recommendationComment = trim('RECOMMENDATION-CONTINUATION-'.str_repeat('Required revision detail ', 45));
        $protocolPayload['responses']['protocol_02']['comment'] = $questionComment;
        $protocolPayload['recommendation'] = ReviewDecision::MinorRevision->value;
        $protocolPayload['recommendation_comments'] = $recommendationComment;
        $this->finalizeForms($reviewer, $assignment, [
            ReviewFormType::Protocol->value => $protocolPayload,
        ]);
        $assignmentComment = trim('ASSIGNMENT-CONTINUATION-'.str_repeat('Confidential assignment comment ', 45));
        $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'required_revision',
            'body' => $assignmentComment,
            'status' => 'open',
        ]);
        $decisionComment = trim('DECISION-CONTINUATION-'.str_repeat('Final decision rationale ', 45));

        $this->submitReview(
            $reviewer,
            $assignment,
            ReviewDecision::MinorRevision,
            $decisionComment,
        );

        $artifact = ReviewFormArtifact::query()
            ->where('template_code', ReviewFormType::Protocol->code())
            ->firstOrFail();
        $form = $artifact->formSubmission;
        $parser = new Fpdi;

        $this->assertGreaterThanOrEqual(4, $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)));
        $this->assertSame($questionComment, $form->finalized_payload_snapshot['responses']['protocol_02']['comment']);
        $this->assertSame($recommendationComment, $form->finalized_payload_snapshot['recommendation_comments']);
        $this->assertDatabaseHas('review_comments', [
            'reviewer_assignment_id' => $assignment->id,
            'body' => $assignmentComment,
        ]);
        $this->assertDatabaseHas('review_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'decision_comment' => $decisionComment,
        ]);
    }

    public function test_legacy_ready_artifact_is_hidden_until_submission_then_superseded_by_a_ready_version(): void
    {
        [$reviewer, , , $application, $assignment] = $this->fixture();
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->finalizeForms($reviewer, $assignment);
        $protocol = $assignment->formSubmissions()
            ->where('form_type', ReviewFormType::Protocol)
            ->firstOrFail();
        $legacy = $this->createStoredArtifact(
            $protocol,
            ReviewFormArtifactStatus::Ready,
            1,
            'legacy-pre-submission-v1.pdf',
        );
        $reviewerPreview = route('reviewer.assignments.forms.artifacts.preview', [$assignment, $protocol, $legacy]);
        $resPreview = route('res.applications.review-form-artifacts.preview', [$application, $assignment, $protocol, $legacy]);

        $this->actingAs($reviewer)->get($reviewerPreview)->assertForbidden();
        $this->actingAs($resLead)->get($resPreview)->assertForbidden();
        $this->actingAs($resLead)
            ->get(route('res.applications.show', $application))
            ->assertOk()
            ->assertDontSee('Official Reviewer Forms')
            ->assertDontSee($legacy->original_file_name);

        $this->submitReview($reviewer, $assignment);

        $legacy->refresh();
        $currentProtocol = ReviewFormArtifact::query()
            ->where('review_form_submission_id', $protocol->id)
            ->where('status', ReviewFormArtifactStatus::Ready->value)
            ->firstOrFail();
        $currentConsent = ReviewFormArtifact::query()
            ->whereHas('formSubmission', fn ($forms) => $forms
                ->where('reviewer_assignment_id', $assignment->id)
                ->where('form_type', ReviewFormType::InformedConsent->value))
            ->where('status', ReviewFormArtifactStatus::Ready->value)
            ->firstOrFail();

        $this->assertSame(ReviewFormArtifactStatus::Superseded, $legacy->status);
        $this->assertSame(2, $currentProtocol->artifact_version);
        $this->assertSame(1, $currentConsent->artifact_version);
        $this->assertSame($currentProtocol->id, $protocol->fresh()->artifact->id);
        $this->assertDatabaseCount('review_form_artifacts', 3);
        $this->assertDatabaseHas('review_form_artifacts', [
            'id' => $legacy->id,
            'status' => ReviewFormArtifactStatus::Superseded->value,
            'artifact_version' => 1,
        ]);

        $this->actingAs($resLead)
            ->get(route('res.applications.show', $application))
            ->assertOk()
            ->assertSee('Official Reviewer Forms')
            ->assertSee($currentProtocol->original_file_name)
            ->assertSee($currentConsent->original_file_name)
            ->assertDontSee($legacy->original_file_name);

        // Historical versions remain private and authorized after the parent review is submitted.
        $this->actingAs($resLead)->get($resPreview)->assertOk();
        $this->actingAs($reviewer)->get($reviewerPreview)->assertOk();
    }

    public function test_artifact_routes_enforce_role_parent_current_assignment_and_private_integrity_headers(): void
    {
        [$reviewer, $applicant, $adviser, $application, $assignment] = $this->fixture();
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->finalizeForms($reviewer, $assignment);
        $this->submitReview($reviewer, $assignment);
        $artifact = ReviewFormArtifact::query()
            ->where('template_code', ReviewFormType::Protocol->code())
            ->where('status', ReviewFormArtifactStatus::Ready->value)
            ->firstOrFail();
        $form = $artifact->formSubmission;
        $reviewerPreview = route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $artifact]);

        $response = $this->actingAs($reviewer)->get($reviewerPreview)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('x-frame-options'));

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.forms.artifacts.download', [$assignment, $form, $artifact]))
            ->assertOk();
        $this->actingAs($otherReviewer)->get($reviewerPreview)->assertForbidden();
        $this->actingAs($applicant)->get($reviewerPreview)->assertRedirect(route('dashboard'));
        $this->actingAs($adviser)->get($reviewerPreview)->assertRedirect(route('dashboard'));
        $this->assertFalse(Route::has('applicant.applications.review-form-artifacts.preview'));

        $otherApplication = ResearchApplication::factory()->create();
        $otherAssignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $otherApplication->id,
            'reviewer_user_id' => $reviewer->id,
        ]);
        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.forms.artifacts.preview', [$otherAssignment, $form, $artifact]))
            ->assertNotFound();

        $resPreview = route('res.applications.review-form-artifacts.preview', [$application, $assignment, $form, $artifact]);
        $this->actingAs($resLead)->get($resPreview)->assertOk();
        $this->actingAs($resLead)
            ->get(route('res.applications.review-form-artifacts.preview', [$otherApplication, $assignment, $form, $artifact]))
            ->assertNotFound();

        $assignment->update([
            'assignment_status' => ReviewerAssignmentStatus::Superseded,
            'superseded_at' => now(),
            'supersession_reason' => 'Reassigned for test coverage.',
        ]);
        $this->actingAs($reviewer)->get($reviewerPreview)->assertForbidden();
        // RES retains authorized access to immutable historical artifacts.
        $this->actingAs($resLead)->get($resPreview)->assertOk();

        Storage::disk('local')->put($artifact->stored_file_path, '%PDF-1.4 tampered');
        $this->actingAs($resLead)->get($resPreview)->assertStatus(409);
    }

    public function test_generation_failure_rolls_back_submission_and_artifacts_but_leaves_forms_final(): void
    {
        [$reviewer, , , $application, $assignment] = $this->fixture();
        $this->finalizeForms($reviewer, $assignment);
        $draftDecisionComment = 'Preserve this earlier decision draft after artifact generation fails.';
        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'draft',
                'decision' => ReviewDecision::MinorRevision->value,
                'decision_comment' => $draftDecisionComment,
            ])
            ->assertSessionHasNoErrors();

        $calls = 0;
        $renderer = Mockery::mock(OfficialReviewFormArtifactService::class);
        $renderer->shouldReceive('renderAndStore')
            ->twice()
            ->andReturnUsing(function (
                ReviewFormType $type,
                array $payload,
                array $context,
                int $version,
            ) use (&$calls): array {
                $calls++;

                if ($calls === 2) {
                    throw new OfficialReviewFormGenerationException('Simulated second-renderer failure.');
                }

                $bytes = "%PDF-1.4\n% ECRATS partial artifact\n%%EOF";
                $path = "review-form-artifacts/tests/partial-{$type->value}-v{$version}.pdf";
                Storage::disk('local')->put($path, $bytes);

                return $this->artifactData($type, $path, $bytes);
            });
        $this->app->instance(OfficialReviewFormArtifactService::class, $renderer);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => 'This final submission must roll back when the second PDF fails.',
            ])
            ->assertSessionHasErrorsIn('reviewDecision', ['review']);

        $review = $assignment->reviewSubmission()->firstOrFail();
        $this->assertSame(ReviewSubmissionStatus::Draft, $review->status);
        $this->assertSame(ReviewDecision::MinorRevision, $review->decision);
        $this->assertSame($draftDecisionComment, $review->decision_comment);
        $this->assertNull($review->submitted_at);
        $this->assertSame(
            2,
            $assignment->formSubmissions()->where('status', ReviewFormStatus::Completed->value)->count(),
        );
        $this->assertTrue($assignment->formSubmissions()->get()->every(
            fn (ReviewFormSubmission $form): bool => $form->finalized_payload_snapshot === null
                && $form->finalized_context_snapshot === null,
        ));
        $this->assertSame(ReviewerAssignmentStatus::InReview, $assignment->fresh()->assignment_status);
        $this->assertSame(ApplicationStatus::UnderExpeditedReview, $application->fresh()->application_status);
        $this->assertSame(ApplicationStage::EthicsReview, $application->fresh()->current_stage);
        $this->assertDatabaseCount('review_form_artifacts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @return array{User, User, User, ResearchApplication, ReviewerAssignment} */
    private function fixture(): array
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'name' => 'Artifact Reviewer']);
        $applicant = User::factory()->create(['role' => UserRole::Applicant, 'name' => 'Private Applicant']);
        $adviser = User::factory()->create(['role' => UserRole::Adviser, 'name' => 'Private Adviser']);
        $application = ResearchApplication::factory()->create([
            'applicant_user_id' => $applicant->id,
            'adviser_user_id' => $adviser->id,
            'research_title' => 'Secure Review Artifact Study',
            'institution' => 'Kolehiyo ng Lungsod ng Dasmarinas',
            'application_status' => ApplicationStatus::UnderExpeditedReview,
            'current_stage' => ApplicationStage::EthicsReview,
            'review_type' => 'expedited',
            'submitted_at' => now()->subDays(2),
        ]);
        $assignment = ReviewerAssignment::factory()->create([
            'research_application_id' => $application->id,
            'reviewer_user_id' => $reviewer->id,
            'assigned_at' => now()->subDay(),
        ]);

        return [$reviewer, $applicant, $adviser, $application, $assignment];
    }

    /** @param array<string, array<string, mixed>> $payloads */
    private function finalizeForms(User $reviewer, ReviewerAssignment $assignment, array $payloads = []): void
    {
        foreach (ReviewFormType::cases() as $type) {
            $payload = $payloads[$type->value] ?? $this->finalPayload($type);

            $this->actingAs($reviewer)
                ->putJson(route('reviewer.assignments.forms.update', [$assignment, $type]), $payload)
                ->assertOk()
                ->assertJsonPath('data.status', ReviewFormStatus::Completed->value)
                ->assertJsonPath('data.artifact', null);
        }

        $this->assertDatabaseCount('review_form_artifacts', 0);
    }

    private function submitReview(
        User $reviewer,
        ReviewerAssignment $assignment,
        ReviewDecision $decision = ReviewDecision::Approved,
        string $decisionComment = 'The complete persisted review supports this final decision.',
    ): void {
        $this->actingAs($reviewer)
            ->postJson(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => $decision->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReviewSubmissionStatus::Submitted->value)
            ->assertJsonPath('data.decision', $decision->value);
    }

    /** @return array<string, mixed> */
    private function finalPayload(ReviewFormType $type): array
    {
        return [
            'intent' => 'submit',
            'responses' => $this->completeResponses($type),
            'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
            'recommendation' => ReviewDecision::Approved->value,
            'recommendation_comments' => 'The completed worksheet supports this recommendation.',
        ];
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

    private function createStoredArtifact(
        ReviewFormSubmission $form,
        ReviewFormArtifactStatus $status,
        int $version,
        string $fileName,
    ): ReviewFormArtifact {
        $bytes = "%PDF-1.4\n% ECRATS historical artifact\n%%EOF";
        $path = "review-form-artifacts/tests/{$form->id}-{$version}-{$fileName}";
        Storage::disk('local')->put($path, $bytes);

        return $form->artifacts()->create([
            ...$this->artifactData($form->form_type, $path, $bytes, $fileName),
            'artifact_version' => $version,
            'status' => $status->value,
            'generated_at' => now(),
        ]);
    }

    /** @return array<string, int|string> */
    private function artifactData(
        ReviewFormType $type,
        string $path,
        string $bytes,
        ?string $fileName = null,
    ): array {
        return [
            'stored_file_path' => $path,
            'original_file_name' => $fileName ?? $type->code().'-test.pdf',
            'mime_type' => 'application/pdf',
            'file_size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'template_code' => $type->code(),
            'template_version' => ReviewFormCatalog::CATALOG_VERSION,
            'template_sha256' => ReviewFormCatalog::TEMPLATE_SHA256,
            'generator_version' => ReviewFormCatalog::GENERATOR_VERSION,
        ];
    }
}
