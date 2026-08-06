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
                </div>
            </div>
            <div class="application-record-actions">
                <a class="dashboard-outline-action" href="{{ route($indexRoute) }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to List</span></a>
                @if ($canEdit)
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.edit', $application) }}"><x-dashboard.icon name="edit" size="17" /><span>Edit Information</span></a>
                @endif
                @if ($canUpload)
                    <a class="dashboard-primary-action" href="{{ route('applicant.applications.requirements', $application) }}"><x-dashboard.icon name="upload" size="17" /><span>Manage Documents</span></a>
                @endif
            </div>
        </header>

        @if ($application->latestEndorsement)
            @php
                $latestDecision = $application->latestEndorsement;
                $decisionAt = $latestDecision->endorsed_at ?? $latestDecision->returned_at;
            @endphp
            {{-- Keep the latest Adviser action visible to every role authorized for this application. --}}
            <section class="application-decision-summary">
                <div>
                    <x-dashboard.status-badge
                        :label="$latestDecision->endorsement_status->label()"
                        :tone="$latestDecision->endorsement_status->tone()"
                        dot
                    />
                    <strong>{{ $latestDecision->adviser?->name ?? 'Research Adviser' }}</strong>
                    @if ($decisionAt)
                        <span>{{ $decisionAt->format('M j, Y \a\t g:i A') }}</span>
                    @endif
                </div>
                @if ($latestDecision->return_reason)
                    <p><strong>{{ $latestDecision->return_reason->label() }}</strong></p>
                @endif
                @if ($latestDecision->endorsement_remarks)
                    <p>{{ $latestDecision->endorsement_remarks }}</p>
                @endif
            </section>
        @endif

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
                <div><dt>Expected Duration</dt><dd>{{ $application->expectedDurationLabel() }}</dd></div>
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
                    <thead><tr><th>Requirement</th><th class="application-document-column">Document</th><th class="application-document-version">Version</th><th class="application-document-uploaded">Uploaded</th><th class="dashboard-table-status">Status</th></tr></thead>
                    <tbody>
                        @foreach ($requirementSummary['items'] as $item)
                            @php
                                $document = $item['document'];
                            @endphp
                            <tr>
                                <td><strong>{{ $item['requirement']->name }}</strong></td>
                                <td class="application-document-column application-document-centered">
                                    @if ($document)
                                        <div class="application-current-document">
                                            <button
                                                class="application-document-title"
                                                type="button"
                                                data-document-open
                                                data-document-name="{{ $document->original_file_name }}"
                                                data-document-meta="{{ $item['requirement']->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                                                data-document-preview-kind="{{ $document->mime_type === 'application/pdf' ? 'pdf' : (str_starts_with($document->mime_type, 'image/') ? 'image' : 'download') }}"
                                                data-document-preview-url="{{ route($documentRouteBase.'.preview', [$application, $document]) }}"
                                                data-document-download-url="{{ route($documentRouteBase.'.download', [$application, $document]) }}"
                                                data-document-replace-input="{{ $role === \App\Enums\UserRole::Applicant && $canEdit ? 'detail_replace_document_'.$item['requirement']->id : '' }}"
                                            >
                                                <x-dashboard.icon :name="$item['icon']" size="18" />
                                                <span data-table-tooltip="{{ $document->original_file_name }}">{{ $document->original_file_name }}</span>
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
                                <td class="application-document-version application-document-centered">{{ $document ? 'v'.$item['version'] : '-' }}</td>
                                <td class="application-document-uploaded application-document-centered">{{ $document?->uploaded_at?->format('M j, Y g:i A') ?? '-' }}</td>
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

        @if ($role === \App\Enums\UserRole::Adviser && $canAdviserDecide)
            {{-- Adviser decisions remain disabled whenever the RES-configured endorsement period is closed. --}}
            <section class="application-panel application-adviser-decision-panel">
                <div class="application-panel-heading">
                    <div>
                        <h2>Adviser Decision</h2>
                        <p>{{ $adviserDecisionWindow['message'] }}</p>
                    </div>
                </div>
                <div class="application-adviser-decision-actions">
                    <button
                        class="dashboard-danger-action"
                        type="button"
                        data-adviser-return-open
                        @disabled(! $adviserDecisionWindow['open'])
                    >
                        <x-dashboard.icon name="refresh" size="18" />
                        <span>Return for Correction</span>
                    </button>
                    <button
                        class="dashboard-primary-action"
                        type="button"
                        data-adviser-endorse-open
                        @disabled(! $adviserDecisionWindow['open'])
                    >
                        <x-dashboard.icon name="check" size="18" />
                        <span>Endorse Application</span>
                    </button>
                </div>
            </section>
        @endif

        {{-- Formal submission metadata appears only after the server has accepted the transition. --}}
        @if ($application->isFormallySubmitted())
            <section class="application-submission-receipt" role="status">
                <x-dashboard.icon name="check" size="22" />
                <div><strong>Submitted to {{ $application->adviser?->name ?? 'Research Adviser' }}</strong><span>{{ $application->submitted_at->format('M j, Y \a\t g:i A') }}</span></div>
            </section>
        @endif

        @if ($role === \App\Enums\UserRole::Adviser && $canAdviserDecide)
            <section
                class="application-modal-backdrop"
                data-adviser-return-dialog
                @if ($errors->adviserReturn->any()) data-open-on-load @else hidden @endif
            >
                <div class="application-modal application-decision-modal" role="dialog" aria-modal="true" aria-labelledby="adviser-return-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close return form" data-adviser-return-close>
                        <x-dashboard.icon name="x" size="20" />
                    </button>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="refresh" size="24" /></span>
                        <div>
                            <h2 id="adviser-return-title">Return for Correction</h2>
                            <p>Tell the applicant exactly what must be corrected before resubmission.</p>
                        </div>
                    </header>
                    <form method="POST" action="{{ route('adviser.applications.return', $application) }}" data-application-submit-once>
                        @csrf
                        <div class="application-decision-fields">
                            <label>
                                <span>Reason</span>
                                <select name="return_reason" required>
                                    <option value="">Select a reason</option>
                                    @foreach ($adviserReturnReasons as $reason)
                                        <option value="{{ $reason->value }}" @selected(old('return_reason') === $reason->value)>{{ $reason->label() }}</option>
                                    @endforeach
                                </select>
                                @error('return_reason', 'adviserReturn')<small class="application-field-error">{{ $message }}</small>@enderror
                            </label>
                            <label>
                                <span>Remarks and Instructions</span>
                                <textarea name="endorsement_remarks" rows="5" maxlength="500" required>{{ old('endorsement_remarks') }}</textarea>
                                <small>Maximum 500 characters.</small>
                                @error('endorsement_remarks', 'adviserReturn')<small class="application-field-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                        <div class="application-modal-actions">
                            <button class="dashboard-outline-action" type="button" data-adviser-return-close>Cancel</button>
                            <button class="dashboard-danger-action" type="submit">Return Application</button>
                        </div>
                    </form>
                </div>
            </section>

            <section
                class="application-modal-backdrop"
                data-adviser-endorse-dialog
                @if ($errors->adviserEndorse->any()) data-open-on-load @else hidden @endif
            >
                <div class="application-modal application-decision-modal" role="dialog" aria-modal="true" aria-labelledby="adviser-endorse-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close endorsement form" data-adviser-endorse-close>
                        <x-dashboard.icon name="x" size="20" />
                    </button>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="check" size="24" /></span>
                        <div>
                            <h2 id="adviser-endorse-title">Endorse Application</h2>
                            <p>Confirm that this complete initial submission is ready for RES screening.</p>
                        </div>
                    </header>
                    <form method="POST" action="{{ route('adviser.applications.endorse', $application) }}" data-application-submit-once>
                        @csrf
                        <div class="application-decision-fields">
                            <label>
                                <span>Remarks (optional)</span>
                                <textarea name="endorsement_remarks" rows="4" maxlength="1000">{{ old('endorsement_remarks') }}</textarea>
                                <small>The decision cannot be edited after confirmation.</small>
                                @error('endorsement_remarks', 'adviserEndorse')<small class="application-field-error">{{ $message }}</small>@enderror
                            </label>
                        </div>
                        <div class="application-modal-actions">
                            <button class="dashboard-outline-action" type="button" data-adviser-endorse-close>Cancel</button>
                            <button class="dashboard-primary-action" type="submit">Confirm Endorsement</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
