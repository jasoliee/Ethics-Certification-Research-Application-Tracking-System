@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page application-workspace">
        {{-- Adviser heading and filter controls mirror the submitted-application hierarchy in the reference. --}}
        <header class="dashboard-page-heading">
            <h1>Submitted Applications</h1>
        </header>

        {{-- GET filters remain bookmarkable and cannot escape the authenticated Adviser scope. --}}
        <form class="application-filter-bar application-filter-bar-wide unified-filter-panel" method="GET" action="{{ route('adviser.applications.index') }}">
            <x-dashboard.filter-header description="Refine the applications assigned to you." :reset-href="route('adviser.applications.index')" />
            <div class="unified-filter-fields">
                <div class="application-field application-search-field">
                    <label for="q">Search</label>
                    <span><x-dashboard.icon name="search" size="18" /></span>
                    <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Applicant, ID, title, or program">
                </div>
                <div class="application-field">
                    <label for="adviser-academic-term">Academic Term</label>
                    <select id="adviser-academic-term" name="academic_term_id">
                        <option value="">All terms</option>
                        @foreach ($termOptions as $term)
                            <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="application-field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="application-field">
                    <label for="date_from">Submitted From</label>
                    <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" title="Submitted from">
                </div>
                <div class="application-field">
                    <label for="date_to">Submitted To</label>
                    <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" title="Submitted to">
                </div>
            </div>
        </form>

        {{-- Results stay inside a focusable horizontal-scroll boundary and retain every required column. --}}
        <section class="application-panel">
            <div class="application-panel-heading"><div><h2>Assigned Submissions</h2><p>{{ $applications->total() }} submitted {{ Str::plural('application', $applications->total()) }}</p></div></div>
            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="No assigned submitted applications"
                    title="No submitted applications found"
                    message="New applications will appear here after assigned applicants complete formal submission."
                />
            @else
                <x-dashboard.overflow label="Adviser submitted application records" wide>
                    <table class="dashboard-table application-table adviser-application-table">
                        <thead><tr><th>Application ID</th><th>Applicant</th><th>Student/Employee ID</th><th>Research Title</th><th>Program</th><th>Date Submitted</th><th class="dashboard-table-status">Status</th><th class="dashboard-table-action">Action</th></tr></thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong></td>
                                    <td>{{ $application->applicant->name }}</td>
                                    <td>{{ $application->applicant->institutional_identifier }}</td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('adviser.applications.show', $application)" /></td>
                                    <td>{{ $application->program ?: ($application->applicant->program ?: 'Not specified') }}</td>
                                    <td>{{ $application->submitted_at->format('M j, Y') }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('adviser.applications.show', $application)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="Adviser submitted application pages" />
            @endif
        </section>
    </div>
@endsection
