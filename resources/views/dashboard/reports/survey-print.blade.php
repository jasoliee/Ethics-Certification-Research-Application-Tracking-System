<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anonymous Applicant Feedback Report</title>
    <style>
        @page { size: A4 portrait; margin: 1in; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202b; font: 11px/1.4 Arial, sans-serif; }
        body::before { content: ""; position: fixed; inset: 0; z-index: -1; opacity: .08; background: center / cover no-repeat; background-image: url({!! json_encode($worksheetBackground) !!}); }
        h1 { margin: 0; color: #087241; font-size: 21px; }
        h2 { margin: 16px 0 6px; color: #075f38; font-size: 14px; break-after: avoid; }
        p { margin: 4px 0; }
        header { display: flex; align-items: center; justify-content: space-between; gap: 20px; border-bottom: 2px solid #087241; padding-bottom: 8px; }
        .report-heading-copy { min-width: 0; }
        .report-scope { overflow-wrap: anywhere; }
        .report-generated { color: #526071; }
        .summary { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin: 12px 0; }
        .summary div { border: 1px solid #b9d6c8; background: rgba(255,255,255,.92); padding: 10px; text-align: center; }
        .summary strong { display: block; color: #087241; font-size: 18px; }
        .dashboard-overflow-region { max-width: 100%; overflow-x: auto; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; background: rgba(255,255,255,.92); }
        col.responses, col.average { width: 110px; }
        th, td { border: 1px solid #aeb9b3; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #e8f4ee; color: #075f38; }
        th.numeric, td.numeric { text-align: center; vertical-align: middle; }
        tr { break-inside: avoid; }
        .privacy { margin-top: 10px; padding: 7px; background: rgba(237,249,243,.94); border: 1px solid #b9d6c8; }
        .report-actions { display: flex; gap: 8px; }
        .no-print { flex: 0 0 auto; min-height: 46px; margin: 0; border: 1px solid #087241; border-radius: 5px; padding: 10px 20px; background: #087241; color: #fff; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .no-print.is-secondary { background: #fff; color: #087241; }
        .download-dialog { width: min(440px, calc(100vw - 32px)); border: 1px solid #b9d6c8; border-radius: 8px; padding: 22px; }
        .download-dialog::backdrop { background: rgba(15, 23, 42, .56); }
        .download-dialog h2, .download-dialog > p { text-align: center; }
        .download-dialog h2 { margin-top: 0; }
        .download-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 18px 0; }
        .download-options .no-print { display: inline-flex; align-items: center; justify-content: center; width: 100%; text-align: center; }
        .download-dialog form { display: flex; justify-content: flex-end; }
        @media (max-width: 620px) { header { align-items: stretch; flex-direction: column; } .no-print { align-self: flex-end; } .summary { grid-template-columns: minmax(0, 1fr); } col.responses, col.average { width: 88px; } }
        @media print { .no-print { display: none; } .dashboard-overflow-region { overflow: visible; } }
    </style>
</head>
<body>
    <main>
        <header>
            <div class="report-heading-copy"><h1>ECRATS Anonymous Applicant Feedback Report</h1><p>Research Ethics Unit</p><p class="report-scope">{{ $filterSummary }}</p><p class="report-generated">Generated: {{ $generatedAt->format('M j, Y g:i A') }}</p></div>
            <div class="report-actions"><button class="no-print is-secondary" type="button" onclick="document.getElementById('survey-download-dialog').showModal()">Download Survey</button><button class="no-print" type="button" onclick="window.print()">Print Report</button></div>
        </header>
        <div class="summary"><div><strong>{{ $surveySummary['response_count'] }}</strong>Current-questionnaire responses</div><div><strong>{{ $surveySummary['overall_average'] === null ? '—' : number_format($surveySummary['overall_average'], 2).' / 5' }}</strong>Overall average</div></div>
        @forelse ($surveySummary['sections'] as $section)
            <section><h2>{{ $section['title'] }} · {{ $section['average'] === null ? 'No data' : number_format($section['average'], 2).' / 5' }}</h2><div class="dashboard-overflow-region"><table><colgroup><col><col class="responses"><col class="average"></colgroup><thead><tr><th>Evaluation Statement</th><th class="numeric">Responses</th><th class="numeric">Average</th></tr></thead><tbody>@foreach ($section['questions'] as $question)<tr><td>{{ $question['label'] }}</td><td class="numeric">{{ $question['response_count'] }}</td><td class="numeric">{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td></tr>@endforeach</tbody></table></div></section>
        @empty
            <p>No feedback data matches the selected filters.</p>
        @endforelse
        <p class="privacy">This report contains anonymous aggregates only. Individual responses, free-text comments, and applicant identities are intentionally excluded.</p>
    </main>
    <dialog id="survey-download-dialog" class="download-dialog"><h2>Download Survey</h2><p>Choose the file format to download.</p><div class="download-options"><a class="no-print is-secondary" href="{{ route('res.reports.survey.download', array_merge(array_filter($filters, fn ($value) => filled($value)), ['format' => 'xlsx'])) }}">Excel</a><a class="no-print is-secondary" href="{{ route('res.reports.survey.download', array_merge(array_filter($filters, fn ($value) => filled($value)), ['format' => 'pdf'])) }}">PDF</a></div><form method="dialog"><button class="no-print" type="submit">Close</button></form></dialog>
</body>
</html>
