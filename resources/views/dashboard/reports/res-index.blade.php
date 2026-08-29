@extends('layouts.dashboard')

@section('content')
    @php
        $query = array_filter($filters, fn ($value) => filled($value));
        $summaryCards = [
            ['label' => 'Unique Applicants', 'key' => 'unique_applicants', 'icon' => 'users', 'tone' => 'green'],
            ['label' => 'Applications Submitted', 'key' => 'submitted', 'icon' => 'file-text', 'tone' => 'blue'],
            ['label' => 'Applicants Not Yet Submitted', 'key' => 'not_submitted', 'icon' => 'clock', 'tone' => 'orange'],
            ['label' => 'Failed Applications', 'key' => 'failed', 'icon' => 'alert-triangle', 'tone' => 'red'],
            ['label' => 'Claimed Certificates', 'key' => 'certificates_claimed', 'icon' => 'award', 'tone' => 'success'],
            ['label' => 'Unclaimed Certificates', 'key' => 'certificates_unclaimed', 'icon' => 'file-search', 'tone' => 'violet'],
        ];
    @endphp

    <div class="dashboard-page report-page">
        <header class="dashboard-page-heading report-page-heading">
            <div>
                <h1>Reports</h1>
                <p>Operational, workload, certificate, and anonymous applicant-feedback reporting.</p>
            </div>
            <div class="report-heading-actions">
                <a class="dashboard-secondary-action" href="{{ route('res.reports.audit.index') }}"><x-dashboard.icon name="clipboard" size="18" /><span>Audit Log</span></a>
                <a class="dashboard-secondary-action" href="{{ route('res.reports.export', $query) }}"><x-dashboard.icon name="download" size="18" /><span>Export CSV</span></a>
                <a class="dashboard-primary-action" href="{{ route('res.reports.print', $query) }}" target="_blank" rel="noopener"><x-dashboard.icon name="printer" size="18" /><span>Print Report</span></a>
            </div>
        </header>

        <form class="report-filter-panel" method="GET" action="{{ route('res.reports.index') }}">
            <div class="report-filter-grid">
                <label class="report-filter-search"><span>Search</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Application code or research title"></label>
                <label><span>Academic Term</span><select name="academic_term_id"><option value="">All terms</option>@foreach ($termOptions as $term)<option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>@endforeach</select></label>
                <label><span>Institute</span><select name="institute"><option value="">All institutes</option>@foreach ($institutes as $institute)<option value="{{ $institute }}" @selected(($filters['institute'] ?? '') === $institute)>{{ $institute }}</option>@endforeach</select></label>
                <label><span>Workflow Status</span><select name="application_status"><option value="">All workflow statuses</option>@foreach ($applicationStatuses as $status)<option value="{{ $status->value }}" @selected(($filters['application_status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
                <label><span>Certificate Status</span><select name="certificate_status"><option value="">All certificate statuses</option>@foreach ($certificateStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['certificate_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label><span>Review Type</span><select name="review_type"><option value="">All review types</option>@foreach ($reviewTypes as $type)<option value="{{ $type->value }}" @selected(($filters['review_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Research Type</span><select name="research_type"><option value="">All research types</option>@foreach ($researchTypes as $type)<option value="{{ $type->value }}" @selected(($filters['research_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Applicant Category</span><select name="applicant_type"><option value="">All applicant categories</option>@foreach ($applicantTypes as $type)<option value="{{ $type->value }}" @selected(($filters['applicant_type'] ?? '') === $type->value)>{{ $type->label() }}</option>@endforeach</select></label>
                <label><span>Date From</span><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></label>
                <label><span>Date To</span><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></label>
            </div>
            <div class="report-filter-actions"><button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="search" size="18" />Apply Filters</button><a class="dashboard-secondary-action" href="{{ route('res.reports.index') }}">Reset</a></div>
        </form>

        <section class="application-panel report-overall-card" aria-labelledby="overall-summary-title">
            <header class="application-panel-heading"><h2 id="overall-summary-title">Overall Summary</h2></header>
            <div class="report-summary-grid">
                @foreach ($summaryCards as $card)
                    <x-dashboard.summary-card :label="$card['label']" :count="$report['summary'][$card['key']]" :icon="$card['icon']" :tone="$card['tone']" />
                @endforeach
            </div>
            <div class="report-classification-strip" aria-label="Review classification summary">
                @foreach ($report['classifications'] as $row)
                    <article><strong>{{ $row['count'] }}</strong><span>{{ $row['label'] }}</span></article>
                @endforeach
            </div>
        </section>

        @unless ($report['has_data'])
            <section class="application-panel report-empty-state"><x-dashboard.icon name="file-search" size="34" /><h2>No applications match the selected filters</h2><p>Adjust the filters to review another reporting period.</p></section>
        @endunless

        <section class="application-panel" aria-labelledby="institute-summary-title">
            <header class="application-panel-heading"><div><h2 id="institute-summary-title">Applicant &amp; Application Summary</h2><p>Per institute for the selected reporting scope.</p></div></header>
            <x-dashboard.overflow label="Applicant and application summary by institute"><table class="dashboard-table report-table"><thead><tr><th>Institute</th><th>Unique Applicants</th><th>Applications Submitted</th><th>Not Yet Submitted</th><th>Failed</th><th>Certificates Claimed</th><th>Certificates Unclaimed</th></tr></thead><tbody>@forelse ($report['institute_summary'] as $row)<tr><th scope="row">{{ $row['institute'] }}</th><td>{{ $row['unique_applicants'] }}</td><td>{{ $row['submitted'] }}</td><td>{{ $row['not_submitted'] }}</td><td>{{ $row['failed'] }}</td><td>{{ $row['claimed'] }}</td><td>{{ $row['unclaimed'] }}</td></tr>@empty<tr><td colspan="7">No institute summary records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="application-list-title">
            <header class="application-panel-heading"><div><h2 id="application-list-title">Filtered Application List</h2><p>Applicant identity remains hidden until every personalized certificate for that application is issued.</p></div><span class="status-badge status-badge-green">Double-blind</span></header>
            <x-dashboard.overflow label="Filtered double-blind application list"><table class="dashboard-table report-table"><thead><tr><th>Application Code</th><th>Research Title</th><th>Institute</th><th>Review Type</th><th>Status</th><th>Certificate Status</th><th>Submitted</th></tr></thead><tbody>@forelse ($report['applications'] as $row)@php($application = $row['application'])<tr><td><a href="{{ route('res.applications.show', $application) }}">{{ $application->application_code }}</a></td><td class="report-title-cell" title="{{ $application->research_title }}">{{ $application->research_title }}</td><td>{{ $application->institution }}</td><td>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $row['certificate_status'] }}</td><td>{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</td></tr>@empty<tr><td colspan="7">No applications match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="released-applicants-title">
            <header class="application-panel-heading"><div><h2 id="released-applicants-title">Certificate-Released Applicants</h2><p>Identity appears only after the server verifies complete certificate issuance. Unreleased applications are never linked here.</p></div></header>
            <x-dashboard.overflow label="Certificate-released applicants"><table class="dashboard-table report-table"><thead><tr><th>Applicant</th><th>Institutional ID</th><th>Institute</th><th>Released Applications</th><th class="dashboard-table-action-heading">Action</th></tr></thead><tbody>@forelse ($report['visible_applicants'] as $applicant)<tr><td>{{ $applicant->name }}</td><td>{{ $applicant->institutional_identifier }}</td><td>{{ $applicant->institution }}</td><td>{{ $applicant->released_application_count }}</td><td class="dashboard-table-action-cell"><a class="dashboard-secondary-action" href="{{ route('res.reports.applicants.show', $applicant) }}">View Released Records</a></td></tr>@empty<tr><td colspan="5">No applicant identity is eligible for release in this reporting scope.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="adviser-reviewer-summary-title">
            <header class="application-panel-heading"><h2 id="adviser-reviewer-summary-title">Adviser &amp; Reviewer Summary</h2></header>
            <x-dashboard.overflow label="Adviser and reviewer summary per institute"><table class="dashboard-table report-table"><thead><tr><th>Institute</th><th>Research Advisers</th><th>Reviewer-enabled Advisers</th></tr></thead><tbody>@forelse ($report['adviser_reviewer_summary'] as $row)<tr><th scope="row">{{ $row['institute'] }}</th><td>{{ $row['advisers'] }}</td><td>{{ $row['reviewers'] }}</td></tr>@empty<tr><td colspan="3">No active Adviser accounts match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="reviewer-workload-title">
            <header class="application-panel-heading"><div><h2 id="reviewer-workload-title">Reviewer Capacity and Delay</h2><p>Reviewer Workload Report. Capacity is fixed at {{ App\Services\Reports\OperationalReportService::REVIEWER_CAPACITY }} assignments.</p></div></header>
            <x-dashboard.overflow label="Reviewer workload report"><table class="dashboard-table report-table"><thead><tr><th>Reviewer</th><th>Institute</th><th>Expedited</th><th>Full Board</th><th>Total Assigned</th><th>Completed</th><th>Pending</th><th>Overdue</th><th>Remaining Capacity</th></tr></thead><tbody>@forelse ($report['reviewer_workload'] as $row)<tr><td>{{ $row['reviewer']->name }}</td><td>{{ $row['institute'] }}</td><td>{{ $row['expedited'] }}</td><td>{{ $row['full_board'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['pending'] }}</td><td>{{ $row['overdue'] }}</td><td>{{ $row['remaining'] }}</td></tr>@empty<tr><td colspan="9">No authorized Reviewer workload records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="adviser-workload-title">
            <header class="application-panel-heading"><div><h2 id="adviser-workload-title">Adviser Endorsement Workload</h2><p>Counts are based on distinct Applicant accounts, not application records.</p></div></header>
            <x-dashboard.overflow label="Adviser endorsement workload report"><table class="dashboard-table report-table"><thead><tr><th>Adviser</th><th>Institute</th><th>Declared Expected</th><th>Applicants Received</th><th>Completed</th><th>Awaiting</th><th>Not Yet Received</th><th>Delayed</th></tr></thead><tbody>@forelse ($report['adviser_workload'] as $row)<tr><td>{{ $row['adviser']->name }}</td><td>{{ $row['institute'] }}</td><td>{{ $row['expected'] }}</td><td>{{ $row['received'] }}</td><td>{{ $row['endorsed'] }}</td><td>{{ $row['awaiting'] }}</td><td>{{ $row['not_received'] }}</td><td>{{ $row['delayed'] }}</td></tr>@empty<tr><td colspan="8">No Adviser workload records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="certificate-follow-up-title">
            <header class="application-panel-heading"><h2 id="certificate-follow-up-title">Certificate Follow-up: Release &amp; Claiming</h2></header>
            <x-dashboard.overflow label="Certificate release and claiming report"><table class="dashboard-table report-table"><thead><tr><th>Application Code</th><th>Recipients</th><th>Certificate Status</th><th>Released</th><th>Claim Status</th><th>Ageing</th><th class="dashboard-table-action-heading">Action</th></tr></thead><tbody>@forelse ($report['certificate_follow_up'] as $row)<tr><td>{{ $row['application']->application_code }}</td><td>{{ $row['recipient_count'] }}</td><td>{{ $row['status'] }}</td><td>{{ $row['released_at'] ? Carbon\Carbon::parse($row['released_at'])->format('M j, Y g:i A') : '—' }}</td><td>{{ $row['claim_status'] }}</td><td>{{ $row['ageing_days'] === null ? '—' : $row['ageing_days'].' days' }}</td><td class="dashboard-table-action-cell"><a class="dashboard-secondary-action" href="{{ route('res.certificates.index', ['application' => $row['application']->id]) }}">View</a></td></tr>@empty<tr><td colspan="7">No certificate records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <div class="report-chart-grid">
            <section class="application-panel report-chart-card" aria-labelledby="pipeline-title"><header class="application-panel-heading"><h2 id="pipeline-title">Workflow Pipeline</h2></header><x-dashboard.overflow label="Workflow pipeline"><table class="dashboard-table"><thead><tr><th>Stage</th><th>Applications</th></tr></thead><tbody>@foreach ($report['pipeline'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow></section>
            <section class="application-panel report-chart-card" aria-labelledby="turnaround-title"><header class="application-panel-heading"><h2 id="turnaround-title">Turnaround Time</h2></header><x-dashboard.overflow label="Turnaround time"><table class="dashboard-table"><thead><tr><th>Interval</th><th>Average Days</th><th>Median Days</th><th>Samples</th></tr></thead><tbody>@foreach ($report['turnaround'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['average_days'] === null ? '—' : number_format($row['average_days'], 2) }}</td><td>{{ $row['median_days'] === null ? '—' : number_format($row['median_days'], 2) }}</td><td>{{ $row['sample_count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow></section>
        </div>

        <section class="application-panel" aria-labelledby="submission-trend-title">
            <header class="application-panel-heading"><div><h2 id="submission-trend-title">Application Submission Trend</h2><p>Submissions grouped by {{ $report['submission_trend']['interval'] }} for the selected scope.</p></div></header>
            <x-dashboard.overflow label="Application submission trend"><table class="dashboard-table report-table"><thead><tr><th>Period</th><th>Applications Submitted</th></tr></thead><tbody>@forelse ($report['submission_trend']['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] }}</td></tr>@empty<tr><td colspan="2">No submitted applications match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="action-required-title">
            <header class="application-panel-heading"><h2 id="action-required-title">Action Required</h2></header>
            <x-dashboard.overflow label="Applications requiring action"><table class="dashboard-table report-table"><thead><tr><th>Application Code</th><th>Workflow Stage</th><th>Deadline</th><th>Responsible Role</th><th class="dashboard-table-action-heading">Action</th></tr></thead><tbody>@forelse ($report['action_required'] as $row)<tr><td>{{ $row['application']?->application_code }}</td><td>{{ $row['stage'] }}</td><td><span class="status-badge {{ $row['deadline']->isPast() ? 'status-badge-red' : 'status-badge-orange' }}">{{ $row['deadline']->format('M j, Y g:i A') }}</span></td><td>{{ $row['responsible_role'] }}</td><td class="dashboard-table-action-cell"><a class="dashboard-secondary-action" href="{{ route('res.applications.show', $row['application']) }}">View</a></td></tr>@empty<tr><td colspan="5">No overdue or due-soon applications match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="data-quality-title"><header class="application-panel-heading"><h2 id="data-quality-title">Operations and Data Quality</h2></header><div class="report-quality-grid">@foreach ($report['data_quality'] as $row)<article><strong>{{ $row['count'] }}</strong><span>{{ $row['label'] }}</span></article>@endforeach</div></section>

        <section class="application-panel" aria-labelledby="applicant-feedback-report-title">
            <header class="application-panel-heading"><div><h2 id="applicant-feedback-report-title">Applicant Feedback Summary</h2><p>Anonymous aggregates only. Individual responses, identities, and comments are never shown.</p></div><div class="report-section-actions"><strong>{{ $surveySummary['response_count'] }} {{ Str::plural('response', $surveySummary['response_count']) }}</strong><a class="dashboard-secondary-action" href="{{ route('res.reports.survey.print', $query) }}" target="_blank" rel="noopener"><x-dashboard.icon name="printer" size="16" />Print Survey</a></div></header>
            @if ($surveySummary['response_count'] > 0)
                <dl class="application-detail-grid"><div><dt>Overall average</dt><dd>{{ number_format($surveySummary['overall_average'], 2) }} / 5</dd></div>@foreach ($surveySummary['sections'] as $section)<div><dt>{{ $section['title'] }}</dt><dd>{{ number_format($section['average'], 2) }} / 5</dd></div>@endforeach</dl>
                @foreach ($surveySummary['sections'] as $section)<h3>{{ $section['title'] }}</h3><x-dashboard.overflow label="{{ $section['title'] }} aggregate ratings"><table class="dashboard-table"><thead><tr><th>Evaluation Statement</th><th>Average</th><th>Responses</th></tr></thead><tbody>@foreach ($section['questions'] as $question)<tr><td>{{ $question['label'] }}</td><td>{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td><td>{{ $question['response_count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow>@endforeach
            @else
                <p class="report-inline-empty">No current-questionnaire responses match the selected filters.</p>
            @endif
            @if ($surveySummary['legacy_response_count'] > 0)<small>{{ $surveySummary['legacy_response_count'] }} preserved earlier-questionnaire {{ Str::plural('response', $surveySummary['legacy_response_count']) }} excluded from current averages.</small>@endif
        </section>
    </div>
@endsection
