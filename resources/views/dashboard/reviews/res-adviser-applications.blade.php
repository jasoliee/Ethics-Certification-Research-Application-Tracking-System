@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page review-monitoring-page">
        <header class="dashboard-page-heading dashboard-page-heading-row">
            <div><h1>Endorsed Applications</h1><p>{{ $adviser->name }}</p></div>
            <a class="dashboard-outline-action" href="{{ route('res.review-monitoring.index').'#review-monitoring-advisers' }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to Monitoring</span></a>
        </header>

        <form class="application-panel monitoring-drilldown-filters" method="GET">
            <div class="application-field">
                <label for="adviser-application-term">Academic Term</label>
                <select id="adviser-application-term" name="academic_term_id">
                    <option value="">All</option>
                    @foreach ($termOptions as $term)
                        <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="dashboard-primary-action" type="submit">Apply Filter</button>
        </form>

        <section class="application-panel review-monitoring-panel" aria-labelledby="endorsed-application-table-title">
            <header class="application-panel-heading"><div><h2 id="endorsed-application-table-title">Applications Endorsed by This Adviser</h2></div><span>{{ $applications->total() }} total</span></header>
            @if ($applications->isEmpty())
                <x-dashboard.empty-state image="no-applications" alt="No endorsed applications" title="No endorsed applications found" message="No endorsed applications match the selected Academic Term." />
            @else
                <x-dashboard.overflow label="Endorsed application records" wide>
                    <table class="dashboard-table monitoring-drilldown-table">
                        <thead><tr><th>Application</th><th>Academic Term</th><th>Status</th><th>Last Updated</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong><small>{{ $application->research_title }}</small></td>
                                    <td>{{ $application->academicTerm?->label() ?? 'Unassigned term' }}</td>
                                    <td><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td>{{ $application->status_updated_at?->format('M j, Y') ?? 'Not recorded' }}</td>
                                    <td><a class="dashboard-outline-action" href="{{ route('res.applications.show', $application) }}">View Application</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="Endorsed application pages" />
            @endif
        </section>
    </div>
@endsection
