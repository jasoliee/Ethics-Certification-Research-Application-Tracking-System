@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading">
            <div>
                <h1>Released Applicant Record</h1>
                <p>Only applications with a complete issued certificate set are shown.</p>
            </div>
            <a class="dashboard-secondary-action" href="{{ route('res.reports.index') }}"><x-dashboard.icon name="arrow-left" size="18" />Back to Reports</a>
        </header>

        <section class="application-panel" aria-labelledby="released-applicant-identity-title">
            <header class="application-panel-heading"><div><h2 id="released-applicant-identity-title">{{ $applicant->name }}</h2><p>Identity release verified by ECRATS certificate records.</p></div><span class="status-badge status-badge-green">Certificate issued</span></header>
            <dl class="application-detail-grid">
                <div><dt>Institutional ID</dt><dd>{{ $applicant->institutional_identifier ?: 'Not specified' }}</dd></div>
                <div><dt>Institution / Affiliation</dt><dd>{{ $applicant->institution ?: 'Not specified' }}</dd></div>
                <div><dt>Email Address</dt><dd>{{ $applicant->email }}</dd></div>
                <div><dt>Released Applications</dt><dd>{{ $applications->count() }}</dd></div>
            </dl>
        </section>

        @foreach ($applications as $application)
            <section class="application-panel" aria-labelledby="released-application-{{ $application->id }}">
                <header class="application-panel-heading">
                    <div><h2 id="released-application-{{ $application->id }}">{{ $application->application_code }}</h2><p>{{ $application->research_title }}</p></div>
                    <a class="dashboard-secondary-action" href="{{ route('res.certificates.workspace', $application) }}">View Certificate Record</a>
                </header>
                <dl class="application-detail-grid">
                    <div><dt>Institute</dt><dd>{{ $application->institution }}</dd></div>
                    <div><dt>Review Type</dt><dd>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</dd></div>
                    <div><dt>Workflow Status</dt><dd>{{ $application->statusLabel() }}</dd></div>
                    <div><dt>Submitted</dt><dd>{{ $application->submitted_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</dd></div>
                </dl>
                <x-dashboard.overflow label="Issued certificates for {{ $application->application_code }}">
                    <table class="dashboard-table report-table">
                        <thead><tr><th>Recipient</th><th>Certificate Number</th><th>Status</th><th>Released</th><th>Claimed</th></tr></thead>
                        <tbody>@foreach ($application->certificates as $certificate)<tr><td>{{ $certificate->recipient_name }}</td><td>{{ $certificate->certificate_number }}</td><td>{{ $certificate->status->label() }}</td><td>{{ $certificate->released_at?->format('M j, Y g:i A') ?? '—' }}</td><td>{{ $certificate->claimed_at?->format('M j, Y g:i A') ?? '—' }}</td></tr>@endforeach</tbody>
                    </table>
                </x-dashboard.overflow>
            </section>
        @endforeach
    </div>
@endsection
