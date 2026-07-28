@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page application-workspace">
        {{-- The applicant list heading keeps the primary create action reachable at every viewport. --}}
        <header class="dashboard-page-heading-row">
            <div class="dashboard-page-heading">
                <h1>My Applications</h1>
                <p>Prepare, submit, and track your research ethics applications.</p>
            </div>
            <a class="dashboard-primary-action" href="{{ route('applicant.applications.create') }}">
                <x-dashboard.icon name="file-plus" size="18" />
                <span>Create Application</span>
            </a>
        </header>

        @if ($applications->isEmpty())
            {{-- The first-time state leads to the same idempotent draft form used by the dashboard actions. --}}
            <section class="application-empty-panel">
                <x-dashboard.empty-state
                    image="no-applications"
                    alt="Empty application workspace"
                    title="No application yet"
                    message="Start an application to enter your research information and complete its document requirements."
                    action-label="Start Application"
                    :action-href="route('applicant.applications.create')"
                />
            </section>
        @else
            {{-- Application history uses the shared internal-overflow component and bounded pagination. --}}
            <section class="application-panel">
                <div class="application-panel-heading">
                    <div><h2>Application Records</h2><p>Your newest application appears first.</p></div>
                </div>
                <x-dashboard.overflow label="Applicant application records" wide>
                    <table class="dashboard-table application-table">
                        <thead>
                            <tr><th>Application ID</th><th>Research Title</th><th>Research Type</th><th>Current Stage</th><th class="dashboard-table-status">Status</th><th>Submitted</th><th class="dashboard-table-action">Action</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($applications as $application)
                                <tr>
                                    <td><strong>{{ $application->application_code }}</strong></td>
                                    <td><x-dashboard.research-title :title="$application->research_title" :href="route('applicant.applications.show', $application)" /></td>
                                    <td>{{ $application->research_type?->label() ?? 'Not set' }}</td>
                                    <td>{{ $application->current_stage?->label() ?? 'Application Information' }}</td>
                                    <td class="dashboard-table-status"><x-dashboard.status-badge :label="$application->application_status->label()" :tone="$application->application_status->tone()" /></td>
                                    <td>{{ $application->submitted_at?->format('M j, Y') ?? 'Not submitted' }}</td>
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
