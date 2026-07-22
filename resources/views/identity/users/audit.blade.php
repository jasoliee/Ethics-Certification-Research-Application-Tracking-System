@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Account Audit Log</h1><p>Review security-relevant account and access events without exposing credentials or tokens.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        <section class="identity-table-panel">
            <div class="identity-table-scroll">
                <table class="identity-user-table">
                    <thead><tr><th>Date and Time</th><th>Actor</th><th>Action</th><th>Subject</th><th>Result</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                                <td>{{ $log->actor?->name ?? 'System / unauthenticated' }}</td>
                                <td>{{ Str::headline(str_replace('.', ' ', $log->action)) }}</td>
                                <td>{{ class_basename($log->subject_type ?? 'Record') }} #{{ $log->subject_id ?? 'N/A' }}</td>
                                <td>{{ Str::headline($log->metadata['result'] ?? 'recorded') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="identity-empty-state"><strong>No audit events found</strong><p>Security-relevant account events will appear here.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($logs->hasPages())<div class="identity-pagination">{{ $logs->links() }}</div>@endif
    </div>
@endsection
