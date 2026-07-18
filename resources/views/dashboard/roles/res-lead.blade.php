@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Welcome back, RES Lead/Admin!</h1>
        </header>

        <div class="dashboard-summary-grid dashboard-summary-grid-five">
            <x-dashboard.summary-card label="For RES Screening" :count="$counts['for_screening']" icon="file-text" tone="orange" :href="route('res.applications.index')" />
            <x-dashboard.summary-card label="Under RES Screening" :count="$counts['screening']" icon="users" tone="blue" :href="route('res.applications.index')" />
            <x-dashboard.summary-card label="Awaiting Assignment" :count="$counts['awaiting_assignment']" icon="user" tone="green" :href="route('res.review-monitoring.index')" />
            <x-dashboard.summary-card label="Under Review" :count="$counts['under_review']" icon="file-search" tone="violet" :href="route('res.review-monitoring.index')" />
            <x-dashboard.summary-card label="For Result Release" :count="$counts['for_release']" icon="clipboard" tone="cyan" :href="route('res.certificates.index')" />
        </div>

        <x-dashboard.section title="Pending Administrative Actions" view-all-route="res.applications.index">
            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-assignments"
                    alt="No pending administrative applications"
                    title="No pending administrative actions"
                    message="There are currently no endorsed applications to screen, classify, or monitor."
                />
            @else
                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead><tr><th>Application Code</th><th>Applicant Category</th><th>Research Type</th><th class="dashboard-table-status">Current Status</th><th>Received Date</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><a href="{{ route('res.applications.show', $application) }}">{{ $application->application_code }}</a></td>
                                    <td>{{ Str::headline($application->applicant_type) }}</td>
                                    <td>{{ $application->review_type ? Str::headline($application->review_type) : 'Pending classification' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td>{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('res.applications.show', $application)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dashboard.section>

        <div class="dashboard-lower-grid">
            <x-dashboard.section title="Deadline Alerts">
                @if ($deadlines->isNotEmpty())
                    <div class="dashboard-deadline-list">
                        <span class="dashboard-deadline-icon"><x-dashboard.icon name="calendar" size="43" /></span>
                        <div>
                            <strong>{{ $deadlines->count() }} active cycle {{ Str::plural('deadline', $deadlines->count()) }}</strong>
                            <ul>
                                @foreach ($deadlines as $item)
                                    <li><span>{{ $item['title'] }}</span><time>{{ $item['due_at']->format('M j, Y') }}</time></li>
                                @endforeach
                            </ul>
                            <a href="{{ route('res.settings.index') }}">View All Alerts <x-dashboard.icon name="arrow-right" size="17" /></a>
                        </div>
                    </div>
                @else
                    <x-dashboard.empty-state
                        image="no-deadlines"
                        alt="Calendar with no active deadlines"
                        title="No active cycle deadlines"
                        message="There are currently no deadline alerts requiring attention."
                        compact
                    />
                @endif
            </x-dashboard.section>
            <x-dashboard.section title="Application Timeline" :header-meta="$termLabel" header-meta-icon="calendar">
                <x-dashboard.timeline :timeline="$timeline" />
            </x-dashboard.section>
        </div>
    </div>
@endsection
