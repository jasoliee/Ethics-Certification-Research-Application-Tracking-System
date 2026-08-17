@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page application-workspace">
        {{-- Application identity and requirement progress share one bounded submission overview. --}}
        <section class="application-panel application-submission-overview">
            <header class="application-record-header application-record-header-integrated">
                <div>
                    <span class="identity-eyebrow">{{ $application->application_code }}</span>
                    <h1>{{ $application->research_title }}</h1>
                    <p>{{ $application->research_type?->label() ?? 'Research Application' }}</p>
                </div>
                <div class="application-record-actions">
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.show', $application) }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Application Details</span></a>
                    @if ($canUpload)
                        <a class="dashboard-outline-action" href="{{ route('applicant.applications.edit', $application) }}"><x-dashboard.icon name="edit" size="17" /><span>Edit Information</span></a>
                    @endif
                </div>
            </header>

            {{-- Completion uses actual active mandatory requirements and current private document versions. --}}
            <div class="application-requirements-progress">
                <div class="application-progress-heading">
                    <div><h2>Requirements Completion</h2><p data-requirement-progress-copy>{{ $requirementSummary['completed_count'] }} of {{ $requirementSummary['mandatory_total'] }} mandatory requirements completed</p></div>
                    <strong data-requirement-progress-percent>{{ $requirementSummary['percentage'] }}%</strong>
                </div>
                <progress class="application-progress" value="{{ $requirementSummary['completed_count'] }}" max="{{ max(1, $requirementSummary['mandatory_total']) }}" data-requirement-progress>{{ $requirementSummary['percentage'] }}%</progress>
                <div class="application-progress-breakdown">
                    <span><strong data-requirement-count="completed_count">{{ $requirementSummary['completed_count'] }}</strong> Completed</span>
                    <span><strong data-requirement-count="missing_count">{{ $requirementSummary['missing_count'] }}</strong> Missing</span>
                    <span><strong data-requirement-count="pending_count">{{ $requirementSummary['pending_count'] }}</strong> Pending</span>
                    <span><strong data-requirement-count="rejected_count">{{ $requirementSummary['rejected_count'] }}</strong> Rejected</span>
                </div>
            </div>
        </section>

        @if ($errors->any())
            {{-- Submission and upload errors remain visible above the affected checklist. --}}
            <div class="identity-validation-summary" role="alert">
                <strong>Document submission needs attention.</strong>
                @foreach ($errors->all() as $message)<span>{{ $message }}</span>@endforeach
            </div>
        @endif

        {{-- Upload All submits every selected requirement without reloading or clearing untouched file inputs. --}}
        <section class="application-requirement-container" aria-label="Document requirements">
            @if ($canUpload && $requirementSummary['items']->isNotEmpty())
                <div class="application-requirement-toolbar">
                    <div><h2>Document Requirements</h2><p>PDF, JPG, JPEG, PNG, GIF, or WebP; up to 100 MB per file.</p></div>
                    <div class="application-upload-all-controls">
                        <button
                            class="dashboard-primary-action"
                            type="button"
                            data-upload-all
                            data-upload-all-url="{{ route('applicant.applications.documents.store-all', $application) }}"
                            disabled
                        >
                            <x-dashboard.icon name="upload" size="18" />
                            <span data-upload-all-label>Upload All</span>
                        </button>
                        <div class="application-upload-all-summary" role="status" aria-live="polite" data-upload-all-summary></div>
                    </div>
                </div>
            @endif

            <div class="application-requirement-list">
                @forelse ($requirementSummary['items'] as $item)
                    @include('dashboard.applications.partials.requirement-upload-row', [
                        'application' => $application,
                        'item' => $item,
                        'canUpload' => $canUpload,
                    ])
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
            </div>
        </section>

        {{-- The final checklist explains every server-enforced submission condition. --}}
        <section class="application-panel application-submit-panel">
            @php
                $submissionReady = $informationSummary['complete']
                    && $requirementSummary['ready']
                    && $informationSummary['adviser_ready']
                    && $submissionWindow['open']
                    && $submissionLimit['can_submit'];
                $finalSubmissionFormId = 'final-application-submit-'.$application->id;
            @endphp
            <div class="application-panel-heading application-submit-heading">
                <div><h2>Submission Checklist</h2><p>Formal submission sends this application to the assigned Research Adviser.</p></div>
                @unless ($application->isFormallySubmitted())
                    {{-- Frontend disabling mirrors but never replaces the dedicated server-side submission checks. --}}
                    <form id="{{ $finalSubmissionFormId }}" method="POST" action="{{ route('applicant.applications.submit', $application) }}" data-final-application-submit data-application-submit-once>
                        @csrf
                        <button
                            class="dashboard-primary-action"
                            type="button"
                            data-final-submit-open
                            data-final-application-button
                            data-other-checks-pass="{{ $canSubmit && $informationSummary['complete'] && $informationSummary['adviser_ready'] && $submissionWindow['open'] && $submissionLimit['can_submit'] ? 'true' : 'false' }}"
                            @disabled(! $canSubmit || ! $submissionReady)
                        >
                            <x-dashboard.icon name="file-text" size="18" />
                            <span>Submit Application</span>
                        </button>
                    </form>
                @endunless
            </div>
            <ul class="application-submit-checklist">
                <li class="{{ $submissionWindow['open'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$submissionWindow['open'] ? 'check' : 'clock'" size="17" /><span>Application submission is open by the RES Lead.</span></li>
                <li class="{{ $submissionLimit['can_submit'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$submissionLimit['can_submit'] ? 'check' : 'clock'" size="17" /><span>{{ $submissionLimit['can_submit'] ? 'A formal application slot is available.' : \App\Services\Applications\ApplicationSubmissionLimit::REACHED_MESSAGE }}</span></li>
                <li class="{{ $informationSummary['complete'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$informationSummary['complete'] ? 'check' : 'clock'" size="17" /><span>All required application information is complete.</span></li>
                <li class="{{ $informationSummary['adviser_ready'] ? 'is-complete' : '' }}"><x-dashboard.icon :name="$informationSummary['adviser_ready'] ? 'check' : 'clock'" size="17" /><span>An eligible Research Adviser is assigned.</span></li>
                <li class="{{ $requirementSummary['ready'] ? 'is-complete' : '' }}" data-requirement-readiness>
                    <span data-requirement-ready-icon @unless ($requirementSummary['ready']) hidden @endunless><x-dashboard.icon name="check" size="17" /></span>
                    <span data-requirement-pending-icon @if ($requirementSummary['ready']) hidden @endif><x-dashboard.icon name="clock" size="17" /></span>
                    <span>Every mandatory requirement is uploaded and complete.</span>
                </li>
            </ul>

            @if ($application->isFormallySubmitted())
                {{-- Submitted applications display an immutable receipt instead of editable upload controls. --}}
                <div class="application-submission-receipt" role="status">
                    <x-dashboard.icon name="check" size="22" />
                    <div><strong>Application submitted</strong><span>{{ $application->submitted_at->format('M j, Y \a\t g:i A') }}</span></div>
                </div>
            @endif
        </section>

        @if (! $application->isFormallySubmitted())
            {{-- A deliberate confirmation is required before the formal workflow transition is posted. --}}
            <section class="application-modal-backdrop" data-final-submit-dialog hidden>
                <div class="application-modal application-confirm-submit-modal" role="dialog" aria-modal="true" aria-labelledby="final-submit-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Cancel application submission" data-final-submit-close>
                        <x-dashboard.icon name="x" size="20" />
                    </button>
                    <header class="application-modal-heading">
                        <span class="application-modal-icon"><x-dashboard.icon name="file-text" size="24" /></span>
                        <div>
                            <h2 id="final-submit-title">Submit Application?</h2>
                            <p>Your complete application and documents will be sent to the assigned Research Adviser.</p>
                        </div>
                    </header>
                    <div class="application-confirm-submit-copy">
                        <strong>{{ $application->research_title }}</strong>
                        <span>Confirm that the information and uploaded requirements are ready for formal review.</span>
                    </div>
                    <div class="application-modal-actions">
                        <button class="dashboard-outline-action" type="button" data-final-submit-close>Cancel</button>
                        <button class="dashboard-primary-action" type="submit" form="{{ $finalSubmissionFormId }}">Confirm Submission</button>
                    </div>
                </div>
            </section>
        @endif

        @include('dashboard.applications.partials.document-dialog')
    </div>
@endsection
