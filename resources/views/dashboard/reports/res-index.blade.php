@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Reports</h1>
            <p>Open authorized operational records and ethics-review reporting tools.</p>
        </header>

        <section class="application-panel">
            <header class="application-panel-heading">
                <div><h2>Audit Log</h2><p>Workflow, release, certificate, account, and access events with authorized filters and pagination.</p></div>
                <a class="dashboard-primary-action" href="{{ route('res.reports.audit.index') }}"><x-dashboard.icon name="clipboard" size="18" /><span>Open Audit Log</span></a>
            </header>
        </section>

        <section class="application-panel" aria-labelledby="applicant-feedback-report-title">
            <header class="application-panel-heading">
                <div>
                    <h2 id="applicant-feedback-report-title">Applicant Feedback Summary</h2>
                    <p>Anonymous aggregates for the current 10-question evaluation. Individual responses and comments are not shown.</p>
                </div>
                <div>
                    <strong>{{ $surveySummary['response_count'] }}</strong>
                    <span>{{ Str::plural('completed response', $surveySummary['response_count']) }}</span>
                </div>
            </header>

            @if ($surveySummary['response_count'] > 0)
                <dl class="application-detail-grid">
                    <div><dt>Overall average</dt><dd>{{ number_format($surveySummary['overall_average'], 2) }} / 5</dd></div>
                    @foreach ($surveySummary['sections'] as $section)
                        <div><dt>{{ $section['title'] }}</dt><dd>{{ number_format($section['average'], 2) }} / 5</dd></div>
                    @endforeach
                </dl>

                @foreach ($surveySummary['sections'] as $section)
                    <h3>{{ $section['title'] }}</h3>
                    <x-dashboard.overflow label="{{ $section['title'] }} aggregate ratings">
                        <table class="dashboard-table">
                            <thead><tr><th>Evaluation statement</th><th>Average</th><th>Responses</th></tr></thead>
                            <tbody>
                                @foreach ($section['questions'] as $question)
                                    <tr>
                                        <td>{{ $question['label'] }}</td>
                                        <td>{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td>
                                        <td>{{ $question['response_count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-dashboard.overflow>
                @endforeach
            @else
                <p>No current-questionnaire responses have been submitted yet.</p>
            @endif

            @if ($surveySummary['legacy_response_count'] > 0)
                <small>{{ $surveySummary['legacy_response_count'] }} earlier questionnaire {{ Str::plural('response', $surveySummary['legacy_response_count']) }} {{ $surveySummary['legacy_response_count'] === 1 ? 'remains' : 'remain' }} preserved and excluded from the current-question averages.</small>
            @endif
        </section>
    </div>
@endsection
