<section class="settings-section" aria-labelledby="worksheet-configuration-title">
    <div class="settings-section-heading settings-worksheet-heading">
        <span><x-dashboard.icon name="file-text" size="23" /></span>
        <div>
            <h2 id="worksheet-configuration-title">Worksheet Configuration</h2>
            <p>The saved name and signature apply only to future finalized Protocol Review and Informed Consent worksheet PDFs.</p>
        </div>
    </div>
    <form class="settings-account-form settings-worksheet-form" method="POST" action="{{ route($settingsRouteBase.'.worksheet-signatory.update') }}" enctype="multipart/form-data" data-disable-on-submit>
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_tab" value="worksheet">
        <div class="settings-field">
            <label for="worksheet-signatory-name">Printed Reviewer Name</label>
            <input id="worksheet-signatory-name" name="worksheet_signatory_name" value="{{ old('worksheet_signatory_name', $settingsUser->worksheet_signatory_name ?: $settingsUser->name) }}" maxlength="120" required>
            @error('worksheet_signatory_name', 'worksheetSignatory')<span class="settings-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="settings-worksheet-signature-row">
            <div class="settings-worksheet-current-signature">
                <strong>Current Signature</strong>
                @if ($settingsUser->worksheet_signature_path)
                    <img src="{{ route($settingsRouteBase.'.worksheet-signature.preview') }}" alt="Current worksheet signature">
                @else
                    <span>No signature uploaded</span>
                @endif
            </div>
            <div class="settings-field settings-worksheet-replacement">
                <label class="settings-file-control" for="worksheet-signature" data-settings-file-control>
                    <x-dashboard.icon name="upload" size="17" />
                    <span data-settings-file-label>{{ $settingsUser->worksheet_signature_path ? 'Replace Signature' : 'Upload Signature' }}</span>
                    <input id="worksheet-signature" name="signature" type="file" accept="image/png,.png">
                </label>
                <small>PNG only. Use a transparent background for a clean generated worksheet.</small>
                @error('signature', 'worksheetSignatory')<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
        </div>
        <button class="dashboard-primary-action" type="submit">
            <x-dashboard.icon name="check" size="17" />
            <span>Save Worksheet Configuration</span>
        </button>
    </form>
</section>
