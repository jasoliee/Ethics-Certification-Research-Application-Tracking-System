@extends('layouts.dashboard')

@section('content')
    @php
        $activeReviewTab = $filters['tab'] ?? null;
        $assignmentSectionTitle = match ($activeReviewTab) {
            'revision' => 'Revision Reviews',
            'completed' => 'Completed Reviews',
            default => 'Assigned Reviews',
        };
    @endphp

    <div class="dashboard-page reviewer-assignment-page">
        <header class="dashboard-page-heading">
            <h1>{{ $reviewTasksPage ? 'Review Tasks' : 'Assigned Applications' }}</h1>
        </header>

        @if ($reviewTasksPage)
            <nav class="settings-tabs reviewer-task-tabs" aria-label="Review task status">
                <a href="{{ route('reviewer.reviews.index', ['tab' => 'assigned']) }}" @if(($filters['tab'] ?? 'assigned') === 'assigned') aria-current="page" @endif>Assigned to Me</a>
                <a href="{{ route('reviewer.reviews.index', ['tab' => 'revision']) }}" @if(($filters['tab'] ?? '') === 'revision') aria-current="page" @endif>Revision Reviews</a>
                <a href="{{ route('reviewer.reviews.index', ['tab' => 'completed']) }}" @if(($filters['tab'] ?? '') === 'completed') aria-current="page" @endif>Completed Reviews</a>
            </nav>
        @endif

        {{-- Assignment filters stay bounded to approved fields and retain pagination query state. --}}
        <form class="reviewer-assignment-filter-bar" method="GET" action="{{ route($reviewTasksPage ? 'reviewer.reviews.index' : 'reviewer.assignments.index') }}">
            @if ($reviewTasksPage)<input type="hidden" name="tab" value="{{ $filters['tab'] ?? 'assigned' }}">@endif
            <div class="application-field application-search-field">
                <label for="assignment-q">Search</label>
                <span><x-dashboard.icon name="search" size="18" /></span>
                <input id="assignment-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application code or research title">
            </div>
            <div class="application-field">
                <label for="assignment-academic-term">Academic Term</label>
                <select id="assignment-academic-term" name="academic_term_id">
                    <option value="">All</option>
                    @foreach ($termOptions as $term)
                        <option value="{{ $term->id }}" @selected((string) ($filters['academic_term_id'] ?? '') === (string) $term->id)>{{ $term->filterLabel() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label for="assignment-cycle">Review Type</label>
                <select id="assignment-cycle" name="review_cycle">
                    <option value="">All review types</option>
                    <option value="initial_review" @selected(($filters['review_cycle'] ?? '') === 'initial_review')>Initial Review</option>
                    <option value="revision_review" @selected(($filters['review_cycle'] ?? '') === 'revision_review')>Revision Review</option>
                </select>
            </div>
            <div class="application-field">
                <label for="assignment-status">Status</label>
                <select id="assignment-status" name="status">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label for="assignment-research-type">Research Type</label>
                <select id="assignment-research-type" name="research_type">
                    <option value="">All research types</option>
                    @foreach ($researchTypes as $researchType)
                        <option value="{{ $researchType->value }}" @selected(($filters['research_type'] ?? '') === $researchType->value)>{{ $researchType->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="application-field">
                <label for="assignment-deadline">Deadline</label>
                <select id="assignment-deadline" name="deadline">
                    <option value="">All deadlines</option>
                    <option value="due_soon" @selected(($filters['deadline'] ?? '') === 'due_soon')>Due within 7 days</option>
                    <option value="overdue" @selected(($filters['deadline'] ?? '') === 'overdue')>Overdue</option>
                    <option value="no_deadline" @selected(($filters['deadline'] ?? '') === 'no_deadline')>Not configured</option>
                </select>
            </div>
            <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="search" size="17" /><span>Apply Filters</span></button>
            <a class="dashboard-outline-action" href="{{ route($reviewTasksPage ? 'reviewer.reviews.index' : 'reviewer.assignments.index', $reviewTasksPage ? ['tab' => $filters['tab'] ?? 'assigned'] : []) }}">Reset</a>
        </form>

        <x-dashboard.section :title="$assignmentSectionTitle">
            @if ($assignments->isEmpty())
                <x-dashboard.empty-state
                    image="no-assignments"
                    alt="No assigned review applications"
                    title="No assigned applications found"
                    message="There are no applications matching the current filters."
                />
            @else
                {{-- The table remains scannable on desktop and scrolls inside its own region on narrow screens. --}}
                <x-dashboard.overflow label="Assigned reviewer applications" wide>
                    <table class="dashboard-table reviewer-assignment-table">
                        <thead>
                            <tr><th>Application Code</th><th>Research Title</th><th>Review Type</th><th class="reviewer-table-centered">Status</th><th class="reviewer-table-centered">Deadline</th><th class="dashboard-table-action">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td><a href="{{ route('reviewer.assignments.show', $assignment) }}">{{ $assignment->researchApplication->application_code }}</a></td>
                                    <td class="reviewer-assignment-title"><x-dashboard.research-title :title="$assignment->researchApplication->research_title" :href="route('reviewer.assignments.show', $assignment)" /></td>
                                    <td>{{ Str::headline($assignment->review_type) }}</td>
                                    <td class="reviewer-table-centered">
                                        <x-dashboard.status-badge
                                            :label="$activeReviewTab === 'completed' ? 'Completed' : $assignment->assignment_status->label()"
                                            :tone="$assignment->assignment_status->tone()"
                                        />
                                    </td>
                                    <td class="reviewer-table-centered reviewer-deadline-value">
                                        <span @class(['reviewer-deadline-overdue' => $assignment->review_deadline_at?->isPast() && in_array($assignment->assignment_status->value, \App\Enums\ReviewerAssignmentStatus::activeValues(), true)])>
                                            {{ $assignment->review_deadline_at?->format('M j, Y') ?? 'Not configured' }}
                                        </span>
                                    </td>
                                    <td class="dashboard-table-action"><x-dashboard.action-link :href="route('reviewer.assignments.show', $assignment)">View</x-dashboard.action-link></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$assignments" label="Assigned application pages" />
            @endif
        </x-dashboard.section>
    </div>
@endsection
