@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page application-workspace">
        {{-- Document Submission heading keeps application identity and navigation visible. --}}
        <header class="application-record-header">
            <div>
                <span class="identity-eyebrow">{{ $application->application_code }}</span>
                <h1>Document Submission</h1>
                <p>{{ $application->research_title }}</p>
            </div>
            <div class="application-record-actions">
                <a class="dashboard-outline-action" href="{{ route('applicant.applications.show', $application) }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Application Details</span></a>
                @if ($canUpload)
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.edit', $application) }}"><x-dashboard.icon name="edit" size="17" /><span>Edit Information</span></a>
                @endif
            </div>
        </header>

        @if ($errors->any())
            {{-- Submission and upload errors remain visible above the affected checklist. --}}
            <div class="identity-validation-summary" role="alert">
                <strong>Document submission needs attention.</strong>
                @foreach ($errors->all() as $message)<span>{{ $message }}</span>@endforeach
            </div>
        @endif

        {{-- Completion uses actual active mandatory requirements and the current private document versions. --}}
        <section class="application-panel application-requirements-progress">
            <div class="application-progress-heading">
                <div><h2>Requirements Completion</h2><p>{{ $requirementSummary['completed_count'] }} of {{ $requirementSummary['mandatory_total'] }} mandatory requirements completed</p></div>
                <strong>{{ $requirementSummary['percentage'] }}%</strong>
            </div>
            <progress class="application-progress" value="{{ $requirementSummary['completed_count'] }}" max="{{ max(1, $requirementSummary['mandatory_total']) }}">{{ $requirementSummary['percentage'] }}%</progress>
            <div class="application-progress-breakdown">
                <span><strong>{{ $requirementSummary['completed_count'] }}</strong> Completed</span>
                <span><strong>{{ $requirementSummary['missing_count'] }}</strong> Missing</span>
                <span><strong>{{ $requirementSummary['pending_count'] }}</strong> Pending</span>
                <span><strong>{{ $requirementSummary['rejected_count'] }}</strong> Rejected</span>
            </div>
        </section>

        {{-- Each configured requirement owns one upload or replace action and secure view/download actions. --}}
        <section class="application-requirement-list" aria-label="Document requirements">
            @forelse ($requirementSummary['items'] as $item)
                @php($document = $item['document'])
                <article class="application-requirement-row">
                    <span class="application-requirement-icon"><x-dashboard.icon :name="$item['icon']" size="25" /></span>
                    <div class="application-requirement-copy">
                        <div class="application-requirement-title">
                            <h2>{{ $item['requirement']->name }}</h2>
                            @if ($item['requirement']->is_mandatory)<span>Required</span>@else<span>Optional</span>@endif
                        </div>
                        <p>{{ $item['requirement']->description ?: $item['requirement']->code }}</p>
                        @if ($document)
                            <dl class="application-file-meta">
                                <div><dt>File</dt><dd>{{ $document->original_file_name }}</dd></div>
                                <div><dt>Uploaded</dt><dd>{{ $document->uploaded_at?->format('M j, Y g:i A') }}</dd></div>
                                <div><dt>Version</dt><dd>{{ $document->document_version }}</dd></div>
                            </dl>
                        @endif
                    </div>
                    <x-dashboard.status-badge :label="$item['status']->label()" :tone="$item['status']->tone()" />
                    <div class="application-requirement-actions">
                        @if ($document)
                            <button
                                class="dashboard-outline-action"
                                type="button"
                                data-document-open
                                data-document-name="{{ $document->original_file_name }}"
                                data-document-meta="{{ $item['requirement']->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                                data-document-preview-url="{{ $document->supportsInlinePreview() ? route('applicant.applications.documents.preview', [$application, $document]) : '' }}"
                                data-document-download-url="{{ route('applicant.applications.documents.download', [$application, $document]) }}"
                            ><x-dashboard.icon name="eye" size="17" /><span>View</span></button>
                            <a class="dashboard-outline-action" href="{{ route('applicant.applications.documents.download', [$application, $document]) }}"><x-dashboard.icon name="download" size="17" /><span>Download</span></a>
                        @endif
                        @if ($canUpload)
                            {{-- File input and submit remain native, CSRF-protected, and scoped to this requirement. --}}
                            <form class="application-upload-form" method="POST" action="{{ route('applicant.applications.documents.store', [$application, $item['requirement']]) }}" enctype="multipart/form-data" data-application-submit-once>
                                @csrf
                                <label class="dashboard-outline-action" for="document_{{ $item['requirement']->id }}"><x-dashboard.icon name="upload" size="17" /><span>{{ $document ? 'Choose Replacement' : 'Choose File' }}</span></label>
                                <input id="document_{{ $item['requirement']->id }}" name="document" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required data-application-file>
                                <span data-application-file-name>No file selected</span>
                                <button class="dashboard-primary-action" type="submit">{{ $document ? 'Replace' : 'Upload' }}</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <section class="application-empty-panel">
                    <x-dashboard.empty-state
                        image="no-requirements"
                        alt="No configured requirements"
                        title="Requirements not configured"
                        message="The RES must configure active requirements for this research type before submission."
                    />
                </section>
            @endforelse
        </section>

        {{-- The final checklist explains every server-enforced submission condition. --}}
        <section class="application-panel application-submit-panel">
            <div class="application-panel-heading"><div><h2>Submission Checklist</h2><p>Formal submission sends this application to the assigned Research Adviser.</p></div></div>
            <ul class="application-submit-checklist">
                <li class="is-complete"><x-dashboard.icon name="check" size="17" /><span>Application information is saved.</span></li>
                <li class="{{ $requirementSummary['ready'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$requirementSummary['ready'] ? 'check' : 'clock'" size="17" /><span>Every mandatory requirement is uploaded and complete.</span></li>
                <li class="{{ $application->adviser_user_id ? 'is-complete' : '' }}"><x-dashboard.icon :name="$application->adviser_user_id ? 'check' : 'clock'" size="17" /><span>An eligible Research Adviser is assigned.</span></li>
                <li class="{{ $submissionWindow['open'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$submissionWindow['open'] ? 'check' : 'clock'" size="17" /><span>{{ $submissionWindow['message'] }}</span></li>
            </ul>

            @if ($application->isFormallySubmitted())
                {{-- Submitted applications display an immutable receipt instead of editable upload controls. --}}
                <div class="application-submission-receipt" role="status">
                    <x-dashboard.icon name="check" size="22" />
                    <div><strong>Application submitted</strong><span>{{ $application->submitted_at->format('M j, Y \a\t g:i A') }}</span></div>
                </div>
            @else
                {{-- Frontend disabling mirrors but never replaces the dedicated server-side submission checks. --}}
                <form method="POST" action="{{ route('applicant.applications.submit', $application) }}" data-final-application-submit data-application-submit-once>
                    @csrf
                    <button
                        class="dashboard-primary-action"
                        type="submit"
                        @disabled(! $canSubmit || ! $requirementSummary['ready'] || ! $submissionWindow['open'])
                    >
                        <x-dashboard.icon name="file-text" size="18" />
                        <span>Submit Application</span>
                    </button>
                </form>
            @endif
        </section>

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
