@extends('layouts.dashboard')

@section('content')
    @php
        $application = $assignment->researchApplication;
        $review = $assignment->reviewSubmission;
        $reviewSubmitted = $review?->status === \App\Enums\ReviewSubmissionStatus::Submitted;
        $completedForms = $forms->filter(fn ($form) => $form->status === \App\Enums\ReviewFormStatus::Final)->count();
    @endphp

    <div class="dashboard-page reviewer-assignment-detail-page reviewer-workspace-page">
        <header class="dashboard-page-heading reviewer-assignment-detail-heading">
            <div>
                <h1>Review Workspace</h1>
                <p>Complete the assigned blind review, record comments, and submit your recommendation.</p>
            </div>
            <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.show', $assignment) }}">
                <x-dashboard.icon name="arrow-left" size="17" />
                <span>Back to Assignment</span>
            </a>
        </header>

        {{-- The workspace omits Applicant and Adviser account identity and never exposes private storage paths. --}}
        <section class="reviewer-confidentiality-banner" role="note">
            <span><x-dashboard.icon name="lock" size="20" /></span>
            <div>
                <strong>Confidential blind review</strong>
                <p>Use the application code when referring to this study. Review content and comments remain restricted until an official RES release.</p>
            </div>
        </section>

        @unless ($reviewWindow['open'])
            <section class="reviewer-window-notice" role="status">
                <x-dashboard.icon name="clock" size="20" />
                <div><strong>Review work is read-only</strong><span>{{ $reviewWindow['message'] }}</span></div>
            </section>
        @endunless

        @if ($errors->reviewerWorkflow->any())
            <div class="res-form-error-summary" role="alert">
                <x-dashboard.icon name="alert-triangle" size="19" />
                <div><strong>Review work was not saved.</strong><span>{{ $errors->reviewerWorkflow->first() }}</span></div>
            </div>
        @endif

        <div class="reviewer-workspace-summary-grid">
            <section class="application-panel">
                <header class="application-panel-heading"><div><h2>Application Overview</h2></div></header>
                <dl class="reviewer-assignment-details">
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Research Type</dt><dd>{{ $application->research_type?->label() ?? 'Not specified' }}</dd></div>
                    <div><dt>Review Type</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                    <div><dt>Deadline</dt><dd>{{ $assignment->review_deadline_at?->format('M j, Y g:i A') ?? 'Not configured' }}</dd></div>
                    <div class="reviewer-assignment-detail-wide"><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                </dl>
            </section>

            <section class="application-panel">
                <header class="application-panel-heading"><div><h2>Study Information</h2></div></header>
                <dl class="reviewer-assignment-details">
                    <div><dt>Research Category</dt><dd>{{ $application->research_category ?: 'Not specified' }}</dd></div>
                    <div><dt>Participant Group</dt><dd>{{ $application->target_participants ?: 'Not specified' }}</dd></div>
                    <div><dt>Expected Duration</dt><dd>{{ $application->expectedDurationLabel() }}</dd></div>
                    <div class="reviewer-assignment-detail-wide"><dt>Study Overview</dt><dd>{{ $application->abstract ?: 'Not provided' }}</dd></div>
                </dl>
            </section>
        </div>

        <section class="application-panel reviewer-workspace-documents">
            <header class="application-panel-heading">
                <div><h2>Supporting Documents</h2><p>Preview or download the current private versions for this assignment.</p></div>
            </header>
            @if ($application->documents->isEmpty())
                <x-dashboard.empty-state
                    image="no-requirements"
                    alt="No supporting documents"
                    title="No supporting documents available"
                    message="No current requirement document is attached to this assignment."
                />
            @else
                <x-dashboard.overflow label="Blind review supporting documents" wide>
                    <table class="dashboard-table reviewer-document-table">
                        <thead><tr><th>Requirement</th><th>Document</th><th>Version</th><th>Uploaded</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($application->documents as $document)
                                <tr>
                                    <td>{{ $document->requirement?->name ?? 'Supporting Document' }}</td>
                                    <td><strong data-table-tooltip="{{ $document->original_file_name }}">{{ $document->original_file_name }}</strong></td>
                                    <td>v{{ $document->document_version }}</td>
                                    <td>{{ $document->uploaded_at?->format('M j, Y') ?? 'Not recorded' }}</td>
                                    <td class="dashboard-table-action reviewer-document-actions">
                                        <button
                                            class="dashboard-icon-action"
                                            type="button"
                                            title="View document"
                                            aria-label="View {{ $document->original_file_name }}"
                                            data-document-open
                                            data-document-name="{{ $document->original_file_name }}"
                                            data-document-meta="{{ $document->requirement?->name ?? 'Supporting Document' }}"
                                            data-document-preview-url="{{ route('reviewer.applications.documents.preview', [$application, $document]) }}"
                                            data-document-download-url="{{ route('reviewer.applications.documents.download', [$application, $document]) }}"
                                        ><x-dashboard.icon name="eye" size="17" /></button>
                                        <a
                                            class="dashboard-icon-action"
                                            href="{{ route('reviewer.applications.documents.download', [$application, $document]) }}"
                                            title="Download document"
                                            aria-label="Download {{ $document->original_file_name }}"
                                        ><x-dashboard.icon name="download" size="17" /></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            @endif
        </section>

        <div class="reviewer-workspace-main-grid">
            <section class="application-panel reviewer-comments-panel">
                <header class="application-panel-heading">
                    <div><h2>Review Comments</h2><p>Comments stay confidential and are not visible to the Applicant before official release.</p></div>
                    <x-dashboard.status-badge :label="$assignment->comments->count().' recorded'" tone="neutral" />
                </header>

                @if ($errors->reviewComment->any())
                    <div class="res-form-error-summary reviewer-workspace-error" role="alert">
                        <x-dashboard.icon name="alert-triangle" size="19" />
                        <div><strong>Comment was not saved.</strong><span>{{ $errors->reviewComment->first() }}</span></div>
                    </div>
                @endif

                <form class="reviewer-comment-form" method="POST" action="{{ route('reviewer.assignments.comments.store', $assignment) }}" data-reviewer-comment-form>
                    @csrf
                    <div class="application-field">
                        <label for="review-comment-category">Category</label>
                        <select id="review-comment-category" name="category" @disabled(! $canWrite) required>
                            @foreach ($commentCategories as $category)
                                <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="application-field">
                        <label for="review-comment-scope">Reference</label>
                        <select id="review-comment-scope" name="scope" data-reviewer-comment-scope @disabled(! $canWrite) required>
                            @foreach ($commentScopes as $scope)
                                <option value="{{ $scope->value }}" @selected(old('scope', 'overall') === $scope->value)>{{ $scope->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="application-field" data-reviewer-comment-document-field hidden>
                        <label for="review-comment-document">Document</label>
                        <select id="review-comment-document" name="application_document_id" data-reviewer-comment-document @disabled(! $canWrite)>
                            <option value="">Select a document</option>
                            @foreach ($application->documents as $document)
                                <option value="{{ $document->id }}" @selected((string) old('application_document_id') === (string) $document->id)>{{ $document->requirement?->name ?? $document->original_file_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="application-field" data-reviewer-comment-page-field hidden>
                        <label for="review-comment-page">Page</label>
                        <input id="review-comment-page" name="page_number" type="number" min="1" max="10000" value="{{ old('page_number') }}" data-reviewer-comment-page @disabled(! $canWrite)>
                    </div>
                    <div class="application-field application-field-full">
                        <label for="review-comment-body">Comment</label>
                        <textarea id="review-comment-body" name="body" rows="4" minlength="3" maxlength="2000" @disabled(! $canWrite) required>{{ old('body') }}</textarea>
                    </div>
                    <button class="dashboard-primary-action" type="submit" @disabled(! $canWrite)>
                        <x-dashboard.icon name="plus" size="17" /><span>Add Comment</span>
                    </button>
                </form>

                <div class="reviewer-comment-list" aria-live="polite">
                    @forelse ($assignment->comments as $comment)
                        <article class="reviewer-comment-item">
                            <div class="reviewer-comment-heading">
                                <div>
                                    <x-dashboard.status-badge :label="$comment->category->label()" :tone="$comment->category->tone()" />
                                    <span>{{ $comment->scope->label() }}@if ($comment->document) - {{ $comment->document->original_file_name }}@endif @if ($comment->page_number) - Page {{ $comment->page_number }}@endif</span>
                                </div>
                                @if ($canWrite)
                                    <form method="POST" action="{{ route('reviewer.assignments.comments.destroy', [$assignment, $comment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="reviewer-comment-remove" type="submit" title="Remove comment" aria-label="Remove comment"><x-dashboard.icon name="x" size="17" /></button>
                                    </form>
                                @endif
                            </div>
                            <p>{{ $comment->body }}</p>
                            <small>{{ $comment->created_at->format('M j, Y g:i A') }}</small>
                        </article>
                    @empty
                        <p class="reviewer-empty-copy">No review comments recorded yet.</p>
                    @endforelse
                </div>
            </section>

            <div class="reviewer-workspace-decision-column">
                <section class="application-panel reviewer-form-panel">
                    <header class="application-panel-heading">
                        <div><h2>Required Review Forms</h2><p>Finalize both official forms before submitting the decision.</p></div>
                        <x-dashboard.status-badge :label="$completedForms.' / 2 complete'" :tone="$completedForms === 2 ? 'success' : 'orange'" />
                    </header>
                    <div class="reviewer-form-list">
                        @foreach ($formCatalog as $catalog)
                            @php
                                $type = $catalog['type'];
                                $form = $forms->get($type->value);
                                $formIsFinal = $form?->status === \App\Enums\ReviewFormStatus::Final;
                            @endphp
                            <div class="reviewer-form-row">
                                <div>
                                    <strong>{{ $type->label() }}</strong>
                                    <span>{{ $type->code() }}</span>
                                </div>
                                <x-dashboard.status-badge :label="$form?->status->label() ?? 'Not Started'" :tone="$formIsFinal ? 'success' : ($form ? 'blue' : 'neutral')" />
                                <button class="dashboard-outline-action" type="button" data-reviewer-form-open="{{ $type->value }}">
                                    <x-dashboard.icon :name="$formIsFinal || ! $canWrite ? 'eye' : 'edit'" size="16" />
                                    <span>{{ $formIsFinal || ! $canWrite ? 'View' : ($form ? 'Continue' : 'Start') }}</span>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="application-panel reviewer-decision-panel">
                    <header class="application-panel-heading"><div><h2>Final Review Decision</h2><p>Submit only after both required forms are complete.</p></div></header>

                    @if ($errors->reviewDecision->any())
                        <div class="res-form-error-summary reviewer-workspace-error" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Review decision was not saved.</strong><span>{{ $errors->reviewDecision->first() }}</span></div>
                        </div>
                    @endif

                    <form class="reviewer-decision-form" method="POST" action="{{ route('reviewer.assignments.review.store', $assignment) }}" data-confirm-review-submit>
                        @csrf
                        <div class="application-field">
                            <label for="review-decision">Decision</label>
                            <select id="review-decision" name="decision" @disabled(! $canWrite)>
                                <option value="">Select a decision</option>
                                @foreach ($decisions as $decision)
                                    <option value="{{ $decision->value }}" @selected(old('decision', $review?->decision?->value) === $decision->value)>{{ $decision->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="application-field">
                            <label for="review-decision-comment">Decision Comment</label>
                            <textarea id="review-decision-comment" name="decision_comment" rows="5" minlength="10" maxlength="2000" @disabled(! $canWrite)>{{ old('decision_comment', $review?->decision_comment) }}</textarea>
                        </div>
                        @if ($reviewSubmitted)
                            <div class="reviewer-submitted-notice"><x-dashboard.icon name="check" size="18" /><span>Submitted {{ $review->submitted_at?->format('M j, Y g:i A') }}</span></div>
                        @else
                            <div class="reviewer-decision-actions">
                                <button class="dashboard-outline-action" type="submit" name="intent" value="draft" @disabled(! $canWrite)>Save Draft</button>
                                <button class="dashboard-primary-action" type="submit" name="intent" value="submit" @disabled(! $canWrite || $completedForms !== 2)>
                                    <x-dashboard.icon name="check" size="17" /><span>Submit Review</span>
                                </button>
                            </div>
                        @endif
                    </form>
                </section>
            </div>
        </div>

        @foreach ($formCatalog as $catalog)
            @php
                $type = $catalog['type'];
                $form = $forms->get($type->value);
                $formIsFinal = $form?->status === \App\Enums\ReviewFormStatus::Final;
                $formCanWrite = $canWrite && ! $formIsFinal;
                $consentValue = old('form_type') === $type->value
                    ? old('consent_required', '')
                    : ($form?->consent_required === null ? '' : (string) (int) $form->consent_required);
            @endphp
            <section
                class="application-modal-backdrop"
                data-reviewer-form-dialog="{{ $type->value }}"
                @if ($errors->reviewerForm->any() && old('form_type') === $type->value) data-open-on-load @else hidden @endif
            >
                <div class="application-modal reviewer-form-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-form-{{ $type->value }}-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close reviewer form" data-reviewer-form-close><x-dashboard.icon name="x" size="20" /></button>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="clipboard" size="24" /></span>
                        <div>
                            <h2 id="reviewer-form-{{ $type->value }}-title">{{ $type->label() }}</h2>
                            <p>{{ $type->code() }} - Application {{ $application->application_code }}</p>
                        </div>
                        <x-dashboard.status-badge :label="$form?->status->label() ?? 'Not Started'" :tone="$formIsFinal ? 'success' : 'neutral'" />
                    </header>

                    @if ($errors->reviewerForm->any() && old('form_type') === $type->value)
                        <div class="res-form-error-summary" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Review the form entries.</strong><span>{{ $errors->reviewerForm->first() }}</span></div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('reviewer.assignments.forms.update', [$assignment, $type]) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form_type" value="{{ $type->value }}">

                        @if ($type === \App\Enums\ReviewFormType::InformedConsent)
                            <fieldset class="reviewer-consent-gate">
                                <legend>Is an informed consent form necessary for this study?</legend>
                                <label><input type="radio" name="consent_required" value="1" @checked((string) $consentValue === '1') @disabled(! $formCanWrite)> Yes</label>
                                <label><input type="radio" name="consent_required" value="0" @checked((string) $consentValue === '0') @disabled(! $formCanWrite)> No</label>
                                <label class="application-field reviewer-consent-explanation">
                                    <span>If no, provide the ethical basis</span>
                                    <textarea name="consent_not_required_explanation" rows="3" maxlength="2000" @disabled(! $formCanWrite)>{{ old('form_type') === $type->value ? old('consent_not_required_explanation') : $form?->consent_not_required_explanation }}</textarea>
                                </label>
                            </fieldset>
                        @endif

                        <div class="reviewer-form-question-list">
                            @foreach ($catalog['questions'] as $questionKey => $question)
                                @php
                                    $savedAnswer = old('form_type') === $type->value
                                        ? old("responses.{$questionKey}.answer")
                                        : data_get($form?->responses, "{$questionKey}.answer");
                                    $savedComment = old('form_type') === $type->value
                                        ? old("responses.{$questionKey}.comment")
                                        : data_get($form?->responses, "{$questionKey}.comment");
                                @endphp
                                <fieldset class="reviewer-form-question">
                                    <legend><span>{{ $loop->iteration }}</span>{{ $question }}</legend>
                                    <div class="reviewer-form-answer-options">
                                        @foreach ($catalog['answers'] as $answerValue => $answerLabel)
                                            <label><input type="radio" name="responses[{{ $questionKey }}][answer]" value="{{ $answerValue }}" @checked($savedAnswer === $answerValue) @disabled(! $formCanWrite)> {{ $answerLabel }}</label>
                                        @endforeach
                                    </div>
                                    <label class="reviewer-form-item-comment">
                                        <span>Comment (optional)</span>
                                        <input name="responses[{{ $questionKey }}][comment]" value="{{ $savedComment }}" maxlength="1000" @disabled(! $formCanWrite)>
                                    </label>
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="reviewer-form-recommendation">
                            <div class="application-field">
                                <label for="{{ $type->value }}-recommendation">Recommendation</label>
                                <select id="{{ $type->value }}-recommendation" name="recommendation" @disabled(! $formCanWrite)>
                                    <option value="">Select a recommendation</option>
                                    @foreach ($decisions as $decision)
                                        <option value="{{ $decision->value }}" @selected((old('form_type') === $type->value ? old('recommendation') : $form?->recommendation?->value) === $decision->value)>{{ $decision->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="application-field">
                                <label for="{{ $type->value }}-recommendation-comments">Recommendation Comments</label>
                                <textarea id="{{ $type->value }}-recommendation-comments" name="recommendation_comments" rows="4" maxlength="2000" @disabled(! $formCanWrite)>{{ old('form_type') === $type->value ? old('recommendation_comments') : $form?->recommendation_comments }}</textarea>
                            </div>
                        </div>

                        <div class="application-modal-actions reviewer-form-actions">
                            <button class="dashboard-outline-action" type="button" data-reviewer-form-close>Close</button>
                            @if ($formCanWrite)
                                <button class="dashboard-outline-action" type="submit" name="intent" value="draft">Save Draft</button>
                                <button class="dashboard-primary-action" type="submit" name="intent" value="final">Finalize Form</button>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        @endforeach

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
