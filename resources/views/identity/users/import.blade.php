@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Bulk Upload: {{ $selectedType['label'] }}</h1><p>Validate every row and confirm the preview before accounts are created.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.create') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        <div class="identity-import-grid">
            <section class="identity-import-guide">
                <div class="identity-dialog-heading"><span class="identity-section-icon"><x-dashboard.icon name="file-text" size="24" /></span><div><h2>Official Template</h2><p>Use the template for {{ Str::lower($selectedType['label']) }} accounts only.</p></div></div>
                <div class="identity-template-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.template', ['format' => 'csv', 'account_type' => $selectedType['key']]) }}"><x-dashboard.icon name="download" size="18" /><span>CSV Template</span></a>
                </div>
                <dl>
                    <div><dt>Required columns</dt><dd>{{ collect($selectedType['required_headers'])->join(', ') }}</dd></div>
                    <div><dt>Accepted format</dt><dd>UTF-8 CSV</dd></div>
                    <div><dt>Limits</dt><dd>{{ \App\Services\Identity\UserBulkImportService::MAX_ROWS }} rows and 2 MB per file</dd></div>
                    <div><dt>Example row</dt><dd>The sample directly below the header is ignored automatically.</dd></div>
                </dl>
            </section>

            <form class="identity-import-form" method="POST" action="{{ route($routeBase.'.import.store') }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="account_type" value="{{ $selectedType['key'] }}">
                <span class="identity-import-icon"><x-dashboard.icon name="upload" size="34" /></span>
                <h2>Upload Account File</h2>
                <p>Accounts are not created until a valid preview is confirmed.</p>
                <label class="identity-file-picker" for="accounts_file">Upload CSV File</label>
                <input id="accounts_file" name="accounts_file" type="file" accept=".csv,text/csv" required data-account-import-file>
                <span class="identity-file-name" data-account-import-name>No file selected</span>
                @error('accounts_file')<span class="identity-field-error" role="alert">{{ $message }}</span>@enderror
                <div class="identity-import-actions">
                    <button class="identity-button identity-button-primary" type="submit">Validate</button>
                    <button class="identity-button identity-button-secondary" type="button" data-import-errors-open>Show Errors</button>
                </div>
            </form>
        </div>

        @if ($preview)
            <section class="identity-preview-panel" aria-labelledby="import-preview-title">
                <div class="identity-panel-heading"><div><h2 id="import-preview-title">Import Preview</h2><p>Review generated usernames and validation results.</p></div></div>
                <div class="identity-preview-stats">
                    <div><strong>{{ $preview['total_count'] }}</strong><span>Total rows</span></div>
                    <div><strong>{{ $preview['valid_count'] }}</strong><span>Valid</span></div>
                    <div><strong>{{ $preview['invalid_count'] }}</strong><span>Invalid</span></div>
                    <div><strong>{{ $preview['skipped_count'] }}</strong><span>Skipped</span></div>
                    <div><strong>{{ $preview['duplicate_count'] }}</strong><span>Duplicates</span></div>
                    <div><strong>{{ $preview['existing_conflict_count'] }}</strong><span>Existing accounts</span></div>
                </div>

                @if ($preview['invalid_count'] > 0)
                    <div class="identity-validation-summary" role="alert"><strong>Import cannot be confirmed.</strong><span>Correct every invalid row, then upload the file again.</span></div>
                @elseif ($preview['valid_count'] === 0)
                    <div class="identity-validation-summary identity-validation-summary-neutral" role="status"><strong>No new accounts to create.</strong><span>Every data row was already present or repeated in the file.</span></div>
                @else
                    <div class="identity-table-scroll">
                        <table class="identity-user-table"><thead><tr><th>Row</th><th>Name</th><th>Email</th><th>Institutional ID</th><th>Generated Username</th></tr></thead><tbody>
                            @foreach ($preview['valid_rows'] as $row)
                                <tr>
                                    <td>{{ $row['row'] }}</td>
                                    <td><span class="identity-table-truncate" data-table-tooltip="{{ $row['name'] }}">{{ $row['name'] }}</span></td>
                                    <td><span class="identity-table-truncate" data-table-tooltip="{{ $row['email'] }}">{{ $row['email'] }}</span></td>
                                    <td>{{ $row['institutional_identifier'] }}</td>
                                    <td><strong>{{ $row['generated_username'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody></table>
                    </div>
                    <form class="identity-preview-confirm" method="POST" action="{{ route($routeBase.'.import.confirm') }}" data-confirm-import>
                        @csrf
                        <input type="hidden" name="import_token" value="{{ $preview['preview_token'] }}">
                        <p>Confirmation creates all {{ $preview['valid_count'] }} new pending accounts and sends an individual setup email to each address.</p>
                        <button class="identity-button identity-button-primary" type="submit">Confirm Account Creation</button>
                    </form>
                @endif

                @if ($preview['skipped_count'] > 0)
                    <div class="identity-skipped-section">
                        <div class="identity-panel-heading"><div><h3>Skipped Rows</h3><p>These rows will not create duplicate accounts.</p></div></div>
                        <div class="identity-table-scroll">
                            <table class="identity-user-table identity-import-result-table"><thead><tr><th>Row</th><th>Reason</th></tr></thead><tbody>
                                @foreach ($preview['skipped_rows'] as $row)
                                    <tr><td>{{ $row['row'] }}</td><td>{{ $row['reason'] }}</td></tr>
                                @endforeach
                            </tbody></table>
                        </div>
                    </div>
                @endif
            </section>
        @endif

        <section class="identity-mode-overlay" data-import-errors-dialog hidden>
            <div class="identity-mode-dialog identity-error-dialog" role="dialog" aria-modal="true" aria-labelledby="import-errors-title" tabindex="-1">
                <button class="identity-mode-close" type="button" aria-label="Close import errors" data-import-errors-close><x-dashboard.icon name="x" size="20" /></button>
                <div class="identity-error-dialog-heading">
                    <h2 id="import-errors-title">CSV Validation Errors</h2>
                    <p>Each error includes its CSV row and the expected value or format.</p>
                </div>

                <div class="identity-error-dialog-body">
                    @if (($preview['invalid_count'] ?? 0) > 0)
                        @foreach ($preview['invalid_rows'] as $row)
                            <article class="identity-import-error">
                                <strong>Row {{ $row['row'] }}</strong>
                                <ul>
                                    @foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach
                                </ul>
                            </article>
                        @endforeach
                    @else
                        <div class="identity-empty-state identity-error-empty">
                            <span><x-dashboard.icon name="check" size="38" /></span>
                            <strong>No errors yet.</strong>
                        </div>
                    @endif
                </div>

                <div class="identity-dialog-actions">
                    @if (filled($preview['error_token'] ?? null))
                        <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.errors', $preview['error_token']) }}"><x-dashboard.icon name="download" size="18" /><span>Download Error Report</span></a>
                    @endif
                    <button class="identity-button identity-button-primary" type="button" data-import-errors-close>Done</button>
                </div>
            </div>
        </section>
    </div>
@endsection
