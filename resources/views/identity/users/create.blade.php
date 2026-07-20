@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading identity-page-heading">
            <h1>Add New User</h1>
            <p>{{ $selectedType ? 'Enter the required account information.' : 'Select the account type to create.' }}</p>
        </header>

        @if (! $selectedType)
            {{-- Role selection only renders account types allowed by the signed-in creator. --}}
            <section class="identity-dialog identity-role-dialog" aria-labelledby="role-dialog-title">
                <div class="identity-dialog-heading">
                    <span class="identity-section-icon"><x-dashboard.icon name="users" size="24" /></span>
                    <div><h2 id="role-dialog-title">Choose Account Type</h2><p>The selected role determines account access and required identifier.</p></div>
                </div>

                <div class="identity-role-options">
                    @foreach ($accountTypes as $accountType)
                        <a href="{{ route($routeBase.'.create', ['account_type' => $accountType['key']]) }}">
                            <span class="identity-role-option-icon"><x-dashboard.icon :name="$accountType['icon']" size="27" /></span>
                            <span><strong>{{ $accountType['label'] }}</strong><small>{{ $accountType['description'] }}</small></span>
                            <x-dashboard.icon name="arrow-right" size="20" />
                        </a>
                    @endforeach
                </div>

                <div class="identity-dialog-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}">Cancel</a>
                </div>
            </section>
        @else
            @php($identifierLabel = $selectedType['applicant_type'] === \App\Enums\ApplicantType::Student->value ? 'Student Number' : 'Employee ID')
            {{-- Account creation accepts no username input; the service generates it after successful validation. --}}
            <form class="identity-form-card" method="POST" action="{{ route($routeBase.'.store') }}" data-managed-account-form>
                @csrf
                <input type="hidden" name="role" value="{{ $selectedType['role'] }}">
                <input type="hidden" name="applicant_type" value="{{ $selectedType['applicant_type'] }}">

                <div class="identity-form-heading">
                    <div>
                        <span class="identity-eyebrow">{{ $selectedType['label'] }}</span>
                        <h2>Account Information</h2>
                        <p>Required fields are marked with an asterisk.</p>
                    </div>
                    <a href="{{ route($routeBase.'.create') }}">Change account type</a>
                </div>

                @if ($errors->any())
                    <div class="identity-validation-summary" role="alert">
                        <strong>Review the highlighted fields.</strong>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                @include('identity.users.partials.profile-fields', ['managedUser' => null, 'identifierLabel' => $identifierLabel])

                <fieldset class="identity-form-section">
                    <legend>Account Security</legend>
                    <div class="identity-form-grid">
                        <div class="identity-field">
                            <label for="password">Initial Password <span aria-hidden="true">*</span></label>
                            <div class="identity-password-wrap">
                                <input id="password" name="password" type="password" minlength="8" maxlength="64" autocomplete="new-password" required data-managed-password>
                                <button type="button" aria-label="Show password" aria-pressed="false" data-managed-password-toggle hidden><x-dashboard.icon name="eye" size="20" /></button>
                            </div>
                            <small>Minimum 8 characters.</small>
                            @error('password')<span class="identity-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="identity-field">
                            <label for="password_confirmation">Confirm Password <span aria-hidden="true">*</span></label>
                            <div class="identity-password-wrap">
                                <input id="password_confirmation" name="password_confirmation" type="password" maxlength="64" autocomplete="new-password" required data-managed-password>
                                <button type="button" aria-label="Show password" aria-pressed="false" data-managed-password-toggle hidden><x-dashboard.icon name="eye" size="20" /></button>
                            </div>
                        </div>
                        <div class="identity-generated-username">
                            <x-dashboard.icon name="lock" size="20" />
                            <span><strong>Username</strong><small>Generated securely after account creation</small></span>
                        </div>
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
