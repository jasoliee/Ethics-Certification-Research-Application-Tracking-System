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
                <p>{{ $screening
                    ? 'View the endorsed application record, screening classification, supporting documents, and reviewer assignment state.'
                    : 'Review endorsed application details, uploaded requirements, receipt status, and eligibility before classification.' }}</p>
            </div>
            <div class="res-page-heading-actions">
                @if ($canUpdateScreening && ! $isEditingScreening)
                    {{-- Saved decisions open in an explicit edit mode so ordinary detail visits remain read-only. --}}
                    <a class="dashboard-outline-action" href="{{ route('res.applications.show', [$application, 'edit_screening' => 1]) }}">
                        <x-dashboard.icon name="edit" size="17" />
                        <span>Re-edit Screening Decision</span>
                    </a>
                @endif
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
                    <p>Standard reviewer assignment is not required. Direct documentation and certificate release remain a later RES process.</p>
                </div>
            </section>
        @elseif ($isUnderReview)
            <section class="res-workflow-banner is-success" role="status">
                <span><x-dashboard.icon name="check" size="22" /></span>
                <div>
                    <strong>Application is now under reviewer evaluation</strong>
                    <p>The application was assigned to the required eligible reviewer or reviewers.</p>
                </div>
                @if ($screening && $savedReviewType?->requiresReviewers())
                    <a class="dashboard-outline-action" href="{{ route('res.applications.reviewers.index', $application) }}">View Assignment</a>
                @endif
            </section>
        @endif

        {{-- The overview mirrors the high-fidelity scan order while showing only fields persisted by ECRATS. --}}
        <div class="res-screening-overview-grid">
            <section class="res-workflow-panel res-overview-panel">
                <header class="res-workflow-panel-heading">
                    <x-dashboard.icon name="file-text" size="21" />
                    <h2>Application Overview</h2>
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

            <section class="res-workflow-panel res-research-panel">
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

            <section class="res-workflow-panel res-requirement-panel">
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="clipboard" size="21" /><h2>Requirement Checklist</h2></div>
                    <x-dashboard.status-badge
                        :label="$requirementSummary['ready'] ? 'Complete' : 'Action Required'"
                        :tone="$requirementSummary['ready'] ? 'success' : 'orange'"
                    />
                </header>
                <x-dashboard.overflow label="RES requirement checklist" wide>
                    <table class="dashboard-table res-requirement-table">
                        <thead>
                            <tr><th>Document Type</th><th>Status</th><th>File Name / Version</th><th>Uploaded Date</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($requirementSummary['items'] as $item)
                                @php($document = $item['document'])
                                <tr>
                                    <td><strong>{{ $item['requirement']->name }}</strong></td>
                                    <td><x-dashboard.status-badge :label="$item['status']->label()" :tone="$item['status']->tone()" /></td>
                                    <td>{{ $document?->original_file_name ?? 'No file uploaded' }}@if ($document)<small>v{{ $item['version'] }}</small>@endif</td>
                                    <td>{{ $document?->uploaded_at?->format('M j, Y') ?? '-' }}</td>
                                    <td>
                                        @if ($document)
                                            <button
                                                class="dashboard-outline-action res-document-action"
                                                type="button"
                                                data-document-open
                                                data-document-name="{{ $document->original_file_name }}"
                                                data-document-meta="{{ $item['requirement']->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                                                data-document-preview-url="{{ route('res.applications.documents.preview', [$application, $document]) }}"
                                                data-document-download-url="{{ route('res.applications.documents.download', [$application, $document]) }}"
                                            >
                                                <x-dashboard.icon name="eye" size="16" />
                                                <span>View Document</span>
                                            </button>
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
        </div>

        @if ($canClassify || $isEditingScreening)
            {{-- Initial decisions and corrections share fields but use separate authorized service operations. --}}
            <form
                class="res-screening-decision-layout"
                method="POST"
                action="{{ $isEditingScreening ? route('res.applications.classification.update', $application) : route('res.applications.classification.store', $application) }}"
                data-application-submit-once
                @if ($isEditingScreening) data-confirm-screening-update @endif
            >
                @csrf
                @if ($isEditingScreening) @method('PUT') @endif

                <section class="res-workflow-panel res-administrative-panel">
                    <header class="res-workflow-panel-heading">
                        <x-dashboard.icon name="user-check" size="21" />
                        <h2>Administrative Screening Panel</h2>
                    </header>

                    @if ($errors->resScreening->any())
                        <div class="res-form-error-summary" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Review the screening information.</strong><span>{{ $errors->resScreening->first() }}</span></div>
                        </div>
                    @endif

                    @if ($isEditingScreening)
                        {{-- The service preserves compatible assignments and blocks changes that would overwrite started work. --}}
                        <div class="res-screening-edit-notice" role="note">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <span>Changing the review path or revoking an administrative confirmation removes only unstarted pending assignments. Started review work cannot be overwritten.</span>
                        </div>
                    @endif

                    <div class="res-screening-fields">
                        <div class="application-field">
                            <label for="completeness_status">Completeness Status</label>
                            <select id="completeness_status" name="completeness_status" required>
                                <option value="">Select status</option>
                                @foreach ($completenessStatuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('completeness_status', $isEditingScreening ? $screening->completeness_status->value : '') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('completeness_status', 'resScreening')<small class="application-field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="application-field">
                            <label for="receipt_check_status">Receipt Check Status</label>
                            <select id="receipt_check_status" name="receipt_check_status" required>
                                <option value="">Select status</option>
                                @foreach ($receiptStatuses as $status)
                                    <option value="{{ $status->value }}" @selected(old('receipt_check_status', $isEditingScreening ? $screening->receipt_check_status->value : '') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('receipt_check_status', 'resScreening')<small class="application-field-error">{{ $message }}</small>@enderror
                        </div>
                        <div class="application-field application-field-full">
                            <label for="screening_notes">Screening Notes</label>
                            <textarea id="screening_notes" name="screening_notes" rows="5" maxlength="2000" placeholder="Record concise administrative observations only.">{{ old('screening_notes', $isEditingScreening ? $screening->screening_notes : '') }}</textarea>
                            @error('screening_notes', 'resScreening')<small class="application-field-error">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <fieldset class="res-eligibility-checks">
                        <legend>Eligibility Checks</legend>
                        {{-- Hidden false values let a correction explicitly revoke a previous confirmation. --}}
                        <input name="required_documents_verified" type="hidden" value="0">
                        <label><input name="required_documents_verified" type="checkbox" value="1" @checked(old('required_documents_verified', $isEditingScreening ? $screening->required_documents_verified : false)) @required(! $isEditingScreening)><span>Required documents verified</span></label>
                        <input name="receipt_status_recorded" type="hidden" value="0">
                        <label><input name="receipt_status_recorded" type="checkbox" value="1" @checked(old('receipt_status_recorded', $isEditingScreening ? $screening->receipt_status_recorded : false)) @required(! $isEditingScreening)><span>Receipt status recorded</span></label>
                        <input name="basic_eligibility_confirmed" type="hidden" value="0">
                        <label><input name="basic_eligibility_confirmed" type="checkbox" value="1" @checked(old('basic_eligibility_confirmed', $isEditingScreening ? $screening->basic_eligibility_confirmed : false)) @required(! $isEditingScreening)><span>Basic eligibility confirmed</span></label>
                    </fieldset>
                </section>

                <section class="res-workflow-panel res-classification-panel">
                    <header class="res-workflow-panel-heading">
                        <x-dashboard.icon name="award" size="21" />
                        <h2>Review Type Classification</h2>
                    </header>
                    <fieldset class="res-review-type-options">
                        <legend>Select Review Type</legend>
                        @foreach ($reviewTypes as $reviewType)
                            <label>
                                <input name="review_type" type="radio" value="{{ $reviewType->value }}" @checked(old('review_type', $isEditingScreening ? $screening->review_type->value : '') === $reviewType->value) required>
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

                    <div class="application-field res-classification-reason">
                        <label for="classification_reason">Reason / Basis for Classification</label>
                        <textarea id="classification_reason" name="classification_reason" rows="4" minlength="15" maxlength="2000" required>{{ old('classification_reason', $isEditingScreening ? $screening->classification_reason : '') }}</textarea>
                        @error('classification_reason', 'resScreening')<small class="application-field-error">{{ $message }}</small>@enderror
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
                <section class="res-workflow-panel res-administrative-panel">
                    <header class="res-workflow-panel-heading"><x-dashboard.icon name="user-check" size="21" /><h2>Administrative Screening</h2></header>
                    <dl class="res-screening-summary">
                        <div><dt>Completeness</dt><dd><x-dashboard.status-badge :label="$screening->completeness_status->label()" tone="success" /></dd></div>
                        <div><dt>Receipt Check</dt><dd><x-dashboard.status-badge :label="$screening->receipt_check_status->label()" tone="blue" /></dd></div>
                        <div><dt>Classified By</dt><dd>{{ $screening->screenedBy?->name ?? 'Archived RES Lead' }}</dd></div>
                        <div><dt>Classification Date</dt><dd>{{ $screening->classified_at->format('M j, Y g:i A') }}</dd></div>
                        <div class="res-detail-wide"><dt>Screening Notes</dt><dd>{{ $screening->screening_notes ?: 'No additional screening notes.' }}</dd></div>
                    </dl>
                </section>
                <section class="res-workflow-panel res-classification-panel">
                    <header class="res-workflow-panel-heading"><x-dashboard.icon name="award" size="21" /><h2>Screening and Classification</h2></header>
                    <dl class="res-screening-summary">
                        <div><dt>Review Type</dt><dd><x-dashboard.status-badge :label="$savedReviewType->label()" tone="success" /></dd></div>
                        <div><dt>Reviewers Required</dt><dd>{{ $savedReviewType->reviewerCount() }} {{ Str::plural('reviewer', $savedReviewType->reviewerCount()) }}</dd></div>
                        <div class="res-detail-wide"><dt>Reason / Basis</dt><dd>{{ $screening->classification_reason }}</dd></div>
                    </dl>
                    @if ($canAssignReviewers && $savedReviewType->requiresReviewers())
                        <a class="dashboard-primary-action res-classification-submit" href="{{ route('res.applications.reviewers.index', $application) }}">
                            <span>Proceed to Reviewer Assignment</span>
                            <x-dashboard.icon name="arrow-right" size="18" />
                        </a>
                    @endif
                </section>
            </div>
        @endif

        @if ($assignedCount > 0)
            <section class="res-workflow-panel res-assigned-reviewers-panel">
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="users" size="21" /><h2>Assigned {{ Str::plural('Reviewer', $assignedCount) }}</h2></div>
                    <x-dashboard.status-badge :label="$assignedCount.' / '.($savedReviewType?->reviewerCount() ?? $assignedCount).' Assigned'" tone="success" />
                </header>
                <x-dashboard.overflow label="Assigned reviewer records" wide>
                    <table class="dashboard-table res-assigned-reviewer-table">
                        <thead><tr><th>#</th><th>Reviewer</th><th>Position</th><th>Department</th><th>Current Load</th><th>Date Assigned</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($application->reviewerAssignments as $assignment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $assignment->reviewer?->name ?? 'Archived reviewer' }}</strong></td>
                                    <td>{{ $assignment->reviewer?->position_title ?: 'Not specified' }}</td>
                                    <td>{{ $assignment->reviewer?->department ?: 'Not specified' }}</td>
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
