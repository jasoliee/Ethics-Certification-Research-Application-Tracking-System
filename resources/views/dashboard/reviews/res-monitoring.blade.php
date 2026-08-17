@extends('layouts.dashboard')

@section('content')
    @php
        $hasReviewerFilters = collect($filters)
            ->only(['q', 'review_type', 'assignment_status', 'deadline', 'consensus'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
        $hasAdviserFilters = collect($filters)
            ->only(['adviser_q', 'adviser_department', 'adviser_workload'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    @endphp

    <div class="dashboard-page review-monitoring-page">
        <header class="dashboard-page-heading dashboard-page-heading-row review-monitoring-heading">
            <div>
                <h1>Review Monitoring</h1>
                <p>Track anonymous assignment progress, deadlines, Full Board agreement, and reviewer-enabled Adviser capacity.</p>
            </div>
            <a class="dashboard-outline-action dashboard-icon-text-action" href="{{ request()->fullUrl() }}">
                <x-dashboard.icon name="refresh" size="17" />
                <span>Refresh</span>
            </a>
        </header>

        <nav class="review-monitoring-section-links" aria-label="Review monitoring sections">
            <a href="#review-monitoring-assignments">
                <x-dashboard.icon name="file-search" size="18" />
                <span>Reviewer Assignments</span>
            </a>
            <a href="#review-monitoring-advisers">
                <x-dashboard.icon name="user-check" size="18" />
                <span>Adviser Endorsements</span>
            </a>
        </nav>

        <div class="dashboard-summary-grid dashboard-summary-grid-five" aria-label="Review operations summary">
            <x-dashboard.summary-card
                label="Active Applications"
                :count="$metrics['active_applications']"
                icon="file-search"
                tone="blue"
                :href="route('res.review-monitoring.index').'#review-monitoring-assignments'"
            />
            <x-dashboard.summary-card
                label="Active Assignments"
                :count="$metrics['active_assignments']"
                icon="users"
                tone="violet"
                :href="route('res.review-monitoring.index').'#review-monitoring-assignments'"
            />
            <x-dashboard.summary-card
                label="Assignment Completion"
                :count="$metrics['completion_rate'].'%'"
                icon="check"
                tone="green"
                :href="route('res.review-monitoring.index').'#review-monitoring-assignments'"
            />
            <x-dashboard.summary-card
                label="Overdue Assignments"
                :count="$metrics['overdue_assignments']"
                icon="clock"
                tone="orange"
                :href="route('res.review-monitoring.index', ['deadline' => 'overdue']).'#review-monitoring-assignments'"
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
                        <h2 id="review-monitoring-conflict-title">Full Board decision conflicts require RES attention</h2>
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

        <section class="application-panel review-monitoring-panel" id="review-monitoring-assignments" aria-labelledby="review-monitoring-table-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-table-title">Assignment progress and deadlines</h2>
                    <p>Applicant identity and confidential reviewer comments are intentionally excluded from this operational view.</p>
                </div>
                <span>{{ $applications->total() }} application{{ $applications->total() === 1 ? '' : 's' }}</span>
            </div>

            <form class="review-monitoring-filters" method="GET" action="{{ route('res.review-monitoring.index') }}">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-q">Search</label>
                    <span><x-dashboard.icon name="search" size="18" /></span>
                    <input
                        id="monitoring-q"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Application code or research title"
                    >
                </div>

                <div class="application-field">
                    <label for="monitoring-review-type">Review Type</label>
                    <select id="monitoring-review-type" name="review_type">
                        <option value="">All review types</option>
                        @foreach ($reviewTypes as $reviewType)
                            <option value="{{ $reviewType->value }}" @selected(($filters['review_type'] ?? '') === $reviewType->value)>{{ $reviewType->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-assignment-status">Assignment Status</label>
                    <select id="monitoring-assignment-status" name="assignment_status">
                        <option value="">All assignment statuses</option>
                        @foreach ($assignmentStatuses as $assignmentStatus)
                            <option value="{{ $assignmentStatus->value }}" @selected(($filters['assignment_status'] ?? '') === $assignmentStatus->value)>{{ $assignmentStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-deadline">Deadline</label>
                    <select id="monitoring-deadline" name="deadline">
                        <option value="">All deadlines</option>
                        <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Overdue</option>
                        <option value="due_soon" @selected(($filters['deadline'] ?? '') === 'due_soon')>Due within 3 days</option>
                        <option value="on_track" @selected(($filters['deadline'] ?? '') === 'on_track')>More than 3 days</option>
                        <option value="no_deadline" @selected(($filters['deadline'] ?? '') === 'no_deadline')>No deadline</option>
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-consensus">Consensus</label>
                    <select id="monitoring-consensus" name="consensus">
                        <option value="">All consensus states</option>
                        @foreach ($consensusStatuses as $consensusStatus)
                            <option value="{{ $consensusStatus->value }}" @selected(($filters['consensus'] ?? '') === $consensusStatus->value)>{{ $consensusStatus->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="review-monitoring-filter-actions">
                    <button class="dashboard-primary-action" type="submit">
                        <x-dashboard.icon name="search" size="17" />
                        <span>Apply Filters</span>
                    </button>
                    <a class="dashboard-outline-action" href="{{ route('res.review-monitoring.index') }}">Reset</a>
                </div>
            </form>

            @if ($applications->isEmpty())
                <div class="review-monitoring-empty">
                    <x-dashboard.icon name="file-search" size="34" />
                    <h3>{{ $hasReviewerFilters ? 'No assignments match these filters' : 'No reviewer assignments yet' }}</h3>
                    <p>{{ $hasReviewerFilters ? 'Adjust the search or filters and try again.' : 'Applications appear here after RES assigns an eligible reviewer set.' }}</p>
                </div>
            @else
                <x-dashboard.overflow class="review-monitoring-table-region" label="Review assignment progress records" wide>
                    <table class="dashboard-table review-monitoring-table">
                        <thead>
                            <tr>
                                <th>Application</th>
                                <th>Review</th>
                                <th>Anonymous Assignment Progress</th>
                                <th>Next Deadline</th>
                                <th>Consensus</th>
                                <th class="dashboard-table-action">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                @php
                                    $assignments = $application->reviewerAssignments->values();
                                    $reviewType = \App\Enums\ReviewType::tryFrom((string) $application->review_type);
                                    $required = max(1, $reviewType?->reviewerCount() ?? $assignments->count());
                                    $completed = $assignments->filter(fn ($assignment) => $assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted)->count();
                                    $completion = min(100, (int) round(($completed / $required) * 100));
                                    $openAssignments = $assignments->reject(fn ($assignment) => $assignment->assignment_status === \App\Enums\ReviewerAssignmentStatus::DecisionSubmitted);
                                    $nextDeadline = $openAssignments
                                        ->filter(fn ($assignment) => $assignment->review_deadline_at !== null)
                                        ->sortBy('review_deadline_at')
                                        ->first()?->review_deadline_at;
                                    $deadlineTone = $nextDeadline?->isPast()
                                        ? 'red'
                                        : ($nextDeadline?->lte(now()->addDays(3)) ? 'orange' : 'blue');
                                @endphp
                                <tr class="{{ $application->review_consensus_status === \App\Enums\ReviewConsensusStatus::Conflicted ? 'is-conflicted' : '' }}">
                                    <td>
                                        <strong class="review-monitoring-code">{{ $application->application_code }}</strong>
                                        <a class="review-monitoring-title" href="{{ route('res.applications.show', $application) }}">{{ $application->research_title }}</a>
                                    </td>
                                    <td>
                                        <strong>{{ $reviewType?->label() ?? 'Review' }}</strong>
                                        <small>{{ $assignments->first()?->review_type === 'revision_review' ? 'Revision cycle '.($assignments->first()?->review_cycle ?? 0) : 'Initial review' }}</small>
                                    </td>
                                    <td>
                                        <div class="review-monitoring-progress-copy">
                                            <strong>{{ $completed }} of {{ $required }} submitted</strong>
                                            <span>{{ $completion }}%</span>
                                        </div>
                                        <progress class="review-monitoring-progress" max="100" value="{{ $completion }}">{{ $completion }}%</progress>
                                        <div class="review-monitoring-assignment-chips" aria-label="Anonymous reviewer assignment statuses">
                                            @foreach ($assignments as $index => $assignment)
                                                <span>
                                                    Reviewer {{ $index + 1 }}
                                                    <x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" />
                                                </span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        @if ($openAssignments->isEmpty())
                                            <x-dashboard.status-badge label="Completed" tone="success" />
                                        @elseif ($nextDeadline)
                                            <x-dashboard.status-badge
                                                :label="($nextDeadline->isPast() ? 'Overdue · ' : '').$nextDeadline->format('M j, Y g:i A')"
                                                :tone="$deadlineTone"
                                            />
                                        @else
                                            <x-dashboard.status-badge label="No deadline" tone="neutral" />
                                        @endif
                                    </td>
                                    <td>
                                        <x-dashboard.status-badge
                                            :label="$application->review_consensus_status?->label() ?? 'Not evaluated'"
                                            :tone="$application->review_consensus_status?->tone() ?? 'neutral'"
                                        />
                                    </td>
                                    <td class="dashboard-table-action">
                                        <div class="review-monitoring-table-actions">
                                            <x-dashboard.action-link :href="route('res.applications.show', $application)">View</x-dashboard.action-link>
                                            <x-dashboard.action-link :href="route('res.applications.reviewers.index', $application)">Reviewer Set</x-dashboard.action-link>
                                            <x-dashboard.action-link :href="route('res.certificates.workspace', $application)">Workspace</x-dashboard.action-link>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="Review monitoring application pages" />
            @endif
        </section>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-capacity" aria-labelledby="review-monitoring-capacity-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-capacity-title">Reviewer-enabled Adviser workload</h2>
                    <p>Active load and declared capacity only. Application decisions and confidential comments are not shown.</p>
                </div>
                <span>{{ $reviewerWorkloads->total() }} enabled</span>
            </div>

            @if ($reviewerWorkloads->isEmpty())
                <div class="review-monitoring-empty is-compact">
                    <x-dashboard.icon name="users" size="32" />
                    <h3>No reviewer-enabled Advisers</h3>
                    <p>Capacity records appear after an active Adviser receives reviewer capability.</p>
                </div>
            @else
                <div class="review-monitoring-workload-grid">
                    @foreach ($reviewerWorkloads as $reviewer)
                        @php
                            $capacity = max(0, (int) ($reviewer->reviewer_capacity ?? 0));
                            $activeLoad = (int) $reviewer->active_assignment_count;
                            $atCapacity = $capacity < 1 || $activeLoad >= $capacity;
                            $utilization = $capacity > 0 ? min(100, (int) round(($activeLoad / $capacity) * 100)) : 100;
                            $classifications = $reviewer->reviewerClassificationLabels();
                        @endphp
                        <article class="review-monitoring-workload-card {{ $atCapacity ? 'is-full' : '' }}">
                            <header>
                                <div>
                                    <h3>{{ $reviewer->name }}</h3>
                                    <p>{{ $reviewer->position_title ?: 'Adviser Reviewer' }}{{ $reviewer->department ? ' - '.$reviewer->department : '' }}</p>
                                </div>
                                <x-dashboard.status-badge :label="$atCapacity ? 'At capacity' : 'Available'" :tone="$atCapacity ? 'red' : 'success'" />
                            </header>

                            <div class="review-monitoring-capacity-copy">
                                <strong>{{ $activeLoad }} / {{ $capacity }}</strong>
                                <span>active assignments</span>
                            </div>
                            <progress class="review-monitoring-progress" max="100" value="{{ $utilization }}">{{ $utilization }}%</progress>

                            <footer>
                                <div class="review-monitoring-classifications" aria-label="Reviewer classifications">
                                    @forelse ($classifications as $classification)
                                        <span>{{ $classification }}</span>
                                    @empty
                                        <span>Unclassified</span>
                                    @endforelse
                                </div>
                                @if ((int) $reviewer->overdue_assignment_count > 0)
                                    <x-dashboard.status-badge :label="$reviewer->overdue_assignment_count.' overdue'" tone="orange" />
                                @endif
                            </footer>
                        </article>
                    @endforeach
                </div>
                <x-dashboard.pagination :paginator="$reviewerWorkloads" label="Reviewer workload pages" />
            @endif
        </section>

        <section class="application-panel review-monitoring-panel" id="review-monitoring-advisers" aria-labelledby="review-monitoring-advisers-title">
            <div class="application-panel-heading review-monitoring-panel-heading">
                <div>
                    <h2 id="review-monitoring-advisers-title">Adviser endorsement workload</h2>
                    <p>Compare declared expectations with live endorsement records and received applications. Applicant identity and credentials are excluded.</p>
                </div>
                <span>{{ $adviserWorkloads->total() }} Adviser{{ $adviserWorkloads->total() === 1 ? '' : 's' }}</span>
            </div>

            <form class="review-monitoring-adviser-filters" method="GET" action="{{ route('res.review-monitoring.index') }}">
                <div class="application-field application-search-field review-monitoring-filter-search">
                    <label for="monitoring-adviser-q">Search Adviser</label>
                    <span><x-dashboard.icon name="search" size="18" /></span>
                    <input
                        id="monitoring-adviser-q"
                        name="adviser_q"
                        value="{{ $filters['adviser_q'] ?? '' }}"
                        placeholder="Name, position, or department"
                    >
                </div>

                <div class="application-field">
                    <label for="monitoring-adviser-department">Department</label>
                    <select id="monitoring-adviser-department" name="adviser_department">
                        <option value="">All departments</option>
                        @foreach ($adviserDepartments as $department)
                            <option value="{{ $department }}" @selected(($filters['adviser_department'] ?? '') === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="application-field">
                    <label for="monitoring-adviser-workload">Workload State</label>
                    <select id="monitoring-adviser-workload" name="adviser_workload">
                        <option value="">All workload states</option>
                        <option value="awaiting_action" @selected(($filters['adviser_workload'] ?? '') === 'awaiting_action')>Awaiting Adviser action</option>
                        <option value="remaining_expected" @selected(($filters['adviser_workload'] ?? '') === 'remaining_expected')>Expected workload remaining</option>
                        <option value="not_received" @selected(($filters['adviser_workload'] ?? '') === 'not_received')>Not yet received</option>
                        <option value="target_met" @selected(($filters['adviser_workload'] ?? '') === 'target_met')>Target met</option>
                        <option value="no_target" @selected(($filters['adviser_workload'] ?? '') === 'no_target')>No declared target</option>
                    </select>
                </div>

                <div class="review-monitoring-filter-actions">
                    <button class="dashboard-primary-action" type="submit">
                        <x-dashboard.icon name="search" size="17" />
                        <span>Apply Filters</span>
                    </button>
                    <a class="dashboard-outline-action" href="{{ route('res.review-monitoring.index').'#review-monitoring-advisers' }}">Reset</a>
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
                                <th>Received, Awaiting Endorsement</th>
                                <th>Remaining Expected</th>
                                <th>Not Yet Received</th>
                                <th>Application Progress / Drill-down</th>
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
                                        <small>{{ $adviser->position_title ?: 'Research Adviser' }}{{ $adviser->department ? ' - '.$adviser->department : '' }}</small>
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
                                    <td><strong class="review-monitoring-stat-value tone-violet">{{ $statistics['remaining'] }}</strong></td>
                                    <td>
                                        <x-dashboard.status-badge
                                            :label="$statistics['not_received'].' not received'"
                                            :tone="$statistics['not_received'] > 0 ? 'blue' : 'success'"
                                        />
                                    </td>
                                    <td>
                                        @if ($adviser->advisedApplications->isEmpty())
                                            <span class="review-monitoring-no-applications">No applications received</span>
                                        @else
                                            <details class="review-monitoring-application-drilldown">
                                                <summary>
                                                    <span>{{ $adviser->advisedApplications->count() }} recent application{{ $adviser->advisedApplications->count() === 1 ? '' : 's' }}</span>
                                                    <x-dashboard.icon name="chevron-down" size="16" />
                                                </summary>
                                                <ul>
                                                    @foreach ($adviser->advisedApplications as $application)
                                                        <li>
                                                            <a href="{{ route('res.applications.show', $application) }}">{{ $application->application_code }}</a>
                                                            <x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" />
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
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
