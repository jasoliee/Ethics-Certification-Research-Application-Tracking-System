@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-applicant-page">

        @if (! $activeApplication)
            <div class="dashboard-applicant-grid">
                <section class="dashboard-focus-card">
                    <h2><x-dashboard.icon name="clipboard" /> Application Status</h2>
                    <x-dashboard.empty-state
                        image="no-applications"
                        alt="Empty application file"
                        title="No application yet"
                        message="Start an application to track its review status here."
                        action-label="Start Application"
                        :action-href="route('applicant.applications.create')"
                    />
                </section>

                <section class="dashboard-focus-card">
                    <h2><x-dashboard.icon name="file-text" /> My Application</h2>
                    <x-dashboard.empty-state
                        image="no-applications"
                        alt="Empty research application"
                        title="No active application"
                        message="Your application details will appear here once you begin."
                        action-label="Create Application"
                        :action-href="route('applicant.applications.create')"
                    />
                </section>

                <section class="dashboard-focus-card">
                    <h2><x-dashboard.icon name="clipboard" /> Requirements Completion</h2>
                    <x-dashboard.empty-state
                        image="no-requirements"
                        alt="Empty requirements checklist"
                        title="No requirements yet"
                        message="Required documents will appear after you create an application."
                    />
                    <button class="dashboard-outline-action" type="button" disabled>View Requirements</button>
                </section>
            </div>
        @else
            {{-- Active application cards always use the authenticated applicant's newest non-archived record. --}}
            <div class="dashboard-applicant-grid">
                <section class="dashboard-focus-card dashboard-detail-card">
                    <h2><x-dashboard.icon name="clipboard" /> Application Status</h2>
                    <div class="dashboard-status-feature">
                        <span>Current Status</span>
                        <x-dashboard.status-badge
                            :label="$activeApplication->application_status->label()"
                            :tone="$activeApplication->application_status->tone()"
                            dot
                        />
                    </div>
                    <dl class="dashboard-detail-list">
                        <div><dt>Application ID</dt><dd>{{ $activeApplication->application_code }}</dd></div>
                        <div><dt>Research Title</dt><dd><x-dashboard.research-title :title="$activeApplication->research_title" /></dd></div>
                        <div><dt>Date Submitted</dt><dd>{{ $activeApplication->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</dd></div>
                    </dl>
                </section>

                <section class="dashboard-focus-card dashboard-detail-card">
                    <h2><x-dashboard.icon name="file-text" /> My Application</h2>
                    <dl class="dashboard-detail-list dashboard-detail-list-wide">
                        <div><dt>Current Stage</dt><dd>{{ $activeApplication->current_stage?->label() ?? 'Application Information' }}</dd></div>
                        <div><dt>Last Updated</dt><dd>{{ ($activeApplication->status_updated_at ?? $activeApplication->updated_at)->format('M j, Y \a\t g:i A') }}</dd></div>
                        <div><dt>Applicant Type</dt><dd>{{ \App\Enums\ApplicantType::tryFrom($activeApplication->applicant_type)?->label() ?? Str::headline($activeApplication->applicant_type) }}</dd></div>
                        <div><dt>Adviser</dt><dd>{{ $activeApplication->adviser?->name ?? 'Not assigned' }}</dd></div>
                    </dl>
                    <button class="dashboard-outline-action" type="button" data-application-details-open>View Application Details</button>
                </section>

                <section class="dashboard-focus-card dashboard-requirements-card">
                    <h2><x-dashboard.icon name="clipboard" /> Requirements Completion</h2>
                    @if ($requirements->isNotEmpty())
                        <div class="dashboard-requirement-progress">
                            <span>{{ $requirementSummary['completed_count'] }} of {{ $requirementSummary['mandatory_total'] }} mandatory completed</span>
                            <progress value="{{ $requirementSummary['completed_count'] }}" max="{{ max(1, $requirementSummary['mandatory_total']) }}">{{ $requirementSummary['percentage'] }}%</progress>
                        </div>
                        <ul class="dashboard-requirement-list">
                            @foreach ($requirements->take(4) as $requirement)
                                <li>
                                    <span class="dashboard-requirement-file"><x-dashboard.icon :name="$requirement['icon']" size="20" /></span>
                                    <span><strong>{{ $requirement['requirement']->code }}</strong><small>{{ $requirement['requirement']->name }}</small></span>
                                    <x-dashboard.status-badge :label="$requirement['status']->label()" :tone="$requirement['status']->tone()" />
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <x-dashboard.empty-state
                            image="no-requirements"
                            alt="Empty requirements checklist"
                            title="Requirements not configured"
                            message="Required documents will appear when the active requirement set is configured."
                            compact
                        />
                    @endif
                    <button class="dashboard-outline-action" type="button" data-requirements-details-open>View Requirements</button>
                </section>
            </div>
        @endif

        <div class="dashboard-lower-grid">
            <x-dashboard.section title="Deadline Alerts">
                <x-dashboard.deadline-alert
                    :deadline="$deadline"
                    empty-title="No upcoming deadlines"
                    empty-message=""
                />
            </x-dashboard.section>

            <x-dashboard.section title="Application Timeline" :header-meta="$termLabel" header-meta-icon="calendar">
                <x-dashboard.timeline :timeline="$timeline" />
            </x-dashboard.section>
        </div>

        @if ($activeApplication)
            {{-- Application Details modal mirrors the dashboard cards without issuing additional database queries. --}}
            <section class="application-modal-backdrop" data-application-details-dialog hidden>
                <div class="application-modal dashboard-application-modal" role="dialog" aria-modal="true" aria-labelledby="dashboard-application-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close application details" data-application-details-close><x-dashboard.icon name="x" size="20" /></button>
                    <header class="application-modal-heading"><span class="application-modal-icon"><x-dashboard.icon name="file-text" size="24" /></span><div><h2 id="dashboard-application-title">My Application Details</h2><p>{{ $activeApplication->application_code }}</p></div></header>
                    <dl class="application-detail-grid">
                        <div class="application-detail-full"><dt>Research Title</dt><dd>{{ $activeApplication->research_title }}</dd></div>
                        <div><dt>Status</dt><dd>{{ $activeApplication->application_status->label() }}</dd></div>
                        <div><dt>Current Stage</dt><dd>{{ $activeApplication->current_stage?->label() ?? 'Application Information' }}</dd></div>
                        <div><dt>Research Type</dt><dd>{{ $activeApplication->research_type?->label() ?? 'Not specified' }}</dd></div>
                        <div><dt>Assigned Adviser</dt><dd>{{ $activeApplication->adviser?->name ?? 'Not assigned' }}</dd></div>
                        <div><dt>Submitted</dt><dd>{{ $activeApplication->submitted_at?->format('M j, Y g:i A') ?? 'Not submitted' }}</dd></div>
                        <div><dt>Last Updated</dt><dd>{{ ($activeApplication->status_updated_at ?? $activeApplication->updated_at)->format('M j, Y g:i A') }}</dd></div>
                    </dl>
                    <div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-application-details-close>Close</button><a class="dashboard-primary-action" href="{{ route('applicant.applications.show', $activeApplication) }}">Open Full Details</a></div>
                </div>
            </section>

            {{-- Requirements Completion modal reuses the exact server-side checklist shown on the dashboard. --}}
            <section class="application-modal-backdrop" data-requirements-details-dialog hidden>
                <div class="application-modal dashboard-requirements-modal" role="dialog" aria-modal="true" aria-labelledby="dashboard-requirements-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close requirements completion" data-requirements-details-close><x-dashboard.icon name="x" size="20" /></button>
                    <header class="application-modal-heading"><span class="application-modal-icon"><x-dashboard.icon name="clipboard" size="24" /></span><div><h2 id="dashboard-requirements-title">Requirements Completion</h2><p>{{ $requirementSummary['percentage'] }}% complete</p></div></header>
                    <progress class="application-progress" value="{{ $requirementSummary['completed_count'] }}" max="{{ max(1, $requirementSummary['mandatory_total']) }}">{{ $requirementSummary['percentage'] }}%</progress>
                    <ul class="application-modal-requirements">
                        @forelse ($requirements as $requirement)
                            <li><span><strong>{{ $requirement['requirement']->name }}</strong><small>{{ $requirement['requirement']->is_mandatory ? 'Mandatory' : 'Optional' }}</small></span><x-dashboard.status-badge :label="$requirement['status']->label()" :tone="$requirement['status']->tone()" /></li>
                        @empty
                            <li><span><strong>No requirements configured</strong><small>Contact the RES Lead.</small></span></li>
                        @endforelse
                    </ul>
                    <div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-requirements-details-close>Close</button><a class="dashboard-primary-action" href="{{ route('applicant.applications.requirements', $activeApplication) }}">Open Document Submission</a></div>
                </div>
            </section>
        @endif
    </div>
@endsection
