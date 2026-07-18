@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Welcome back, Reviewer!</h1>
            <p>Manage assigned ethics reviews, monitor deadlines, and continue pending review tasks.</p>
        </header>

        <div class="dashboard-summary-grid dashboard-summary-grid-four">
            <x-dashboard.summary-card label="Pending Reviews" :count="$counts['pending']" icon="file-search" tone="orange" :href="route('reviewer.assignments.index')" />
            <x-dashboard.summary-card label="Near Deadline" :count="$counts['near_deadline']" icon="calendar" tone="red" :href="route('reviewer.assignments.index')" />
            <x-dashboard.summary-card label="Revision Reviews" :count="$counts['revision']" icon="refresh" tone="blue" :href="route('reviewer.reviews.index')" />
            <x-dashboard.summary-card label="Completed Reviews" :count="$counts['completed']" icon="clipboard" tone="green" :href="route('reviewer.reviews.index')" />
        </div>

        <x-dashboard.section title="Assigned Reviews" view-all-route="reviewer.assignments.index">
            @if ($assignments->isEmpty())
                <x-dashboard.empty-state
                    image="no-assignments"
                    alt="No assigned review documents"
                    title="No assigned applications yet"
                    message="New ethics review assignments will appear here once they are assigned to you."
                    action-label="Refresh"
                    :action-href="request()->url()"
                    action-icon="refresh"
                />
            @else
                <div class="dashboard-table-wrap">
                    <table class="dashboard-table">
                        <thead><tr><th>Application Code</th><th>Review Type</th><th>Research Title</th><th>Date Submitted</th><th>Date Assigned</th><th class="dashboard-table-status">Status</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td><a href="{{ route('reviewer.assignments.show', $assignment) }}">{{ $assignment->researchApplication->application_code }}</a></td>
                                    <td>{{ Str::headline($assignment->review_type) }}</td>
                                    <td><x-dashboard.research-title :title="$assignment->researchApplication->research_title" :href="route('reviewer.assignments.show', $assignment)" /></td>
                                    <td>{{ $assignment->researchApplication->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
                                    <td>{{ $assignment->assigned_at?->format('M j, Y') ?? 'Not assigned' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" /></td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('reviewer.assignments.show', $assignment)">View</x-dashboard.action-link></td>
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
