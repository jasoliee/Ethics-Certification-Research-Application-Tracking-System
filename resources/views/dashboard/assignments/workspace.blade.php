@extends('layouts.dashboard')

@section('content')
    @php
        $application = $assignment->researchApplication;
        $review = $assignment->reviewSubmission;
        $reviewSubmitted = $review?->status === \App\Enums\ReviewSubmissionStatus::Submitted;
        $formIsComplete = static fn ($form): bool => $form?->status === \App\Enums\ReviewFormStatus::Final
            && $form?->artifact?->status === \App\Enums\ReviewFormArtifactStatus::Ready;
        $completedForms = $forms->filter($formIsComplete)->count();
        $activeDocument = $application->documents->first();
        $formProgress = static function ($form, array $catalog) use ($formIsComplete): array {
            $total = count($catalog['questions']);
            $answered = $formIsComplete($form)
                ? $total
                : collect($form?->responses ?? [])->filter(
                    fn ($response) => filled(data_get($response, 'answer')),
                )->count();

            return ['answered' => $answered, 'total' => $total];
        };
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

        <section class="application-panel reviewer-workspace-meta-bar" aria-label="Application review summary">
            <dl>
                <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                <div><dt>Review Type</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                <div><dt>Category</dt><dd>{{ $application->research_category ?: ($application->research_type?->label() ?? 'Not specified') }}</dd></div>
                <div><dt>Review Deadline</dt><dd>{{ $assignment->review_deadline_at?->format('M j, Y g:i A') ?? 'Not configured' }}</dd></div>
            </dl>
        </section>

        {{-- Desktop mirrors the high-fidelity three-column workspace; smaller viewports stack without losing controls. --}}
        <div class="reviewer-review-studio" data-reviewer-review-studio>
            <aside class="application-panel reviewer-document-library" aria-label="Submitted documents">
                <header class="application-panel-heading"><div><h2>Documents</h2><p>Select a current private file.</p></div></header>
                @if ($application->documents->isEmpty())
                    <p class="reviewer-empty-copy">No supporting documents are available.</p>
                @else
                    <div class="reviewer-document-list" role="list">
                        @foreach ($application->documents as $document)
                            @php
                                $fileKind = $document->fileTypeLabel();
                                $fileIcon = match ($document->mime_type) {
                                    'application/pdf' => 'file-pdf',
                                    'application/msword',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'file-word',
                                    'application/vnd.ms-excel',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'file-spreadsheet',
                                    default => 'file',
                                };
                            @endphp
                            <button
                                @class(['reviewer-document-choice', 'is-active' => $activeDocument?->is($document)])
                                type="button"
                                role="listitem"
                                data-reviewer-document-choice
                                data-document-id="{{ $document->id }}"
                                data-document-name="{{ $document->original_file_name }}"
                                data-document-requirement="{{ $document->requirement?->name ?? 'Supporting Document' }}"
                                data-document-kind="{{ $fileKind }}"
                                data-document-version="v{{ $document->document_version }}"
                                data-document-preview-url="{{ route('reviewer.applications.documents.preview', [$application, $document]) }}"
                                data-document-download-url="{{ route('reviewer.applications.documents.download', [$application, $document]) }}"
                                aria-pressed="{{ $activeDocument?->is($document) ? 'true' : 'false' }}"
                            >
                                <span class="reviewer-document-choice-icon"><x-dashboard.icon :name="$fileIcon" size="20" /></span>
                                <span><strong>{{ $document->requirement?->name ?? 'Supporting Document' }}</strong><small>{{ $fileKind }} - v{{ $document->document_version }}</small></span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </aside>

            <section class="application-panel reviewer-document-pane" aria-label="Document viewer">
                <header class="application-panel-heading reviewer-document-pane-heading">
                    <div>
                        <h2 data-reviewer-document-title>{{ $activeDocument?->requirement?->name ?? 'Document Viewer' }}</h2>
                        <p data-reviewer-document-meta>{{ $activeDocument?->original_file_name ?? 'Select a document to begin reviewing.' }}</p>
                    </div>
                    <div class="reviewer-document-pane-actions">
                        <a class="dashboard-outline-action" href="{{ $activeDocument ? route('reviewer.applications.documents.preview', [$application, $activeDocument]) : '#' }}" target="_blank" rel="noopener" data-reviewer-document-open-tab @if (! $activeDocument) hidden @endif>
                            <x-dashboard.icon name="eye" size="16" /><span>Open</span>
                        </a>
                        <a class="dashboard-outline-action" href="{{ $activeDocument ? route('reviewer.applications.documents.download', [$application, $activeDocument]) : '#' }}" data-reviewer-document-download @if (! $activeDocument) hidden @endif>
                            <x-dashboard.icon name="download" size="16" /><span>Download</span>
                        </a>
                    </div>
                </header>
                @if ($activeDocument)
                    <div class="reviewer-document-frame-shell" data-reviewer-document-frame-shell aria-busy="false">
                        <div class="reviewer-document-loading" data-reviewer-document-loading role="status">Loading secure preview...</div>
                        <iframe
                            src="{{ route('reviewer.applications.documents.preview', [$application, $activeDocument]) }}"
                            title="Preview of {{ $activeDocument->original_file_name }}"
                            loading="eager"
                            data-reviewer-document-frame
                        ></iframe>
                    </div>
                @else
                    <div class="reviewer-document-empty"><x-dashboard.icon name="file-search" size="28" /><p>No document is available to preview.</p></div>
                @endif
            </section>

            <aside class="reviewer-review-rail">
                <section class="application-panel reviewer-decision-panel">
                    <header class="application-panel-heading"><div><h2>Review Tools</h2><p>Save a draft or submit after both required forms are complete.</p></div></header>

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
                            <textarea id="review-decision-comment" name="decision_comment" rows="4" minlength="10" maxlength="2000" @disabled(! $canWrite)>{{ old('decision_comment', $review?->decision_comment) }}</textarea>
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

                <section class="application-panel reviewer-comments-panel">
                <header class="application-panel-heading">
                    <div><h2>Review Comments</h2><p>Comments stay confidential and are not visible to the Applicant before official release.</p></div>
                    <span data-reviewer-comment-count><x-dashboard.status-badge :label="$assignment->comments->count().' recorded'" tone="neutral" /></span>
                </header>

                @if ($errors->reviewComment->any())
                    <div class="res-form-error-summary reviewer-workspace-error" role="alert">
                        <x-dashboard.icon name="alert-triangle" size="19" />
                        <div><strong>Comment was not saved.</strong><span>{{ $errors->reviewComment->first() }}</span></div>
                    </div>
                @endif

                <form
                    class="reviewer-comment-form"
                    method="POST"
                    action="{{ route('reviewer.assignments.comments.store', $assignment) }}"
                    data-reviewer-comment-form
                    data-comment-store-url="{{ route('reviewer.assignments.comments.store', $assignment) }}"
                >
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-reviewer-comment-method disabled>
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
                    <div class="application-field application-field-full">
                        <label for="review-comment-body">Comment</label>
                        <textarea id="review-comment-body" name="body" rows="4" minlength="3" maxlength="2000" @disabled(! $canWrite) required>{{ old('body') }}</textarea>
                    </div>
                    <p class="reviewer-comment-feedback" role="status" aria-live="polite" data-reviewer-comment-feedback></p>
                    <div class="reviewer-comment-form-actions">
                        <button class="dashboard-outline-action" type="button" data-reviewer-comment-cancel hidden>Cancel Edit</button>
                        <button class="dashboard-primary-action" type="submit" @disabled(! $canWrite) data-reviewer-comment-submit>
                            <x-dashboard.icon name="plus" size="17" /><span data-reviewer-comment-submit-label>Add Comment</span>
                        </button>
                    </div>
                </form>

                <div class="reviewer-comment-list" aria-live="polite" data-reviewer-comment-list>
                    @forelse ($assignment->comments as $comment)
                        @include('dashboard.assignments.partials.comment-item', ['comment' => $comment, 'assignment' => $assignment, 'canWrite' => $canWrite])
                    @empty
                    @endforelse
                    <p class="reviewer-empty-copy" data-reviewer-comment-empty @if ($assignment->comments->isNotEmpty()) hidden @endif>No review comments recorded yet. Add an overall or document comment while reviewing.</p>
                </div>
                </section>

                <section class="application-panel reviewer-form-panel reviewer-worksheet-launch-panel">
                    <header class="application-panel-heading">
                        <div><h2>Review Worksheets</h2><p>Complete both official worksheets before submitting the decision.</p></div>
                        <x-dashboard.status-badge :label="$completedForms.' / 2 complete'" :tone="$completedForms === 2 ? 'success' : 'orange'" />
                    </header>
                    <button class="dashboard-outline-action reviewer-worksheet-launch" type="button" data-reviewer-worksheet-open>
                        <x-dashboard.icon name="clipboard" size="18" />
                        <span>Open Review Worksheet</span>
                    </button>
                </section>

            </aside>
        </div>

        <section class="application-modal-backdrop" data-reviewer-worksheet-dialog hidden>
            <div class="application-modal reviewer-worksheet-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-worksheet-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Close worksheet selection" data-reviewer-worksheet-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="clipboard" size="24" /></span>
                    <div>
                        <h2 id="reviewer-worksheet-title">Open Review Worksheet</h2>
                        <p>Select a worksheet to answer, continue, or review.</p>
                    </div>
                </header>
                <div class="reviewer-worksheet-list">
                    @foreach ($formCatalog as $catalog)
                        @php
                            $type = $catalog['type'];
                            $form = $forms->get($type->value);
                            $formIsFinal = $formIsComplete($form);
                            $progress = $formProgress($form, $catalog);
                            $statusLabel = $formIsFinal ? 'Complete' : ($form ? 'Draft Saved' : 'Not Started');
                        @endphp
                        <article class="reviewer-worksheet-option">
                            <span class="reviewer-worksheet-option-icon"><x-dashboard.icon name="clipboard" size="23" /></span>
                            <div>
                                <strong>{{ $type->code() }}: {{ $type->label() }}</strong>
                                <span>{{ $type === \App\Enums\ReviewFormType::Protocol ? 'Reviewer assessment form for protocol evaluation.' : 'Reviewer checklist for informed-consent evaluation.' }}</span>
                                <div class="reviewer-worksheet-option-status">
                                    <x-dashboard.status-badge
                                        :label="$statusLabel"
                                        :tone="$formIsFinal ? 'success' : ($form ? 'blue' : 'neutral')"
                                        data-reviewer-form-status="{{ $type->value }}"
                                    />
                                    <span data-reviewer-form-progress="{{ $type->value }}">{{ $progress['answered'] }} of {{ $progress['total'] }} items completed</span>
                                    @if ($formIsFinal)
                                        <span class="reviewer-worksheet-artifact-actions">
                                            <a href="{{ route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $form->artifact]) }}" target="_blank" rel="noopener">Preview official PDF</a>
                                            <a href="{{ route('reviewer.assignments.forms.artifacts.download', [$assignment, $form, $form->artifact]) }}">Download</a>
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <button class="dashboard-outline-action" type="button" data-reviewer-form-open="{{ $type->value }}">
                                <span data-reviewer-form-open-label="{{ $type->value }}">{{ $formIsFinal || ! $canWrite ? 'View' : ($form ? 'Continue' : 'Start') }}</span>
                                <x-dashboard.icon name="arrow-right" size="16" />
                            </button>
                        </article>
                    @endforeach
                </div>
                <div class="application-modal-actions reviewer-worksheet-actions">
                    <button class="dashboard-outline-action" type="button" data-reviewer-worksheet-close>Close</button>
                </div>
            </div>
        </section>

        @foreach ($formCatalog as $catalog)
            @php
                $type = $catalog['type'];
                $form = $forms->get($type->value);
                $formIsFinal = $formIsComplete($form);
                $formCanWrite = $canWrite && ! $formIsFinal;
                $progress = $formProgress($form, $catalog);
                $statusLabel = $formIsFinal ? 'Complete' : ($form ? 'Draft Saved' : 'Not Started');
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
                        <x-dashboard.status-badge
                            :label="$statusLabel"
                            :tone="$formIsFinal ? 'success' : ($form ? 'blue' : 'neutral')"
                            data-reviewer-form-status="{{ $type->value }}"
                        />
                    </header>

                    <dl class="reviewer-form-context" aria-label="Blind review form context">
                        <div><dt>Title of the Study</dt><dd>{{ $application->research_title }}</dd></div>
                        <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                        <div><dt>Type of Review</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                        <div><dt>Date Received</dt><dd>{{ $application->submitted_at?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                    </dl>

                    @if ($errors->reviewerForm->any() && old('form_type') === $type->value)
                        <div class="res-form-error-summary" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Review the form entries.</strong><span>{{ $errors->reviewerForm->first() }}</span></div>
                        </div>
                    @endif

                    <form
                        class="reviewer-worksheet-form"
                        method="POST"
                        action="{{ route('reviewer.assignments.forms.update', [$assignment, $type]) }}"
                        data-reviewer-worksheet-form
                        data-reviewer-form-type="{{ $type->value }}"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form_type" value="{{ $type->value }}">

                        @if ($type === \App\Enums\ReviewFormType::InformedConsent)
                            <fieldset class="reviewer-consent-gate">
                                <legend>Is it necessary to seek the informed consent of the participants?</legend>
                                <label><input type="radio" name="consent_required" value="1" @checked((string) $consentValue === '1') @disabled(! $formCanWrite)> Yes</label>
                                <label><input type="radio" name="consent_required" value="0" @checked((string) $consentValue === '0') @disabled(! $formCanWrite)> No</label>
                                <label class="application-field reviewer-consent-explanation">
                                    <span>If NO, please explain (answer briefly)</span>
                                    <textarea name="consent_not_required_explanation" rows="3" maxlength="2000" @disabled(! $formCanWrite)>{{ old('form_type') === $type->value ? old('consent_not_required_explanation') : $form?->consent_not_required_explanation }}</textarea>
                                </label>
                            </fieldset>
                        @endif

                        <div class="reviewer-form-question-list">
                            @foreach ($catalog['items'] as $questionKey => $item)
                                @php
                                    $question = $item['text'];
                                    $savedAnswer = old('form_type') === $type->value
                                        ? old("responses.{$questionKey}.answer")
                                        : data_get($form?->responses, "{$questionKey}.answer");
                                    $savedComment = old('form_type') === $type->value
                                        ? old("responses.{$questionKey}.comment")
                                        : data_get($form?->responses, "{$questionKey}.comment");
                                @endphp
                                <fieldset class="reviewer-form-question" data-reviewer-form-question>
                                    <legend>
                                        @if (array_key_exists('printed_number', $item) && $item['printed_number'] !== null)
                                            <span>{{ $item['printed_number'] }}</span>
                                        @endif
                                        {{ $question }}
                                    </legend>
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

                        <div class="reviewer-form-progress" aria-live="polite">
                            <span data-reviewer-form-progress="{{ $type->value }}">{{ $progress['answered'] }} of {{ $progress['total'] }} items completed</span>
                            <progress value="{{ $progress['answered'] }}" max="{{ $progress['total'] }}" data-reviewer-form-progress-bar>{{ $progress['answered'] }} / {{ $progress['total'] }}</progress>
                        </div>
                        <p class="reviewer-form-feedback" role="status" aria-live="polite" data-reviewer-form-feedback></p>

                        <div class="application-modal-actions reviewer-form-actions">
                            <button class="dashboard-outline-action" type="button" data-reviewer-form-close>Back to Worksheets</button>
                            @if ($formIsFinal)
                                <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $form->artifact]) }}" target="_blank" rel="noopener">
                                    <x-dashboard.icon name="eye" size="16" />
                                    <span>Preview Official PDF</span>
                                </a>
                                <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.forms.artifacts.download', [$assignment, $form, $form->artifact]) }}">
                                    <x-dashboard.icon name="download" size="16" />
                                    <span>Download PDF</span>
                                </a>
                            @endif
                            @if ($formCanWrite)
                                <button class="dashboard-outline-action" type="submit" name="intent" value="draft" data-reviewer-form-save-draft>Save Draft</button>
                                <button class="dashboard-primary-action" type="submit" name="intent" value="final">Finalize Form</button>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        @endforeach

    </div>
@endsection
