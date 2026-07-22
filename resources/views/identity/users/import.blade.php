@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Bulk Upload: {{ $selectedType['label'] }}</h1><p>Validate every row and confirm the preview before accounts are created.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.create') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        <div class="identity-import-grid">
            <section class="identity-import-guide">
                <div class="identity-dialog-heading"><span class="identity-section-icon"><x-dashboard.icon name="file-text" size="24" /></span><div><h2>Official Templates</h2><p>Use the template for {{ Str::lower($selectedType['label']) }} accounts only.</p></div></div>
                <div class="identity-template-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.template', ['format' => 'csv', 'account_type' => $selectedType['key']]) }}"><x-dashboard.icon name="download" size="18" /><span>CSV Template</span></a>
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.template', ['format' => 'xlsx', 'account_type' => $selectedType['key']]) }}"><x-dashboard.icon name="download" size="18" /><span>Excel Template</span></a>
                </div>
                <dl>
                    <div><dt>Template version</dt><dd>{{ \App\Services\Identity\AccountTypeCatalog::TEMPLATE_VERSION }}</dd></div>
                    <div><dt>Required columns</dt><dd>{{ collect($selectedType['required_headers'])->join(', ') }}</dd></div>
                    <div><dt>Accepted formats</dt><dd>UTF-8 CSV or macro-free XLSX</dd></div>
                    <div><dt>Limits</dt><dd>{{ \App\Services\Identity\UserBulkImportService::MAX_ROWS }} rows and 2 MB per file</dd></div>
                </dl>
            </section>

            <form class="identity-import-form" method="POST" action="{{ route($routeBase.'.import.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="account_type" value="{{ $selectedType['key'] }}">
                <span class="identity-import-icon"><x-dashboard.icon name="upload" size="34" /></span>
                <h2>Upload Account File</h2>
                <p>Accounts are not created until a valid preview is confirmed.</p>
                <label class="identity-file-picker" for="accounts_file">Choose CSV or Excel File</label>
                <input id="accounts_file" name="accounts_file" type="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-account-import-file>
                <span class="identity-file-name" data-account-import-name>No file selected</span>
                @error('accounts_file')<span class="identity-field-error" role="alert">{{ $message }}</span>@enderror
                <button class="identity-button identity-button-primary" type="submit">Validate and Preview</button>
            </form>
        </div>

        @if ($preview)
            <section class="identity-preview-panel" aria-labelledby="import-preview-title">
                <div class="identity-panel-heading"><div><h2 id="import-preview-title">Import Preview</h2><p>Review generated usernames and all validation results.</p></div></div>
                <div class="identity-preview-stats">
                    <div><strong>{{ $preview['total_count'] }}</strong><span>Total rows</span></div>
                    <div><strong>{{ $preview['valid_count'] }}</strong><span>Valid</span></div>
                    <div><strong>{{ $preview['invalid_count'] }}</strong><span>Invalid</span></div>
                    <div><strong>{{ $preview['duplicate_count'] }}</strong><span>Duplicates</span></div>
                    <div><strong>{{ $preview['existing_conflict_count'] }}</strong><span>Existing conflicts</span></div>
                </div>

                @if ($preview['invalid_count'] > 0)
                    <div class="identity-validation-summary" role="alert"><strong>Import cannot be confirmed.</strong><span>Correct every invalid row, then upload the file again.</span></div>
                    <div class="identity-table-scroll">
                        <table class="identity-user-table"><thead><tr><th>Row</th><th>Validation errors</th></tr></thead><tbody>
                            @foreach ($preview['invalid_rows'] as $row)
                                <tr><td>{{ $row['row'] }}</td><td>{{ implode(' ', $row['errors']) }}</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.errors', $preview['error_token']) }}"><x-dashboard.icon name="download" size="18" /><span>Download Error Report</span></a>
                @else
                    <div class="identity-table-scroll">
                        <table class="identity-user-table"><thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Institutional ID</th><th>Generated Username</th></tr></thead><tbody>
                            @foreach ($preview['valid_rows'] as $row)
                                <tr><td>{{ $row['row'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['email'] }}</td><td>{{ $row['institutional_identifier'] }}</td><td><strong>{{ $row['generated_username'] }}</strong></td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                    <form class="identity-preview-confirm" method="POST" action="{{ route($routeBase.'.import.confirm') }}" data-confirm-import>
                        @csrf
                        <input type="hidden" name="import_token" value="{{ $preview['preview_token'] }}">
                        <p>Confirmation creates all {{ $preview['valid_count'] }} pending accounts and sends an individual setup email to each address.</p>
                        <button class="identity-button identity-button-primary" type="submit">Confirm Account Creation</button>
                    </form>
                @endif
            </section>
        @endif
    </div>
@endsection
