@extends('layouts.dashboard')

@section('content')
    @php
        $query = array_filter($filters, fn ($value) => filled($value));
        $summaryFilterQuery = collect($query)
            ->except(['summary_filter', 'application_status', 'certificate_status', 'applications_page'])
            ->all();
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
            <h1>Reports</h1>
            <div class="report-heading-actions">
                <a class="dashboard-outline-action" href="{{ route('res.reports.audit.index') }}"><x-dashboard.icon name="clipboard" size="18" /><span>Audit Log</span></a>
                <a class="dashboard-outline-action" href="{{ route('res.reports.export', $query) }}"><x-dashboard.icon name="download" size="18" /><span>Export CSV</span></a>
                <a class="dashboard-primary-action" href="{{ route('res.reports.print', $query) }}" target="_blank" rel="noopener"><x-dashboard.icon name="printer" size="18" /><span>Print Report</span></a>
            </div>
        </header>

        <form class="report-filter-panel unified-filter-panel" method="GET" action="{{ route('res.reports.index') }}">
            <x-dashboard.filter-header description="Refine reporting results across the selected period." :reset-href="route('res.reports.index')" />
            <div class="report-filter-grid unified-filter-fields">
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
        </form>

        <section class="application-panel report-overall-card" aria-labelledby="overall-summary-title">
            <header class="application-panel-heading"><h2 id="overall-summary-title">Overall Summary</h2></header>
            <div class="report-summary-grid">
                @foreach ($summaryCards as $card)
                    @php($cardUrl = route('res.reports.index', array_merge($summaryFilterQuery, ['summary_filter' => $card['key']])).'#filtered-application-list')
                    <x-dashboard.summary-card
                        :label="$card['label']"
                        :count="$report['summary'][$card['key']]"
                        :icon="$card['icon']"
                        :tone="$card['tone']"
                        :href="$cardUrl"
                        :active="($filters['summary_filter'] ?? null) === $card['key']"
                    />
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
            <header class="application-panel-heading"><h2 id="institute-summary-title">Applicant &amp; Application Summary</h2></header>
            <x-dashboard.overflow label="Applicant and application summary by institute">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Institute</th><th class="report-numeric">Unique Applicants</th><th class="report-numeric">Applications Submitted</th><th class="report-numeric">Not Yet Submitted</th><th class="report-numeric">Failed</th><th class="report-numeric">Certificates Claimed</th><th class="report-numeric">Certificates Unclaimed</th></tr></thead>
                    <tbody>@forelse ($report['institute_summary'] as $row)<tr><th scope="row">{{ $row['institute'] }}</th><td class="report-numeric">{{ $row['unique_applicants'] }}</td><td class="report-numeric">{{ $row['submitted'] }}</td><td class="report-numeric">{{ $row['not_submitted'] }}</td><td class="report-numeric">{{ $row['failed'] }}</td><td class="report-numeric">{{ $row['claimed'] }}</td><td class="report-numeric">{{ $row['unclaimed'] }}</td></tr>@empty<tr><td colspan="7">No institute summary records match the selected filters.</td></tr>@endforelse</tbody>
                </table>
            </x-dashboard.overflow>
        </section>

        <section id="filtered-application-list" class="application-panel" aria-labelledby="application-list-title">
            <header class="application-panel-heading"><h2 id="application-list-title">Filtered Application List</h2><span class="status-badge status-badge-green">Double-blind</span></header>
            <x-dashboard.overflow label="Filtered double-blind application list">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Application Code</th><th>Research Title</th><th>Institute</th><th>Review Type</th><th>Status</th><th>Certificate Status</th><th>Submitted</th><th class="report-action">Action</th></tr></thead>
                    <tbody>@forelse ($report['applications'] as $row)@php($application = $row['application'])<tr><td>{{ $application->application_code }}</td><td class="report-title-cell" title="{{ $application->research_title }}">{{ $application->research_title }}</td><td>{{ $application->institution }}</td><td>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $row['certificate_status'] }}</td><td>{{ $application->submitted_at?->format('M j, Y') ?? '—' }}</td><td class="report-action"><a class="dashboard-action-link" href="{{ route('res.applications.show', $application) }}">View</a></td></tr>@empty<tr><td colspan="8">No applications match the selected filters.</td></tr>@endforelse</tbody>
                </table>
            </x-dashboard.overflow>
            <footer class="report-list-navigation">
                <x-dashboard.pagination :paginator="$report['applications']" label="Filtered application pages" />
                <a class="dashboard-outline-action" href="{{ route('res.reports.applications.index', $query) }}">View All</a>
            </footer>
        </section>

        <section class="application-panel" aria-labelledby="applicant-certification-title">
            <header class="application-panel-heading"><h2 id="applicant-certification-title">Applicant Certification</h2></header>
            <x-dashboard.overflow label="Applicant certification records">
                <table class="dashboard-table report-table">
                    <thead><tr><th>Applicant</th><th>Institutional ID</th><th>Institute</th><th>Application Code</th><th>Recipient</th><th>Certificate Status</th><th>Released Date</th><th class="report-numeric">Ageing</th><th class="report-action">Action</th></tr></thead>
                    <tbody>@forelse ($report['applicant_certification'] as $row)<tr><td>{{ $row['applicant']?->name }}</td><td>{{ $row['applicant']?->institutional_identifier }}</td><td>{{ $row['application']->institution }}</td><td>{{ $row['application']->application_code }}</td><td>{{ $row['certificate']->recipient_name }}</td><td>{{ $row['certificate']->status->label() }}</td><td>{{ $row['released_at']?->format('M j, Y g:i A') ?? '—' }}</td><td class="report-numeric">{{ $row['ageing_days'] === null ? '—' : $row['ageing_days'].' days' }}</td><td class="report-action">@if ($row['applicant'])<a class="dashboard-action-link" href="{{ route('res.reports.applicants.show', array_merge($query, ['applicant' => $row['applicant']->id, 'application' => $row['application']->id])) }}">View</a>@endif</td></tr>@empty<tr><td colspan="9">No applicant certification records match the selected filters.</td></tr>@endforelse</tbody>
                </table>
            </x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="adviser-reviewer-summary-title">
            <header class="application-panel-heading"><h2 id="adviser-reviewer-summary-title">Adviser &amp; Reviewer Summary</h2></header>
            <x-dashboard.overflow label="Adviser and reviewer summary per institute"><table class="dashboard-table report-table"><thead><tr><th>Institute</th><th class="report-numeric">Research Advisers</th><th class="report-numeric">Reviewer-enabled Advisers</th></tr></thead><tbody>@forelse ($report['adviser_reviewer_summary'] as $row)<tr><th scope="row">{{ $row['institute'] }}</th><td class="report-numeric">{{ $row['advisers'] }}</td><td class="report-numeric">{{ $row['reviewers'] }}</td></tr>@empty<tr><td colspan="3">No active Adviser accounts match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="reviewer-workload-title">
            <header class="application-panel-heading"><h2 id="reviewer-workload-title">Reviewer Review Workload</h2></header>
            <x-dashboard.overflow label="Reviewer workload report"><table class="dashboard-table report-table"><thead><tr><th>Reviewer</th><th>Institute</th><th class="report-numeric">Expedited</th><th class="report-numeric">Full Board</th><th class="report-numeric">Total Assigned</th><th class="report-numeric">Completed</th><th class="report-numeric">Pending</th><th class="report-numeric">Overdue</th><th class="report-numeric">Remaining Capacity</th></tr></thead><tbody>@forelse ($report['reviewer_workload'] as $row)<tr><td>{{ $row['reviewer']->name }}</td><td>{{ $row['institute'] }}</td><td class="report-numeric">{{ $row['expedited'] }}</td><td class="report-numeric">{{ $row['full_board'] }}</td><td class="report-numeric">{{ $row['total'] }}</td><td class="report-numeric">{{ $row['completed'] }}</td><td class="report-numeric">{{ $row['pending'] }}</td><td class="report-numeric">{{ $row['overdue'] }}</td><td class="report-numeric">{{ $row['remaining'] }}</td></tr>@empty<tr><td colspan="9">No authorized Reviewer workload records match the current active term and selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="adviser-workload-title">
            <header class="application-panel-heading"><h2 id="adviser-workload-title">Adviser Endorsement Workload</h2></header>
            <x-dashboard.overflow label="Adviser endorsement workload report"><table class="dashboard-table report-table"><thead><tr><th>Adviser</th><th>Institute</th><th class="report-numeric">Declared Expected</th><th class="report-numeric">Applicants Received</th><th class="report-numeric">Completed</th><th class="report-numeric">Awaiting</th><th class="report-numeric">Not Yet Received</th></tr></thead><tbody>@forelse ($report['adviser_workload'] as $row)<tr><td>{{ $row['adviser']->name }}</td><td>{{ $row['institute'] }}</td><td class="report-numeric">{{ $row['expected'] }}</td><td class="report-numeric">{{ $row['received'] }}</td><td class="report-numeric">{{ $row['endorsed'] }}</td><td class="report-numeric">{{ $row['awaiting'] }}</td><td class="report-numeric">{{ $row['not_received'] }}</td></tr>@empty<tr><td colspan="7">No Adviser workload records match the selected filters.</td></tr>@endforelse</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel" aria-labelledby="pipeline-title">
            <header class="application-panel-heading"><h2 id="pipeline-title">Workflow Pipeline</h2></header>
            <x-dashboard.overflow label="Workflow pipeline"><table class="dashboard-table report-compact-table"><thead><tr><th>Stage</th><th class="report-numeric">Applications</th></tr></thead><tbody>@foreach ($report['pipeline'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td class="report-numeric">{{ $row['count'] }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow>
        </section>

        <section class="application-panel report-feedback-panel" aria-labelledby="applicant-feedback-report-title">
            <header class="application-panel-heading report-feedback-heading">
                <h2 id="applicant-feedback-report-title">Applicant Feedback Summary</h2>
                <strong class="report-response-counter">{{ $surveySummary['response_count'] }} {{ Str::plural('response', $surveySummary['response_count']) }}</strong>
                <a class="dashboard-outline-action" href="{{ route('res.reports.survey.print', $query) }}" target="_blank" rel="noopener"><x-dashboard.icon name="printer" size="16" />Print Survey</a>
            </header>
            @if ($surveySummary['response_count'] > 0)
                <div class="report-feedback-metrics"><article><span>Overall average</span><strong>{{ number_format($surveySummary['overall_average'], 2) }} / 5</strong></article>@foreach ($surveySummary['sections'] as $section)<article><span>{{ $section['title'] }}</span><strong>{{ number_format($section['average'], 2) }} / 5</strong></article>@endforeach</div>
                @foreach ($surveySummary['sections'] as $section)
                    <h3 class="report-feedback-section-title">{{ $section['title'] }}</h3>
                    <x-dashboard.overflow label="{{ $section['title'] }} aggregate ratings"><table class="dashboard-table report-feedback-table"><colgroup><col><col class="report-feedback-count-column"><col class="report-feedback-average-column"></colgroup><thead><tr><th>Evaluation Statement</th><th class="report-numeric">Responses</th><th class="report-numeric">Average</th></tr></thead><tbody>@foreach ($section['questions'] as $question)<tr><td>{{ $question['label'] }}</td><td class="report-numeric">{{ $question['response_count'] }}</td><td class="report-numeric">{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td></tr>@endforeach</tbody></table></x-dashboard.overflow>
                @endforeach
            @else
                <p class="report-inline-empty">No current-questionnaire responses match the selected filters.</p>
            @endif
        </section>
    </div>
@endsection
