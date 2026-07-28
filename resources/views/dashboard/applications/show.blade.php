@extends('layouts.dashboard')

@section('content')
    @php
        // Resolve role-specific protected document routes without exposing storage paths.
        $documentRouteBase = match ($role) {
            \App\Enums\UserRole::Adviser => 'adviser.applications.documents',
            \App\Enums\UserRole::ResLead => 'res.applications.documents',
            default => 'applicant.applications.documents',
        };
    @endphp

    <div class="dashboard-page application-workspace">
        {{-- Application identity and current workflow state remain the first detail-page signal. --}}
        <header class="application-record-header">
            <div>
                <span class="identity-eyebrow">{{ $application->application_code }}</span>
                <h1>{{ $application->research_title }}</h1>
                <div class="application-record-statuses">
                    <x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" dot />
                    <span>{{ $application->current_stage?->label() ?? 'Application Information' }}</span>
                </div>
            </div>
            <div class="application-record-actions">
                <a class="dashboard-outline-action" href="{{ route($indexRoute) }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to List</span></a>
                @if ($canEdit)
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.edit', $application) }}"><x-dashboard.icon name="edit" size="17" /><span>Edit Information</span></a>
                    <a class="dashboard-primary-action" href="{{ route('applicant.applications.requirements', $application) }}"><x-dashboard.icon name="upload" size="17" /><span>Manage Documents</span></a>
                @endif
            </div>
        </header>

        {{-- Research information is arranged for rapid applicant and Adviser review. --}}
        <section class="application-panel">
            <div class="application-panel-heading"><div><h2>Application Information</h2><p>Submitted research and institutional details.</p></div></div>
            <dl class="application-detail-grid">
                <div><dt>Applicant Type</dt><dd>{{ \App\Enums\ApplicantType::tryFrom($application->applicant_type)?->label() ?? Str::headline($application->applicant_type) }}</dd></div>
                <div><dt>Research Type</dt><dd>{{ $application->research_type?->label() ?? 'Not specified' }}</dd></div>
                <div><dt>Research Category</dt><dd>{{ $application->research_category ?: 'Not specified' }}</dd></div>
                <div><dt>Assigned Adviser</dt><dd>{{ $application->adviser?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Institution or College</dt><dd>{{ $application->institution ?: 'Not specified' }}</dd></div>
                <div><dt>Department</dt><dd>{{ $application->department ?: 'Not specified' }}</dd></div>
                <div><dt>Program</dt><dd>{{ $application->program ?: 'Not applicable' }}</dd></div>
                <div><dt>Expected Duration</dt><dd>{{ $application->expected_duration ?: 'Not specified' }}</dd></div>
                <div class="application-detail-full"><dt>Target Participants</dt><dd>{{ $application->target_participants ?: 'Not specified' }}</dd></div>
                <div class="application-detail-full"><dt>Brief Description or Abstract</dt><dd class="application-long-copy">{{ $application->abstract ?: 'Not specified' }}</dd></div>
            </dl>
        </section>

        @if ($role !== \App\Enums\UserRole::Applicant)
            {{-- Authorized staff receive only the applicant identity needed for the assigned application. --}}
            <section class="application-panel">
                <div class="application-panel-heading"><div><h2>Applicant Information</h2><p>Identity details associated with this submission.</p></div></div>
                <dl class="application-detail-grid">
                    <div><dt>Name</dt><dd>{{ $application->applicant->name }}</dd></div>
                    <div><dt>{{ $application->applicant->institutionalIdentifierLabel() }}</dt><dd>{{ $application->applicant->institutional_identifier }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $application->applicant->email }}</dd></div>
                    <div><dt>Program</dt><dd>{{ $application->applicant->program ?: 'Not specified' }}</dd></div>
                </dl>
            </section>
        @endif

        {{-- Requirement totals and status rows use the same server summary that gates final submission. --}}
        <section class="application-panel">
            <div class="application-panel-heading application-progress-heading">
                <div><h2>Requirements</h2><p>{{ $requirementSummary['completed_count'] }} of {{ $requirementSummary['mandatory_total'] }} mandatory requirements completed</p></div>
                <strong>{{ $requirementSummary['percentage'] }}%</strong>
            </div>
            <progress class="application-progress" value="{{ $requirementSummary['completed_count'] }}" max="{{ max(1, $requirementSummary['mandatory_total']) }}">{{ $requirementSummary['percentage'] }}%</progress>

            <x-dashboard.overflow label="Application requirement documents" wide>
                <table class="dashboard-table application-document-table">
                    <thead><tr><th>Requirement</th><th>Required</th><th>Document</th><th>Version</th><th>Uploaded</th><th class="dashboard-table-status">Status</th></tr></thead>
                    <tbody>
                        @foreach ($requirementSummary['items'] as $item)
                            @php
                                $document = $item['document'];
                            @endphp
                            <tr>
                                <td><strong>{{ $item['requirement']->name }}</strong><small>{{ $item['requirement']->code }}</small></td>
                                <td>{{ $item['requirement']->is_mandatory ? 'Yes' : 'Optional' }}</td>
                                <td>
                                    @if ($document)
                                        <div class="application-current-document">
                                            <button
                                                class="application-document-title"
                                                type="button"
                                                data-document-open
                                                data-document-name="{{ $document->original_file_name }}"
                                                data-document-meta="{{ $item['requirement']->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                                                data-document-preview-url="{{ $document->supportsInlinePreview() ? route($documentRouteBase.'.preview', [$application, $document]) : '' }}"
                                                data-document-download-url="{{ route($documentRouteBase.'.download', [$application, $document]) }}"
                                                data-document-replace-input="{{ $role === \App\Enums\UserRole::Applicant && $canEdit ? 'detail_replace_document_'.$item['requirement']->id : '' }}"
                                            >
                                                <x-dashboard.icon :name="$item['icon']" size="18" />
                                                <span>{{ $document->original_file_name }}</span>
                                            </button>
                                            @if ($role === \App\Enums\UserRole::Applicant && $canEdit)
                                                <form method="POST" action="{{ route('applicant.applications.documents.destroy', [$application, $document]) }}" data-confirm-document-remove>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="application-document-remove" type="submit" aria-label="Remove {{ $document->original_file_name }}" title="Remove uploaded document">
                                                        <x-dashboard.icon name="x" size="17" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>

                                        @if ($role === \App\Enums\UserRole::Applicant && $canEdit)
                                            <form class="application-document-replace-form" method="POST" action="{{ route('applicant.applications.documents.store', [$application, $item['requirement']]) }}" enctype="multipart/form-data" data-application-submit-once>
                                                @csrf
                                                <input id="detail_replace_document_{{ $item['requirement']->id }}" name="document" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required data-document-replace-file>
                                            </form>
                                        @endif
                                    @else
                                        <span>No file uploaded</span>
                                    @endif
                                </td>
                                <td>{{ $document ? 'v'.$document->document_version : '-' }}</td>
                                <td>{{ $document?->uploaded_at?->format('M j, Y g:i A') ?? '-' }}</td>
                                <td class="dashboard-table-status"><x-dashboard.status-badge :label="$item['status']->label()" :tone="$item['status']->tone()" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-dashboard.overflow>

            @if ($role === \App\Enums\UserRole::Applicant && $canEdit)
                {{-- Applicants return to the upload workspace while the record remains editable. --}}
                <div class="application-panel-actions">
                    <a class="dashboard-primary-action" href="{{ route('applicant.applications.requirements', $application) }}">Continue Document Submission</a>
                    @if ($canDiscard)
                        <form method="POST" action="{{ route('applicant.applications.destroy', $application) }}" data-confirm-draft-discard>
                            @csrf
                            @method('DELETE')
                            <button class="dashboard-danger-action" type="submit">
                                <x-dashboard.icon name="x" size="18" />
                                <span>Discard Draft</span>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </section>

        {{-- Formal submission metadata appears only after the server has accepted the transition. --}}
        @if ($application->isFormallySubmitted())
            <section class="application-submission-receipt" role="status">
                <x-dashboard.icon name="check" size="22" />
                <div><strong>Submitted to {{ $application->adviser?->name ?? 'Research Adviser' }}</strong><span>{{ $application->submitted_at->format('M j, Y \a\t g:i A') }}</span></div>
            </section>
        @endif

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
