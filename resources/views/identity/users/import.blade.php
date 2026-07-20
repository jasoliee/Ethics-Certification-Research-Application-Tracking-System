@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Bulk Account Import</h1><p>Create a controlled batch of accounts from the formatted CSV template.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        <div class="identity-import-grid">
            {{-- The template and format reference keep spreadsheet preparation predictable for the team. --}}
            <section class="identity-import-guide">
                <div class="identity-dialog-heading"><span class="identity-section-icon"><x-dashboard.icon name="file-text" size="24" /></span><div><h2>CSV Format</h2><p>Use one row per account and keep the header names unchanged.</p></div></div>
                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.template') }}"><x-dashboard.icon name="download" size="18" /><span>Download Template</span></a>
                <dl>
                    <div><dt>Allowed account types</dt><dd>{{ $allowedTypes->join(', ') }}</dd></div>
                    <div><dt>Required columns</dt><dd>account_type, first_name, last_name, email, institutional_identifier, password</dd></div>
                    <div><dt>Batch limit</dt><dd>250 accounts per CSV, up to 2 MB</dd></div>
                    <div><dt>Password rule</dt><dd>At least 8 characters for every row</dd></div>
                </dl>
            </section>

            {{-- Uploaded CSV files are processed from private storage and deleted after every outcome. --}}
            <form class="identity-import-form" method="POST" action="{{ route($routeBase.'.import.store') }}" enctype="multipart/form-data">
                @csrf
                <span class="identity-import-icon"><x-dashboard.icon name="upload" size="34" /></span>
                <h2>Upload Account File</h2>
                <p>Select the completed CSV template.</p>
                <label class="identity-file-picker" for="accounts_file">Choose CSV File</label>
                <input id="accounts_file" name="accounts_file" type="file" accept=".csv,text/csv" required data-account-import-file>
                <span class="identity-file-name" data-account-import-name>No file selected</span>
                @error('accounts_file')<span class="identity-field-error" role="alert">{{ $message }}</span>@enderror
                <button class="identity-button identity-button-primary" type="submit">Validate and Import</button>
            </form>
        </div>
    </div>
@endsection
