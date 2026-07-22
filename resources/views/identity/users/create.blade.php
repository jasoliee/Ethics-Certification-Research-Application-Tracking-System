@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        @if (! $selectedType)
            <header class="dashboard-page-heading identity-page-heading identity-role-page-heading">
                <h1>Add Account</h1>
                <p>Select the account type you are authorized to create.</p>
            </header>

            <div class="identity-role-workspace" aria-labelledby="role-selection-title">
                <div class="identity-dialog-heading">
                    <span class="identity-section-icon"><x-dashboard.icon name="users" size="24" /></span>
                    <div><h2 id="role-selection-title">Choose Account Type</h2><p>The account type controls its profile fields and system access.</p></div>
                </div>

                <div class="identity-role-options identity-role-options-page">
                    @foreach ($accountTypes as $accountType)
                        <button
                            type="button"
                            data-account-mode-open
                            data-account-label="{{ $accountType['label'] }}"
                            data-individual-url="{{ route($routeBase.'.create', ['account_type' => $accountType['key'], 'mode' => 'individual']) }}"
                            data-bulk-url="{{ route($routeBase.'.import.form', ['account_type' => $accountType['key']]) }}"
                        >
                            <span class="identity-role-option-icon"><x-dashboard.icon :name="$accountType['icon']" size="27" /></span>
                            <span><strong>{{ $accountType['label'] }}</strong><small>{{ $accountType['description'] }}</small></span>
                            <x-dashboard.icon name="arrow-right" size="20" />
                        </button>
                    @endforeach
                </div>

                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}">Cancel</a>
            </div>

            <section class="identity-mode-overlay" data-account-mode-dialog hidden>
                <div class="identity-mode-dialog" role="dialog" aria-modal="true" aria-labelledby="account-mode-title" tabindex="-1">
                    <button class="identity-mode-close" type="button" aria-label="Close account creation options" data-account-mode-close><x-dashboard.icon name="x" size="20" /></button>
                    <span class="identity-section-icon"><x-dashboard.icon name="plus" size="24" /></span>
                    <h2 id="account-mode-title">Create <span data-account-mode-label>Account</span></h2>
                    <p>Choose how account information will be entered.</p>
                    <div class="identity-mode-options">
                        <a href="#" data-account-individual-link><x-dashboard.icon name="user" size="24" /><span><strong>Individual Account</strong><small>Enter one account in a secure form.</small></span></a>
                        <a href="#" data-account-bulk-link><x-dashboard.icon name="upload" size="24" /><span><strong>Bulk Upload</strong><small>Validate and preview a role-specific CSV or Excel file.</small></span></a>
                    </div>
                </div>
            </section>
        @else
            @php($identifierLabel = $selectedType['applicant_type'] === \App\Enums\ApplicantType::Student->value ? 'Student Number' : 'Employee ID')
            <header class="dashboard-page-heading identity-page-heading">
                <h1>Add {{ $selectedType['label'] }}</h1>
                <p>Enter the required identity and institutional information.</p>
            </header>

            <form class="identity-form-card" method="POST" action="{{ route($routeBase.'.store') }}" data-managed-account-form>
                @csrf
                <input type="hidden" name="role" value="{{ $selectedType['role'] }}">
                <input type="hidden" name="applicant_type" value="{{ $selectedType['applicant_type'] }}">

                <div class="identity-form-heading">
                    <div><span class="identity-eyebrow">{{ $selectedType['label'] }}</span><h2>Account Information</h2><p>Required fields are marked with an asterisk.</p></div>
                    <a href="{{ route($routeBase.'.create') }}">Change account type</a>
                </div>

                @if ($errors->any())
                    <div class="identity-validation-summary" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>
                @endif

                @include('identity.users.partials.profile-fields', ['managedUser' => null, 'identifierLabel' => $identifierLabel, 'selectedType' => $selectedType])

                <fieldset class="identity-form-section">
                    <legend>Account Access</legend>
                    <div class="identity-generated-access">
                        <div><x-dashboard.icon name="user" size="21" /><span><strong>Username</strong><small>Generated from the institutional identifier and surname.</small></span></div>
                        <div><x-dashboard.icon name="mail" size="21" /><span><strong>Password setup</strong><small>A one-time setup link valid for seven days will be emailed to the user.</small></span></div>
                    </div>
                </fieldset>

                <div class="identity-form-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}">Cancel</a>
                    <button class="identity-button identity-button-primary" type="submit">Create Account</button>
                </div>
            </form>
        @endif
    </div>
@endsection
