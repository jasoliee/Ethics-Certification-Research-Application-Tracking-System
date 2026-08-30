@extends('layouts.dashboard')

@section('content')
    @php
        $hasReviewerFilters = collect($filters)
            ->only(['reviewer_q', 'reviewer_institute', 'academic_term_id'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
        $hasAdviserFilters = collect($filters)
            ->only(['adviser_q', 'adviser_institute', 'academic_term_id'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    @endphp

    <div class="dashboard-page review-monitoring-page">
        <header class="dashboard-page-heading dashboard-page-heading-row review-monitoring-heading">
            <div>
                <h1>Review Monitoring</h1>
            </div>
            <a class="dashboard-outline-action dashboard-icon-text-action" href="{{ request()->fullUrl() }}">
                <x-dashboard.icon name="refresh" size="17" />
                <span>Refresh</span>
            </a>
        </header>

        <div class="dashboard-summary-grid dashboard-summary-grid-five" aria-label="Review operations summary">
            <x-dashboard.summary-card
                label="Active Applications"
                :count="$metrics['active_applications']"
                icon="file-search"
                tone="blue"
                :href="route('res.review-monitoring.index').'#review-monitoring-capacity'"
            />
            <x-dashboard.summary-card
                label="Active Assignments"
                :count="$metrics['active_assignments']"
                icon="users"
                tone="violet"
                :href="route('res.review-monitoring.index').'#review-monitoring-capacity'"
            />
            <x-dashboard.summary-card
                label="Assignment Completion"
                :count="$metrics['completion_rate'].'%'"
                icon="check"
                tone="green"
                :href="route('res.review-monitoring.index').'#review-monitoring-capacity'"
            />
            <x-dashboard.summary-card
                label="Overdue Assignments"
                :count="$metrics['overdue_assignments']"
                icon="clock"
                tone="orange"
                :href="route('res.review-monitoring.index').'#review-monitoring-capacity'"
            />
            <x-dashboard.summary-card
                label="Full Board Conflicts"
                :count="$metrics['conflicted_applications']"
                icon="alert-triangle"
                tone="red"
                :href="route('res.review-monitoring.index').'#review-monitoring-conflicts'"
            />
        </div>

        @if ($conflicts->isNotEmpty())
            <section
                class="review-monitoring-conflicts"
                id="review-monitoring-conflicts"
                aria-labelledby="review-monitoring-conflict-title"
                role="alert"
            >
                <header class="review-monitoring-section-heading">
                    <span class="review-monitoring-heading-icon" aria-hidden="true">
                        <x-dashboard.icon name="alert-triangle" size="22" />
                    </span>
                    <div>
                        <h2 id="review-monitoring-conflict-title">Full Board decision conflicts require REU attention</h2>
                        <p>Submitted outcomes disagree. Reviewer identities remain anonymous here; inspect the authorized read-only workspace before any release action.</p>
                    </div>
                    <x-dashboard.status-badge :label="$metrics['conflicted_applications'].' unresolved'" tone="red" />
                </header>

                <div class="review-monitoring-conflict-list">
                    @foreach ($conflicts as $application)
                        @php
                            $cycleAssignments = $application->reviewerAssignments
                                ->when(
                                    $application->review_consensus_cycle !== null,
                                    fn ($assignments) => $assignments->where('review_cycle', $application->review_consensus_cycle),
                                )
                                ->values();
                            if ($cycleAssignments->isEmpty()) {
                                $cycleAssignments = $application->reviewerAssignments->values();
                            }
                        @endphp
                        <article class="review-monitoring-conflict-card">
                            <div class="review-monitoring-conflict-copy">
                                <span class="review-monitoring-code">{{ $application->application_code }}</span>
                                <h3>{{ $application->research_title }}</h3>
                                <small>
                                    {{ $application->review_conflicted_at?->format('M j, Y g:i A') ?? 'Conflict recorded' }}
                                    @if ($application->review_consensus_cycle !== null)
                                        &middot; Cycle {{ $application->review_consensus_cycle }}
                                    @endif
                                </small>
                            </div>

                            <div class="review-monitoring-decisions" aria-label="Anonymous submitted decisions">
                                @foreach ($cycleAssignments as $index => $assignment)
                                    @php
                                        $decision = $assignment->reviewSubmission?->currentVersion?->decision
                                            ?? $assignment->reviewSubmission?->decision;
                                    @endphp
                                    <div>
                                        <span>Reviewer {{ $index + 1 }}</span>
                                        <x-dashboard.status-badge
                                            :label="$decision?->label() ?? 'Awaiting Submission'"
                                            :tone="$decision?->tone() ?? 'neutral'"
                                        />
                                    </div>
                                @endforeach
                            </div>

                            <div class="review-monitoring-row-actions">
                                <a class="dashboard-outline-action" href="{{ route('res.applications.show', $application) }}">Application</a>
                                <a class="dashboard-primary-action" href="{{ route('res.certificates.workspace', $application) }}">Read-only Workspace</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @else
            <section class="review-monitoring-no-conflicts" id="review-monitoring-conflicts" aria-label="Full Board consensus status">
                <x-dashboard.icon name="check" size="21" />
                <div>
                    <strong>No unresolved Full Board conflicts</strong>
                    <span>Current submitted reviewer sets do not contain a recorded disagreement.</span>
                </div>
            </section>
        @endif

        <section class="application-panel review-monitoring-panel" id="review-monitoring-capacity" aria-labelledby="review-monitoring-capacity-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-capacity-title">Reviewer-enabled Adviser workload</h2>
                </div>
                <span>{{ $reviewerWorkloads->total() }} enabled</span>
            </div>

            <form class="review-monitoring-adviser-filters unified-filter-panel" method="GET" action="{{ route('res.review-monitoring.index') }}">
                <x-dashboard.filter-header description="Refine reviewer workload results." :reset-href="route('res.review-monitoring.index').'#review-monitoring-capacity'" />
                <div class="unified-filter-fields">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-reviewer-q">Search</label>
                    <span><x-dashboard.icon name="search" size="18" /></span>
                    <input id="monitoring-reviewer-q" name="reviewer_q" value="{{ $filters['reviewer_q'] ?? '' }}" placeholder="Reviewer/Adviser name">
                </div>
                <div class="application-field">
                    <label for="monitoring-reviewer-term">Academic Term</label>
                    <select id="monitoring-reviewer-term" name="academic_term_id">
                        <option value="">All</option>
                        @foreach ($termOptions as $term)
                            <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="application-field">
                    <label for="monitoring-reviewer-institute">Institute</label>
                    <select id="monitoring-reviewer-institute" name="reviewer_institute">
                        <option value="">All institutes</option>
                        @foreach ($reviewerInstitutes as $institute)
                            <option value="{{ $institute }}" @selected(($filters['reviewer_institute'] ?? '') === $institute)>{{ $institute }}</option>
                        @endforeach
                    </select>
                </div>
                </div>
            </form>

            @if ($reviewerWorkloads->isEmpty())
                <div class="review-monitoring-empty is-compact">
                    <x-dashboard.icon name="users" size="32" />
                    <h3>No reviewer-enabled Advisers</h3>
                    <p>Capacity records appear after an active Adviser receives reviewer capability.</p>
                </div>
            @else
                <x-dashboard.overflow class="review-monitoring-adviser-table-region" label="Reviewer-enabled Adviser workload records" wide>
                    <table class="dashboard-table review-monitoring-adviser-table review-monitoring-reviewer-table">
                        <thead><tr><th>Reviewer/Adviser</th><th>Current Number of Applications</th><th>Successfully Completed Applications</th><th>Remaining Applications to Be Reviewed</th><th>Action</th></tr></thead>
                        <tbody>
                            @foreach ($reviewerWorkloads as $reviewer)
                                @php
                                    $activeLoad = (int) $reviewer->active_assignment_count;
                                    $completed = (int) $reviewer->completed_application_count;
                                    $remaining = max(0, (int) ($reviewer->reviewer_capacity ?? 0) - $activeLoad);
                                @endphp
                                <tr>
                                    <td><strong>{{ $reviewer->name }}</strong><small>{{ $reviewer->institution ?: 'Institute not recorded' }}</small></td>
                                    <td><strong class="review-monitoring-stat-value">{{ $activeLoad }}</strong></td>
                                    <td><strong class="review-monitoring-stat-value tone-green">{{ $completed }}</strong></td>
                                    <td><strong class="review-monitoring-stat-value tone-violet">{{ $remaining }}</strong></td>
                                    <td><a class="identity-view-link" href="{{ route('res.review-monitoring.reviewers.assignments', ['reviewer' => $reviewer, 'academic_term_id' => $filters['academic_term_id'] ?? null]) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$reviewerWorkloads" label="Reviewer workload pages" />
            @endif
        </section>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-advisers" aria-labelledby="review-monitoring-advisers-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-advisers-title">Adviser endorsement workload</h2>
                </div>
                <span>{{ $adviserWorkloads->total() }} Adviser{{ $adviserWorkloads->total() === 1 ? '' : 's' }}</span>
            </div>

            <form class="review-monitoring-adviser-filters unified-filter-panel" method="GET" action="{{ route('res.review-monitoring.index') }}">
                <x-dashboard.filter-header description="Refine Adviser workload results." :reset-href="route('res.review-monitoring.index').'#review-monitoring-advisers'" />
                <div class="unified-filter-fields">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-adviser-q">Search</label>
                    <span><x-dashboard.icon name="search" size="18" /></span>
                    <input
                        id="monitoring-adviser-q"
                        name="adviser_q"
                        value="{{ $filters['adviser_q'] ?? '' }}"
                        placeholder="Adviser name"
                    >
                </div>
                <div class="application-field">
                    <label for="monitoring-adviser-term">Academic Term</label>
                    <select id="monitoring-adviser-term" name="academic_term_id">
                        <option value="">All</option>
                        @foreach ($termOptions as $term)
                            <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="application-field">
                    <label for="monitoring-adviser-institute">Institute</label>
                    <select id="monitoring-adviser-institute" name="adviser_institute">
                        <option value="">All institutes</option>
                        @foreach ($adviserInstitutes as $institute)
                            <option value="{{ $institute }}" @selected(($filters['adviser_institute'] ?? '') === $institute)>{{ $institute }}</option>
                        @endforeach
                    </select>
                </div>

                </div>
            </form>

            @if ($adviserWorkloads->isEmpty())
                <div class="review-monitoring-empty is-compact">
                    <x-dashboard.icon name="user-check" size="32" />
                    <h3>{{ $hasAdviserFilters ? 'No Advisers match these filters' : 'No authorized Advisers found' }}</h3>
                    <p>{{ $hasAdviserFilters ? 'Adjust the Adviser search or workload filters and try again.' : 'Active Adviser accounts appear here when their endorsement workload is available.' }}</p>
                </div>
            @else
                <x-dashboard.overflow class="review-monitoring-adviser-table-region" label="Adviser endorsement workload records" wide>
                    <table class="dashboard-table review-monitoring-adviser-table">
                        <thead>
                            <tr>
                                <th>Authorized Adviser</th>
                                <th>Declared Expected</th>
                                <th>Completed Endorsements</th>
                                <th>Awaiting Endorsement</th>
                                <th>Not Yet Received</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adviserWorkloads as $adviser)
                                @php
                                    $statistics = $adviser->endorsement_statistics ?? [
                                        'declared' => 0,
                                        'endorsed' => 0,
                                        'awaiting' => 0,
                                        'remaining' => 0,
                                        'not_received' => 0,
                                    ];
                                    $declared = (int) $statistics['declared'];
                                    $endorsed = (int) $statistics['endorsed'];
                                    $awaiting = (int) $statistics['awaiting'];
                                    $completion = $declared > 0
                                        ? min(100, (int) round(($endorsed / $declared) * 100))
                                        : 0;
                                @endphp
                                <tr
                                    data-adviser-workload-row="{{ $adviser->id }}"
                                    data-declared="{{ $declared }}"
                                    data-endorsed="{{ $endorsed }}"
                                    data-awaiting="{{ $awaiting }}"
                                    data-remaining="{{ $statistics['remaining'] }}"
                                    data-not-received="{{ $statistics['not_received'] }}"
                                >
                                    <td>
                                        <strong>{{ $adviser->name }}</strong>
                                        <small>{{ $adviser->position_title ?: 'Research Adviser' }}{{ $adviser->institution ? ' - '.$adviser->institution : '' }}</small>
                                    </td>
                                    <td><strong class="review-monitoring-stat-value">{{ $declared }}</strong></td>
                                    <td>
                                        <strong class="review-monitoring-stat-value tone-green">{{ $endorsed }}</strong>
                                        <progress class="review-monitoring-progress" max="100" value="{{ $completion }}">{{ $completion }}%</progress>
                                        <small>{{ $completion }}% of declared expectation</small>
                                    </td>
                                    <td>
                                        <x-dashboard.status-badge
                                            :label="$awaiting.' received'"
                                            :tone="$awaiting > 0 ? 'orange' : 'neutral'"
                                        />
                                    </td>
                                    <td>
                                        <x-dashboard.status-badge
                                            :label="$statistics['not_received'].' not received'"
                                            :tone="$statistics['not_received'] > 0 ? 'blue' : 'success'"
                                        />
                                    </td>
                                    <td>
                                        <a class="identity-view-link" href="{{ route('res.review-monitoring.advisers.applications', ['adviser' => $adviser, 'academic_term_id' => $filters['academic_term_id'] ?? null]) }}">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$adviserWorkloads" label="Adviser endorsement workload pages" />
            @endif
        </section>
    </div>
@endsection
