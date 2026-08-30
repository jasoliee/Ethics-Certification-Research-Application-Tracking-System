@extends('layouts.dashboard')

@section('content')
    @php($query = array_filter($filters, fn ($value) => filled($value)))
    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading report-record-heading">
            <div><h1>Institute Applicant List</h1><p>{{ $institute }}</p></div>
            <a class="dashboard-outline-action" href="{{ route('res.reports.index', $query) }}"><x-dashboard.icon name="arrow-left" size="18" />Back to Reports</a>
        </header>
        <section class="application-panel" aria-labelledby="institute-applicant-list-title">
            <header class="application-panel-heading"><h2 id="institute-applicant-list-title">Applicants and Applications</h2></header>
            <x-dashboard.overflow label="Applicant list for {{ $institute }}">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Applicant Name</th><th>Institutional ID</th><th>Application Code</th><th>Application Status</th><th class="report-action">Action</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr><td>{{ $row['applicant']->name }}</td><td>{{ $row['applicant']->institutional_identifier ?: 'Not specified' }}</td><td>{{ $row['application_code'] }}</td><td>{{ $row['status'] }}</td><td class="report-action">@if ($row['application'])<a class="dashboard-action-link" href="{{ route('res.applications.show', $row['application']) }}">View</a>@else<span>—</span>@endif</td></tr>
                        @empty
                            <tr><td colspan="5">No applicant accounts match this institute and reporting scope.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-dashboard.overflow>
        </section>
    </div>
@endsection
