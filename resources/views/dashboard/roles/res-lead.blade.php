@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Welcome back, REU Lead/Admin!</h1>
        </header>


        {{-- REU Lead summary cards use the shared vertical card component without changing administrative counts. --}}
        <div class="dashboard-summary-grid dashboard-summary-grid-five" aria-label="Administrative application summary">
            <x-dashboard.summary-card label="For REU Screening" :count="$counts['for_screening']" icon="file-text" tone="orange" :href="route('res.applications.index')" />
            <x-dashboard.summary-card label="Under REU Screening" :count="$counts['screening']" icon="users" tone="blue" :href="route('res.applications.index')" />
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
                {{-- The shared overflow class contains REU screening columns without widening the page. --}}
                <div class="dashboard-table-wrap dashboard-overflow-region" role="region" aria-label="Recent REU applications" tabindex="0">
                    <table class="dashboard-table">
                        <thead><tr><th>Application Code</th><th>Adviser</th><th class="dashboard-table-status">Current Status</th><th>Received Date</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><a href="{{ route('res.applications.show', $application) }}">{{ $application->application_code }}</a></td>
                                    <td>{{ $application->adviser?->name ?? 'Not assigned' }}</td>
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
                    <div class="dashboard-deadline-alert-stack">@foreach ($deadlines as $item)<x-dashboard.deadline-alert :deadline="$item" />@endforeach</div>
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
