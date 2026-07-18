@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-profile-page">
        <header class="dashboard-page-heading">
            <h1>Profile</h1>
            <p>Review your account identity and access classification.</p>
        </header>

        <section class="dashboard-profile-card">
            <div class="dashboard-profile-summary">
                <span class="dashboard-avatar dashboard-profile-avatar" aria-hidden="true">{{ $dashboardUserInitials }}</span>
                <div>
                    <h2>{{ $profileUser->name }}</h2>
                    <p>{{ $profileUser->displayRoleLabel() }}</p>
                </div>
            </div>

            <dl class="dashboard-profile-details">
                <div><dt>Full Name</dt><dd>{{ $profileUser->name }}</dd></div>
                <div><dt>Username</dt><dd>{{ $profileUser->username }}</dd></div>
                <div><dt>Email Address</dt><dd>{{ $profileUser->email }}</dd></div>
                <div><dt>Role</dt><dd>{{ $profileUser->displayRoleLabel() }}</dd></div>
                <div><dt>Account Status</dt><dd>{{ Str::headline($profileUser->account_status) }}</dd></div>
            </dl>

            <a class="dashboard-outline-action" href="{{ route($dashboardSettingsRoute) }}">
                <x-dashboard.icon name="settings" size="18" />
                Account Settings
            </a>
        </section>
    </div>
@endsection
