@extends('layouts.dashboard')

@section('content')
    {{-- Normalize the server-authoritative validation result once so the message, button, and modal cannot disagree. --}}
    @php
        // Collect safe request-validation messages and categorized workbook results from the current response only.
        $requestValidationErrors = collect($errors->all())->filter()->unique()->values()->all();
        $invalidRows = $preview['invalid_rows'] ?? [];
        $importWarnings = $preview['warnings'] ?? [];
        $duplicateRows = $preview['duplicate_rows'] ?? [];
        $activeExistingAccounts = $preview['active_existing_accounts'] ?? [];
        $archivedAccounts = $preview['archived_accounts'] ?? [];
        $restoredAccounts = $preview['restored_accounts'] ?? [];
        $restorationConflicts = $preview['restoration_conflicts'] ?? [];
        $canRestoreArchived = auth()->user()->role === \App\Enums\UserRole::ResLead
            && filled($preview['preview_token'] ?? null);

        // An error state is reserved for failed request or row validation, while other categories remain reviewable results.
        $hasImportErrors = $requestValidationErrors !== [] || $invalidRows !== [];
        $hasImportResults = $hasImportErrors
            || $importWarnings !== []
            || $duplicateRows !== []
            || $activeExistingAccounts !== []
            || $archivedAccounts !== []
            || $restoredAccounts !== []
            || $restorationConflicts !== [];
    @endphp

    <div class="dashboard-page identity-management-page">
        {{-- Page heading identifies the selected role-specific workbook and preserves back navigation. --}}
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Excel Bulk Import: {{ $selectedType['label'] }}</h1><p>Validate the official workbook and review every result before creating accounts.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.create') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        {{-- The two-column workspace keeps official instructions separate from the upload action. --}}
        <div class="identity-import-grid">
            {{-- Official-template guidance describes the trusted workbook contract without exposing internal paths. --}}
            <section class="identity-import-guide">
                <div class="identity-dialog-heading"><span class="identity-section-icon"><x-dashboard.icon name="file-spreadsheet" size="24" /></span><div><h2>Official Template</h2><p>Use the current template for {{ Str::lower($selectedType['label']) }} accounts only.</p></div></div>
                <div class="identity-template-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.template', ['account_type' => $selectedType['key']]) }}"><x-dashboard.icon name="download" size="18" /><span>Download Excel Template</span></a>
                </div>
                <dl>
                    <div><dt>Required columns</dt><dd>{{ collect($selectedType['required_headers'])->join(', ') }}</dd></div>
                    <div><dt>Accepted format</dt><dd>.xlsx only</dd></div>
                    <div><dt>Limits</dt><dd>{{ \App\Services\Identity\UserBulkImportService::MAX_ROWS }} account rows and {{ \App\Services\Identity\UserBulkImportService::MAX_FILE_KILOBYTES / 1024 }} MB per file</dd></div>
                    <div><dt>Workbook rules</dt><dd>Keep Accounts, Options, and Instructions with their original headers. Do not add formulas, macros, embedded files, or external workbook links.</dd></div>
                    <div><dt>Example row</dt><dd>Row 2 is ignored only while the exact Example Row Marker remains in Instructions. Enter new accounts from Row 3.</dd></div>
                </dl>
            </section>

            {{-- Upload form submits one CSRF-protected workbook to the authorized and rate-limited validation route. --}}
            <form class="identity-import-form" method="POST" action="{{ route($routeBase.'.import.store') }}" enctype="multipart/form-data" data-import-validation-form>
                @csrf
                <input type="hidden" name="account_type" value="{{ $selectedType['key'] }}">
                <span class="identity-import-icon"><x-dashboard.icon name="upload" size="34" /></span>
                <h2>Upload Excel File</h2>
                <p>One approved .xlsx file is validated first. Selecting a file never creates accounts.</p>
                <label class="identity-file-picker" for="accounts_file">Upload Excel File</label>
                <input id="accounts_file" name="accounts_file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required data-account-import-file>
                <span class="identity-file-name" data-account-import-name>No file selected</span>

                {{-- The main surface intentionally gives only the approved generic failure message. --}}
                @if ($hasImportErrors)
                    <div class="identity-validation-summary identity-import-general-error" role="alert" data-import-general-error>
                        <strong>An error occurred.</strong>
                    </div>
                @endif

                {{-- Validate and Show Errors remain stable while the badge overlays without shifting either button. --}}
                <div class="identity-import-actions">
                    <button class="identity-button identity-button-primary" type="submit">Validate</button>
                    <button
                        class="identity-button identity-button-secondary identity-import-errors-button {{ $hasImportErrors ? 'has-errors is-attention' : '' }}"
                        type="button"
                        data-import-errors-open
                        aria-label="{{ $hasImportErrors ? 'Show Errors. Validation errors available' : 'Show Errors' }}"
                    >
                        <span>Show Errors</span>
                        <span class="identity-import-error-badge" aria-hidden="true">!</span>
                        @if ($hasImportErrors)<span class="sr-only">Validation errors available</span>@endif
                    </button>
                </div>
            </form>
        </div>

        @if ($preview)
            {{-- Preview remains read-only and disappears client-side when a different workbook is selected. --}}
            <section class="identity-preview-panel" aria-labelledby="import-preview-title" data-import-preview>
                <div class="identity-panel-heading"><div><h2 id="import-preview-title">Import Preview</h2><p>Only rows marked valid are eligible for account creation.</p></div></div>
                <div class="identity-preview-stats">
                    <div><strong>{{ $preview['total_count'] }}</strong><span>Total rows</span></div>
                    <div><strong>{{ $preview['valid_count'] }}</strong><span>Valid</span></div>
                    <div><strong>{{ $preview['invalid_count'] }}</strong><span>Invalid</span></div>
                    <div><strong>{{ $preview['duplicate_count'] }}</strong><span>Duplicates</span></div>
                    <div><strong>{{ $preview['active_existing_count'] }}</strong><span>Active existing</span></div>
                    <div><strong>{{ $preview['archived_count'] }}</strong><span>Archived found</span></div>
                    <div><strong>{{ $preview['restored_count'] }}</strong><span>Restored</span></div>
                    <div><strong>{{ $preview['estimated_create_count'] }}</strong><span>Estimated create</span></div>
                </div>

                @if ($activeExistingAccounts !== [])
                    {{-- Active matches remain visible in a dedicated non-restorable account container. --}}
                    <section class="identity-existing-panel" aria-labelledby="active-existing-heading">
                        <div class="identity-existing-heading">
                            <div>
                                <h3 id="active-existing-heading">Active Existing Accounts ({{ count($activeExistingAccounts) }})</h3>
                                <p>These institutional identifiers, email addresses or usernames already belong to active accounts. They will not be recreated or overwritten.</p>
                            </div>
                        </div>
                        <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Active existing accounts" tabindex="0">
                            <table class="identity-user-table identity-existing-table">
                                <thead><tr><th>Excel Row</th><th>Name</th><th>Institutional ID</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach ($activeExistingAccounts as $account)
                                        @php
                                            // Non-archived matches retain their authoritative active, inactive, or pending setup state.
                                            $accountStatus = \App\Enums\AccountStatus::tryFrom($account['account_status']);
                                            $accountStatusTone = match ($accountStatus) {
                                                \App\Enums\AccountStatus::Active => 'success',
                                                \App\Enums\AccountStatus::PendingSetup => 'amber',
                                                default => 'red',
                                            };
                                        @endphp
                                        <tr>
                                            <td>{{ $account['row'] }}</td>
                                            <td>{{ $account['name'] }}</td>
                                            <td>{{ $account['institutional_identifier'] }}</td>
                                            <td>{{ $account['email'] }}</td>
                                            <td>{{ $account['role'] }}</td>
                                            <td><x-dashboard.status-badge :label="$accountStatus?->label() ?? 'Unknown'" :tone="$accountStatusTone" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if ($archivedAccounts !== [])
                    {{-- Archived matches use a separate container and expose restoration only to the RES Lead. --}}
                    <section class="identity-existing-panel identity-archived-panel" aria-labelledby="archived-existing-heading">
                        <div class="identity-existing-heading">
                            <div>
                                <h3 id="archived-existing-heading">Archived Accounts Found ({{ count($archivedAccounts) }})</h3>
                                <p>These credentials belong to accounts that were previously archived. Restore the original accounts to reactivate them and preserve their existing records.</p>
                            </div>
                            @if ($canRestoreArchived)
                                <button
                                    class="identity-button identity-button-secondary"
                                    type="button"
                                    data-restore-confirm
                                    data-restore-all
                                    data-restore-action="{{ route('res.users.import.restore-all') }}"
                                    data-restore-count="{{ count($archivedAccounts) }}"
                                >
                                    <x-dashboard.icon name="refresh" size="17" />
                                    <span>Restore All Flagged Archived Accounts</span>
                                </button>
                            @endif
                        </div>
                        <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Archived accounts found" tabindex="0">
                            <table class="identity-user-table identity-existing-table">
                                <thead><tr><th>Excel Row</th><th>Name</th><th>Institutional ID</th><th>Email</th><th>Role</th><th>Archived Date</th><th>Status</th>@if ($canRestoreArchived)<th>Action</th>@endif</tr></thead>
                                <tbody>
                                    @foreach ($archivedAccounts as $account)
                                        <tr>
                                            <td>{{ $account['row'] }}</td>
                                            <td>{{ $account['name'] }}</td>
                                            <td>{{ $account['institutional_identifier'] }}</td>
                                            <td>{{ $account['email'] }}</td>
                                            <td>{{ $account['role'] }}</td>
                                            <td>{{ filled($account['archived_at']) ? \Illuminate\Support\Carbon::parse($account['archived_at'])->format('M j, Y g:i A') : 'Unavailable' }}</td>
                                            <td><x-dashboard.status-badge label="Archived" tone="neutral" /></td>
                                            @if ($canRestoreArchived)
                                                <td>
                                                    <button
                                                        class="identity-button identity-button-secondary identity-button-compact"
                                                        type="button"
                                                        data-restore-confirm
                                                        data-restore-action="{{ route('res.users.import.restore-account') }}"
                                                        data-restore-user-id="{{ $account['id'] }}"
                                                        data-restore-account-name="{{ $account['name'] }}"
                                                        data-restore-count="1"
                                                    >
                                                        <x-dashboard.icon name="refresh" size="16" />
                                                        <span>Restore</span>
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @unless ($canRestoreArchived)
                            {{-- Advisers receive an escalation message and never receive restoration controls. --}}
                            <p class="identity-archived-guidance">This account was previously archived. Contact the RES Lead to restore the original account.</p>
                        @endunless
                    </section>
                @endif

                @if ($restoredAccounts !== [])
                    {{-- Restored rows move out of the archived category while retaining their original preview row. --}}
                    <section class="identity-existing-panel identity-restored-panel" aria-labelledby="restored-existing-heading">
                        <div class="identity-existing-heading"><div><h3 id="restored-existing-heading">Restored Accounts ({{ count($restoredAccounts) }})</h3><p>The original accounts are available again with their internal IDs and related records preserved.</p></div></div>
                        <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Restored accounts" tabindex="0">
                            <table class="identity-user-table identity-existing-table">
                                <thead><tr><th>Excel Row</th><th>Name</th><th>Institutional ID</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                                <tbody>
                                    @foreach ($restoredAccounts as $account)
                                        @php
                                            // Restored setup-pending accounts retain an amber tone until password setup completes.
                                            $restoredStatus = \App\Enums\AccountStatus::tryFrom($account['account_status']);
                                            $restoredTone = $restoredStatus === \App\Enums\AccountStatus::Active ? 'success' : 'amber';
                                        @endphp
                                        <tr><td>{{ $account['row'] }}</td><td>{{ $account['name'] }}</td><td>{{ $account['institutional_identifier'] }}</td><td>{{ $account['email'] }}</td><td>{{ $account['role'] }}</td><td><x-dashboard.status-badge :label="$restoredStatus?->label() ?? 'Unknown'" :tone="$restoredTone" /></td></tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                @if ($restorationConflicts !== [])
                    {{-- Restoration conflicts remain archived and show only a bounded safe reason. --}}
                    <section class="identity-validation-summary" role="alert">
                        <strong>Restoration conflicts ({{ count($restorationConflicts) }})</strong>
                        @foreach ($restorationConflicts as $conflict)
                            <span>Excel Row {{ $conflict['row'] ?? 'Unknown' }}: {{ $conflict['reason'] }}</span>
                        @endforeach
                    </section>
                @endif

                @if ($preview['valid_count'] > 0)
                    {{-- Valid rows use the shared focusable horizontal-scroll region without displaying error details. --}}
                    <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Valid account rows" tabindex="0">
                        <table class="identity-user-table identity-import-preview-table"><thead><tr><th>Excel Row</th><th>Name</th><th>Email</th><th>Institutional ID</th><th>Generated Username</th></tr></thead><tbody>
                            @foreach ($preview['valid_rows'] as $row)
                                <tr>
                                    <td>{{ $row['row'] }}</td>
                                    <td><span class="identity-table-truncate" data-table-tooltip="{{ $row['name'] }}" tabindex="0">{{ $row['name'] }}</span></td>
                                    <td><span class="identity-table-truncate" data-table-tooltip="{{ $row['email'] }}" tabindex="0">{{ $row['email'] }}</span></td>
                                    <td><span class="identity-table-truncate" data-table-tooltip="{{ $row['institutional_identifier'] }}" tabindex="0">{{ $row['institutional_identifier'] }}</span></td>
                                    <td><strong>{{ $row['generated_username'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody></table>
                    </div>
                    {{-- Confirmation submits only the private, user-bound preview token produced by server validation. --}}
                    <form class="identity-preview-confirm" method="POST" action="{{ route($routeBase.'.import.confirm') }}" data-confirm-import>
                        @csrf
                        <input type="hidden" name="import_token" value="{{ $preview['preview_token'] }}">
                        <p>Confirmation creates {{ $preview['estimated_create_count'] }} valid pending {{ Str::plural('account', $preview['estimated_create_count']) }}. Invalid, duplicate, and existing rows remain excluded.</p>
                        <button class="identity-button identity-button-primary" type="submit">Confirm Account Creation</button>
                    </form>
                @else
                    {{-- A no-create result distinguishes actionable archives from ordinary validation corrections. --}}
                    <div class="identity-validation-summary identity-validation-summary-neutral" role="status"><strong>No new accounts to create.</strong><span>{{ $archivedAccounts !== [] ? 'Restore eligible archived accounts or validate another workbook.' : 'Review the result categories and validate another workbook when needed.' }}</span></div>
                @endif
            </section>
        @endif

        {{-- Validation modal is the only interface that renders complete safe validation-result details. --}}
        <section class="identity-mode-overlay" data-import-errors-dialog hidden>
            <div class="identity-mode-dialog identity-error-dialog" role="dialog" aria-modal="true" aria-labelledby="import-errors-title" tabindex="-1">
                {{-- Close control restores focus to the Show Errors trigger for keyboard users. --}}
                <button class="identity-mode-close" type="button" aria-label="Close import results" data-import-errors-close><x-dashboard.icon name="x" size="20" /></button>
                {{-- Modal heading explains that every detail refers to the submitted workbook row. --}}
                <div class="identity-error-dialog-heading">
                    <h2 id="import-errors-title">Excel Validation Results</h2>
                    <p>Results are separated by category and reference the original Excel row.</p>
                </div>

                {{-- Scrollable body separates request errors, row errors, warnings, duplicates, and existing accounts. --}}
                <div class="identity-error-dialog-body" data-import-errors-body>
                    @if ($requestValidationErrors !== [])
                        {{-- Request-level workbook errors are escaped and never include raw exception details. --}}
                        <section class="identity-import-category" aria-labelledby="request-errors-heading" data-import-result-category>
                            <h3 id="request-errors-heading">Errors ({{ count($requestValidationErrors) }})</h3>
                            @foreach ($requestValidationErrors as $message)
                                <article class="identity-import-error">
                                    <strong>Workbook validation</strong>
                                    <dl class="identity-import-error-details">
                                        <div><dt>Field</dt><dd>Excel file</dd></div>
                                        <div><dt>Submitted value</dt><dd>Not displayed</dd></div>
                                        <div><dt>Reason</dt><dd>{{ $message }}</dd></div>
                                        <div><dt>Expected</dt><dd>A current official, structurally valid .xlsx workbook.</dd></div>
                                    </dl>
                                </article>
                            @endforeach
                        </section>
                    @endif

                    @if ($invalidRows !== [])
                        {{-- Row-level errors retain safe submitted values and expected formats from server validation. --}}
                        <section class="identity-import-category" aria-labelledby="invalid-rows-heading" data-import-result-category>
                            <h3 id="invalid-rows-heading">Errors ({{ count($invalidRows) }})</h3>
                            @foreach ($invalidRows as $row)
                                <article class="identity-import-error">
                                    <strong>Excel Row {{ $row['row'] }}</strong>
                                    @foreach ($row['errors'] as $error)
                                        <dl class="identity-import-error-details">
                                            <div><dt>Field</dt><dd>{{ $error['field'] }}</dd></div>
                                            <div><dt>Submitted value</dt><dd>{{ filled($error['value'] ?? null) ? $error['value'] : 'Blank' }}</dd></div>
                                            <div><dt>Reason</dt><dd>{{ $error['reason'] }}</dd></div>
                                            <div><dt>Expected</dt><dd>{{ $error['expected'] }}</dd></div>
                                        </dl>
                                    @endforeach
                                </article>
                            @endforeach
                        </section>
                    @endif

                    @if ($importWarnings !== [])
                        {{-- Warnings remain distinct from blocking validation errors. --}}
                        <section class="identity-import-category identity-import-warnings" aria-labelledby="import-warnings-heading" data-import-result-category><h3 id="import-warnings-heading">Warnings ({{ count($importWarnings) }})</h3>@foreach ($importWarnings as $warning)<p>{{ $warning }}</p>@endforeach</section>
                    @endif

                    @if ($duplicateRows !== [])
                        {{-- Workbook duplicates are listed separately because they are skipped rather than overwritten. --}}
                        <section class="identity-import-category" aria-labelledby="duplicate-rows-heading" data-import-result-category><h3 id="duplicate-rows-heading">Duplicate Rows ({{ count($duplicateRows) }})</h3>@foreach ($duplicateRows as $row)<p><strong>Excel Row {{ $row['row'] }}:</strong> {{ $row['reason'] }}</p>@endforeach</section>
                    @endif

                    @if ($activeExistingAccounts !== [])
                        {{-- Active account messages remain separate from archived restoration candidates. --}}
                        <section class="identity-import-category" aria-labelledby="active-results-heading" data-import-result-category><h3 id="active-results-heading">Active Existing Accounts ({{ count($activeExistingAccounts) }})</h3>@foreach ($activeExistingAccounts as $row)<p><strong>Excel Row {{ $row['row'] }}:</strong> {{ $row['reason'] }}</p>@endforeach</section>
                    @endif

                    @if ($archivedAccounts !== [])
                        {{-- Archived result messages explain restoration without merging them into active matches. --}}
                        <section class="identity-import-category" aria-labelledby="archived-results-heading" data-import-result-category><h3 id="archived-results-heading">Archived Accounts Found ({{ count($archivedAccounts) }})</h3>@foreach ($archivedAccounts as $row)<p><strong>Excel Row {{ $row['row'] }}:</strong> {{ $row['reason'] }}</p>@endforeach</section>
                    @endif

                    {{-- Empty state is shown initially, after a successful validation, or after the selected file changes. --}}
                    <div class="identity-empty-state identity-error-empty" data-import-errors-empty @if ($hasImportResults) hidden @endif><span><x-dashboard.icon name="check" size="38" /></span><strong>No errors yet.</strong></div>
                </div>

                {{-- Footer action closes the modal without clearing unresolved server validation results. --}}
                <div class="identity-dialog-actions"><button class="identity-button identity-button-primary" type="button" data-import-errors-close>Close</button></div>
            </div>
        </section>

        @if ($canRestoreArchived)
            {{-- One accessible confirmation dialog serves both individual and current-preview bulk restoration. --}}
            <section class="identity-mode-overlay" data-restore-dialog hidden>
                <div class="identity-mode-dialog identity-restore-dialog" role="dialog" aria-modal="true" aria-labelledby="restore-dialog-title" tabindex="-1">
                    <button class="identity-mode-close" type="button" aria-label="Close restoration confirmation" data-restore-cancel><x-dashboard.icon name="x" size="20" /></button>
                    <span class="identity-section-icon"><x-dashboard.icon name="refresh" size="25" /></span>
                    <h2 id="restore-dialog-title" data-restore-title>Restore Archived Account</h2>
                    <p data-restore-message>The original account will be reactivated without creating a duplicate.</p>
                    <ul class="identity-restore-notes">
                        <li>Existing applications and records will remain connected.</li>
                        <li>The original internal user ID will be preserved.</li>
                        <li>No password will be revealed or replaced.</li>
                    </ul>
                    <form method="POST" action="{{ route('res.users.import.restore-all') }}" data-restore-form>
                        @csrf
                        <input type="hidden" name="import_token" value="{{ $preview['preview_token'] }}">
                        <input type="hidden" name="archived_user_id" value="" data-restore-user-input disabled>
                        <div class="identity-dialog-actions">
                            <button class="identity-button identity-button-secondary" type="button" data-restore-cancel>Cancel</button>
                            <button class="identity-button identity-button-primary" type="submit" data-restore-submit>Restore Account</button>
                        </div>
                    </form>
                </div>
            </section>
        @endif
    </div>
@endsection
