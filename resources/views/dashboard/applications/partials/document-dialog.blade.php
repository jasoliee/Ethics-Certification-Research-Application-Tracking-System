{{-- The secure document dialog previews only controller-approved formats and otherwise offers a download fallback. --}}
<section class="application-modal-backdrop" data-document-dialog hidden>
    <div class="application-modal application-document-modal" role="dialog" aria-modal="true" aria-labelledby="document-dialog-title" tabindex="-1">
        {{-- Close controls restore focus to the document row that opened the dialog. --}}
        <button class="application-modal-close" type="button" aria-label="Close document viewer" data-document-close>
            <x-dashboard.icon name="x" size="20" />
        </button>
        <header class="application-modal-heading">
            <span class="application-modal-icon"><x-dashboard.icon name="file-search" size="24" /></span>
            <div class="application-document-heading-copy"><h2 id="document-dialog-title" data-document-title data-table-tooltip="Document" tabindex="0">Document</h2><p data-document-meta>Selected requirement document</p></div>
        </header>
        <div class="application-document-toolbar" role="toolbar" aria-label="Document view controls" data-document-toolbar hidden>
            <button type="button" data-document-zoom-out aria-label="Zoom out">−</button>
            <output data-document-render-control data-document-zoom aria-live="polite">100%</output>
            <button type="button" data-document-render-control data-document-zoom-in aria-label="Zoom in">+</button>
            <button type="button" data-document-render-control data-document-fit-width>Fit width</button>
            <button type="button" data-document-render-control data-document-fit-page>Fit page</button>
            <button type="button" data-document-render-control data-document-reset>Reset</button>
            <button type="button" data-document-render-control data-document-rotate hidden>Rotate</button>
            <button type="button" data-document-fullscreen>Fullscreen</button>
            <a href="#" target="_blank" rel="noopener" data-document-open-tab>Open in new tab</a>
        </div>
        {{-- Preview elements receive only a secure controller URL and never a private storage path. --}}
        <div class="application-document-preview" data-document-preview>
            <iframe title="Secure document preview" data-document-frame hidden></iframe>
            <img alt="Secure document preview" data-document-image hidden>
            <div class="application-document-fallback" data-document-fallback hidden>
                <x-dashboard.icon name="download" size="34" />
                <strong>Preview unavailable</strong>
                <p>This file type is available through the secure download action.</p>
            </div>
        </div>
        {{-- Replace and Download remain reachable below an internally scrolling preview. --}}
        <div class="application-modal-actions">
            <button class="dashboard-outline-action" type="button" data-document-replace hidden>
                <x-dashboard.icon name="upload" size="18" />
                <span>Replace</span>
            </button>
            <a class="dashboard-primary-action" href="#" data-document-download>
                <x-dashboard.icon name="download" size="18" />
                <span>Download</span>
            </a>
        </div>
    </div>
</section>
