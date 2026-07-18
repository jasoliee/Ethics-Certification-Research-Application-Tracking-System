@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Welcome back, Adviser!</h1>
            <p>Manage research ethics applications.</p>
        </header>

        <div class="dashboard-summary-grid dashboard-summary-grid-four">
            <x-dashboard.summary-card label="Pending" :count="$counts['pending']" icon="file-text" tone="orange" :href="route('adviser.applications.index')" />
            <x-dashboard.summary-card label="In Review" :count="$counts['in_review']" icon="users" tone="blue" :href="route('adviser.applications.index')" />
            <x-dashboard.summary-card label="Endorsed" :count="$counts['endorsed']" icon="check" tone="green" :href="route('adviser.applications.index')" />
            <x-dashboard.summary-card label="Returned" :count="$counts['returned']" icon="refresh" tone="red" :href="route('adviser.applications.index')" />
        </div>

        <x-dashboard.section title="Submitted Application" view-all-route="adviser.applications.index">
            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="No submitted application documents"
                    title="No submitted applications yet"
                    message="Research ethics applications will appear here once your applicants submit them for endorsement."
                    action-label="Refresh"
                    :action-href="request()->url()"
                    action-icon="refresh"
                />
            @else
                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead><tr><th>Application ID</th><th>Applicant</th><th>Research Title</th><th>Date Submitted</th><th class="dashboard-table-status">Status</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><a href="{{ route('adviser.applications.show', $application) }}">{{ $application->application_code }}</a></td>
                                    <td>{{ $application->applicant->name }}</td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('adviser.applications.show', $application)" /></td>
                                    <td>{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('adviser.applications.show', $application)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dashboard.section>

        <div class="dashboard-lower-grid">
            <x-dashboard.section title="Deadline Alerts">
                <x-dashboard.deadline-alert :deadline="$deadline" />
            </x-dashboard.section>
            <x-dashboard.section title="Application Timeline" :header-meta="$termLabel" header-meta-icon="calendar">
                <x-dashboard.timeline :timeline="$timeline" />
            </x-dashboard.section>
        </div>
    </div>
@endsection
