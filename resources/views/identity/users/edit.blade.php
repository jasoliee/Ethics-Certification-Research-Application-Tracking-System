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

            @include('identity.users.partials.profile-fields', ['managedUser' => $managedUser, 'identifierLabel' => $managedUser->institutionalIdentifierLabel()])

            <div class="identity-form-actions">
                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.show', $managedUser) }}">Cancel</a>
                <button class="identity-button identity-button-primary" type="submit">Save Changes</button>
            </div>
        </form>
    </div>
@endsection
