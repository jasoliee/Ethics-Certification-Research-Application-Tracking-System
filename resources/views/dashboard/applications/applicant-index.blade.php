@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page application-workspace">
        {{-- The applicant list heading keeps the primary create action reachable at every viewport. --}}
        <header class="dashboard-page-heading-row">
            <div class="dashboard-page-heading">
                <h1>My Applications</h1>
            </div>
            <div class="application-heading-actions">
                <span class="application-submission-state {{ $submissionWindow['open'] ? 'is-open' : 'is-closed' }}" role="status">
                    <x-dashboard.icon :name="$submissionWindow['open'] ? 'check' : 'x'" size="16" />
                    <span>Application Submission is {{ $submissionWindow['open'] ? 'Open' : 'Closed' }}</span>
                </span>
                @if ($canStartApplication)
                    <a class="dashboard-primary-action" href="{{ route('applicant.applications.create') }}">
                        <x-dashboard.icon :name="$editableApplication ? 'edit' : 'file-plus'" size="18" />
                        <span>{{ $editableApplication ? 'Resume Application' : 'Create Application' }}</span>
                    </a>
                @else
                    <button class="dashboard-primary-action" type="button" disabled aria-describedby="application-limit-message">
                        <x-dashboard.icon name="file-plus" size="18" />
                        <span>Create Application</span>
                    </button>
                @endif
            </div>
        </header>

        @if ($submissionLimit['reached'] && ! $editableApplication)
            <div class="application-limit-notice" id="application-limit-message" role="alert">
                <x-dashboard.icon name="alert-triangle" size="20" />
                <div>
                    <strong>Application Submission Limit Reached</strong>
                    <span>You already have {{ $submissionLimit['submitted_count'] }} formally submitted applications. Drafts do not count toward this limit.</span>
                </div>
            </div>
        @endif

        @error('application_limit')
            <div class="identity-validation-summary" role="alert"><strong>{{ $message }}</strong></div>
        @enderror

        @error('submission_window')
            <div class="identity-validation-summary" role="alert"><strong>{{ $message }}</strong></div>
        @enderror

        @if ($applications->isEmpty())
            {{-- The first-time state leads to the same idempotent draft form used by the dashboard actions. --}}
            <section class="application-empty-panel">
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="Empty application workspace"
                    title="No application yet"
                    message="Start an application to enter your research information and complete its document requirements."
                    :action-label="$canStartApplication ? ($editableApplication ? 'Resume Application' : 'Start Application') : null"
                    :action-href="$canStartApplication ? route('applicant.applications.create') : null"
                />
            </section>
        @else
            {{-- Application history uses the shared internal-overflow component and bounded pagination. --}}
            <section class="application-panel">
                <div class="application-panel-heading">
                    <div><h2>Application Records</h2></div>
                </div>
                <x-dashboard.overflow label="Applicant application records" wide>
                    <table class="dashboard-table application-table">
                        <thead>
                            <tr><th>Application ID</th><th>Research Title</th><th>Research Type</th><th>Current Stage</th><th class="dashboard-table-status">Status</th><th class="applicant-submitted-column">Submitted</th><th class="dashboard-table-action">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong></td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('applicant.applications.show', $application)" /></td>
                                    <td>{{ $application->research_type?->label() ?? 'Not set' }}</td>
                                    <td>{{ $application->current_stage?->label() ?? 'Application Information' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->statusLabel()" :tone="$application->application_status->tone()" /></td>
                                    <td class="applicant-submitted-column">{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
                                    <td class="application-table-actions dashboard-table-action">
                                        <x-dashboard.action-link :href="route('applicant.applications.show', $application)">View</x-dashboard.action-link>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-dashboard.overflow>
                <x-dashboard.pagination :paginator="$applications" label="Applicant application pages" />
            </section>
        @endif
    </div>
@endsection
