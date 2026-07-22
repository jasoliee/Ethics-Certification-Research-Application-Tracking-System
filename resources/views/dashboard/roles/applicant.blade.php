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
            <div class="dashboard-applicant-grid">
                <section class="dashboard-focus-card dashboard-detail-card">
                    <h2><x-dashboard.icon name="clipboard" /> Application Status</h2>
                    @if ($hasSubmittedApplication)
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
                    @else
                        <x-dashboard.empty-state
                            image="no-applications"
                            alt="Application not yet submitted"
                            title="No submitted application"
                            message="Complete every mandatory requirement before submitting your draft."
                            action-label="Review Draft"
                            :action-href="route('applicant.applications.show', $activeApplication)"
                        />
                    @endif
                </section>

                <section class="dashboard-focus-card dashboard-detail-card">
                    <h2><x-dashboard.icon name="file-text" /> My Application</h2>
                    @if ($hasSubmittedApplication)
                    <dl class="dashboard-detail-list dashboard-detail-list-wide">
                        <div><dt>Current Step</dt><dd><x-dashboard.status-badge :label="$activeApplication->application_status->label()" :tone="$activeApplication->application_status->tone()" /></dd></div>
                        <div><dt>Last Updated</dt><dd>{{ ($activeApplication->status_updated_at ?? $activeApplication->updated_at)->format('M j, Y \a\t g:i A') }}</dd></div>
                        <div><dt>Application Type</dt><dd>{{ Str::headline($activeApplication->application_type) }}</dd></div>
                        <div><dt>Adviser</dt><dd>{{ $activeApplication->adviser?->name ?? 'Not assigned' }}</dd></div>
                    </dl>
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.show', $activeApplication) }}">View Application Details</a>
                    @else
                        <x-dashboard.empty-state
                            image="no-applications"
                            alt="No submitted application record"
                            title="Application not submitted"
                            message="Submitted application details will appear here after the server accepts your complete application."
                            action-label="Continue Application"
                            :action-href="route('applicant.applications.show', $activeApplication)"
                        />
                    @endif
                </section>

                <section class="dashboard-focus-card dashboard-requirements-card">
                    <h2><x-dashboard.icon name="clipboard" /> Requirements Completion</h2>
                    @if ($requirements->isNotEmpty())
                        <div class="dashboard-requirement-progress">
                            <span>{{ $completedRequirementCount }} of {{ $requirements->count() }} completed</span>
                            <progress value="{{ $completedRequirementCount }}" max="{{ $requirements->count() }}">{{ $completedRequirementCount }} of {{ $requirements->count() }}</progress>
                        </div>
                        <ul class="dashboard-requirement-list">
                            @foreach ($requirements->take(4) as $requirement)
                                <li>
                                    <span class="dashboard-requirement-file"><x-dashboard.icon :name="$requirement['icon']" size="20" /></span>
                                    <span><strong>{{ $requirement['code'] }}</strong><small>{{ $requirement['name'] }}</small></span>
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
                    <a class="dashboard-outline-action" href="{{ route('applicant.applications.requirements', $activeApplication) }}">View Requirements</a>
                </section>
            </div>
        @endif

        <div class="dashboard-lower-grid">
            <x-dashboard.section title="Deadline Alerts">
                <x-dashboard.deadline-alert
                    :deadline="$deadline"
                    empty-title="No upcoming deadlines"
                    empty-message="Important application deadlines and reminders will appear here."
                />
            </x-dashboard.section>

            <x-dashboard.section title="Application Timeline" :header-meta="$termLabel" header-meta-icon="calendar">
                <x-dashboard.timeline :timeline="$timeline" />
            </x-dashboard.section>
        </div>
    </div>
@endsection
