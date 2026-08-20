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
use App\Services\Applications\OfficialReviewFormArtifactService;
use App\Support\ReviewFormCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ReviewerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $renderer = Mockery::mock(OfficialReviewFormArtifactService::class);
        $renderer->shouldReceive('renderAndStore')->andReturnUsing(
            function (ReviewFormType $type, array $payload, array $context, int $version): array {
                $path = 'review-form-artifacts/tests/'.Str::uuid().'.pdf';
                $bytes = "%PDF-1.4\n% test official review artifact\n";
                Storage::disk('local')->put($path, $bytes);

                return [
                    'stored_file_path' => $path,
                    'original_file_name' => $type->code().'-test-v'.$version.'.pdf',
                    'mime_type' => 'application/pdf',
                    'file_size_bytes' => strlen($bytes),
                    'sha256' => hash('sha256', $bytes),
                    'template_code' => $type->code(),
                    'template_version' => ReviewFormCatalog::CATALOG_VERSION,
                    'template_sha256' => ReviewFormCatalog::TEMPLATE_SHA256,
                    'generator_version' => ReviewFormCatalog::GENERATOR_VERSION,
                ];
            },
        );
        $this->app->instance(OfficialReviewFormArtifactService::class, $renderer);
    }

    public function test_assignment_owner_can_open_blind_documents_without_a_conflict_declaration(): void
    {
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
            ->assertSee('data-reviewer-review-studio', false)
            ->assertSee('data-reviewer-document-frame', false)
            ->assertSee('data-reviewer-comment-form', false)
            ->assertSee('data-reviewer-comment-category', false)
            ->assertSee('Entire Application')
            ->assertDontSee('data-reviewer-comment-scope', false)
            ->assertSeeInOrder(['reviewer-document-library', 'reviewer-document-pane', 'reviewer-review-rail'], false)
            ->assertSeeInOrder(['Review Comment', 'Review Worksheet', 'Review Assessment'])
            ->assertSee('data-reviewer-worksheet-accordion', false)
            ->assertDontSee('data-reviewer-inline-forms', false)
            ->assertSee('data-reviewer-form-dialog="protocol"', false)
            ->assertSee('data-reviewer-form-dialog="informed_consent"', false)
            ->assertSee('data-reviewer-form-open="protocol"', false)
            ->assertSee('data-reviewer-form-open="informed_consent"', false)
            ->assertSee('data-reviewer-form-status="protocol"', false)
            ->assertSee('data-reviewer-form-progress="protocol"', false)
            ->assertSee('0 of 15 items completed')
            ->assertSee('data-reviewer-form-progress-bar', false)
            ->assertSee('aria-labelledby="reviewer-form-protocol-progress-label"', false)
            ->assertSee('aria-label="View Protocol Review Worksheet"', false)
            ->assertSee('aria-label="View Informed Consent Checklist"', false)
            ->assertSee('data-reviewer-consent-gate', false)
            ->assertSee('class="reviewer-form-recommendation-options"', false)
            ->assertSee('data-reviewer-document-frame-shell aria-busy="true"', false)
            ->assertDontSee('role="listitem"', false)
            ->assertSee('<dt>Date Received</dt>', false)
            ->assertDontSee('Page Comment')
            ->assertDontSee('data-reviewer-page-comment', false)
            ->assertDontSee('<dt>Institution</dt>', false)
            ->assertDontSee('<dt>Reviewer</dt>', false)
            ->assertDontSee('<dt>Researcher / Study Leader</dt>', false)
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

    public function test_only_a_writable_assignment_owner_receives_the_accessible_comment_action_menu(): void
    {
        $deadline = $this->openReviewWindow();
        [$reviewer, , , , $assignment] = $this->assignmentFixture();
        $comment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'general',
            'body' => 'This comment exposes owner actions while the review remains writable.',
            'status' => 'open',
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertOk()
            ->assertSee('data-comment-id="'.$comment->id.'"', false)
            ->assertSee('data-reviewer-comment-menu-toggle', false)
            ->assertSee('aria-haspopup="menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('role="menu"', false);

        $deadline->update([
            'starts_at' => now()->subDays(2),
            'due_at' => now()->subMinute(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertOk()
            ->assertSee($comment->body)
            ->assertDontSee('data-reviewer-comment-menu-toggle', false)
            ->assertDontSee('role="menu"', false);
    }

    public function test_historical_page_comments_do_not_offer_the_unsafe_edit_action(): void
    {
        [, , , , $assignment, $document] = $this->assignmentFixture();
        $overallComment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'general',
            'body' => 'An overall comment remains editable.',
            'status' => 'open',
        ]);
        $pageComment = $assignment->comments()->create([
            'application_document_id' => $document->id,
            'scope' => 'page',
            'category' => 'general',
            'page_number' => 3,
            'body' => 'A historical page-scoped comment remains actionable but immutable.',
            'status' => 'open',
        ]);

        $overallHtml = view('dashboard.assignments.partials.comment-item', [
            'assignment' => $assignment,
            'comment' => $overallComment,
            'canWrite' => true,
        ])->render();
        $pageHtml = view('dashboard.assignments.partials.comment-item', [
            'assignment' => $assignment,
            'comment' => $pageComment,
            'canWrite' => true,
        ])->render();

        $this->assertStringContainsString('data-reviewer-comment-edit', $overallHtml);
        $this->assertStringNotContainsString('data-reviewer-comment-edit', $pageHtml);
        $this->assertStringContainsString('data-reviewer-comment-action-form="status"', $pageHtml);
        $this->assertStringContainsString('data-reviewer-comment-action-form="delete"', $pageHtml);
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
        [$reviewer, $applicant, , $application, $assignment, $document] = $this->assignmentFixture();
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
            'application_document_id' => null,
            'scope' => 'overall',
            'body' => $comment,
        ]);
        $assignment->comments()->where('body', $comment)->delete();

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'document',
                'category' => 'required_revision',
                'application_document_id' => $document->id,
                'body' => $comment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('review_comments', [
            'reviewer_assignment_id' => $assignment->id,
            'application_document_id' => $document->id,
            'body' => $comment,
            'released_at' => null,
            'status' => 'open',
        ]);
        $storedComment = $assignment->comments()->firstOrFail();
        $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.comments.update', [$assignment, $storedComment]), [
                'scope' => 'document',
                'category' => 'required_revision',
                'application_document_id' => $document->id,
                'body' => $comment.' updated',
            ])
            ->assertOk()
            ->assertJsonPath('data.body', $comment.' updated');
        $this->actingAs($reviewer)
            ->patchJson(route('reviewer.assignments.comments.status', [$assignment, $storedComment]), [
                'status' => 'resolved',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.id', $storedComment->id)
            ->assertJson(fn ($json) => $json->whereType('data.html', 'string')->etc());

        $unsafeComment = '<script>alert("review-comment-xss")</script>';
        $asyncResponse = $this->actingAs($reviewer)
            ->postJson(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'document',
                'category' => 'clarification',
                'application_document_id' => $application->documents()->firstOrFail()->id,
                'body' => $unsafeComment,
            ])
            ->assertCreated()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.scope', 'document')
            ->assertJsonPath('data.page_number', null)
            ->assertSee('data-reviewer-comment-item', false);

        $asyncHtml = $asyncResponse->json('data.html');
        $this->assertIsString($asyncHtml);
        $this->assertStringNotContainsString('<script>', $asyncHtml);
        $this->assertStringNotContainsString($unsafeComment, $asyncHtml);
        $this->assertStringContainsString('&lt;script&gt;', $asyncHtml);

        $asyncComment = $assignment->comments()->findOrFail($asyncResponse->json('data.id'));
        $this->actingAs($reviewer)
            ->deleteJson(route('reviewer.assignments.comments.destroy', [$assignment, $asyncComment]))
            ->assertOk()
            ->assertJsonPath('data.deleted_id', $asyncComment->id)
            ->assertJsonPath('data.count', 1);
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

    public function test_workspace_bounds_initial_comments_and_loads_older_assignment_owned_history(): void
    {
        $this->openReviewWindow();
        [$reviewer, , , $application, $assignment, $document] = $this->assignmentFixture();
        $foreignReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $comments = collect();

        foreach (range(1, 45) as $index) {
            $comments->push($assignment->comments()->create([
                'application_document_id' => $index === 1 ? $document->id : null,
                'scope' => $index === 1 ? 'page' : 'overall',
                'category' => 'general',
                'page_number' => $index === 1 ? 4 : null,
                'body' => sprintf('COMMENT-HISTORY-TOKEN-%02d', $index),
                'status' => 'open',
            ]));
        }

        $this->actingAs($reviewer)
            ->get(route('reviewer.assignments.workspace', $assignment))
            ->assertOk()
            ->assertSee('<dt>Research Title</dt><dd>'.$application->research_title.'</dd>', false)
            ->assertSee('45 recorded')
            ->assertSee('COMMENT-HISTORY-TOKEN-45')
            ->assertSee('COMMENT-HISTORY-TOKEN-26')
            ->assertDontSee('COMMENT-HISTORY-TOKEN-25')
            ->assertSee('data-reviewer-comments-load', false)
            ->assertSee('data-before-id="'.$comments[25]->id.'"', false);

        $older = $this->actingAs($reviewer)
            ->getJson(route('reviewer.assignments.comments.index', [
                $assignment,
                'before_id' => $comments[25]->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.count', 45)
            ->assertJsonPath('data.has_more', true)
            ->assertJsonPath('data.next_before_id', $comments[5]->id)
            ->assertJsonCount(20, 'data.items')
            ->assertJsonPath('data.items.0.id', $comments[24]->id)
            ->assertJsonPath('data.items.19.id', $comments[5]->id);

        $oldest = $this->actingAs($reviewer)
            ->getJson(route('reviewer.assignments.comments.index', [
                $assignment,
                'before_id' => $older->json('data.next_before_id'),
            ]))
            ->assertOk()
            ->assertJsonPath('data.count', 45)
            ->assertJsonPath('data.has_more', false)
            ->assertJsonPath('data.next_before_id', null)
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.items.0.id', $comments[4]->id)
            ->assertJsonPath('data.items.4.id', $comments[0]->id);

        $this->assertStringContainsString('COMMENT-HISTORY-TOKEN-01', $oldest->json('data.items.4.html'));
        $this->assertStringContainsString('Page 4', $oldest->json('data.items.4.html'));

        $this->actingAs($foreignReviewer)
            ->getJson(route('reviewer.assignments.comments.index', [
                $assignment,
                'before_id' => $comments[25]->id,
            ]))
            ->assertForbidden();
    }

    public function test_comment_ui_exposes_bounded_history_and_in_flight_feedback_contracts(): void
    {
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $javascript = (string) file_get_contents(resource_path('js/dashboard.js'));

        $this->assertMatchesRegularExpression(
            '/\.reviewer-comment-list\s*\{[^}]*max-height:[^;]+;[^}]*overflow-y:\s*auto;/s',
            $css,
        );
        $this->assertStringContainsString('commentRequestInFlight', $javascript);
        $this->assertStringContainsString('commentActionsInFlight', $javascript);
        $this->assertStringContainsString('olderCommentsRequestInFlight', $javascript);
        $this->assertStringContainsString('Delete this review comment? This action cannot be undone.', $javascript);
        $this->assertStringContainsString("setCommentsHistoryFeedback('Loading older comments...')", $javascript);
        $this->assertStringContainsString('grid-auto-rows: max-content;', $css);
        $this->assertStringContainsString("namedItem('category')", $javascript);
        $this->assertStringNotContainsString("scope.value = 'document';", $javascript);
        $this->assertStringNotContainsString('data-reviewer-comment-scope', $javascript);
        $this->assertStringContainsString('Comment reference set to ${choice.dataset.documentRequirement}.', $javascript);
    }

    public function test_workspace_ui_exposes_preview_form_and_modal_accessibility_contracts(): void
    {
        $blade = (string) file_get_contents(resource_path('views/dashboard/assignments/workspace.blade.php'));
        $css = (string) file_get_contents(resource_path('css/dashboard.css'));
        $javascript = (string) file_get_contents(resource_path('js/dashboard.js'));

        $this->assertStringContainsString("{{ \$item['printed_number'] ?? \$loop->iteration }}", $blade);
        $this->assertMatchesRegularExpression(
            '/name="consent_required" value="1".*Yes<\/label>\s*<label><input[^>]+value="0".*No<\/label>/s',
            $blade,
        );
        $this->assertStringContainsString('data-reviewer-consent-explanation', $blade);
        $this->assertStringContainsString('class="reviewer-form-recommendation-options"', $blade);
        $this->assertStringContainsString("\$statusLabel = \$formIsFinal ? 'Final' : (\$formCompleted ? 'Completed' : (\$form ? 'In Progress' : 'Not Started'));", $blade);
        $this->assertStringNotContainsString("\$formIsFinal ? 'Complete' : (\$form ? 'Draft Saved'", $blade);
        $this->assertStringContainsString("\$openLabel = ! \$canWrite ? 'View' : (\$formCompleted ? 'Edit' : (\$form ? 'Continue' : 'Start'));", $blade);
        $this->assertStringContainsString('aria-label="{{ $openLabel }} {{ $type->label() }}"', $blade);
        $this->assertStringContainsString('data-reviewer-form-submit-final>Submit', $blade);
        $this->assertStringContainsString('data-reviewer-consent-dependent', $blade);
        $this->assertStringContainsString('minlength="15"', $blade);
        $this->assertStringContainsString('aria-labelledby="reviewer-form-{{ $type->value }}-progress-label"', $blade);
        $this->assertStringContainsString('data-reviewer-submit-dialog', $blade);
        $this->assertStringContainsString('data-reviewer-submit-confirmation', $blade);
        $this->assertStringContainsString('data-reviewer-submit-result', $blade);
        $this->assertStringContainsString('A permanent submission version will be created.', $blade);
        $this->assertStringContainsString('You may continue editing and submit a newer version until RES releases the decision.', $blade);

        $this->assertStringContainsString('draftSaveInFlight', $javascript);
        $this->assertStringContainsString('setWorksheetControlsLocked', $javascript);
        $this->assertStringContainsString('syncConsentExplanation', $javascript);
        $this->assertStringContainsString('syncModalEnvironment', $javascript);
        $this->assertStringContainsString('modalFocusSelector', $javascript);
        $this->assertStringContainsString('documentPreviewRequestId', $javascript);
        $this->assertStringContainsString("documentLoading.textContent = 'Loading secure preview...'", $javascript);
        $this->assertStringContainsString("status.textContent = completed ? 'Completed' : 'In Progress';", $javascript);
        $this->assertStringContainsString('finalSubmissionInFlight', $javascript);
        $this->assertStringContainsString('validateFinalReview', $javascript);
        $this->assertStringNotContainsString('Submit this final review? Submitted forms, comments, and the decision can no longer be changed.', $javascript);
        $this->assertStringContainsString('.ecrats-dashboard-body.has-application-modal-open', $css);
        $this->assertStringContainsString('.reviewer-form-recommendation-options', $css);
        $this->assertStringContainsString('.reviewer-submit-modal', $css);
        $this->assertStringContainsString('.reviewer-submit-summary', $css);
    }

    public function test_foreign_reviewer_cannot_mutate_comments_or_save_an_official_form(): void
    {
        $this->openReviewWindow();
        [$owner, , , , $assignment] = $this->assignmentFixture();
        $foreignReviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $comment = $assignment->comments()->create([
            'scope' => 'overall',
            'category' => 'general',
            'body' => 'Only the assigned reviewer may change this confidential comment.',
            'status' => 'open',
        ]);

        $this->actingAs($foreignReviewer)
            ->postJson(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'A foreign reviewer must not add a comment.',
            ])
            ->assertForbidden();

        $this->actingAs($foreignReviewer)
            ->putJson(route('reviewer.assignments.comments.update', [$assignment, $comment]), [
                'scope' => 'overall',
                'category' => 'required_revision',
                'body' => 'A foreign reviewer must not edit an owner comment.',
            ])
            ->assertForbidden();

        $this->actingAs($foreignReviewer)
            ->patchJson(route('reviewer.assignments.comments.status', [$assignment, $comment]), [
                'status' => 'resolved',
            ])
            ->assertForbidden();

        $this->actingAs($foreignReviewer)
            ->deleteJson(route('reviewer.assignments.comments.destroy', [$assignment, $comment]))
            ->assertForbidden();

        $this->actingAs($foreignReviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'draft',
                'responses' => [
                    'protocol_01' => ['answer' => 'yes', 'comment' => 'Unauthorized draft attempt.'],
                ],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('review_comments', [
            'id' => $comment->id,
            'reviewer_assignment_id' => $assignment->id,
            'category' => 'general',
            'status' => 'open',
            'body' => $comment->body,
        ]);
        $this->assertDatabaseCount('review_form_submissions', 0);
        $this->assertSame($owner->id, $assignment->reviewer_user_id);
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

    public function test_official_forms_support_editable_completed_worksheets_until_overall_submission(): void
    {
        $this->openReviewWindow();
        [$reviewer, , , , $assignment] = $this->assignmentFixture();

        $draftResponse = $this->actingAs($reviewer)
            ->putJson(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'draft',
                'responses' => [
                    'protocol_01' => ['answer' => 'yes', 'comment' => 'Initial assessment.'],
                    'protocol_02' => ['answer' => 'unable_to_assess', 'comment' => null],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.form_type', ReviewFormType::Protocol->value)
            ->assertJsonPath('data.status', ReviewFormStatus::Draft->value)
            ->assertJsonPath('data.answered_items', 2)
            ->assertJsonPath('data.total_items', count(ReviewFormCatalog::questions(ReviewFormType::Protocol)));
        $this->assertNull($draftResponse->json('data.finalized_at'));
        $this->assertDatabaseHas('review_form_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'form_type' => ReviewFormType::Protocol->value,
            'status' => ReviewFormStatus::Draft->value,
        ]);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'submit',
                'responses' => [
                    'protocol_01' => ['answer' => 'yes', 'comment' => null],
                ],
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'Complete recommendation comments.',
            ])
            ->assertSessionHasErrorsIn('reviewerForm', ['responses.protocol_02.answer']);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'submit',
                'responses' => $this->completeResponses(ReviewFormType::Protocol),
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'Complete recommendation comments.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::InformedConsent->value]), [
                'intent' => 'submit',
                'consent_required' => '0',
                'consent_not_required_explanation' => 'The approved protocol uses only fully anonymized secondary records.',
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'Complete recommendation comments.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            $assignment->formSubmissions()->where('status', ReviewFormStatus::Completed->value)->count(),
        );
        $protocol = $assignment->formSubmissions()->where('form_type', ReviewFormType::Protocol->value)->firstOrFail();
        $this->assertNull($protocol->catalog_version);
        $this->assertNull($protocol->catalog_snapshot);
        $this->assertNull($protocol->finalized_payload_snapshot);
        $this->assertNull($protocol->finalized_context_snapshot);
        $this->assertNotNull($protocol->completed_at);
        $this->assertNull($protocol->artifact);
        $this->assertDatabaseCount('review_form_artifacts', 0);
        $this->assertCount(15, ReviewFormCatalog::questions(ReviewFormType::InformedConsent));

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'submit',
                'responses' => $this->completeResponses(ReviewFormType::Protocol),
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'Updated completed recommendation comments.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            'Updated completed recommendation comments.',
            $protocol->refresh()->recommendation_comments,
        );
    }

    public function test_final_decision_versions_resubmissions_and_remains_editable_until_res_release(): void
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
            ->postJson(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', ReviewSubmissionStatus::Submitted->value)
            ->assertJsonPath('data.decision', ReviewDecision::Approved->value)
            ->assertJsonPath('data.decision_label', ReviewDecision::Approved->label())
            ->assertJsonPath('data.redirect_url', route('reviewer.assignments.show', $assignment));

        $this->assertDatabaseHas('review_submissions', [
            'reviewer_assignment_id' => $assignment->id,
            'status' => ReviewSubmissionStatus::Submitted->value,
            'decision' => ReviewDecision::Approved->value,
        ]);
        $this->assertSame(2, $assignment->formSubmissions()->withCount('artifacts')->get()->sum('artifacts_count'));
        $this->assertSame(2, $assignment->formSubmissions()->where('status', ReviewFormStatus::Final->value)->count());
        $this->assertTrue($assignment->formSubmissions()->get()->every(
            fn ($form): bool => $form->finalized_payload_snapshot !== null
                && $form->finalized_context_snapshot !== null,
        ));
        $this->assertSame(ReviewerAssignmentStatus::DecisionSubmitted, $assignment->fresh()->assignment_status);
        $this->assertSame(ApplicationStatus::ReviewSubmittedPendingRelease, $application->fresh()->application_status);
        $this->assertSame(ApplicationStage::DecisionRelease, $application->fresh()->current_stage);
        $firstVersion = $assignment->reviewSubmission()->firstOrFail()->currentVersion()->firstOrFail();
        $this->assertSame(1, $firstVersion->version_number);

        $this->actingAs($reviewer)
            ->postJson(route('reviewer.assignments.review.store', $assignment), [
                'intent' => 'submit',
                'decision' => ReviewDecision::Approved->value,
                'decision_comment' => $decisionComment,
            ])
            ->assertOk();
        $this->assertSame(2, $assignment->reviewSubmission()->firstOrFail()->versions()->count());
        $this->assertSame(1, $firstVersion->refresh()->version_number);

        $this->actingAs($reviewer)
            ->post(route('reviewer.assignments.comments.store', $assignment), [
                'scope' => 'overall',
                'category' => 'general',
                'body' => 'This amendment remains private until it is re-submitted.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertTrue($assignment->reviewSubmission()->firstOrFail()->has_unsubmitted_changes);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'draft',
                'responses' => $this->completeResponses(ReviewFormType::Protocol),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($applicant)
            ->get(route('applicant.applications.show', $application))
            ->assertOk()
            ->assertDontSee($decisionComment);

        $audit = AuditLog::query()->where('action', 'review.decision_submitted')->firstOrFail();
        $this->assertArrayNotHasKey('decision_comment', $audit->metadata);
        $this->assertStringNotContainsString($decisionComment, json_encode($audit->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_informed_consent_no_clears_dependent_answers_and_yes_requires_them(): void
    {
        $this->openReviewWindow();
        [$reviewer, , , , $assignment] = $this->assignmentFixture();

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::InformedConsent->value]), [
                'intent' => 'submit',
                'consent_required' => '1',
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'This recommendation is sufficiently detailed.',
            ])
            ->assertSessionHasErrorsIn('reviewerForm', ['responses.consent_01.answer']);

        $this->actingAs($reviewer)
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::InformedConsent->value]), [
                'intent' => 'submit',
                'consent_required' => '0',
                'consent_not_required_explanation' => 'Only previously anonymized aggregate records are used.',
                'responses' => $this->completeResponses(ReviewFormType::InformedConsent),
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'This recommendation is sufficiently detailed.',
            ])
            ->assertSessionHasNoErrors();

        $form = $assignment->formSubmissions()
            ->where('form_type', ReviewFormType::InformedConsent->value)
            ->firstOrFail();
        $this->assertSame(ReviewFormStatus::Completed, $form->status);
        $this->assertNull($form->responses);
        $this->assertSame(
            'Only previously anonymized aggregate records are used.',
            $form->consent_not_required_explanation,
        );
    }

    public function test_recommendation_comments_require_fifteen_non_whitespace_characters(): void
    {
        $this->openReviewWindow();
        [$reviewer, , , , $assignment] = $this->assignmentFixture();
        $value = "a b c d e f g h i j k l m n\n";

        $this->actingAs($reviewer)
            ->from(route('reviewer.assignments.workspace', $assignment))
            ->put(route('reviewer.assignments.forms.update', [$assignment, ReviewFormType::Protocol->value]), [
                'intent' => 'submit',
                'responses' => $this->completeResponses(ReviewFormType::Protocol),
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => $value,
            ])
            ->assertSessionHasErrorsIn('reviewerForm', ['recommendation_comments'])
            ->assertSessionHasInput('recommendation_comments', trim($value));

        $this->assertDatabaseCount('review_form_submissions', 0);
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
            $payload = [
                'responses' => $this->completeResponses($type),
                'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
                'consent_not_required_explanation' => null,
                'recommendation' => ReviewDecision::Approved->value,
                'recommendation_comments' => 'Complete recommendation comments.',
            ];
            $assignment->formSubmissions()->create([
                'form_type' => $type,
                'status' => ReviewFormStatus::Completed,
                'responses' => $payload['responses'],
                'consent_required' => $type === ReviewFormType::InformedConsent ? true : null,
                'recommendation' => ReviewDecision::Approved,
                'recommendation_comments' => $payload['recommendation_comments'],
                'completed_at' => now(),
            ]);
        }
    }
}
