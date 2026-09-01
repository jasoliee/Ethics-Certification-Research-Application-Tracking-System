<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>REU Operational Report</title>
    <style>
        @page { size: A4 portrait; margin: 1in; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202b; font: 10px/1.35 Arial, sans-serif; }
        body::before { content: ""; position: fixed; inset: 0; z-index: -1; opacity: .08; background: center / cover no-repeat; background-image: url({!! json_encode($worksheetBackground) !!}); }
        h1 { margin: 0; color: #087241; font-size: 22px; }
        h2 { margin: 14px 0 6px; color: #075f38; font-size: 13px; break-after: avoid; }
        p { margin: 3px 0; }
        .report-head { display: flex; justify-content: space-between; gap: 24px; align-items: center; border-bottom: 2px solid #087241; padding-bottom: 8px; }
        .report-heading-copy { min-width: 0; }
        .report-scope { margin-top: 3px; color: #087241; font-size: 15px; font-weight: 700; }
        .report-generated { margin-top: 2px; color: #526071; }
        .report-meta { flex: 0 0 auto; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .report-filter-summary { max-width: 620px; overflow-wrap: anywhere; }
        .summary { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin: 10px 0; }
        .summary div { border: 1px solid #b9d6c8; background: rgba(255,255,255,.9); padding: 7px; text-align: center; }
        .summary strong { display: block; color: #087241; font-size: 18px; }
        .dashboard-overflow-region { max-width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.92); break-inside: auto; }
        th, td { border: 1px solid #aeb9b3; padding: 4px 5px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e8f4ee; color: #075f38; }
        th.numeric, td.numeric { text-align: center; vertical-align: middle; }
        tr { break-inside: avoid; }
        .section { break-inside: avoid-page; }
        .no-print { display: inline-flex; align-items: center; justify-content: center; min-height: 46px; margin: 0; border: 1px solid #087241; border-radius: 5px; padding: 10px 20px; background: #087241; color: #fff; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .no-print.is-secondary { background: #fff; color: #087241; }
        .download-dialog { width: min(440px, calc(100vw - 32px)); border: 1px solid #b9d6c8; border-radius: 8px; padding: 22px; }
        .download-dialog::backdrop { background: rgba(15, 23, 42, .56); }
        .download-dialog h2, .download-dialog > p { text-align: center; }
        .download-dialog h2 { margin-top: 0; }
        .download-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 18px 0; }
        .download-options .no-print { width: 100%; text-align: center; }
        .download-dialog form { display: flex; justify-content: flex-end; }
        @media (max-width: 760px) { .report-head { align-items: stretch; flex-direction: column; } .report-meta { justify-content: flex-end; } .summary { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media print { .no-print { display: none; } .dashboard-overflow-region { overflow: visible; } }
    </style>
</head>
<body>
    <main>
        <header class="report-head">
            <div class="report-heading-copy"><h1>ECRATS Research Ethics Unit Operational Report</h1><div class="report-scope">{{ $filterSummary === 'All Records' ? 'All Records' : 'Filtered Records' }}</div><p class="report-generated">Generated: {{ $generatedAt->format('M j, Y g:i A') }}</p>@if ($filterSummary !== 'All Records')<p class="report-filter-summary">{{ $filterSummary }}</p>@endif</div>
            <div class="report-meta">
                <button class="no-print is-secondary" type="button" onclick="document.getElementById('report-download-dialog').showModal()">Download Report</button>
                <button class="no-print" type="button" onclick="window.print()">Print Report</button>
            </div>
        </header>

        <div class="summary">
            @foreach ([
                'Unique Applicants' => 'unique_applicants',
                'Submitted' => 'submitted',
                'Not Yet Submitted' => 'not_submitted',
                'Failed' => 'failed',
                'Certificates Claimed' => 'certificates_claimed',
                'Certificates Unclaimed' => 'certificates_unclaimed',
            ] as $label => $key)<div><strong>{{ $report['summary'][$key] }}</strong>{{ $label }}</div>@endforeach
        </div>

        <section class="section"><h2>Applicant &amp; Application Summary by Institute</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Institute</th><th class="numeric">Unique Applicants</th><th class="numeric">Submitted</th><th class="numeric">Not Yet Submitted</th><th class="numeric">Failed</th><th class="numeric">Claimed</th><th class="numeric">Unclaimed</th></tr></thead><tbody>@forelse ($report['institute_summary'] as $row)<tr><td>{{ $row['institute'] }}</td><td class="numeric">{{ $row['unique_applicants'] }}</td><td class="numeric">{{ $row['submitted'] }}</td><td class="numeric">{{ $row['not_submitted'] }}</td><td class="numeric">{{ $row['failed'] }}</td><td class="numeric">{{ $row['claimed'] }}</td><td class="numeric">{{ $row['unclaimed'] }}</td></tr>@empty<tr><td colspan="7">No matching records.</td></tr>@endforelse</tbody></table></div></section>

        <section class="section"><h2>Review Classification Summary</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Classification</th><th class="numeric">Applications</th></tr></thead><tbody>@foreach ($report['classifications'] as $row)<tr><td>{{ $row['label'] }}</td><td class="numeric">{{ $row['count'] }}</td></tr>@endforeach</tbody></table></div></section>

        <section class="section"><h2>Adviser &amp; Reviewer Summary</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Institute</th><th class="numeric">Research Advisers</th><th class="numeric">Reviewer-enabled Advisers</th></tr></thead><tbody>@forelse ($report['adviser_reviewer_summary'] as $row)<tr><td>{{ $row['institute'] }}</td><td class="numeric">{{ $row['advisers'] }}</td><td class="numeric">{{ $row['reviewers'] }}</td></tr>@empty<tr><td colspan="3">No matching records.</td></tr>@endforelse</tbody></table></div></section>

        <section><h2>Reviewer Review Workload</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Reviewer</th><th>Institute</th><th class="numeric">Expedited</th><th class="numeric">Full Board</th><th class="numeric">Total</th><th class="numeric">Completed</th><th class="numeric">Pending</th><th class="numeric">Overdue</th><th class="numeric">Remaining Capacity</th></tr></thead><tbody>@forelse ($report['reviewer_workload'] as $row)<tr><td>{{ $row['reviewer']->name }}</td><td>{{ $row['institute'] }}</td><td class="numeric">{{ $row['expedited'] }}</td><td class="numeric">{{ $row['full_board'] }}</td><td class="numeric">{{ $row['total'] }}</td><td class="numeric">{{ $row['completed'] }}</td><td class="numeric">{{ $row['pending'] }}</td><td class="numeric">{{ $row['overdue'] }}</td><td class="numeric">{{ $row['remaining'] }}</td></tr>@empty<tr><td colspan="9">No matching records.</td></tr>@endforelse</tbody></table></div></section>

        <section><h2>Adviser Endorsement Workload</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Adviser</th><th>Institute</th><th class="numeric">Declared Expected</th><th class="numeric">Applicants Received</th><th class="numeric">Completed</th><th class="numeric">Awaiting</th><th class="numeric">Not Yet Received</th></tr></thead><tbody>@forelse ($report['adviser_workload'] as $row)<tr><td>{{ $row['adviser']->name }}</td><td>{{ $row['institute'] }}</td><td class="numeric">{{ $row['expected'] }}</td><td class="numeric">{{ $row['received'] }}</td><td class="numeric">{{ $row['endorsed'] }}</td><td class="numeric">{{ $row['awaiting'] }}</td><td class="numeric">{{ $row['not_received'] }}</td></tr>@empty<tr><td colspan="7">No matching records.</td></tr>@endforelse</tbody></table></div></section>

        <section><h2>Filtered Applications</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Application Code</th><th>Research Title</th><th>Institute</th><th>Review Type</th><th>Workflow Status</th><th>Certificate Status</th><th>Submitted</th></tr></thead><tbody>@forelse ($report['applications'] as $row)@php($application = $row['application'])<tr><td>{{ $application->application_code }}</td><td>{{ $application->research_title }}</td><td>{{ $application->institution }}</td><td>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $row['certificate_status'] }}</td><td>{{ $application->submitted_at?->format('M j, Y g:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="7">No matching records.</td></tr>@endforelse</tbody></table></div></section>

        <section><h2>Applicant Certification</h2><div class="dashboard-overflow-region"><table><thead><tr><th>Applicant</th><th>Institutional ID</th><th>Institute</th><th>Application Code</th><th class="numeric">Certificate Status</th><th class="numeric">Released Date</th><th class="numeric">Ageing</th></tr></thead><tbody>@forelse ($report['applicant_certification'] as $row)<tr><td>{{ $row['applicant']?->name }}</td><td>{{ $row['applicant']?->institutional_identifier }}</td><td>{{ $row['application']->institution }}</td><td>{{ $row['application']->application_code }}</td><td class="numeric">{{ $row['certificate_status'] }}</td><td class="numeric">{{ $row['released_at']?->format('M j, Y g:i A') ?? '—' }}</td><td class="numeric">{{ $row['ageing_days'] === null ? '—' : $row['ageing_days'].' days' }}</td></tr>@empty<tr><td colspan="7">No identities are eligible for release in this reporting scope.</td></tr>@endforelse</tbody></table></div></section>
    </main>
    <dialog id="report-download-dialog" class="download-dialog"><h2>Download Report</h2><p>Choose the file format to download.</p><div class="download-options"><a class="no-print is-secondary" href="{{ route('res.reports.download', array_merge(array_filter($filters, fn ($value) => filled($value)), ['format' => 'xlsx'])) }}">Excel</a><a class="no-print is-secondary" href="{{ route('res.reports.download', array_merge(array_filter($filters, fn ($value) => filled($value)), ['format' => 'pdf'])) }}">PDF</a></div><form method="dialog"><button class="no-print" type="submit">Close</button></form></dialog>
</body>
</html>
