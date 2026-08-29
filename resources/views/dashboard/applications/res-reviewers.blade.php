@extends('layouts.dashboard')

@section('content')
    @php
        $screening = $application->screening;
        $assignments = $application->reviewerAssignments;
        $selectedReviewerIds = collect(old('reviewer_ids', $assignments->pluck('reviewer_user_id')->all()))->map(fn ($id) => (int) $id);
    @endphp

    <div class="dashboard-page application-workspace res-workflow-page">
        <header class="dashboard-page-heading res-screening-page-heading">
            <div>
                <h1>Reviewer Assignment</h1>
                <p>Assign eligible reviewer-enabled Advisers for this {{ $reviewType->label() }} application.</p>
            </div>
            <a class="dashboard-outline-action" href="{{ route('res.applications.show', $application) }}">
                <x-dashboard.icon name="arrow-left" size="17" />
                <span>Back to Screening Details</span>
            </a>
        </header>

        @if ($assignments->isNotEmpty())
            <section class="res-workflow-banner is-success" role="status">
                <span><x-dashboard.icon name="check" size="22" /></span>
                <div>
                    <strong>Current reviewer set</strong>
                    <p>You may replace reviewers when necessary. Prior assignments and submitted work remain in the audit history.</p>
                </div>
                <a class="dashboard-outline-action" href="{{ route('res.applications.show', $application) }}">View Application</a>
            </section>
        @endif

        <div class="res-assignment-context-grid">
            <section class="res-workflow-panel">
                <header class="res-workflow-panel-heading"><x-dashboard.icon name="file-text" size="21" /><h2>Application Details</h2></header>
                <dl class="res-screening-summary res-assignment-context-details">
                    <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                    <div><dt>Applicant Category</dt><dd>{{ Str::headline($application->applicant_type) }}</dd></div>
                    <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                    <div><dt>Research Type</dt><dd>{{ $application->research_type?->label() ?? 'Not specified' }}</dd></div>
                    <div><dt>Program</dt><dd>{{ $application->program ?: 'Not applicable' }}</dd></div>
                    <div><dt>Institute</dt><dd>{{ $application->institution ?: 'Not specified' }}</dd></div>
                    <div><dt>Adviser</dt><dd>{{ $application->adviser?->name ?? 'Archived adviser' }}</dd></div>
                    <div><dt>Current Status</dt><dd><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></dd></div>
                </dl>
            </section>

            <section class="res-workflow-panel">
                <header class="res-workflow-panel-heading"><x-dashboard.icon name="user-check" size="21" /><h2>Screening and Classification</h2></header>
                <dl class="res-screening-summary">
                    <div><dt>Classification Date</dt><dd>{{ $screening->classified_at->format('M j, Y') }}</dd></div>
                    <div><dt>Classified By</dt><dd>{{ $screening->screenedBy?->name ?? 'Archived REU Lead' }}</dd></div>
                    <div><dt>Review Type</dt><dd><x-dashboard.status-badge :label="$reviewType->label()" tone="success" /></dd></div>
                    <div><dt>Reviewers Required</dt><dd>{{ $requiredReviewerCount }} {{ Str::plural('reviewer', $requiredReviewerCount) }}</dd></div>
                    <div class="res-detail-wide"><dt>Reason / Basis</dt><dd>{{ $screening->classification_reason }}</dd></div>
                </dl>
            </section>

        </div>

        @if ($canAssign)
            <div class="res-reviewer-assignment-layout">
                <section class="res-workflow-panel res-reviewer-candidates-panel">
                    <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                        <div><x-dashboard.icon name="users" size="21" /><h2>Eligible Reviewers</h2></div>
                    </header>

                    {{-- Filter and selection forms are siblings inside one panel, avoiding invalid nested forms. --}}
                    <form class="res-reviewer-filter-bar" method="GET" action="{{ route('res.applications.reviewers.index', $application) }}">
                        <div class="application-field application-search-field">
                            <label for="reviewer-q">Search Reviewer</label>
                            <span><x-dashboard.icon name="search" size="18" /></span>
                            <input id="reviewer-q" name="reviewer_q" value="{{ $filters['reviewer_q'] ?? '' }}" placeholder="Name, position, or institute">
                        </div>
                        <div class="application-field">
                            <label for="reviewer-institute">Institute</label>
                            <select id="reviewer-institute" name="institute">
                                <option value="">All institutes</option>
                                @foreach ($institutes as $institute)
                                    <option value="{{ $institute }}" @selected(($filters['institute'] ?? '') === $institute)>{{ $institute }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="search" size="17" /><span>Filter</span></button>
                        <a class="dashboard-outline-action" href="{{ route('res.applications.reviewers.index', $application) }}">Reset</a>
                    </form>

                    @if ($errors->reviewerAssignment->any())
                        <div class="res-form-error-summary res-reviewer-assignment-error" role="alert">
                            <x-dashboard.icon name="alert-triangle" size="19" />
                            <div><strong>Reviewer assignment was not saved.</strong><span>{{ $errors->reviewerAssignment->first() }}</span></div>
                        </div>
                    @endif

                    <form
                        id="res-reviewer-assignment-form"
                        class="res-reviewer-selection-form"
                        method="POST"
                        action="{{ route('res.applications.reviewers.store', $application) }}"
                        data-reviewer-assignment-form
                        data-required-reviewers="{{ $requiredReviewerCount }}"
                        data-application-submit-once
                    >
                        @csrf
                        <input name="confirm_assignment" type="hidden" value="1">

                        @if ($candidates->isEmpty())
                            <x-dashboard.empty-state
                                image="no-applications"
                                alt="No eligible reviewers"
                                title="No eligible reviewers found"
                                message="No active reviewer-enabled Adviser matches the current filters and eligibility rules."
                            />
                        @else
                            <x-dashboard.overflow label="Eligible reviewer candidates" wide>
                                <table class="dashboard-table res-reviewer-table">
                                <thead><tr><th class="res-reviewer-select-column"><span class="sr-only">Select</span></th><th>Reviewer</th><th class="res-reviewer-load-cell">Current Load</th><th>Institute</th><th>Position</th></tr></thead>
                                <tbody>
                                    @foreach ($candidates as $candidate)
                                        @php
                                            $capacity = (int) ($candidate->reviewer_capacity ?? 0);
                                            $activeLoad = (int) $candidate->active_assignment_count;
                                            $isRetained = $assignments->contains('reviewer_user_id', $candidate->id);
                                            $available = $isRetained || ($capacity > 0 && $activeLoad < $capacity);
                                        @endphp
                                        <tr
                                            data-reviewer-row
                                            data-reviewer-id="{{ $candidate->id }}"
                                            data-reviewer-name="{{ $candidate->name }}"
                                            data-reviewer-position="{{ $candidate->position_title ?: 'Not specified' }}"
                                            data-reviewer-institute="{{ $candidate->institution ?: 'Not specified' }}"
                                            data-reviewer-load="{{ $activeLoad }} / {{ $capacity }}"
                                            @class(['is-unavailable' => ! $available])
                                        >
                                            <td class="res-reviewer-select-column">
                                                <input
                                                    id="reviewer-{{ $candidate->id }}"
                                                    name="reviewer_ids[]"
                                                    type="checkbox"
                                                    value="{{ $candidate->id }}"
                                                    aria-label="Select {{ $candidate->name }}"
                                                    @checked($selectedReviewerIds->contains($candidate->id))
                                                    @disabled(! $available)
                                                    data-reviewer-select
                                                >
                                            </td>
                                            <td><label for="reviewer-{{ $candidate->id }}"><strong>{{ $candidate->name }}</strong></label></td>
                                            <td class="res-reviewer-load-cell">
                                                <strong>{{ $activeLoad }} / {{ $capacity }}</strong>
                                                @unless ($available)
                                                    <small class="res-reviewer-capacity-note">Capacity reached</small>
                                                @endunless
                                            </td>
                                            <td>{{ $candidate->institution ?: 'Not specified' }}</td>
                                            <td>{{ $candidate->position_title ?: 'Not specified' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                </table>
                            </x-dashboard.overflow>
                            <x-dashboard.pagination :paginator="$candidates" label="Eligible reviewer pages" />
                        @endif
                    </form>
                </section>

                <section class="res-workflow-panel res-selected-reviewers-panel">
                    <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                        <div><x-dashboard.icon name="user-check" size="21" /><h2>Selected {{ Str::plural('Reviewer', $requiredReviewerCount) }}</h2></div>
                        <span class="res-selection-count" data-reviewer-selection-count>0 / {{ $requiredReviewerCount }} Selected</span>
                    </header>
                    <ul class="res-selected-reviewer-list" data-selected-reviewer-list aria-live="polite"></ul>
                    <template data-reviewer-remove-icon><x-dashboard.icon name="x" size="17" /></template>
                    @if ($assignments->isNotEmpty())
                        {{-- The form attribute preserves server validation and audit handling while placing this field with the save action. --}}
                        <div class="application-field res-reassignment-reason">
                            <label for="reassignment_reason">Reason for Reassignment</label>
                            <textarea id="reassignment_reason" name="reassignment_reason" rows="3" minlength="10" maxlength="1000" form="res-reviewer-assignment-form">{{ old('reassignment_reason') }}</textarea>
                            @error('reassignment_reason', 'reviewerAssignment')<small class="application-field-error">{{ $message }}</small>@enderror
                        </div>
                    @endif
                    <button class="dashboard-primary-action res-assignment-submit" type="button" data-reviewer-assignment-confirm-open disabled>
                        <x-dashboard.icon name="arrow-right" size="18" />
                        <span>{{ $assignments->isEmpty() ? 'Confirm and Assign' : 'Save Reviewer Set' }}</span>
                    </button>
                </section>
            </div>

            {{-- The confirmation dialog submits the original assignment form after showing the current reviewer set. --}}
            <section class="application-modal-backdrop" data-reviewer-assignment-dialog hidden>
                <div class="application-modal res-assignment-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="reviewer-assignment-title" tabindex="-1">
                    <button class="application-modal-close" type="button" aria-label="Close reviewer assignment confirmation" data-reviewer-assignment-confirm-close>
                        <x-dashboard.icon name="x" size="20" />
                    </button>
                    <header class="application-modal-heading">
                        <div>
                            <h2 id="reviewer-assignment-title">Confirm Reviewer Set</h2>
                        </div>
                    </header>
                    <dl class="res-confirmation-details">
                        <div><dt>Application Code</dt><dd>{{ $application->application_code }}</dd></div>
                        <div><dt>Research Title</dt><dd>{{ $application->research_title }}</dd></div>
                        <div><dt>Review Type</dt><dd>{{ $reviewType->label() }}</dd></div>
                        <div><dt>Required Reviewers</dt><dd>{{ $requiredReviewerCount }}</dd></div>
                    </dl>
                    <div>
                        <h3>Selected {{ Str::plural('Reviewer', $requiredReviewerCount) }}</h3>
                        <ul class="res-confirmation-reviewers" data-confirmation-reviewer-list></ul>
                    </div>
                    <div class="res-classification-note">
                        <span>The application will move to {{ $reviewType === \App\Enums\ReviewType::Expedited ? 'Under Expedited Review' : 'Under Full Board Review' }}.</span>
                    </div>
                    <div class="application-modal-actions">
                        <button class="dashboard-outline-action" type="button" data-reviewer-assignment-confirm-close>Cancel</button>
                        <button class="dashboard-primary-action" type="submit" form="res-reviewer-assignment-form">Confirm Assignment</button>
                    </div>
                </div>
            </section>
        @else
            <section class="res-workflow-panel res-assigned-reviewers-panel">
                <header class="res-workflow-panel-heading res-workflow-panel-heading-split">
                    <div><x-dashboard.icon name="users" size="21" /><h2>Assigned {{ Str::plural('Reviewer', $assignments->count()) }}</h2></div>
                    <x-dashboard.status-badge :label="$assignments->count().' / '.$requiredReviewerCount.' Assigned'" tone="success" />
                </header>
                <x-dashboard.overflow label="Assigned reviewer result" wide>
                    <table class="dashboard-table res-assigned-reviewer-table">
                        <thead><tr><th>#</th><th>Reviewer</th><th>Position</th><th>Institute</th><th>Current Load</th><th>Date Assigned</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($assignments as $assignment)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td><strong>{{ $assignment->reviewer?->name ?? 'Archived reviewer' }}</strong></td>
                                    <td>{{ $assignment->reviewer?->position_title ?: 'Not specified' }}</td>
                                    <td>{{ $assignment->reviewer?->institution ?: 'Not specified' }}</td>
                                    <td>{{ $assignment->reviewer?->active_assignment_count ?? 0 }} / {{ $assignment->reviewer?->reviewer_capacity ?? 0 }}</td>
                                    <td>{{ $assignment->assigned_at?->format('M j, Y g:i A') ?? 'Not recorded' }}</td>
                                    <td><x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
            </section>
        @endif
    </div>
@endsection
