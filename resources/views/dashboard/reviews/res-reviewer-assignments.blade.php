@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page review-monitoring-page">
        <header class="dashboard-page-heading dashboard-page-heading-row">
            <div><h1>Reviewer Assignments</h1><p>{{ $reviewer->name }}</p></div>
            <a class="dashboard-outline-action" href="{{ route('res.review-monitoring.index').'#review-monitoring-capacity' }}"><x-dashboard.icon name="arrow-left" size="17" /><span>Back to Monitoring</span></a>
        </header>

        <form class="application-panel monitoring-drilldown-filters" method="GET">
            <div class="application-field">
                <label for="reviewer-assignment-term">Academic Term</label>
                <select id="reviewer-assignment-term" name="academic_term_id">
                    <option value="">All</option>
                    @foreach ($termOptions as $term)
                        <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="dashboard-primary-action" type="submit">Apply Filter</button>
        </form>

        <section class="application-panel review-monitoring-panel" aria-labelledby="reviewer-assignment-table-title">
            <header class="application-panel-heading"><div><h2 id="reviewer-assignment-table-title">All Assignments</h2></div><span>{{ $assignments->total() }} total</span></header>
            @if ($assignments->isEmpty())
                <x-dashboard.empty-state image="no-applications" alt="No reviewer assignments" title="No assignments found" message="No assignments match the selected Academic Term." />
            @else
                <x-dashboard.overflow label="Reviewer assignment records" wide>
                    <table class="dashboard-table monitoring-drilldown-table">
                        <thead><tr><th>Application</th><th>Academic Term</th><th>Review Type</th><th>Assignment Status</th><th>Completion</th><th>Assigned</th></tr></thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                @php($application = $assignment->researchApplication)
                                <tr>
                                    <td><strong>{{ $application?->application_code }}</strong><small>{{ $application?->research_title }}</small></td>
                                    <td>{{ $application?->academicTerm?->label() ?? 'Unassigned term' }}</td>
                                    <td>{{ filled($application?->review_type) ? Str::headline($application->review_type) : Str::headline($assignment->review_type) }}</td>
                                    <td><x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" /></td>
                                    <td><x-dashboard.status-badge :label="$assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted ? 'Completed' : 'Not Completed'" :tone="$assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted ? 'success' : 'orange'" /></td>
                                    <td>{{ $assignment->assigned_at?->format('M j, Y') ?? 'Not recorded' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$assignments" label="Reviewer assignment pages" />
            @endif
        </section>
    </div>
@endsection
