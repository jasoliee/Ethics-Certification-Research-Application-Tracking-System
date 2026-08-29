@extends('layouts.dashboard')

@section('content')
    @php
        $screening = $application->screening;
        // Legacy assigned records may predate application_screenings; never invent or dereference a missing decision.
        $savedReviewType = $screening?->review_type
            ?? \App\Enums\ReviewType::tryFrom((string) $application->review_type);
        $assignedCount = $application->reviewerAssignments->count();
        $isUnderReview = in_array($application->application_status, \App\Enums\ApplicationStatus::underReview(), true);
        $isEditingScreening = $editingScreening && $screening;
    @endphp

    <div class="dashboard-page application-workspace res-workflow-page">
        <header class="dashboard-page-heading res-screening-page-heading">
            <div>
                <h1>{{ $screening ? 'Application Details' : 'Application Screening Details' }}</h1>
            </div>
            <div class="res-page-heading-actions">
                <a class="dashboard-outline-action" href="{{ route('res.applications.index') }}">
                    <x-dashboard.icon name="arrow-left" size="17" />
                    <span>Back to Applications</span>
                </a>
            </div>
        </header>

        @if ($application->application_status === \App\Enums\ApplicationStatus::Exempted)
            {{-- Exempted cases stop at the documented direct-release boundary without exposing unfinished certificate controls. --}}
            <section class="res-workflow-banner is-success" role="status">
                <span><x-dashboard.icon name="check" size="22" /></span>
                <div>
                    <strong>Application classified as exempted</strong>
                    <p>Standard reviewer assignment is not required. Direct documentation and certificate release remain a later REU process.</p>
                </div>
                @if ($canUpdateScreening && ! $isEditingScreening)
                    <a class="dashboard-outline-action" href="{{ route('res.applications.show', [$application, 'edit_screening' => 1]) }}">
                        <x-dashboard.icon name="edit" size="17" />
                        <span>Re-edit Decision</span>
                    </a>
                @endif
            </section>
        @elseif ($isUnderReview)
            <section class="res-workflow-banner is-success" role="status">
                <span><x-dashboard.icon name="check" size="22" /></span>
                <div>
                    <strong>Application is now under reviewer evaluation</strong>
                    <p>The application was assigned to the required eligible reviewer or reviewers.</p>
                </div>
                @if ($screening && $savedReviewType?->requiresReviewers())
                    {{-- Keep the two related assignment decisions together instead of separating edit from view. --}}
                    <div class="res-workflow-banner-actions">
                        @if ($canUpdateScreening && ! $isEditingScreening)
                            <a class="dashboard-outline-action" href="{{ route('res.applications.show', [$application, 'edit_screening' => 1]) }}">
                                <x-dashboard.icon name="edit" size="17" />
                                <span>Re-edit Decision</span>
                            </a>
                        @endif
                        <a class="dashboard-outline-action" href="{{ route('res.applications.reviewers.index', $application) }}">Re-edit Assignment</a>
                    </div>
                @endif
            </section>
        @endif

        {{-- The overview mirrors the high-fidelity scan order while showing only fields persisted by ECRATS. --}}
        <div class="res-screening-overview-grid">
            <section class="res-workflow-panel res-requirement-panel" data-collapsible-panel>
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="clipboard" size="21" /><h2>Requirement Checklist</h2></div>
                    <x-dashboard.status-badge
                        :label="$requirementSummary['ready'] ? 'Complete' : 'Action Required'"
                        :tone="$requirementSummary['ready'] ? 'success' : 'orange'"
                    />
                </header>
                <x-dashboard.overflow label="REU requirement checklist" wide>
                    <table class="dashboard-table res-requirement-table">
                        <thead>
                            <tr><th>Document Type</th><th>Status</th><th>File Name / Version</th><th>Uploaded Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($requirementSummary['items'] as $item)
                                @php
                                    $document = $item['document'];
                                @endphp
                                <tr>
                                    <td><strong>{{ $item['requirement']->name }}</strong></td>
                                    <td><x-dashboard.status-badge :label="$item['status']->label()" :tone="$item['status']->tone()" /></td>
                                    <td>{{ $document?->original_file_name ?? 'No file uploaded' }}@if ($document)<small>v{{ $item['version'] }}</small>@endif</td>
                                    <td>{{ $document?->uploaded_at?->format('M j, Y') ?? '-' }}</td>
                                    <td class="res-document-actions">
                                        @if ($document)
                                            <button
                                                class="dashboard-outline-action res-document-action"
                                                type="button"
                                                data-document-open
                                                data-document-name="{{ $document->original_file_name }}"
                                                data-document-type="{{ $document->fileTypeLabel() }}"
                                                data-document-meta="{{ $item['requirement']->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                                                data-document-preview-kind="{{ $document->previewKind() }}"
                                                data-document-preview-url="{{ route('res.applications.documents.preview', [$application, $document]) }}"
                                                data-document-download-url="{{ route('res.applications.documents.download', [$application, $document]) }}"
                                            >
                                                <x-dashboard.icon name="eye" size="16" />
                                                <span class="sr-only">Preview {{ $document->original_file_name }}</span>
                                            </button>
                                            <a
                                                class="dashboard-icon-action"
                                                href="{{ route('res.applications.documents.download', [$application, $document]) }}"
                                                title="Download document"
                                                aria-label="Download {{ $document->original_file_name }}"
                                            >
                                                <x-dashboard.icon name="download" size="16" />
                                            </a>
                                        @else
                                            <span class="res-muted-value">Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            </section>

            <section class="res-workflow-panel res-overview-panel" data-collapsible-panel>
                <header class="res-workflow-panel-heading">
                    <x-dashboard.icon name="file-text" size="21" />
                    <h2>Application Details</h2>
                </header>
                <dl class="res-compact-details">
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Applicant Category</dt><dd>{{ Str::headline($application->applicant_type) }}</dd></div>
                    <div><dt>Research Type</dt><dd>{{ $application->research_type?->label() ?? 'Not specified' }}</dd></div>
                    <div><dt>Institute / Program</dt><dd>{{ $application->institution ?: 'Not specified' }}@if ($application->program)<br><small>{{ $application->program }}</small>@endif</dd></div>
                    <div><dt>Adviser</dt><dd>{{ $application->adviser?->name ?? 'Archived adviser' }}</dd></div>
                    <div><dt>Date Endorsed</dt><dd>{{ $application->latestEndorsement?->endorsed_at?->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                    <div><dt>Current Status</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
                </dl>
            </section>

            <section class="res-workflow-panel res-research-panel" data-collapsible-panel>
                <header class="res-workflow-panel-heading">
                    <x-dashboard.icon name="file-search" size="21" />
                    <h2>Research Information</h2>
                </header>
                <dl class="res-compact-details">
                    <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                    <div><dt>Research Category</dt><dd>{{ $application->research_category ?: 'Not specified' }}</dd></div>
                    <div><dt>Participant Group</dt><dd>{{ $application->target_participants ?: 'Not specified' }}</dd></div>
                    <div><dt>Expected Duration</dt><dd>{{ $application->expectedDurationLabel() }}</dd></div>
                    <div class="res-detail-wide"><dt>Study Overview</dt><dd>{{ $application->abstract ?: 'Not specified' }}</dd></div>
                </dl>
            </section>

        @if ($canClassify || $isEditingScreening)
            {{-- Initial decisions and corrections share fields but use separate authorized service operations. --}}
            <form
                class="res-screening-decision-layout"
                method="POST"
                action="{{ $isEditingScreening ? route('res.applications.classification.update', $application) : route('res.applications.classification.store', $application) }}"
                data-application-submit-once
                data-partial-auto-save-draft="{{ route('res.applications.classification.draft', $application) }}"
                @if ($isEditingScreening) data-confirm-screening-update @endif
            >
                @csrf
                @if ($isEditingScreening) @method('PUT') @endif

                <section class="res-workflow-panel res-classification-panel" data-collapsible-panel>
                    <header class="res-workflow-panel-heading">
                        <x-dashboard.icon name="award" size="21" />
                        <h2>Review Type Classification</h2>
                    </header>
                    {{-- The decision inputs share the full panel width and collapse to one column on compact screens. --}}
                    <div class="res-classification-fields">
                        <div class="res-review-type-column">
                            <fieldset class="res-review-type-options">
                                <legend>Select Review Type</legend>
                                @foreach ($reviewTypes as $reviewType)
                                    <label>
                                        <input name="review_type" type="radio" value="{{ $reviewType->value }}" @checked(old('review_type', $screeningDraft['review_type'] ?? ($isEditingScreening ? $screening->review_type->value : '')) === $reviewType->value) required>
                                        <span class="res-review-type-copy">
                                            <strong>{{ $reviewType->label() }}</strong>
                                            <small>{{ match ($reviewType) {
                                                \App\Enums\ReviewType::Expedited => 'For eligible minimal-risk studies. Requires exactly one reviewer.',
                                                \App\Enums\ReviewType::FullBoard => 'For studies requiring broader committee review. Requires exactly three reviewers.',
                                                \App\Enums\ReviewType::Exempted => 'Bypasses standard reviewer assignment and enters documentation processing.',
                                            } }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                            @error('review_type', 'resScreening')<small class="application-field-error res-standalone-error">{{ $message }}</small>@enderror
                        </div>

                        <div class="application-field res-classification-reason">
                            <label for="classification_reason">Reason / Basis for Classification</label>
                            <textarea id="classification_reason" name="classification_reason" rows="8" minlength="5" maxlength="2000" required>{{ old('classification_reason', $screeningDraft['classification_reason'] ?? ($isEditingScreening ? $screening->classification_reason : '')) }}</textarea>
                            @error('classification_reason', 'resScreening')<small class="application-field-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="res-classification-note">
                        <x-dashboard.icon name="circle-help" size="18" />
                        <span>Classification determines the reviewer count and next workflow status.</span>
                    </div>
                    <div class="res-classification-actions">
                        @if ($isEditingScreening)
                            <a class="dashboard-outline-action" href="{{ route('res.applications.show', $application) }}">Cancel</a>
                        @endif
                        <button class="dashboard-primary-action res-classification-submit" type="submit" @disabled(! $isEditingScreening && ! $requirementSummary['ready'])>
                            <span>{{ $isEditingScreening ? 'Update Screening Decision' : 'Save Classification' }}</span>
                            <x-dashboard.icon name="arrow-right" size="18" />
                        </button>
                    </div>
                </section>
            </form>
        @elseif ($screening)
            {{-- Saved classifications remain readable by default; authorized corrections open through explicit edit mode. --}}
            <div class="res-screening-decision-layout is-readonly">
                <section class="res-workflow-panel res-classification-panel" data-collapsible-panel>
                    <header class="res-workflow-panel-heading"><x-dashboard.icon name="award" size="21" /><h2>Screening and Classification</h2></header>
                    <dl class="res-screening-summary">
                        <div><dt>Review Type</dt><dd><x-dashboard.status-badge :label="$savedReviewType->label()" tone="success" /></dd></div>
                        <div><dt>Reviewers Required</dt><dd>{{ $savedReviewType->reviewerCount() }} {{ Str::plural('reviewer', $savedReviewType->reviewerCount()) }}</dd></div>
                        <div><dt>Classified By</dt><dd>{{ $screening->screenedBy?->name ?? 'Archived REU Lead' }}</dd></div>
                        <div><dt>Classification Date</dt><dd>{{ $screening->classified_at->format('M j, Y g:i A') }}</dd></div>
                        <div class="res-detail-wide"><dt>Reason / Basis</dt><dd>{{ $screening->classification_reason }}</dd></div>
                    </dl>
                    @if (! $isUnderReview && (($canUpdateScreening && ! $isEditingScreening) || ($canAssignReviewers && $savedReviewType->requiresReviewers())))
                        <div class="res-classification-actions">
                            @if ($canUpdateScreening && ! $isEditingScreening)
                                <a class="dashboard-outline-action" href="{{ route('res.applications.show', [$application, 'edit_screening' => 1]) }}">
                                    <x-dashboard.icon name="edit" size="17" />
                                    <span>Re-edit Decision</span>
                                </a>
                            @endif
                            @if ($canAssignReviewers && $savedReviewType->requiresReviewers())
                                <a class="dashboard-primary-action res-classification-submit" href="{{ route('res.applications.reviewers.index', $application) }}">
                                    <span>Proceed to Reviewer Assignment</span>
                                    <x-dashboard.icon name="arrow-right" size="18" />
                                </a>
                            @endif
                        </div>
                    @endif
                </section>
            </div>
        @endif
        </div>

        @if ($officialReviewArtifacts->isNotEmpty())
            <section class="res-workflow-panel res-official-review-forms-panel">
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="file-pdf" size="21" /><h2>Official Reviewer Forms</h2></div>
                    <x-dashboard.status-badge :label="$officialReviewArtifacts->count().' finalized'" tone="success" />
                </header>
                <x-dashboard.overflow label="Finalized official reviewer forms" wide>
                    <table class="dashboard-table res-official-review-forms-table">
                        <thead><tr><th>Official Form</th><th>Reviewer</th><th>Assignment</th><th>Finalized</th><th>Artifact</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($officialReviewArtifacts as $artifact)
                                @php
                                    $form = $artifact->formSubmission;
                                    $assignmentRecord = $form->assignment;
                                @endphp
                                <tr>
                                    <td><strong>{{ $form->form_type->code() }}</strong><small>{{ $form->form_type->label() }}</small></td>
                                    <td>{{ $assignmentRecord->reviewer?->name ?? 'Archived reviewer' }}</td>
                                    <td>#{{ $assignmentRecord->assignment_sequence }}<small>{{ $assignmentRecord->isCurrent() ? 'Current' : 'Superseded record' }}</small></td>
                                    <td>{{ $artifact->generated_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                                    <td>V{{ $artifact->business_version ?? (((int) $assignmentRecord->review_cycle) + 1) }}<small>Internal artifact {{ $artifact->artifact_version }} · SHA-256 {{ Str::upper(Str::substr($artifact->sha256, 0, 12)) }}...</small></td>
                                    <td class="res-document-actions">
                                        <a
                                            class="dashboard-outline-action res-document-action"
                                            href="{{ route('res.applications.review-form-artifacts.preview', [$application, $assignmentRecord, $form, $artifact]) }}"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <x-dashboard.icon name="eye" size="16" />
                                            <span class="sr-only">Preview {{ $form->form_type->label() }}</span>
                                        </a>
                                        <a
                                            class="dashboard-icon-action"
                                            href="{{ route('res.applications.review-form-artifacts.download', [$application, $assignmentRecord, $form, $artifact]) }}"
                                            title="Download finalized official form"
                                            aria-label="Download {{ $artifact->original_file_name }}"
                                        >
                                            <x-dashboard.icon name="download" size="16" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            </section>
        @endif

        @if ($assignedCount > 0)
            <section class="res-workflow-panel res-assigned-reviewers-panel">
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="users" size="21" /><h2>Assigned {{ Str::plural('Reviewer', $assignedCount) }}</h2></div>
                    <x-dashboard.status-badge :label="$assignedCount.' / '.($savedReviewType?->reviewerCount() ?? $assignedCount).' Assigned'" tone="success" />
                </header>
                <x-dashboard.overflow label="Assigned reviewer records" wide>
                    <table class="dashboard-table res-assigned-reviewer-table">
                        <thead><tr><th>#</th><th>Reviewer</th><th>Position</th><th>Institute</th><th>Current Load</th><th>Date Assigned</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($application->reviewerAssignments as $assignment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $assignment->reviewer?->name ?? 'Archived reviewer' }}</strong></td>
                                    <td>{{ $assignment->reviewer?->position_title ?: 'Not specified' }}</td>
                                    <td>{{ $assignment->reviewer?->institution ?: 'Not specified' }}</td>
                                    <td>{{ $assignment->reviewer?->active_assignment_count ?? 0 }} / {{ $assignment->reviewer?->reviewer_capacity ?? 0 }}</td>
                                    <td>{{ $assignment->assigned_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                                    <td><x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            </section>
        @endif

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
