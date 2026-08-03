@extends('layouts.dashboard')

@section('content')
    @php
        $hasFilters = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
    @endphp

    <div class="dashboard-page application-workspace res-workflow-page">
        <header class="dashboard-page-heading">
            <h1>Applications Queue</h1>
            <p>View adviser-endorsed submissions, track current status, and open records requiring RES action.</p>
        </header>

        {{-- Queue filters mirror the approved screening fields and remain outside the table overflow region. --}}
        <form class="application-filter-bar application-filter-bar-res" method="GET" action="{{ route('res.applications.index') }}">
            <div class="application-field application-search-field res-filter-search">
                <label for="res-q">Search</label>
                <span><x-dashboard.icon name="search" size="18" /></span>
                <input id="res-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Code, title, applicant, adviser, institute, or program">
            </div>

            <div class="application-field res-filter-status">
                <label for="res-status">Status</label>
                <select id="res-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="application-field res-filter-applicant-type">
                <label for="res-applicant-type">Applicant Category</label>
                <select id="res-applicant-type" name="applicant_type">
                    <option value="">All categories</option>
                    @foreach ($applicantTypes as $applicantType)
                        <option value="{{ $applicantType->value }}" @selected(($filters['applicant_type'] ?? '') === $applicantType->value)>{{ Str::headline($applicantType->value) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="application-field res-filter-research-type">
                <label for="res-research-type">Research Type</label>
                <select id="res-research-type" name="research_type">
                    <option value="">All types</option>
                    @foreach ($researchTypes as $researchType)
                        <option value="{{ $researchType->value }}" @selected(($filters['research_type'] ?? '') === $researchType->value)>{{ $researchType->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="application-field res-filter-review-type">
                <label for="res-review-type">Review Type</label>
                <select id="res-review-type" name="review_type">
                    <option value="">All review types</option>
                    @foreach ($reviewTypes as $reviewType)
                        <option value="{{ $reviewType->value }}" @selected(($filters['review_type'] ?? '') === $reviewType->value)>{{ $reviewType->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="application-field res-filter-affiliation">
                <label for="res-affiliation">Institute / Program</label>
                <select id="res-affiliation" name="affiliation">
                    <option value="">All institutes / programs</option>
                    @foreach ($affiliations as $affiliation)
                        <option value="{{ $affiliation }}" @selected(($filters['affiliation'] ?? '') === $affiliation)>{{ $affiliation }}</option>
                    @endforeach
                </select>
            </div>

            <div class="application-field res-filter-date-range">
                <span class="application-field-label">Endorsement Date Range</span>
                <div class="res-date-range-inputs">
                    <label class="sr-only" for="res-date-from">Endorsed from</label>
                    <input id="res-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" title="Endorsed from">
                    <span aria-hidden="true">to</span>
                    <label class="sr-only" for="res-date-to">Endorsed to</label>
                    <input id="res-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" title="Endorsed to">
                </div>
            </div>

            <div class="res-application-filter-actions">
                <button class="dashboard-primary-action res-filter-apply" type="submit">Apply Filters</button>
                <a class="dashboard-outline-action res-filter-clear" href="{{ route('res.applications.index') }}">Reset Filters</a>
            </div>
        </form>

        <section class="application-panel res-application-panel">
            <div class="application-panel-heading res-queue-heading">
                <div>
                    <h2>Showing {{ $applications->firstItem() ?? 0 }} to {{ $applications->lastItem() ?? 0 }} of {{ $applications->total() }} applications{{ $hasFilters ? ' (filtered)' : '' }}</h2>
                </div>
                <a class="dashboard-outline-action dashboard-icon-text-action" href="{{ request()->fullUrl() }}">
                    <x-dashboard.icon name="refresh" size="17" />
                    <span>Refresh</span>
                </a>
            </div>

            @if ($applications->isEmpty())
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="No applications found"
                    title="No applications found"
                    :message="$hasFilters
                        ? 'No records match the current search or filter criteria. Try adjusting your filters or search terms.'
                        : 'Applications will appear here after a Research Adviser endorses them for RES screening.'"
                />
            @else
                <x-dashboard.overflow class="res-application-scroll" label="RES application queue records" wide>
                    <table class="dashboard-table application-table res-application-table">
                        <thead>
                            <tr>
                                <th>Application Code</th>
                                <th>Research Title</th>
                                <th>Applicant Category</th>
                                <th>Research Type</th>
                                <th>Adviser</th>
                                <th>Institute / Program</th>
                                <th class="dashboard-table-status">Current Status</th>
                                <th class="dashboard-table-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong></td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('res.applications.show', $application)" /></td>
                                    <td>{{ Str::headline($application->applicant_type) }}</td>
                                    <td>{{ $application->research_type?->label() ?? 'Not specified' }}</td>
                                    <td>{{ $application->adviser?->name ?? 'Archived adviser' }}</td>
                                    <td>
                                        <strong>{{ $application->institution ?: 'Not specified' }}</strong>
                                        @if ($application->program)<small>{{ $application->program }}</small>@endif
                                    </td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('res.applications.show', $application)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="RES application queue pages" />
            @endif
        </section>
    </div>
@endsection
