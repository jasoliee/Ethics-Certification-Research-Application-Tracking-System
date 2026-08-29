@extends('layouts.dashboard')

@section('content')
    @php
        $application = $assignment->researchApplication;
        $review = $assignment->reviewSubmission;
        $reviewSubmitted = $review?->status === \App\Enums\ReviewSubmissionStatus::Submitted;
        $formIsComplete = static fn ($form): bool => in_array($form?->status, [
            \App\Enums\ReviewFormStatus::Completed,
            \App\Enums\ReviewFormStatus::Final,
        ], true);
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
                <p>Use the application code when referring to this study. Review content and comments remain restricted until an official REU release.</p>
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
                <div class="reviewer-workspace-meta-title"><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                <div><dt>Review Type</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                <div><dt>Category</dt><dd>{{ $application->research_category ?: ($application->research_type?->label() ?? 'Not specified') }}</dd></div>
                <div><dt>Review Deadline</dt><dd>{{ $assignment->review_deadline_at?->format('M j, Y g:i A') ?? 'Not configured' }}</dd></div>
            </dl>
        </section>

        {{-- Documents stay beside the viewer; review controls follow below in a focused two-column workspace. --}}
        <div class="reviewer-review-studio" data-reviewer-review-studio>
            <aside class="application-panel reviewer-document-library" aria-label="Submitted documents">
                <header class="application-panel-heading"><div><h2>Documents</h2></div></header>
                @if ($application->documents->isEmpty())
                    <p class="reviewer-empty-copy">No supporting documents are available.</p>
                @else
                    <div class="reviewer-document-list">
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
                    <div class="reviewer-document-frame-shell" data-reviewer-document-frame-shell aria-busy="true">
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
                <details class="application-panel reviewer-workflow-accordion reviewer-comments-panel">
                <summary class="application-panel-heading reviewer-workflow-accordion-summary">
                    <div><h2>Review Comment</h2></div>
                    <span data-reviewer-comment-count>
                        <x-dashboard.status-badge :label="$commentTotal.' recorded'" tone="neutral" data-reviewer-comment-total="{{ $commentTotal }}" />
                    </span>
                    <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                </summary>
                <div class="reviewer-workflow-accordion-body">

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
                        <select id="review-comment-category" name="category" data-reviewer-comment-category @disabled(! $canWrite) required>
                            @foreach ($commentCategories as $category)
                                <option value="{{ $category->value }}" @selected(old('category') === $category->value)>{{ $category->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="application-field">
                        <label for="review-comment-document">Document</label>
                        <select id="review-comment-document" name="application_document_id" data-reviewer-comment-document @disabled(! $canWrite)>
                            <option value="" @selected(blank(old('application_document_id')))>Entire Application</option>
                            @foreach ($application->documents as $document)
                                <option value="{{ $document->id }}" @selected((string) old('application_document_id') === (string) $document->id)>{{ $document->requirement?->name ?? 'Supporting Document' }} — {{ $document->original_file_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="application-field application-field-full">
                        <label for="review-comment-body">Comment</label>
                        <textarea id="review-comment-body" name="body" rows="4" minlength="5" maxlength="2000" @disabled(! $canWrite) required>{{ old('body') }}</textarea>
                    </div>
                    <p class="reviewer-comment-feedback" role="status" aria-live="polite" data-reviewer-comment-feedback></p>
                    <div class="reviewer-comment-form-actions">
                        <button class="dashboard-outline-action" type="button" data-reviewer-comment-cancel hidden>Cancel Edit</button>
                        <button class="dashboard-primary-action" type="submit" @disabled(! $canWrite) data-reviewer-comment-submit>
                            <x-dashboard.icon name="plus" size="17" /><span data-reviewer-comment-submit-label>Add Comment</span>
                        </button>
                    </div>
                </form>

                <div class="reviewer-comment-list" id="reviewer-comment-history" aria-live="polite" aria-busy="false" data-reviewer-comment-list>
                    @forelse ($comments as $comment)
                        @include('dashboard.assignments.partials.comment-item', ['comment' => $comment, 'assignment' => $assignment, 'canWrite' => $canWrite])
                    @empty
                    @endforelse
                    <p class="reviewer-empty-copy" data-reviewer-comment-empty @if ($comments->isNotEmpty()) hidden @endif>No review comments recorded yet. Add an overall or document comment while reviewing.</p>
                </div>
                <div class="reviewer-comment-history-controls">
                    <button
                        class="dashboard-outline-action"
                        type="button"
                        aria-controls="reviewer-comment-history"
                        data-reviewer-comments-load
                        data-comments-url="{{ route('reviewer.assignments.comments.index', $assignment) }}"
                        data-before-id="{{ $commentsNextBeforeId }}"
                        @if (! $commentsHaveOlder) hidden @endif
                    >
                        <x-dashboard.icon name="refresh" size="16" />
                        <span data-reviewer-comments-load-label>Load Older Comments</span>
                    </button>
                    <p class="reviewer-comments-history-feedback" role="status" aria-live="polite" data-reviewer-comments-history-feedback></p>
                    @if ($commentsHaveOlder)
                        <noscript><p class="reviewer-comments-noscript">The newest 20 comments are shown. Enable JavaScript to load older comments.</p></noscript>
                    @endif
                </div>
                </div>
                </details>

                <details class="application-panel reviewer-workflow-accordion reviewer-form-panel reviewer-worksheet-launch-panel" data-reviewer-worksheet-accordion>
                    <summary class="application-panel-heading reviewer-workflow-accordion-summary">
                        <div><h2>Review Worksheet</h2></div>
                        <x-dashboard.status-badge :label="$completedForms.' / 2 completed'" :tone="$completedForms === 2 ? 'success' : 'orange'" data-reviewer-forms-summary />
                        <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                    </summary>
                    <div class="reviewer-workflow-accordion-body">
                        <div class="reviewer-worksheet-list" aria-label="Official review worksheets">
                            @foreach ($formCatalog as $catalog)
                                @php
                                    $type = $catalog['type'];
                                    $form = $forms->get($type->value);
                                    $formIsFinal = $form?->status === \App\Enums\ReviewFormStatus::Final;
                                    $formCompleted = $formIsComplete($form);
                                    $progress = $formProgress($form, $catalog);
                                    $statusLabel = $formIsFinal ? 'Submitted' : ($formCompleted ? 'Completed' : ($form ? 'In Progress' : 'Not Started'));
                                    $openLabel = ! $canWrite ? 'View' : ($formCompleted ? 'Edit' : ($form ? 'Continue' : 'Start'));
                                    $worksheetVersions = $review?->versions?->sortByDesc('version_number')->map(
                                        function ($version) use ($type): array {
                                            $artifact = $version->artifacts->first(
                                                fn ($candidate): bool => $candidate->formSubmission?->form_type === $type,
                                            );

                                            return ['submission' => $version, 'artifact' => $artifact];
                                        },
                                    )->filter(fn (array $version): bool => $version['artifact'] !== null) ?? collect();
                                @endphp
                                <article class="reviewer-worksheet-option">
                                    <span class="reviewer-worksheet-option-icon"><x-dashboard.icon name="clipboard" size="23" /></span>
                                    <div>
                                        <strong>{{ $type->code() }}: {{ $type->label() }}</strong>
                                        <span>{{ $type === \App\Enums\ReviewFormType::Protocol ? 'Reviewer assessment form for protocol evaluation.' : 'Reviewer checklist for informed-consent evaluation.' }}</span>
                                        <div class="reviewer-worksheet-option-status">
                                            <x-dashboard.status-badge
                                                :label="$statusLabel"
                                                :tone="$formCompleted ? 'success' : ($form ? 'blue' : 'neutral')"
                                                data-reviewer-form-status="{{ $type->value }}"
                                            />
                                            <span data-reviewer-form-progress="{{ $type->value }}">{{ $progress['answered'] }} of {{ $progress['total'] }} items completed</span>
                                            @if ($reviewSubmitted && $form?->artifact?->status === \App\Enums\ReviewFormArtifactStatus::Ready)
                                                <span class="reviewer-worksheet-artifact-actions">
                                                    <a href="{{ route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $form->artifact]) }}" target="_blank" rel="noopener">Preview submitted PDF</a>
                                                    <a href="{{ route('reviewer.assignments.forms.artifacts.download', [$assignment, $form, $form->artifact]) }}">Download</a>
                                                </span>
                                            @elseif ($formCompleted)
                                                <span>{{ $canWrite ? 'Editable until REU releases the decision.' : 'Read-only.' }}</span>
                                            @endif
                                        </div>
                                        @if ($worksheetVersions->isNotEmpty())
                                            <details class="reviewer-worksheet-version-history">
                                                <summary>{{ $worksheetVersions->count() }} submitted {{ Str::plural('version', $worksheetVersions->count()) }}</summary>
                                                <div>
                                                    @foreach ($worksheetVersions as $worksheetVersion)
                                                        @php
                                                            $artifact = $worksheetVersion['artifact'];
                                                        @endphp
                                                        <span>
                                                            <strong>Version {{ $artifact->business_version ?? (((int) $assignment->review_cycle) + 1) }}</strong>
                                                            <time datetime="{{ $worksheetVersion['submission']->submitted_at?->toIso8601String() }}">{{ $worksheetVersion['submission']->submitted_at?->format('M j, Y g:i A') }}</time>
                                                            <a href="{{ route('reviewer.assignments.forms.artifacts.preview', [$assignment, $artifact->formSubmission, $artifact]) }}" target="_blank" rel="noopener">Preview</a>
                                                            <a href="{{ route('reviewer.assignments.forms.artifacts.download', [$assignment, $artifact->formSubmission, $artifact]) }}">Download</a>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                    <button
                                        class="dashboard-outline-action"
                                        type="button"
                                        aria-label="{{ $openLabel }} {{ $type->label() }}"
                                        aria-controls="reviewer-inline-form-{{ $type->value }}"
                                        data-reviewer-form-open="{{ $type->value }}"
                                    >
                                        <span data-reviewer-form-open-label="{{ $type->value }}">{{ $openLabel }}</span>
                                        <x-dashboard.icon name="arrow-right" size="16" />
                                    </button>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </details>

                <details class="application-panel reviewer-workflow-accordion reviewer-decision-panel">
                    <summary class="application-panel-heading reviewer-workflow-accordion-summary">
                        <div><h2>Review Assessment</h2></div>
                        <span class="reviewer-workflow-accordion-chevron" aria-hidden="true"><x-dashboard.icon name="chevron-down" size="18" /></span>
                    </summary>
                    <div class="reviewer-workflow-accordion-body">

                    @if ($errors->reviewDecision->any())
                        <div class="res-form-error-summary reviewer-workspace-error" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Review decision was not saved.</strong><span>{{ $errors->reviewDecision->first() }}</span></div>
                        </div>
                    @endif

                    <form
                        class="reviewer-decision-form"
                        method="POST"
                        action="{{ route('reviewer.assignments.review.store', $assignment) }}"
                        data-reviewer-decision-form
                        data-completed-reviewer-forms="{{ $completedForms }}"
                        data-required-reviewer-forms="2"
                        data-reviewer-result-url="{{ route('reviewer.assignments.show', $assignment) }}"
                    >
                        @csrf
                        <input type="hidden" name="submission_token" value="{{ (string) Str::uuid() }}">
                        <div class="application-field">
                            <label for="review-decision">Decision</label>
                            <select id="review-decision" name="decision" aria-describedby="review-decision-feedback" @disabled(! $canWrite)>
                                <option value="">Select a decision</option>
                                @foreach ($decisions as $decision)
                                    <option value="{{ $decision->value }}" @selected(old('decision', $review?->draft_decision?->value ?? $review?->decision?->value) === $decision->value)>{{ $decision->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="application-field">
                            <label for="review-decision-comment">Decision Comment</label>
                            <textarea id="review-decision-comment" name="decision_comment" rows="4" minlength="5" maxlength="2000" aria-describedby="review-decision-feedback" @disabled(! $canWrite)>{{ old('decision_comment', $review?->draft_decision_comment ?? $review?->decision_comment) }}</textarea>
                        </div>
                        <p class="reviewer-decision-feedback" id="review-decision-feedback" role="alert" aria-live="assertive" tabindex="-1" data-reviewer-decision-feedback></p>
                        @if ($reviewSubmitted)
                            <div class="reviewer-submitted-notice"><x-dashboard.icon name="check" size="18" /><span>Submitted {{ $review->submitted_at?->format('M j, Y g:i A') }} · Version {{ $review->currentVersion?->version_number ?? 1 }}{{ $review->has_unsubmitted_changes ? ' · Unsaved release changes' : '' }}</span></div>
                        @endif
                        @if ($canWrite)
                            <div class="reviewer-decision-actions">
                                <button class="dashboard-outline-action" type="submit" name="intent" value="draft" @disabled(! $canWrite)>Save Draft</button>
                                <button class="dashboard-primary-action" type="submit" name="intent" value="submit" @disabled(! $canWrite)>
                                    <x-dashboard.icon name="check" size="17" /><span>{{ $reviewSubmitted ? 'Re-submit Review' : 'Submit Review' }}</span>
                                </button>
                            </div>
                        @endif
                    </form>
                    </div>
                </details>

            </aside>
        </div>

        @if ((int) $assignment->review_cycle > 0)
            <section class="application-panel reviewer-revision-history" aria-labelledby="reviewer-revision-history-title">
                <header class="application-panel-heading">
                    <div>
                        <h2 id="reviewer-revision-history-title">Previous Versions and Comments</h2>
                    </div>
                    <x-dashboard.status-badge :label="'Revision '.$assignment->review_cycle" tone="violet" />
                </header>

                <div class="reviewer-requirement-history-list">
                    @forelse ($historicalDocuments->groupBy('document_requirement_id') as $requirementId => $versions)
                        @php
                            $orderedVersions = $versions->sortByDesc('document_version')->values();
                        @endphp
                        <details class="reviewer-history-disclosure reviewer-requirement-history">
                            <summary>
                                <span>{{ $orderedVersions->first()?->requirement?->name ?? 'Supporting Document' }}</span>
                                <small>{{ $orderedVersions->count() }} stored {{ Str::plural('version', $orderedVersions->count()) }}</small>
                            </summary>
                            <div class="reviewer-requirement-history-body" data-reviewer-history-group>
                                <label class="application-field reviewer-history-version-selector">
                                    <span>Document version</span>
                                    <select data-reviewer-history-version-select>
                                        @foreach ($orderedVersions as $document)
                                            <option value="{{ $document->id }}">Version {{ $document->document_version }} · {{ $document->original_file_name }}</option>
                                        @endforeach
                                    </select>
                                </label>

                                @foreach ($orderedVersions as $document)
                                    @php
                                        $versionComments = $historicalCommentsByDocument->get($document->id, collect());
                                    @endphp
                                    <section
                                        class="reviewer-history-version-panel"
                                        data-reviewer-history-version-panel="{{ $document->id }}"
                                        @if (! $loop->first) hidden @endif
                                    >
                                        <header>
                                            <div>
                                                <strong>Version {{ $document->document_version }}</strong>
                                                <span>{{ $document->original_file_name }}</span>
                                                <small>{{ $document->uploaded_at?->format('M j, Y g:i A') ?? 'Date not recorded' }} · {{ $document->validation_status?->label() ?? 'Stored' }}</small>
                                            </div>
                                            <div class="reviewer-history-actions">
                                                <a href="{{ route('reviewer.applications.documents.preview', [$application, $document]) }}" target="_blank" rel="noopener">Preview</a>
                                                <a href="{{ route('reviewer.applications.documents.download', [$application, $document]) }}">Download</a>
                                            </div>
                                        </header>
                                        <div class="reviewer-history-comments">
                                            @forelse ($versionComments as $historicalComment)
                                                <article>
                                                    <header>
                                                        <x-dashboard.status-badge :label="$historicalComment->category->label()" :tone="$historicalComment->category->tone()" />
                                                        <span>My private comment</span>
                                                        <time datetime="{{ $historicalComment->created_at?->toIso8601String() }}">{{ $historicalComment->created_at?->format('M j, Y g:i A') }}</time>
                                                    </header>
                                                    <p>{{ $historicalComment->body }}</p>
                                                </article>
                                            @empty
                                                <p class="reviewer-empty-copy">You did not record a comment for this document version.</p>
                                            @endforelse
                                        </div>
                                    </section>
                                @endforeach
                            </div>
                        </details>
                    @empty
                        <p class="reviewer-empty-copy">No earlier document versions are available for this revision cycle.</p>
                    @endforelse

                    @if ($historicalOverallComments->isNotEmpty())
                        <details class="reviewer-history-disclosure reviewer-requirement-history">
                            <summary>
                                <span>Entire Application</span>
                                <small>{{ $historicalOverallComments->count() }} of my previous {{ Str::plural('comment', $historicalOverallComments->count()) }}</small>
                            </summary>
                            <div class="reviewer-history-comments reviewer-overall-history-comments">
                                @foreach ($historicalOverallComments as $historicalComment)
                                    <article>
                                        <header>
                                            <x-dashboard.status-badge :label="$historicalComment->category->label()" :tone="$historicalComment->category->tone()" />
                                            <span>My private comment</span>
                                            <time datetime="{{ $historicalComment->created_at?->toIso8601String() }}">{{ $historicalComment->created_at?->format('M j, Y g:i A') }}</time>
                                        </header>
                                        <p>{{ $historicalComment->body }}</p>
                                    </article>
                                @endforeach
                            </div>
                        </details>
                    @endif

                    @foreach ($historicalReviews as $historicalReview)
                        @php
                            $submissionVersions = $historicalReview->reviewSubmission?->versions?->sortByDesc('version_number') ?? collect();
                        @endphp
                        @if ($submissionVersions->isNotEmpty())
                            <details class="reviewer-history-disclosure reviewer-requirement-history">
                                <summary>
                                    <span>{{ $historicalReview->review_cycle === 0 ? 'Initial review worksheets' : 'Revision '.$historicalReview->review_cycle.' worksheets' }}</span>
                                    <small>{{ $submissionVersions->count() }} immutable submission {{ Str::plural('version', $submissionVersions->count()) }}</small>
                                </summary>
                                <div class="reviewer-submission-version-list">
                                    @foreach ($submissionVersions as $submissionVersion)
                                        <article>
                                            <div>
                                                <strong>Submission version {{ $submissionVersion->version_number }} · {{ $submissionVersion->decision->label() }}</strong>
                                                <time datetime="{{ $submissionVersion->submitted_at?->toIso8601String() }}">{{ $submissionVersion->submitted_at?->format('M j, Y g:i A') }}</time>
                                            </div>
                                            <div>
                                                @foreach ($submissionVersion->artifacts as $artifact)
                                                    @if ($artifact->formSubmission)
                                                        <span>
                                                            {{ $artifact->formSubmission->form_type->label() }}:
                                                            <a href="{{ route('reviewer.assignments.forms.artifacts.preview', [$historicalReview, $artifact->formSubmission, $artifact]) }}" target="_blank" rel="noopener">Preview</a>
                                                            <a href="{{ route('reviewer.assignments.forms.artifacts.download', [$historicalReview, $artifact->formSubmission, $artifact]) }}">Download</a>
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    @endforeach

                    @if ($historicalDocuments->count() >= 100)
                        <p class="reviewer-history-limit">The newest 100 authorized historical files are shown.</p>
                    @endif
                </div>
            </section>
        @endif

        <section class="application-modal-backdrop" data-reviewer-submit-dialog hidden>
            <div
                class="application-modal reviewer-submit-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="reviewer-submit-title"
                aria-describedby="reviewer-submit-description reviewer-submit-warning"
                tabindex="-1"
                data-reviewer-submit-panel
            >
                <button class="application-modal-close" type="button" aria-label="Cancel final review submission" data-reviewer-submit-cancel><x-dashboard.icon name="x" size="20" /></button>

                <div class="reviewer-submit-state" data-reviewer-submit-confirmation>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="clipboard" size="24" /></span>
                        <div>
                            <h2 id="reviewer-submit-title">Submit Final Review</h2>
                            <p id="reviewer-submit-description">Confirm the decision that will be sent into the protected REU workflow.</p>
                        </div>
                    </header>

                    <dl class="reviewer-submit-summary">
                        <div>
                            <dt>Selected Final Decision</dt>
                            <dd data-reviewer-submit-decision>Not selected</dd>
                        </div>
                        <div>
                            <dt>Required Worksheets</dt>
                            <dd data-reviewer-submit-form-summary>{{ $completedForms }} of 2 completed</dd>
                        </div>
                    </dl>

                    <div class="reviewer-submit-warning" id="reviewer-submit-warning" role="note">
                        <x-dashboard.icon name="alert-triangle" size="20" />
                        <p><strong>A permanent submission version will be created.</strong> You may continue editing and submit a newer version until REU releases the decision.</p>
                    </div>
                    <p class="reviewer-submit-feedback" role="alert" aria-live="assertive" data-reviewer-submit-feedback></p>

                    <div class="application-modal-actions reviewer-submit-actions">
                        <button class="dashboard-outline-action" type="button" data-reviewer-submit-cancel>Cancel</button>
                        <button class="dashboard-primary-action" type="button" data-reviewer-submit-confirm>
                            <x-dashboard.icon name="check" size="17" />
                            <span data-reviewer-submit-confirm-label>Confirm Final Submission</span>
                        </button>
                    </div>
                </div>

                <div class="reviewer-submit-state reviewer-submit-result" data-reviewer-submit-result hidden>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon reviewer-submit-result-icon"><x-dashboard.icon name="check" size="24" /></span>
                        <div>
                            <h2 id="reviewer-submit-result-title">Review Submitted</h2>
                            <p id="reviewer-submit-result-description" data-reviewer-submit-result-message>Your final review was recorded and is pending the next authorized REU action.</p>
                        </div>
                    </header>
                    <dl class="reviewer-submit-summary">
                        <div>
                            <dt>Final Decision</dt>
                            <dd data-reviewer-submit-result-decision></dd>
                        </div>
                        <div>
                            <dt>Submitted</dt>
                            <dd data-reviewer-submit-result-time></dd>
                        </div>
                    </dl>
                    <div class="application-modal-actions reviewer-submit-actions">
                        <a class="dashboard-primary-action" href="{{ route('reviewer.assignments.show', $assignment) }}" data-reviewer-submit-result-link>
                            <span>Return to Assignment</span>
                            <x-dashboard.icon name="arrow-right" size="17" />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        @foreach ($formCatalog as $catalog)
            @php
                $type = $catalog['type'];
                $form = $forms->get($type->value);
                $formIsFinal = $form?->status === \App\Enums\ReviewFormStatus::Final;
                $formCompleted = $formIsComplete($form);
                $formCanWrite = $canWrite;
                $progress = $formProgress($form, $catalog);
                $statusLabel = $formIsFinal ? 'Final' : ($formCompleted ? 'Completed' : ($form ? 'In Progress' : 'Not Started'));
                $consentValue = old('form_type') === $type->value
                    ? old('consent_required', '')
                    : ($form?->consent_required === null ? '' : (string) (int) $form->consent_required);
                $recommendationValue = old('form_type') === $type->value
                    ? old('recommendation')
                    : $form?->recommendation?->value;
            @endphp
            <section
                class="application-modal-backdrop reviewer-inline-form-shell"
                id="reviewer-inline-form-{{ $type->value }}"
                data-reviewer-form-dialog="{{ $type->value }}"
                @if ($errors->reviewerForm->any() && old('form_type') === $type->value) data-open-on-load @else hidden @endif
            >
                <div class="application-modal reviewer-inline-form" role="dialog" aria-modal="true" aria-labelledby="reviewer-form-{{ $type->value }}-title" tabindex="-1">
                    <header class="application-modal-heading reviewer-inline-form-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="clipboard" size="24" /></span>
                        <div>
                            <h2 id="reviewer-form-{{ $type->value }}-title">{{ $type->label() }}</h2>
                            <p>{{ $type->code() }} - Application {{ $application->application_code }}</p>
                        </div>
                        <x-dashboard.status-badge
                            :label="$statusLabel"
                            :tone="$formCompleted ? 'success' : ($form ? 'blue' : 'neutral')"
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
                        data-saved-recommendation="{{ $form?->recommendation?->value }}"
                        data-form-completed="{{ $formCompleted ? 'true' : 'false' }}"
                    >
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form_type" value="{{ $type->value }}">

                        @if ($type === \App\Enums\ReviewFormType::InformedConsent)
                            <fieldset
                                class="reviewer-consent-gate"
                                data-reviewer-consent-gate
                                data-reviewer-consent-writable="{{ $formCanWrite ? 'true' : 'false' }}"
                            >
                                <legend>Is it necessary to seek the informed consent of the participants?</legend>
                                <label><input type="radio" name="consent_required" value="1" @checked((string) $consentValue === '1') @disabled(! $formCanWrite)> Yes</label>
                                <label><input type="radio" name="consent_required" value="0" @checked((string) $consentValue === '0') @disabled(! $formCanWrite)> No</label>
                                <label class="application-field reviewer-consent-explanation" data-reviewer-consent-explanation @if ((string) $consentValue !== '0') hidden @endif>
                                    <span>If NO, please explain (answer briefly)</span>
                                    <textarea name="consent_not_required_explanation" rows="3" minlength="10" maxlength="2000" data-reviewer-consent-explanation-input @disabled(! $formCanWrite || (string) $consentValue !== '0') @required($formCanWrite && (string) $consentValue === '0')>{{ old('form_type') === $type->value ? old('consent_not_required_explanation') : $form?->consent_not_required_explanation }}</textarea>
                                </label>
                            </fieldset>
                        @endif

                        <div class="reviewer-form-question-list" @if ($type === \App\Enums\ReviewFormType::InformedConsent) data-reviewer-consent-dependent @if ((string) $consentValue !== '1') hidden @endif @endif>
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
                                        <span>{{ $item['printed_number'] ?? $loop->iteration }}</span>
                                        {{ $question }}
                                    </legend>
                                    <div class="reviewer-form-answer-options">
                                        @foreach ($catalog['answers'] as $answerValue => $answerLabel)
                                            <label><input type="radio" name="responses[{{ $questionKey }}][answer]" value="{{ $answerValue }}" @checked($savedAnswer === $answerValue) @disabled(! $formCanWrite || ($type === \App\Enums\ReviewFormType::InformedConsent && (string) $consentValue !== '1')) @required($loop->first && $formCanWrite && ! ($type === \App\Enums\ReviewFormType::InformedConsent && (string) $consentValue !== '1'))> {{ $answerLabel }}</label>
                                        @endforeach
                                    </div>
                                    @if ($type === \App\Enums\ReviewFormType::Protocol)
                                        <label class="reviewer-form-item-comment">
                                            <span>Comment (optional)</span>
                                            <input name="responses[{{ $questionKey }}][comment]" value="{{ $savedComment }}" maxlength="1000" @disabled(! $formCanWrite)>
                                        </label>
                                    @endif
                                </fieldset>
                            @endforeach
                        </div>

                        <div class="reviewer-form-recommendation">
                            <fieldset class="reviewer-form-recommendation-options">
                                <legend>Recommendation</legend>
                                @foreach ($decisions as $decision)
                                    <label>
                                        <input
                                            type="radio"
                                            name="recommendation"
                                            value="{{ $decision->value }}"
                                            @checked($recommendationValue === $decision->value)
                                            @disabled(! $formCanWrite)
                                        >
                                        <span>{{ $decision->label() }}</span>
                                    </label>
                                @endforeach
                            </fieldset>
                            <div class="application-field">
                                <label for="{{ $type->value }}-recommendation-comments">Recommendation Comments</label>
                                <textarea id="{{ $type->value }}-recommendation-comments" name="recommendation_comments" rows="4" minlength="5" maxlength="2000" @disabled(! $formCanWrite) @required($formCanWrite)>{{ old('form_type') === $type->value ? old('recommendation_comments') : $form?->recommendation_comments }}</textarea>
                                @error('recommendation_comments', 'reviewerForm')<small class="application-field-error">{{ $message }}</small>@enderror
                            </div>
                        </div>

                        <div class="reviewer-form-progress" aria-live="polite">
                            <span id="reviewer-form-{{ $type->value }}-progress-label" data-reviewer-form-progress="{{ $type->value }}">{{ $progress['answered'] }} of {{ $progress['total'] }} items completed</span>
                            <progress value="{{ $progress['answered'] }}" max="{{ $progress['total'] }}" aria-labelledby="reviewer-form-{{ $type->value }}-progress-label" data-reviewer-form-progress-bar>{{ $progress['answered'] }} / {{ $progress['total'] }}</progress>
                        </div>
                        <p class="reviewer-form-feedback" role="status" aria-live="polite" data-reviewer-form-feedback></p>

                        <div class="application-modal-actions reviewer-form-actions">
                            @if ($reviewSubmitted && $form?->artifact?->status === \App\Enums\ReviewFormArtifactStatus::Ready)
                                <div class="reviewer-form-artifact-actions">
                                    <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.forms.artifacts.preview', [$assignment, $form, $form->artifact]) }}" target="_blank" rel="noopener">
                                        <x-dashboard.icon name="eye" size="16" />
                                        <span>Preview Official PDF</span>
                                    </a>
                                    <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.forms.artifacts.download', [$assignment, $form, $form->artifact]) }}">
                                        <x-dashboard.icon name="download" size="16" />
                                        <span>Download PDF</span>
                                    </a>
                                </div>
                            @endif
                            @if ($formCanWrite)
                                <div class="reviewer-form-submit-actions">
                                    <button class="dashboard-outline-action" type="submit" name="intent" value="draft" formnovalidate data-reviewer-form-save-draft>Save Draft</button>
                                    <button class="dashboard-primary-action" type="submit" name="intent" value="submit" data-reviewer-form-submit-final>Submit</button>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        @endforeach

    </div>
@endsection
