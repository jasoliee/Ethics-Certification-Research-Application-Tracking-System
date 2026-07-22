@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-record-page">
        <header class="dashboard-page-heading">
            <h1>{{ $pageTitle }}</h1>
            <p>{{ $application->application_code }}</p>
        </header>

        <section class="dashboard-record-summary">
            <span class="dashboard-placeholder-icon"><x-dashboard.icon :name="$moduleIcon" size="38" /></span>
            <div>
                <h2><x-dashboard.research-title :title="$application->research_title" /></h2>
                <x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" />
            </div>
            <dl>
                <div><dt>Applicant</dt><dd>{{ $application->applicant->name }}</dd></div>
                <div><dt>Adviser</dt><dd>{{ $application->adviser?->name ?? 'Not assigned' }}</dd></div>
                <div><dt>Submitted</dt><dd>{{ $application->submitted_at?->format('M j, Y \a\t g:i A') ?? 'Not submitted' }}</dd></div>
                <div><dt>Review Type</dt><dd>{{ $application->review_type ? Str::headline($application->review_type) : 'Not classified' }}</dd></div>
            </dl>
            <a class="dashboard-outline-action" href="{{ route($indexRoute) }}">Back to List</a>
            @if ($canSubmit)
                <form method="POST" action="{{ route('applicant.applications.submit', $application) }}">
                    @csrf
                    <button class="dashboard-primary-action" type="submit">Submit Application</button>
                    @error('requirements')<span class="identity-field-error">{{ $message }}</span>@enderror
                </form>
            @endif
        </section>
    </div>
@endsection
