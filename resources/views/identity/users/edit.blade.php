@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading identity-page-heading">
            <h1>Edit Profile Information</h1>
            <p>Update the authorized identity and institutional fields for {{ $managedUser->name }}.</p>
        </header>

        {{-- Role, username, status, join date, and password remain outside this profile update form. --}}
        <form class="identity-form-card" method="POST" action="{{ route($routeBase.'.update', $managedUser) }}">
            @csrf
            @method('PUT')

            <div class="identity-form-heading">
                <div><span class="identity-eyebrow">{{ $managedUser->displayRoleLabel() }}</span><h2>Profile Information</h2></div>
                <dl class="identity-readonly-summary"><div><dt>Username</dt><dd>{{ $managedUser->username }}</dd></div><div><dt>Status</dt><dd>{{ Str::headline($managedUser->account_status) }}</dd></div></dl>
            </div>

            @if ($errors->any())
                <div class="identity-validation-summary" role="alert"><strong>Review the highlighted fields.</strong><span>{{ $errors->first() }}</span></div>
            @endif

            @include('identity.users.partials.profile-fields', ['managedUser' => $managedUser, 'identifierLabel' => $managedUser->institutionalIdentifierLabel(), 'lockIdentity' => true])

            <div class="identity-form-actions">
                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.show', $managedUser) }}">Cancel</a>
                <button class="identity-button identity-button-primary" type="submit">Save Changes</button>
            </div>
        </form>

        <form class="identity-form-card identity-correction-form" method="POST" action="{{ route($routeBase.'.username', $managedUser) }}" data-confirm-username-change>
            @csrf
            @method('PATCH')
            <div class="identity-form-heading"><div><span class="identity-eyebrow">Confirmed Action</span><h2>Correct Identity and Regenerate Username</h2><p>Use only when the surname or institutional identifier was recorded incorrectly.</p></div></div>
            <div class="identity-form-section identity-form-grid">
                <div class="identity-field"><label for="identity_last_name">Last Name</label><input id="identity_last_name" name="last_name" value="{{ old('last_name', $managedUser->last_name) }}" maxlength="100" required></div>
                <div class="identity-field"><label for="identity_identifier">{{ $managedUser->institutionalIdentifierLabel() }}</label><input id="identity_identifier" name="institutional_identifier" value="{{ old('institutional_identifier', $managedUser->institutional_identifier) }}" maxlength="50" required></div>
                <label class="identity-confirm-check"><input type="checkbox" name="confirm_username_regeneration" value="1" required><span>I confirm this correction will generate a new username and notify the user.</span></label>
            </div>
            @error('identity')<div class="identity-validation-summary" role="alert"><strong>Identity correction was not applied.</strong><span>{{ $message }}</span></div>@enderror
            <div class="identity-form-actions"><button class="identity-button identity-button-secondary" type="submit">Correct Identity</button></div>
        </form>
    </div>
@endsection
