{{-- The secure document dialog previews only controller-approved formats and otherwise offers a download fallback. --}}
<section class="application-modal-backdrop" data-document-dialog hidden>
    <div class="application-modal application-document-modal" role="dialog" aria-modal="true" aria-labelledby="document-dialog-title" tabindex="-1">
        {{-- Close controls restore focus to the document row that opened the dialog. --}}
        <button class="application-modal-close" type="button" aria-label="Close document viewer" data-document-close>
            <x-dashboard.icon name="x" size="20" />
        </button>
        <header class="application-modal-heading">
            <span class="application-modal-icon"><x-dashboard.icon name="file-search" size="24" /></span>
            <div><h2 id="document-dialog-title" data-document-title>Document</h2><p data-document-meta>Selected requirement document</p></div>
        </header>
        {{-- The iframe receives only a secure preview-controller URL and never a private storage path. --}}
        <div class="application-document-preview" data-document-preview>
            <iframe title="Secure document preview" sandbox data-document-frame hidden></iframe>
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
