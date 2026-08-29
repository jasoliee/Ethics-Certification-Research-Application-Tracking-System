<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anonymous Applicant Feedback Report</title>
    <style>
        @page { size: A4 portrait; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #17202b; font: 11px/1.4 Arial, sans-serif; }
        body::before { content: ""; position: fixed; inset: 0; z-index: -1; opacity: .08; background: center / cover no-repeat; background-image: url({!! json_encode($worksheetBackground) !!}); }
        h1 { margin: 0; color: #087241; font-size: 21px; }
        h2 { margin: 16px 0 6px; color: #075f38; font-size: 14px; break-after: avoid; }
        p { margin: 4px 0; }
        header { border-bottom: 2px solid #087241; padding-bottom: 8px; }
        .summary { display: flex; gap: 8px; margin: 12px 0; }
        .summary div { flex: 1; border: 1px solid #b9d6c8; background: rgba(255,255,255,.92); padding: 8px; }
        .summary strong { display: block; color: #087241; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.92); }
        th, td { border: 1px solid #aeb9b3; padding: 5px; text-align: left; vertical-align: top; }
        th { background: #e8f4ee; color: #075f38; }
        tr { break-inside: avoid; }
        .privacy { margin-top: 10px; padding: 7px; background: rgba(237,249,243,.94); border: 1px solid #b9d6c8; }
        .no-print { margin: 12px; padding: 8px 14px; border: 0; border-radius: 4px; background: #087241; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <button class="no-print" type="button" onclick="window.print()">Print Survey Report</button>
    <main>
        <header><h1>ECRATS Anonymous Applicant Feedback Report</h1><p>Research Ethics Unit · Generated {{ $generatedAt->format('M j, Y g:i A') }}</p><p>{{ collect($filters)->map(fn ($value, $key) => Str::headline($key).': '.$value)->implode(' | ') ?: 'All records' }}</p></header>
        <div class="summary"><div><strong>{{ $surveySummary['response_count'] }}</strong>Current-questionnaire responses</div><div><strong>{{ $surveySummary['overall_average'] === null ? '—' : number_format($surveySummary['overall_average'], 2).' / 5' }}</strong>Overall average</div><div><strong>{{ $surveySummary['legacy_response_count'] }}</strong>Preserved legacy responses excluded</div></div>
        @forelse ($surveySummary['sections'] as $section)
            <section><h2>{{ $section['title'] }} · {{ $section['average'] === null ? 'No data' : number_format($section['average'], 2).' / 5' }}</h2><table><thead><tr><th>Evaluation Statement</th><th>Average</th><th>Responses</th></tr></thead><tbody>@foreach ($section['questions'] as $question)<tr><td>{{ $question['label'] }}</td><td>{{ $question['average'] === null ? '—' : number_format($question['average'], 2).' / 5' }}</td><td>{{ $question['response_count'] }}</td></tr>@endforeach</tbody></table></section>
        @empty
            <p>No feedback data matches the selected filters.</p>
        @endforelse
        <p class="privacy">This report contains anonymous aggregates only. Individual responses, free-text comments, and applicant identities are intentionally excluded.</p>
    </main>
</body>
</html>
