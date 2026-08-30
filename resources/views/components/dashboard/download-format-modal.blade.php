@props([
    'kind' => 'report',
    'title' => 'Download Report',
    'route',
    'query' => [],
])

<section class="application-modal-backdrop" data-download-format-dialog="{{ $kind }}" hidden>
    <div class="application-modal report-download-modal" role="dialog" aria-modal="true" aria-labelledby="download-{{ $kind }}-title" tabindex="-1">
        <button class="application-modal-close" type="button" aria-label="Close download options" data-download-format-close><x-dashboard.icon name="x" size="20" /></button>
        <header class="application-modal-heading">
            <span class="application-modal-icon"><x-dashboard.icon name="download" size="23" /></span>
            <div><h2 id="download-{{ $kind }}-title">{{ $title }}</h2><p>Choose the file format to download.</p></div>
        </header>
        <div class="report-download-options">
            <a class="dashboard-outline-action" href="{{ route($route, array_merge($query, ['format' => 'xlsx'])) }}"><x-dashboard.icon name="file-text" size="19" /><span>Excel (.xlsx)</span></a>
            <a class="dashboard-outline-action" href="{{ route($route, array_merge($query, ['format' => 'pdf'])) }}"><x-dashboard.icon name="file-text" size="19" /><span>PDF</span></a>
        </div>
        <div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-download-format-close>Cancel</button></div>
    </div>
</section>
