@extends('layouts.dashboard')

@section('content')
    @php
        $semesterOptions = $termOptions->pluck('semester')->unique()->values();
        $academicYearOptions = $termOptions->pluck('academic_year')->unique()->values();
    @endphp

    <div class="dashboard-page application-workspace">
        <header class="dashboard-page-heading">
            <h1>Endorsed Applications</h1>
            <p>Review every application that has entered the RES workflow through Adviser endorsement.</p>
        </header>

        <form class="application-filter-bar application-filter-bar-res" method="GET" action="{{ route('res.applications.index') }}">
            <div class="application-field application-search-field">
                <label class="sr-only" for="res-q">Search applications</label>
                <span><x-dashboard.icon name="search" size="18" /></span>
                <input id="res-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Applicant, adviser, ID, or title">
            </div>
            <div class="application-field">
                <label class="sr-only" for="res-status">Status</label>
                <select id="res-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label class="sr-only" for="res-semester">Semester</label>
                <select id="res-semester" name="semester">
                    <option value="">All semesters</option>
                    @foreach ($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected(($filters['semester'] ?? '') === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label class="sr-only" for="res-academic-year">Academic Year</label>
                <select id="res-academic-year" name="academic_year">
                    <option value="">All academic years</option>
                    @foreach ($academicYearOptions as $academicYear)
                        <option value="{{ $academicYear }}" @selected(($filters['academic_year'] ?? '') === $academicYear)>{{ $academicYear }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label class="sr-only" for="res-date-from">Endorsed from</label>
                <input id="res-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" title="Endorsed from">
            </div>
            <div class="application-field">
                <label class="sr-only" for="res-date-to">Endorsed to</label>
                <input id="res-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" title="Endorsed to">
            </div>
            <button class="dashboard-primary-action" type="submit">Apply Filters</button>
            <a class="dashboard-outline-action" href="{{ route('res.applications.index') }}">Clear</a>
        </form>

        <section class="application-panel">
            <div class="application-panel-heading">
                <div>
                    <h2>Applications in RES Flow</h2>
                    <p>{{ $applications->total() }} endorsed {{ Str::plural('application', $applications->total()) }}</p>
                </div>
            </div>

            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="No endorsed applications"
                    title="No endorsed applications found"
                    message="Applications will appear here after a Research Adviser endorses them for RES screening."
                />
            @else
                <x-dashboard.overflow label="RES endorsed application records" wide>
                    <table class="dashboard-table application-table res-application-table">
                        <thead>
                            <tr>
                                <th>Application ID</th>
                                <th>Applicant</th>
                                <th>Student/Employee ID</th>
                                <th>Research Title</th>
                                <th>Research Adviser</th>
                                <th>Review Type</th>
                                <th>Received</th>
                                <th class="dashboard-table-status">Status</th>
                                <th class="dashboard-table-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                @php($receivedAt = $application->latestEndorsement?->endorsed_at ?? $application->status_updated_at ?? $application->submitted_at)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong></td>
                                    <td>{{ $application->applicant?->name ?? 'Archived applicant' }}</td>
                                    <td>{{ $application->applicant?->institutional_identifier ?? 'Not available' }}</td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('res.applications.show', $application)" /></td>
                                    <td>{{ $application->adviser?->name ?? 'Archived adviser' }}</td>
                                    <td>{{ $application->review_type ? Str::headline($application->review_type) : 'Pending classification' }}</td>
                                    <td>{{ $receivedAt?->format('M j, Y') ?? 'Not recorded' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('res.applications.show', $application)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="RES endorsed application pages" />
            @endif
        </section>
    </div>
@endsection
