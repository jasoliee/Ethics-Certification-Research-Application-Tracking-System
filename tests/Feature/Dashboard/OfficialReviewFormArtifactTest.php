<?php

namespace Tests\Feature\Dashboard;

use App\Enums\ApplicationStage;
use App\Enums\ApplicationStatus;
use App\Enums\ReviewDecision;
use App\Enums\ReviewerAssignmentStatus;
use App\Enums\ReviewFormArtifactStatus;
use App\Enums\ReviewFormStatus;
use App\Enums\ReviewFormType;
use App\Enums\UserRole;
use App\Exceptions\OfficialReviewFormGenerationException;
use App\Models\DeadlineConfiguration;
use App\Models\ResearchApplication;
use App\Models\ReviewerAssignment;
use App\Models\ReviewFormArtifact;
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

    public function test_finalization_creates_an_integrity_checked_flattened_official_pdf_idempotently(): void
    {
        [$reviewer, $applicant, $adviser, $application, $assignment] = $this->fixture();
        $payload = $this->finalPayload(ReviewFormType::Protocol);

        $first = $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $payload)
            ->assertOk()
            ->assertJsonPath('data.status', ReviewFormStatus::Final->value)
            ->assertJsonPath('data.artifact.status', ReviewFormArtifactStatus::Ready->value)
            ->assertJsonPath('data.artifact.version', 1);

        $artifact = ReviewFormArtifact::query()->firstOrFail();
        $form = $artifact->formSubmission;
        $bytes = Storage::disk('local')->get($artifact->stored_file_path);

        $this->assertSame($artifact->id, $first->json('data.artifact.id'));
        $this->assertSame(strlen($bytes), $artifact->file_size_bytes);
        $this->assertSame(hash('sha256', $bytes), $artifact->sha256);
        $this->assertSame(ReviewFormCatalog::TEMPLATE_SHA256, $artifact->template_sha256);
        $this->assertSame(ReviewFormCatalog::CATALOG_VERSION, $artifact->template_version);
        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertStringNotContainsString('/AcroForm', $bytes);
        $this->assertStringNotContainsString('/Widget', $bytes);
        $this->assertStringNotContainsString('/JavaScript', $bytes);

        $parser = new Fpdi;
        $this->assertSame(3, $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)));
        $this->assertSame($application->research_title, $form->finalized_context_snapshot['research_title']);
        $this->assertSame($application->institution, $form->finalized_context_snapshot['institution']);
        $this->assertSame('authenticated_electronic_attestation', $form->finalized_context_snapshot['attestation']['method']);
        $this->assertStringNotContainsString($applicant->name, json_encode($form->finalized_context_snapshot, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($adviser->name, json_encode($form->finalized_context_snapshot, JSON_THROW_ON_ERROR));

        // A retried final request is idempotent and returns the immutable artifact.
        $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $payload)
            ->assertOk()
            ->assertJsonPath('data.artifact.id', $artifact->id);
        $this->assertDatabaseCount('review_form_artifacts', 1);
        $this->assertSame($artifact->sha256, ReviewFormArtifact::query()->firstOrFail()->sha256);
    }

    public function test_consent_form_uses_only_official_pages_seven_and_eight(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();

        $this->actingAs($reviewer)
            ->putJson(
                route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::InformedConsent]),
                $this->finalPayload(ReviewFormType::InformedConsent),
            )
            ->assertOk();

        $artifact = ReviewFormArtifact::query()->firstOrFail();
        $parser = new Fpdi;

        $this->assertSame(2, $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)));
        $this->assertSame('KLD-RES-04-002', $artifact->template_code);
    }

    public function test_long_printed_responses_are_preserved_in_a_flattened_continuation_page(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $payload = $this->finalPayload(ReviewFormType::Protocol);
        $questionComment = trim('QUESTION-CONTINUATION-'.str_repeat('Complete source response ', 35));
        $recommendationComment = trim('RECOMMENDATION-CONTINUATION-'.str_repeat('Required revision detail ', 45));
        $payload['responses']['protocol_02']['comment'] = $questionComment;
        $payload['recommendation'] = ReviewDecision::MinorRevision->value;
        $payload['recommendation_comments'] = $recommendationComment;

        $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $payload)
            ->assertOk();

        $artifact = ReviewFormArtifact::query()->firstOrFail();
        $form = $artifact->formSubmission;
        $parser = new Fpdi;

        $this->assertSame(4, $parser->setSourceFile(Storage::disk('local')->path($artifact->stored_file_path)));
        $this->assertSame($questionComment, $form->finalized_payload_snapshot['responses']['protocol_02']['comment']);
        $this->assertSame($recommendationComment, $form->finalized_payload_snapshot['recommendation_comments']);
    }

    public function test_artifact_routes_enforce_role_parent_current_assignment_and_private_headers(): void
    {
        [$reviewer, $applicant, $adviser, $application, $assignment] = $this->fixture();
        $otherReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $resLead = User::factory()->create(['role' => UserRole::ResLead]);
        $this->actingAs($reviewer)
            ->putJson(
                route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]),
                $this->finalPayload(ReviewFormType::Protocol),
            )
            ->assertOk();
        $artifact = ReviewFormArtifact::query()->firstOrFail();
        $form = $artifact->formSubmission;
        $reviewerPreview = route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $artifact]);

        $response = $this->actingAs($reviewer)->get($reviewerPreview)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));

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

    public function test_generation_failure_rolls_back_finalization_and_final_rows_without_ready_artifacts_do_not_gate_decisions(): void
    {
        [$reviewer, , , , $assignment] = $this->fixture();
        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), [
                'intent' => 'draft',
                'responses' => ['protocol_01' => ['answer' => 'no', 'comment' => 'Preserved draft response.']],
            ])
            ->assertSessionHasNoErrors();

        $renderer = Mockery::mock(OfficialReviewFormArtifactService::class);
        $renderer->shouldReceive('renderAndStore')
            ->once()
            ->andThrow(new OfficialReviewFormGenerationException('Simulated renderer failure.'));
        $this->app->instance(OfficialReviewFormArtifactService::class, $renderer);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol]), $this->finalPayload(ReviewFormType::Protocol))
            ->assertSessionHasErrorsIn('reviewerForm', ['form']);

        $form = $assignment->formSubmissions()->where('form_type', ReviewFormType::Protocol)->firstOrFail();
        $this->assertSame(ReviewFormStatus::Draft, $form->status);
        $this->assertSame('no', $form->responses['protocol_01']['answer']);
        $this->assertDatabaseCount('review_form_artifacts', 0);

        foreach (ReviewFormType::cases() as $type) {
            $assignment->formSubmissions()->updateOrCreate(
                ['form_type' => $type->value],
                [
                    'status' => ReviewFormStatus::Final,
                    'responses' => $this->completeResponses($type),
                    'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
                    'recommendation' => ReviewDecision::Approved,
                    'finalized_at' => now(),
                ],
            );
        }

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => 'The forms are final rows but have no verified artifacts.',
            ])
            ->assertSessionHasErrorsIn('reviewDecision', ['forms']);
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

    /** @return array<string, mixed> */
    private function finalPayload(ReviewFormType $type): array
    {
        return [
            'intent' => 'final',
            'responses' => $this->completeResponses($type),
            'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
            'recommendation' => ReviewDecision::Approved->value,
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
}
