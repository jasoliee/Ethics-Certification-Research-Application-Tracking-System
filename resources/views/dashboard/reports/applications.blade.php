@extends('layouts.dashboard')

@section('content')
    @php($query = array_filter($filters, fn ($value) => filled($value)))

    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading report-record-heading">
            <div>
                <h1>Filtered Applications</h1>
                <p>{{ $filterSummary }}</p>
            </div>
            <a class="dashboard-outline-action" href="{{ route('res.reports.index', $query) }}"><x-dashboard.icon name="arrow-left" size="18" />Back to Reports</a>
        </header>

        <section class="application-panel" aria-labelledby="all-filtered-applications-title">
            <header class="application-panel-heading">
                <h2 id="all-filtered-applications-title">All Matching Applications</h2>
                <strong class="report-response-counter">{{ $applications->count() }} {{ Str::plural('record', $applications->count()) }}</strong>
            </header>
            <x-dashboard.overflow label="All filtered double-blind applications">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Application Code</th><th>Research Title</th><th>Institute</th><th>Review Type</th><th>Status</th><th>Certificate Status</th><th>Submitted</th><th class="report-action">Action</th></tr></thead>
                    <tbody>@forelse ($applications as $row)@php($application = $row['application'])<tr><td>{{ $application->application_code }}</td><td class="report-title-cell" title="{{ $application->research_title }}">{{ $application->research_title }}</td><td>{{ $application->institution }}</td><td>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $row['certificate_status'] }}</td><td>{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</td><td class="report-action"><a class="dashboard-action-link" href="{{ route('res.applications.show', $application) }}">View</a></td></tr>@empty<tr><td colspan="8">No applications match the selected filters.</td></tr>@endforelse</tbody>
                </table>
            </x-dashboard.overflow>
        </section>
    </div>
@endsection
