@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page reviewer-assignment-detail-page res-review-workspace-page">
        <header class="dashboard-page-heading reviewer-assignment-detail-heading">
            <div><h1>Read-only Review Workspace</h1><p>Inspect submitted Reviewer materials and release one exact decision without changing review content.</p></div>
            <a class="dashboard-outline-action" href="{{ route('res.certificates.index', ['application' => $application->id]) }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to Decision &amp; Certificates</span></a>
        </header>

        <section class="reviewer-confidentiality-banner" role="note">
            <span><x-dashboard.icon name="lock" size="20" /></span>
            <div><strong>RES read-only access</strong><p>Reviewer decisions, comments, worksheets, and supporting documents cannot be edited from this workspace.</p></div>
        </section>

        <section class="application-panel reviewer-workspace-meta-bar" aria-label="Application review summary">
            <dl>
                <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                <div class="reviewer-workspace-meta-title"><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                <div><dt>Review Type</dt><dd>{{ filled($application->review_type) ? Str::headline($application->review_type) : 'Not specified' }}</dd></div>
                <div><dt>Consensus</dt><dd><x-dashboard.status-badge :label="$application->review_consensus_status?->label() ?? 'Not evaluated'" :tone="$application->review_consensus_status?->tone() ?? 'neutral'" /></dd></div>
                <div><dt>Status</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
            </dl>
        </section>

        <section class="application-panel">
            <header class="application-panel-heading"><div><h2>Supporting Documents</h2><p>Current private files delivered only through authorized RES routes.</p></div></header>
            @if ($application->documents->isEmpty())
                <p class="reviewer-empty-copy">No current supporting documents are available.</p>
            @else
                <x-dashboard.overflow label="RES read-only supporting documents" wide>
                    <table class="dashboard-table reviewer-document-table">
                        <thead><tr><th>Requirement</th><th>Document</th><th>Version</th><th>Uploaded</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($application->documents as $document)
                                <tr>
                                    <td>{{ $document->requirement?->name ?? 'Supporting Document' }}</td>
                                    <td><strong>{{ $document->original_file_name }}</strong></td>
                                    <td>v{{ $document->document_version }}</td>
                                    <td>{{ $document->uploaded_at?->format('M j, Y') ?? 'Not recorded' }}</td>
                                    <td class="res-document-actions">
                                        <button class="dashboard-outline-action" type="button" data-document-open data-document-name="{{ $document->original_file_name }}" data-document-type="{{ $document->fileTypeLabel() }}" data-document-meta="{{ $document->requirement?->name ?? 'Supporting Document' }}" data-document-preview-kind="{{ $document->previewKind() }}" data-document-preview-url="{{ route('res.applications.documents.preview', [$application, $document]) }}" data-document-download-url="{{ route('res.applications.documents.download', [$application, $document]) }}"><x-dashboard.icon name="eye" size="16" /><span>Open</span></button>
                                        <a class="dashboard-icon-action" href="{{ route('res.applications.documents.download', [$application, $document]) }}" aria-label="Download {{ $document->original_file_name }}"><x-dashboard.icon name="download" size="16" /></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            @endif
        </section>

        @if ($application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Conflicted)
            <div class="res-form-error-summary" role="alert"><x-dashboard.icon name="alert-triangle" size="19" /><div><strong>Conflicted Full Board decisions.</strong><span>Release is blocked until every current Reviewer has re-submitted the same decision.</span></div></div>
        @endif

        <div class="res-readonly-review-grid {{ $application->reviewerAssignments->count() === 1 ? 'is-single-reviewer' : '' }}">
            @forelse ($application->reviewerAssignments as $assignment)
                @php
                    $submittedVersion = $assignment->reviewSubmission?->currentVersion;
                    $snapshotComments = collect(data_get($submittedVersion?->payload_snapshot, 'comments', []));
                @endphp
                <section class="application-panel res-readonly-review-card">
                    <header class="application-panel-heading">
                        <div><h2>Reviewer {{ $loop->iteration }}</h2><p>Submitted {{ $submittedVersion?->submitted_at?->format('M j, Y g:i A') ?? 'Not submitted' }}@if ($submittedVersion) · Version {{ $submittedVersion->version_number }}@endif</p></div>
                        <x-dashboard.status-badge :label="$submittedVersion?->decision?->label() ?? 'Pending'" :tone="$submittedVersion?->decision?->tone() ?? 'neutral'" />
                    </header>

                    <div class="res-readonly-review-section"><h3>Decision Comment</h3><p>{{ $submittedVersion?->decision_comment ?: 'No decision comment recorded.' }}</p></div>
                    <div class="res-readonly-review-section"><h3>Review Comments</h3>
                        @forelse ($snapshotComments as $comment)
                            @php
                                $category = \App\Enums\ReviewCommentCategory::tryFrom((string) data_get($comment, 'category'));
                            @endphp
                            <article class="res-readonly-comment"><header><x-dashboard.status-badge :label="$category?->label() ?? 'Review Comment'" :tone="$category?->tone() ?? 'neutral'" /><span>{{ data_get($comment, 'application_document_id') ? 'Submitted document #'.data_get($comment, 'application_document_id') : 'Entire Application' }}</span></header><p>{{ data_get($comment, 'body') }}</p></article>
                        @empty
                            <p>No comments were submitted by this Reviewer.</p>
                        @endforelse
                    </div>
                    <div class="res-readonly-review-section"><h3>Submitted Worksheets</h3>
                        @forelse ($submittedVersion?->artifacts ?? collect() as $artifact)
                            @php
                                $form = $artifact->formSubmission;
                            @endphp
                            @continue(! $form)
                            <div class="res-readonly-worksheet"><span><strong>{{ $form->form_type->code() }}</strong> {{ $form->form_type->label() }}</span>
                                <span><a href="{{ route('res.applications.review-form-artifacts.preview', [$application, $assignment, $form, $artifact]) }}" target="_blank" rel="noopener">Preview</a><a href="{{ route('res.applications.review-form-artifacts.download', [$application, $assignment, $form, $artifact]) }}">Download</a></span>
                            </div>
                        @empty
                            <p>No worksheets are available.</p>
                        @endforelse
                    </div>

                    @if ($application->application_status === \App\Enums\ApplicationStatus::ReviewSubmittedPendingRelease && $application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Consensus && $submittedVersion)
                        <form method="POST" action="{{ route('res.certificates.decisions.release', $application) }}" data-disable-on-submit>
                            @csrf
                            <input type="hidden" name="review_submission_id" value="{{ $assignment->reviewSubmission->id }}">
                            <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="send" size="17" /><span>Release This Decision</span></button>
                        </form>
                    @endif
                </section>
            @empty
                <section class="application-panel"><p class="reviewer-empty-copy">No current submitted Reviewer records are available.</p></section>
            @endforelse
        </div>

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
