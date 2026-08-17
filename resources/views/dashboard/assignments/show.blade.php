@extends('layouts.dashboard')

@section('content')
    @php
        $application = $assignment->researchApplication;
    @endphp

    <div class="dashboard-page reviewer-assignment-detail-page">
        <header class="dashboard-page-heading reviewer-assignment-detail-heading">
            <div>
                <h1>Assigned Application</h1>
                <p>Review the research record and its current supporting documents.</p>
            </div>
            <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.index') }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to Assignments</span></a>
        </header>

        <section class="reviewer-assignment-ready" role="status">
            <x-dashboard.icon name="check" size="20" />
            <div><strong>Assignment ready</strong><span>{{ $reviewWindow['message'] }}</span></div>
            <a class="dashboard-primary-action" href="{{ route('reviewer.assignments.workspace', $assignment) }}">
                <x-dashboard.icon name="file-search" size="17" /><span>{{ $assignment->reviewSubmission?->submitted_at ? 'View Submitted Review' : 'Open Review Workspace' }}</span>
            </a>
        </section>

        {{-- Identity fields are intentionally omitted from the Reviewer-facing summary. --}}
        <div class="reviewer-assignment-detail-grid">
            <section class="application-panel">
                <header class="application-panel-heading"><div><h2>Application Overview</h2></div></header>
                <dl class="reviewer-assignment-details">
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Research Type</dt><dd>{{ $application->research_type?->label() ?? 'Not specified' }}</dd></div>
                    <div><dt>Review Type</dt><dd>{{ Str::headline($assignment->review_type) }}</dd></div>
                    <div><dt>Classification</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                    <div><dt>Status</dt><dd><x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" /></dd></div>
                    <div><dt>Date Assigned</dt><dd>{{ $assignment->assigned_at?->format('M j, Y g:i A') ?? 'Not configured' }}</dd></div>
                    <div><dt>Deadline</dt><dd>{{ $assignment->review_deadline_at?->format('M j, Y g:i A') ?? 'Not configured' }}</dd></div>
                </dl>
            </section>

            <section class="application-panel">
                <header class="application-panel-heading"><div><h2>Research Information</h2></div></header>
                <dl class="reviewer-assignment-details">
                    <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                    <div><dt>Research Category</dt><dd>{{ $application->research_category ?: 'Not specified' }}</dd></div>
                    <div><dt>Participant Group</dt><dd>{{ $application->target_participants ?: 'Not specified' }}</dd></div>
                    <div><dt>Expected Duration</dt><dd>{{ $application->expectedDurationLabel() }}</dd></div>
                    <div class="reviewer-assignment-detail-wide reviewer-study-overview"><dt>Study Overview</dt><dd>{{ $application->abstract ?: 'Not provided' }}</dd></div>
                </dl>
            </section>
        </div>

        @if ($canOpenWorkspace)
        <section class="application-panel">
            <header class="application-panel-heading">
                <div><h2>Supporting Documents</h2><p>Current private versions attached to this assigned application.</p></div>
            </header>
            @if ($application->documents->isEmpty())
                <x-dashboard.empty-state
                    image="no-requirements"
                    alt="No supporting documents"
                    title="No supporting documents available"
                    message="No current requirement document is attached to this assignment."
                />
            @else
                {{-- Every action uses a nested, assignment-authorized route; private storage paths never enter markup. --}}
                <x-dashboard.overflow label="Assigned application documents" wide>
                    <table class="dashboard-table reviewer-document-table">
                        <thead><tr><th class="reviewer-document-primary-column">Requirement</th><th class="reviewer-document-primary-column">Document</th><th>Version</th><th>Uploaded</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($application->documents as $document)
                                <tr>
                                    <td class="reviewer-document-primary-column">{{ $document->requirement?->name ?? 'Supporting Document' }}</td>
                                    <td class="reviewer-document-primary-column"><strong data-table-tooltip="{{ $document->original_file_name }}">{{ $document->original_file_name }}</strong></td>
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
                                            data-document-type="{{ $document->fileTypeLabel() }}"
                                            data-document-meta="{{ $document->requirement?->name ?? 'Supporting Document' }}"
                                            data-document-preview-kind="{{ $document->previewKind() }}"
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
        @endif

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
