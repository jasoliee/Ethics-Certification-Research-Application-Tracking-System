@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page">
        <header class="dashboard-page-heading">
            <h1>Reports</h1>
            <p>Open authorized operational records and ethics-review reporting tools.</p>
        </header>

        <section class="application-panel">
            <header class="application-panel-heading">
                <div><h2>Audit Log</h2><p>Workflow, release, certificate, account, and access events with authorized filters and pagination.</p></div>
                <a class="dashboard-primary-action" href="{{ route('res.reports.audit.index') }}"><x-dashboard.icon name="clipboard" size="18" /><span>Open Audit Log</span></a>
            </header>
        </section>
    </div>
@endsection
