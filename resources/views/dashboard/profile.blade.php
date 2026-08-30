@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-profile-page">
        <header class="dashboard-page-heading">
            <h1>Profile</h1>
            <p>Review your account identity and access details.</p>
        </header>

        <section class="dashboard-profile-card">
            <div class="dashboard-profile-summary">
                <div class="profile-image-settings-control">
                    <form class="profile-image-form" method="POST" action="{{ route('profile-image.store') }}" enctype="multipart/form-data" data-profile-image-form>
                        @csrf
                        <label class="profile-image-control" title="Replace profile image">
                            <span class="dashboard-avatar dashboard-profile-avatar" aria-hidden="true">@if ($dashboardHasProfileImage)<img src="{{ $dashboardProfileImageUrl }}" alt="">@else{{ $dashboardUserInitials }}@endif</span>
                            <span class="profile-image-replace">Replace</span>
                            <input name="profile_image" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" data-profile-image-input>
                        </label>
                    </form>
                    @error('profile_image')<small class="settings-field-error">{{ $message }}</small>@enderror
                </div>
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
                <div><dt>{{ $profileUser->institutionalIdentifierLabel() }}</dt><dd>{{ $profileUser->institutional_identifier }}</dd></div>
                <div><dt>Phone Number</dt><dd>{{ $profileUser->phone_number ?: 'Not provided' }}</dd></div>
                <div><dt>Institute</dt><dd>{{ $profileUser->institution ?: 'Not provided' }}</dd></div>
                @if ($profileUser->role === \App\Enums\UserRole::Applicant)
                    <div><dt>Program</dt><dd>{{ $profileUser->program ?: 'Not provided' }}</dd></div>
                    @if ($profileUser->applicant_type === \App\Enums\ApplicantType::Student)<div><dt>Year Level</dt><dd>{{ $profileUser->year_level ?: 'Not provided' }}</dd></div>@endif
                @endif
                @if ($profileUser->role === \App\Enums\UserRole::Adviser)
                    <div><dt>Position / Designation</dt><dd>{{ $profileUser->position_title ?: 'Not provided' }}</dd></div>
                    <div><dt>Declared Expected Endorsements</dt><dd>{{ $adviserStatistics['declared'] }}</dd></div>
                    <div><dt>Successfully Endorsed</dt><dd>{{ $adviserStatistics['endorsed'] }}</dd></div>
                    <div><dt>Received, Awaiting Endorsement</dt><dd>{{ $adviserStatistics['awaiting'] }}</dd></div>
                    <div><dt>Remaining Expected Total</dt><dd>{{ $adviserStatistics['remaining'] }}</dd></div>
                    <div><dt>Not Yet Received</dt><dd>{{ $adviserStatistics['not_received'] }}</dd></div>
                    @if (($reviewerProfile['enabled'] ?? false) === true)
                        <div><dt>Reviewer Access</dt><dd>Enabled</dd></div>
                        <div><dt>Maximum Active Application Load</dt><dd>{{ $reviewerProfile['capacity'] ?: 'Not configured' }}</dd></div>
                        <div><dt>Current Active Assignment Load</dt><dd>{{ $reviewerProfile['active_load'] }}</dd></div>
                        <div><dt>Available Capacity</dt><dd>{{ $reviewerProfile['available_capacity'] }}</dd></div>
                        <div><dt>Assignment Eligibility</dt><dd>{{ $reviewerProfile['eligibility_label'] }}</dd></div>
                    @endif
                @endif
                @if ($profileUser->role === \App\Enums\UserRole::ResLead)
                    <div><dt>Certificate Signatory</dt><dd>{{ $profileUser->certificate_signatory_name ?: $profileUser->name }}</dd></div>
                @endif
            </dl>

            <div class="dashboard-profile-actions">
                @if ($dashboardHasProfileImage)<form method="POST" action="{{ route('profile-image.destroy') }}">@csrf @method('DELETE')<button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="refresh" size="18" />Use Initials</button></form>@endif
                <a class="dashboard-outline-action" href="{{ route($dashboardSettingsRoute, ['tab' => 'security']) }}"><x-dashboard.icon name="lock" size="18" />Security &amp; Privacy</a>
            </div>
        </section>
    </div>
@endsection
