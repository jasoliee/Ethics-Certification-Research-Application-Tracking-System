@if ($replacement)
    <button
        class="dashboard-outline-action revision-file-control revision-upload-complete application-document-title"
        type="button"
        data-revision-file-control
        data-document-open
        data-document-name="{{ $replacement->original_file_name }}"
        data-document-type="{{ $replacement->fileTypeLabel() }}"
        data-document-meta="Version {{ $replacement->document_version }} uploaded {{ $replacement->uploaded_at?->format('M j, Y g:i A') }}"
        data-document-preview-kind="{{ $replacement->previewKind() }}"
        data-document-preview-url="{{ route('applicant.applications.documents.preview', [$application, $replacement]) }}"
        data-document-download-url="{{ route('applicant.applications.documents.download', [$application, $replacement]) }}"
        data-document-replace-input="{{ $inputId }}"
    >
        <x-dashboard.icon name="file-text" size="16" />
        <span data-revision-file-name data-table-tooltip="{{ $replacement->original_file_name }}">{{ $replacement->original_file_name }}</span>
    </button>
@else
    <label class="dashboard-outline-action revision-file-control" for="{{ $inputId }}" data-revision-file-control>
        <x-dashboard.icon name="upload" size="16" />
        <span data-revision-file-name>Choose File</span>
    </label>
@endif
