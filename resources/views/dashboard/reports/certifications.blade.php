@extends('layouts.dashboard')

@section('content')
    @php($query = array_filter($filters, fn ($value) => filled($value)))
    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading report-record-heading">
            <h1>Applicant Certification</h1>
            <a class="dashboard-outline-action" href="{{ route('res.reports.index', $query).'#applicant-certification-title' }}"><x-dashboard.icon name="arrow-left" size="18" />Back to Reports</a>
        </header>
        <section class="application-panel" aria-labelledby="certification-list-title">
            <header class="application-panel-heading"><h2 id="certification-list-title">Released Applications</h2></header>
            <x-dashboard.overflow label="All applicant certification records">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Applicant</th><th>Institutional ID</th><th>Institute</th><th>Application Code</th><th class="report-cell-centered">Certificate Status</th><th class="report-cell-centered">Released Date</th><th class="report-cell-centered">Ageing</th><th class="report-action">Action</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr><td>{{ $row['applicant']?->name }}</td><td>{{ $row['applicant']?->institutional_identifier }}</td><td>{{ $row['application']->institution }}</td><td>{{ $row['application']->application_code }}</td><td class="report-cell-centered">{{ $row['certificate_status'] }}</td><td class="report-cell-centered">{{ $row['released_at']?->format('M j, Y g:i A') ?? '—' }}</td><td class="report-cell-centered">{{ $row['ageing_days'] === null ? '—' : $row['ageing_days'].' days' }}</td><td class="report-action">@if ($row['applicant'])<a class="dashboard-action-link" href="{{ route('res.reports.applicants.show', array_merge($query, ['applicant' => $row['applicant']->id, 'application' => $row['application']->id])) }}">View</a>@endif</td></tr>
                        @empty
                            <tr><td colspan="8">No applicant certification records match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-dashboard.overflow>
        </section>
    </div>
@endsection
