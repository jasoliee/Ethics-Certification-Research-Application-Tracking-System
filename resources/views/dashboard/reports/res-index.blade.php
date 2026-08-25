@extends('layouts.dashboard')

@section('content')
    @php
        $summaryCards = [
            ['label' => 'Submitted Applications', 'key' => 'submitted', 'icon' => 'file-text', 'tone' => 'blue'],
            ['label' => 'Awaiting RES Screening', 'key' => 'screening', 'icon' => 'search', 'tone' => 'orange'],
            ['label' => 'Awaiting Reviewer Assignment', 'key' => 'assignment', 'icon' => 'users', 'tone' => 'violet'],
            ['label' => 'Under Review', 'key' => 'review', 'icon' => 'file-search', 'tone' => 'green'],
            ['label' => 'Awaiting Decision Release', 'key' => 'decision_release', 'icon' => 'clipboard', 'tone' => 'cyan'],
            ['label' => 'For Certificate Release', 'key' => 'certificate_release', 'icon' => 'award', 'tone' => 'purple'],
            ['label' => 'Certificates Released', 'key' => 'certificates_released', 'icon' => 'check', 'tone' => 'success'],
            ['label' => 'Overdue or Due Soon', 'key' => 'due_items', 'icon' => 'alert-triangle', 'tone' => 'red'],
        ];
    @endphp

    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading">
            <h1>Reports</h1>
            <a class="dashboard-primary-action" href="{{ route('res.reports.audit.index') }}"><x-dashboard.icon name="clipboard" size="18" /><span>Open Audit Log</span></a>
        </header>

        <form class="report-filter-panel" method="GET" action="{{ route('res.reports.index') }}">
            <div class="report-filter-grid">
                <label><span>Academic Term</span><select name="academic_term_id"><option value="">All terms</option>@foreach ($termOptions as $term)<option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>@endforeach</select></label>
                <label><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
                <label><span>Research Type</span><select name="research_type"><option value="">All research types</option>@foreach ($researchTypes as $type)<option value="{{ $type->value }}" @selected(($filters['research_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Applicant Category</span><select name="applicant_type"><option value="">All applicant categories</option>@foreach ($applicantTypes as $type)<option value="{{ $type->value }}" @selected(($filters['applicant_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Review Type</span><select name="review_type"><option value="">All review types</option>@foreach ($reviewTypes as $type)<option value="{{ $type->value }}" @selected(($filters['review_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Institute</span><select name="institute"><option value="">All institutes</option>@foreach ($institutes as $institute)<option value="{{ $institute }}" @selected(($filters['institute'] ?? '') === $institute)>{{ $institute }}</option>@endforeach</select></label>
                <label><span>Workflow Status</span><select name="application_status"><option value="">All workflow statuses</option>@foreach ($applicationStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['application_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
            </div>
            <div class="report-filter-actions"><button class="dashboard-primary-action" type="submit">Apply Filters</button><a class="dashboard-secondary-action" href="{{ route('res.reports.index') }}">Reset</a></div>
        </form>

        <section class="report-summary-grid" aria-label="Operational summary">
            @foreach ($summaryCards as $card)
                <x-dashboard.summary-card :label="$card['label']" :count="$report['summary'][$card['key']]" :icon="$card['icon']" :tone="$card['tone']" />
            @endforeach
        </section>

        @unless ($report['has_data'])
            <section class="application-panel report-empty-state"><x-dashboard.icon name="file-search" size="34" /><h2>No applications match the selected filters</h2><p>Adjust the filters to review another reporting period.</p></section>
        @endunless

        <div class="report-chart-grid">
            <section class="application-panel report-chart-card" aria-labelledby="pipeline-title">
                <header class="application-panel-heading"><h2 id="pipeline-title">Workflow Pipeline</h2></header>
                @php($pipelineMax = max(1, collect($report['pipeline'])->max('count')))
                <x-dashboard.overflow label="Workflow pipeline report"><table class="report-chart-table"><thead><tr><th>Stage</th><th>Applications</th><th>Relative volume</th></tr></thead><tbody>@foreach ($report['pipeline'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td><td><meter min="0" max="{{ $pipelineMax }}" value="{{ $row['count'] }}">{{ $row['count'] }}</meter></td></tr>@endforeach</tbody></table></x-dashboard.overflow>
            </section>

            <section class="application-panel report-chart-card" aria-labelledby="trend-title">
                <header class="application-panel-heading"><h2 id="trend-title">Application Submission Trend</h2></header>
                @php($trendMax = max(1, collect($report['submission_trend']['rows'])->max('count') ?? 1))
                @if ($report['submission_trend']['rows'])
                    <x-dashboard.overflow label="Application submission trend report"><table class="report-chart-table"><thead><tr><th>{{ Str::headline($report['submission_trend']['interval']) }} starting</th><th>Applications</th><th>Relative volume</th></tr></thead><tbody>@foreach ($report['submission_trend']['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td><td><meter min="0" max="{{ $trendMax }}" value="{{ $row['count'] }}">{{ $row['count'] }}</meter></td></tr>@endforeach</tbody></table></x-dashboard.overflow>
                @else
                    <p class="report-inline-empty">No submissions are available for the selected period.</p>
                @endif
            </section>

            @foreach ([['title' => 'Review Classification Distribution', 'rows' => $report['classifications']], ['title' => 'Decision Outcome Distribution', 'rows' => $report['decisions']], ['title' => 'Certificate Operations', 'rows' => $report['certificate_operations']]] as $chart)
                <section class="application-panel report-chart-card">
                    <header class="application-panel-heading"><h2>{{ $chart['title'] }}</h2></header>
                    @php($chartMax = max(1, collect($chart['rows'])->max('count')))
                    <x-dashboard.overflow label="{{ $chart['title'] }} report"><table class="report-chart-table"><thead><tr><th>Category</th><th>Count</th><th>Relative volume</th></tr></thead><tbody>@foreach ($chart['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td><td><meter min="0" max="{{ $chartMax }}" value="{{ $row['count'] }}">{{ $row['count'] }}</meter></td></tr>@endforeach</tbody></table></x-dashboard.overflow>
                </section>
            @endforeach

            <section class="application-panel report-chart-card" aria-labelledby="turnaround-title">
                <header class="application-panel-heading"><h2 id="turnaround-title">Turnaround Time</h2></header>
                <x-dashboard.overflow label="Turnaround time report"><table class="report-chart-table"><thead><tr><th>Interval</th><th>Average days</th><th>Median days</th><th>Samples</th></tr></thead><tbody>@foreach ($report['turnaround'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['average_days'] === null ? '—' : number_format($row['average_days'], 2) }}</td><td>{{ $row['median_days'] === null ? '—' : number_format($row['median_days'], 2) }}</td><td>{{ $row['sample_count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow>
            </section>
        </div>

        <section class="application-panel" aria-labelledby="reviewer-workload-title">
            <header class="application-panel-heading"><h2 id="reviewer-workload-title">Reviewer Capacity and Delay</h2></header>
            <x-dashboard.overflow label="Reviewer capacity and delay report"><table class="dashboard-table report-table"><thead><tr><th>Reviewer</th><th>Current Load</th><th>Capacity</th><th>Remaining</th><th>Completed Reviews</th><th>Overdue</th><th>Due Soon</th></tr></thead><tbody>@forelse ($report['reviewer_workload'] as $row)<tr><td>{{ $row['reviewer']->name }}</td><td>{{ $row['active'] }}</td><td>{{ $row['capacity'] }}</td><td>{{ $row['remaining'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['overdue'] }}</td><td>{{ $row['due_soon'] }}</td></tr>@empty<tr><td colspan="7">No authorized Reviewer workload records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="adviser-workload-title">
            <header class="application-panel-heading"><h2 id="adviser-workload-title">Adviser Endorsement Workload</h2></header>
            <x-dashboard.overflow label="Adviser endorsement workload report"><table class="dashboard-table report-table"><thead><tr><th>Adviser</th><th>Expected Endorsements</th><th>Received</th><th>Completed</th><th>Awaiting</th><th>Delayed</th></tr></thead><tbody>@forelse ($report['adviser_workload'] as $row)<tr><td>{{ $row['adviser']->name }}</td><td>{{ $row['expected'] }}</td><td>{{ $row['received'] }}</td><td>{{ $row['endorsed'] }}</td><td>{{ $row['awaiting'] }}</td><td>{{ $row['delayed'] }}</td></tr>@empty<tr><td colspan="6">No Adviser workload records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="action-required-title">
            <header class="application-panel-heading"><h2 id="action-required-title">Action Required</h2></header>
            <x-dashboard.overflow label="Applications requiring action"><table class="dashboard-table report-table"><thead><tr><th>Application Code</th><th>Workflow Stage</th><th>Deadline</th><th>Responsible Role</th><th class="dashboard-table-action-heading">Action</th></tr></thead><tbody>@forelse ($report['action_required'] as $row)<tr><td>{{ $row['application']?->application_code }}</td><td>{{ $row['stage'] }}</td><td><span class="status-badge {{ $row['deadline']->isPast() ? 'status-badge-red' : 'status-badge-orange' }}">{{ $row['deadline']->format('M j, Y g:i A') }}</span></td><td>{{ $row['responsible_role'] }}</td><td class="dashboard-table-action-cell"><a class="dashboard-secondary-action" href="{{ route('res.applications.show', $row['application']) }}">View</a></td></tr>@empty<tr><td colspan="5">No overdue or due-soon applications match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="certificate-follow-up-title">
            <header class="application-panel-heading"><h2 id="certificate-follow-up-title">Certificate Follow-up</h2></header>
            <x-dashboard.overflow label="Certificate follow-up report"><table class="dashboard-table report-table"><thead><tr><th>Application Code</th><th>Recipients</th><th>Certificate Status</th><th>Release Date</th><th>Claim Status</th><th>Ageing</th><th class="dashboard-table-action-heading">Action</th></tr></thead><tbody>@forelse ($report['certificate_follow_up'] as $row)<tr><td>{{ $row['application']->application_code }}</td><td>{{ $row['recipient_count'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['released_at'] ? Carbon\Carbon::parse($row['released_at'])->format('M j, Y') : '—' }}</td><td>{{ $row['claim_status'] }}</td><td>{{ $row['ageing_days'] === null ? '—' : $row['ageing_days'].' days' }}</td><td class="dashboard-table-action-cell"><a class="dashboard-secondary-action" href="{{ route('res.certificates.index', ['application' => $row['application']->id]) }}">View</a></td></tr>@empty<tr><td colspan="7">No certificate records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="data-quality-title">
            <header class="application-panel-heading"><h2 id="data-quality-title">Operations and Data Quality</h2></header>
            <div class="report-quality-grid">@foreach ($report['data_quality'] as $row)<article><strong>{{ $row['count'] }}</strong><span>{{ $row['label'] }}</span></article>@endforeach</div>
        </section>

        <section class="application-panel" aria-labelledby="applicant-feedback-report-title">
            <header class="application-panel-heading"><div><h2 id="applicant-feedback-report-title">Applicant Feedback Summary</h2><p>Anonymous aggregates only. Individual responses and comments are never shown.</p></div><strong>{{ $surveySummary['response_count'] }} {{ Str::plural('response', $surveySummary['response_count']) }}</strong></header>
            @if ($surveySummary['response_count'] > 0)
                <dl class="application-detail-grid"><div><dt>Overall average</dt><dd>{{ number_format($surveySummary['overall_average'], 2) }} / 5</dd></div>@foreach ($surveySummary['sections'] as $section)<div><dt>{{ $section['title'] }}</dt><dd>{{ number_format($section['average'], 2) }} / 5</dd></div>@endforeach</dl>
                @foreach ($surveySummary['sections'] as $section)<h3>{{ $section['title'] }}</h3><x-dashboard.overflow label="{{ $section['title'] }} aggregate ratings"><table class="dashboard-table"><thead><tr><th>Evaluation statement</th><th>Average</th><th>Responses</th></tr></thead><tbody>@foreach ($section['questions'] as $question)<tr><td>{{ $question['label'] }}</td><td>{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td><td>{{ $question['response_count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow>@endforeach
            @else
                <p class="report-inline-empty">No current-questionnaire responses match the selected filters.</p>
            @endif
            @if ($surveySummary['legacy_response_count'] > 0)<small>{{ $surveySummary['legacy_response_count'] }} preserved earlier-questionnaire {{ Str::plural('response', $surveySummary['legacy_response_count']) }} excluded from current averages.</small>@endif
        </section>
    </div>
@endsection
