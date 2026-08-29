<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>REU Operational Report</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202b; font: 10px/1.35 Arial, sans-serif; }
        body::before { content: ""; position: fixed; inset: 0; z-index: -1; opacity: .08; background: center / cover no-repeat; background-image: url({!! json_encode($worksheetBackground) !!}); }
        h1 { margin: 0; color: #087241; font-size: 22px; }
        h2 { margin: 14px 0 6px; color: #075f38; font-size: 13px; break-after: avoid; }
        p { margin: 3px 0; }
        .report-head { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; border-bottom: 2px solid #087241; padding-bottom: 8px; }
        .report-meta { text-align: right; color: #526071; }
        .summary { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; margin: 10px 0; }
        .summary div { border: 1px solid #b9d6c8; background: rgba(255,255,255,.9); padding: 7px; text-align: center; }
        .summary strong { display: block; color: #087241; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.92); break-inside: auto; }
        th, td { border: 1px solid #aeb9b3; padding: 4px 5px; text-align: left; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e8f4ee; color: #075f38; }
        tr { break-inside: avoid; }
        .section { break-inside: avoid-page; }
        .privacy { margin-top: 8px; padding: 6px; border: 1px solid #b9d6c8; background: rgba(237,249,243,.94); }
        .no-print { margin: 12px; padding: 8px 14px; border: 0; border-radius: 4px; background: #087241; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print Report</button>
    <main>
        <header class="report-head">
            <div><h1>ECRATS Research Ethics Unit Operational Report</h1><p>Filtered management report</p></div>
            <div class="report-meta"><strong>Generated</strong><br>{{ $generatedAt->format('M j, Y g:i A') }}<br>{{ collect($filters)->map(fn ($value, $key) => Str::headline($key).': '.$value)->implode(' | ') ?: 'All records' }}</div>
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

        <section class="section"><h2>Applicant &amp; Application Summary by Institute</h2><table><thead><tr><th>Institute</th><th>Unique Applicants</th><th>Submitted</th><th>Not Yet Submitted</th><th>Failed</th><th>Claimed</th><th>Unclaimed</th></tr></thead><tbody>@forelse ($report['institute_summary'] as $row)<tr><td>{{ $row['institute'] }}</td><td>{{ $row['unique_applicants'] }}</td><td>{{ $row['submitted'] }}</td><td>{{ $row['not_submitted'] }}</td><td>{{ $row['failed'] }}</td><td>{{ $row['claimed'] }}</td><td>{{ $row['unclaimed'] }}</td></tr>@empty<tr><td colspan="7">No matching records.</td></tr>@endforelse</tbody></table></section>

        <section class="section"><h2>Review Classification Summary</h2><table><thead><tr><th>Classification</th><th>Applications</th></tr></thead><tbody>@foreach ($report['classifications'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['count'] }}</td></tr>@endforeach</tbody></table></section>

        <section class="section"><h2>Adviser &amp; Reviewer Summary</h2><table><thead><tr><th>Institute</th><th>Research Advisers</th><th>Reviewer-enabled Advisers</th></tr></thead><tbody>@forelse ($report['adviser_reviewer_summary'] as $row)<tr><td>{{ $row['institute'] }}</td><td>{{ $row['advisers'] }}</td><td>{{ $row['reviewers'] }}</td></tr>@empty<tr><td colspan="3">No matching records.</td></tr>@endforelse</tbody></table></section>

        <section><h2>Reviewer Workload</h2><table><thead><tr><th>Reviewer</th><th>Institute</th><th>Expedited</th><th>Full Board</th><th>Total</th><th>Completed</th><th>Pending</th><th>Overdue</th></tr></thead><tbody>@forelse ($report['reviewer_workload'] as $row)<tr><td>{{ $row['reviewer']->name }}</td><td>{{ $row['institute'] }}</td><td>{{ $row['expedited'] }}</td><td>{{ $row['full_board'] }}</td><td>{{ $row['total'] }}</td><td>{{ $row['completed'] }}</td><td>{{ $row['pending'] }}</td><td>{{ $row['overdue'] }}</td></tr>@empty<tr><td colspan="8">No matching records.</td></tr>@endforelse</tbody></table></section>

        <section><h2>Filtered Applications (Double-blind)</h2><table><thead><tr><th>Application Code</th><th>Research Title</th><th>Institute</th><th>Review Type</th><th>Workflow Status</th><th>Certificate Status</th><th>Submitted</th></tr></thead><tbody>@forelse ($report['applications'] as $row)@php($application = $row['application'])<tr><td>{{ $application->application_code }}</td><td>{{ $application->research_title }}</td><td>{{ $application->institution }}</td><td>{{ $application->review_type ? App\Enums\ReviewType::tryFrom((string) $application->review_type)?->label() : 'Not classified' }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $row['certificate_status'] }}</td><td>{{ $application->submitted_at?->format('M j, Y g:i A') ?? '—' }}</td></tr>@empty<tr><td colspan="7">No matching records.</td></tr>@endforelse</tbody></table></section>

        <section><h2>Certificate-Released Applicants</h2><table><thead><tr><th>Applicant</th><th>Institutional ID</th><th>Institute</th><th>Released Applications</th></tr></thead><tbody>@forelse ($report['visible_applicants'] as $applicant)<tr><td>{{ $applicant->name }}</td><td>{{ $applicant->institutional_identifier }}</td><td>{{ $applicant->institution }}</td><td>{{ $applicant->released_application_count }}</td></tr>@empty<tr><td colspan="4">No identities are eligible for release in this reporting scope.</td></tr>@endforelse</tbody></table></section>

        <p class="privacy">Privacy boundary: applicant identity is included only when ECRATS verifies that every configured personalized certificate for the displayed application has been released or claimed.</p>
    </main>
</body>
</html>
