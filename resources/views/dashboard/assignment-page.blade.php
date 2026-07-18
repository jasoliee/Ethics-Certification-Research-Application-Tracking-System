@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-record-page">
        <header class="dashboard-page-heading">
            <h1>Assignment Details</h1>
            <p>{{ $assignment->researchApplication->application_code }}</p>
        </header>

        <section class="dashboard-record-summary">
            <span class="dashboard-placeholder-icon"><x-dashboard.icon name="file-search" size="38" /></span>
            <div>
                <h2><x-dashboard.research-title :title="$assignment->researchApplication->research_title" /></h2>
                <x-dashboard.status-badge :label="$assignment->assignment_status->label()" :tone="$assignment->assignment_status->tone()" />
            </div>
            <dl>
                <div><dt>Review Type</dt><dd>{{ Str::headline($assignment->review_type) }}</dd></div>
                <div><dt>Date Assigned</dt><dd>{{ $assignment->assigned_at?->format('M j, Y \a\t g:i A') ?? 'Not assigned' }}</dd></div>
                <div><dt>Review Deadline</dt><dd>{{ $assignment->review_deadline_at?->format('M j, Y \a\t g:i A') ?? 'Not configured' }}</dd></div>
                <div><dt>Date Submitted</dt><dd>{{ $assignment->submitted_at?->format('M j, Y \a\t g:i A') ?? 'Not submitted' }}</dd></div>
            </dl>
            <a class="dashboard-outline-action" href="{{ route('reviewer.assignments.index') }}">Back to Assignments</a>
        </section>
    </div>
@endsection
