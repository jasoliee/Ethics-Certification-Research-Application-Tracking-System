@php
    $document = $item['document'];
    $requirement = $item['requirement'];
@endphp

<article class="application-requirement-row" data-requirement-row data-requirement-id="{{ $requirement->id }}">
    <span class="application-requirement-icon"><x-dashboard.icon :name="$item['icon']" size="25" /></span>
    <div class="application-requirement-copy">
        <div class="application-requirement-title">
            <h2>{{ $requirement->name }}</h2>
            @if ($requirement->is_mandatory)<span>Required</span>@else<span>Optional</span>@endif
        </div>
        @if (filled($requirement->description))
            <p>{{ $requirement->description }}</p>
        @endif
        @if ($document)
            <dl class="application-file-meta">
                <div><dt>Uploaded</dt><dd>{{ $document->uploaded_at?->format('M j, Y g:i A') }}</dd></div>
                <div><dt>Version</dt><dd>{{ $item['version'] }}</dd></div>
            </dl>
        @endif
    </div>
    <div class="application-requirement-actions">
        <x-dashboard.status-badge :label="$item['status']->label()" :tone="$item['status']->tone()" />
        @if ($document)
            <div class="application-current-document">
                <button
                    class="application-document-title"
                    type="button"
                    data-document-open
                    data-document-name="{{ $document->original_file_name }}"
                    data-document-meta="{{ $requirement->name }} - Uploaded {{ $document->uploaded_at?->format('M j, Y g:i A') }}"
                    data-document-preview-kind="{{ $document->mime_type === 'application/pdf' ? 'pdf' : (str_starts_with($document->mime_type, 'image/') ? 'image' : 'download') }}"
                    data-document-preview-url="{{ route('applicant.applications.documents.preview', [$application, $document]) }}"
                    data-document-download-url="{{ route('applicant.applications.documents.download', [$application, $document]) }}"
                    data-document-replace-input="{{ $canUpload ? 'replace_document_'.$requirement->id : '' }}"
                >
                    <x-dashboard.icon :name="$item['icon']" size="18" />
                    <span data-table-tooltip="{{ $document->original_file_name }}">{{ $document->original_file_name }}</span>
                </button>
                @if ($canUpload)
                    <form method="POST" action="{{ route('applicant.applications.documents.destroy', [$application, $document]) }}" data-confirm-document-remove>
                        @csrf
                        @method('DELETE')
                        <button class="application-document-remove" type="submit" aria-label="Remove {{ $document->original_file_name }}" title="Remove uploaded document">
                            <x-dashboard.icon name="x" size="17" />
                        </button>
                    </form>
                @endif
            </div>

            @if ($canUpload)
                <form
                    class="application-document-replace-form"
                    method="POST"
                    action="{{ route('applicant.applications.documents.store', [$application, $requirement]) }}"
                    enctype="multipart/form-data"
                    data-application-upload-form
                    data-requirement-id="{{ $requirement->id }}"
                >
                    @csrf
                    <input
                        id="replace_document_{{ $requirement->id }}"
                        name="document"
                        type="file"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                        required
                        data-document-replace-file
                        data-requirement-file
                        data-requirement-id="{{ $requirement->id }}"
                    >
                </form>
            @endif
        @elseif ($canUpload)
            <form
                class="application-upload-form"
                method="POST"
                action="{{ route('applicant.applications.documents.store', [$application, $requirement]) }}"
                enctype="multipart/form-data"
                data-application-upload-form
                data-requirement-id="{{ $requirement->id }}"
            >
                @csrf
                <input
                    id="document_{{ $requirement->id }}"
                    name="document"
                    type="file"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                    required
                    data-application-file
                    data-requirement-file
                    data-requirement-id="{{ $requirement->id }}"
                >
                <div class="application-file-choice">
                    <span data-application-file-name>No file selected</span>
                    <label class="dashboard-outline-action" for="document_{{ $requirement->id }}"><x-dashboard.icon name="upload" size="17" /><span>Choose File</span></label>
                </div>
                <button class="dashboard-primary-action" type="submit">Upload</button>
            </form>
        @endif
        <span class="application-upload-feedback" role="status" data-upload-feedback></span>
    </div>
</article>
