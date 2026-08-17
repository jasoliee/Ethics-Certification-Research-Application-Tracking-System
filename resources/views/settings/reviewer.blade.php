@extends('layouts.dashboard')

@section('content')
    @php
        $passwordFields = [
            ['current_password', 'Current Password', 'current-password'],
            ['password', 'New Password', 'new-password'],
            ['password_confirmation', 'Confirm New Password', 'new-password'],
        ];
        $securityHasErrors = $errors->has('username') || $errors->has('email') || collect($passwordFields)->contains(
            fn (array $field): bool => $errors->has($field[0])
        );
        $initialTab = old('settings_tab') ?: ($securityHasErrors ? 'security' : 'profile');
    @endphp

    <div class="dashboard-page res-settings-page" data-settings-tabs data-settings-active-tab="{{ $initialTab }}">
        <header class="dashboard-page-heading">
            <h1>Settings</h1>
            <p>Review your account details and manage your sign-in security.</p>
        </header>

        <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
            <button id="settings-tab-profile" type="button" role="tab" aria-controls="settings-panel-profile" aria-selected="{{ $initialTab === 'profile' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'profile' ? '0' : '-1' }}" data-settings-tab="profile">
                <x-dashboard.icon name="user" size="18" />
                <span>Profile</span>
            </button>
            <button id="settings-tab-security" type="button" role="tab" aria-controls="settings-panel-security" aria-selected="{{ $initialTab === 'security' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'security' ? '0' : '-1' }}" data-settings-tab="security">
                <x-dashboard.icon name="lock" size="18" />
                <span>Security and Privacy</span>
            </button>
        </nav>

        <section class="settings-tab-panel" id="settings-panel-profile" role="tabpanel" aria-labelledby="settings-tab-profile" data-settings-panel="profile" @if ($initialTab !== 'profile') hidden @endif>
            <section class="settings-section" aria-labelledby="profile-settings-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="user" size="23" /></span>
                    <div>
                        <h2 id="profile-settings-title">Profile</h2>
                        <p>Review the identity attached to your reviewer account.</p>
                    </div>
                </div>
                <dl class="settings-profile-summary">
                    <div><dt>Name</dt><dd>{{ $settingsUser->name }}</dd></div>
                    <div><dt>Email Address</dt><dd>{{ $settingsUser->email }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $settingsUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Reviewer Classification</dt><dd>{{ $settingsUser->reviewer_classification ?: 'Not specified' }}</dd></div>
                </dl>
                @include('settings.partials.profile-form')
            </section>
        </section>

        <section class="settings-tab-panel" id="settings-panel-security" role="tabpanel" aria-labelledby="settings-tab-security" data-settings-panel="security" @if ($initialTab !== 'security') hidden @endif>
            <section class="settings-section" aria-labelledby="security-settings-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="lock" size="23" /></span>
                    <div>
                        <h2 id="security-settings-title">Security and Privacy</h2>
                        <p>Manage the username and password used to access your Reviewer account.</p>
                    </div>
                </div>

                <div class="settings-account-grid">
                    <form class="settings-account-form settings-username-form" method="POST" action="{{ route('reviewer.settings.username.update') }}" data-settings-confirm data-confirm-title="Confirm Username Change" data-confirm-message="Use this new username the next time you sign in?" data-confirm-action="Update Username">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div><h3>Change Username</h3><p>Your current username is <strong>{{ $settingsUser->username }}</strong>.</p></div>
                        <div class="settings-field">
                            <label for="settings_username">New Username</label>
                            <input id="settings_username" name="username" type="text" value="{{ old('username', $settingsUser->username) }}" minlength="6" maxlength="30" autocomplete="username" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" aria-describedby="settings-username-error" required>
                            <span class="settings-field-error" id="settings-username-error">@error('username'){{ $message }}@enderror</span>
                        </div>
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="edit" size="17" /><span>Update Username</span></button>
                    </form>

                    <form class="settings-account-form settings-email-form" method="POST" action="{{ route('reviewer.settings.email.update') }}" data-settings-confirm data-confirm-title="Confirm Email Change" data-confirm-message="Change your email address and revoke other signed-in sessions?" data-confirm-action="Update Email">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div><h3>Change Email Address</h3><p>Your current email is <strong>{{ $settingsUser->email }}</strong>.</p></div>
                        <div class="settings-field"><label for="settings_email">New Email Address</label><input id="settings_email" name="email" type="email" value="{{ old('email', $settingsUser->email) }}" maxlength="255" autocomplete="email" required>@error('email')<span class="settings-field-error">{{ $message }}</span>@enderror</div>
                        <div class="settings-field"><label for="settings_email_current_password">Current Password</label><input id="settings_email_current_password" name="current_password" type="password" maxlength="128" autocomplete="current-password" required>@error('current_password')<span class="settings-field-error">{{ $message }}</span>@enderror</div>
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="mail" size="17" /><span>Update Email</span></button>
                    </form>

                    <form class="settings-account-form settings-password-form" method="POST" action="{{ route('reviewer.settings.password.update') }}" data-settings-password-form data-settings-confirm data-confirm-title="Confirm Password Change" data-confirm-message="Change the password for your Reviewer account?" data-confirm-action="Change Password">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div><h3>Change Password</h3><p>Confirm your current password before choosing a new password.</p></div>
                        <div class="settings-password-fields">
                            @foreach ($passwordFields as [$field, $label, $autocomplete])
                                <div class="settings-field">
                                    <label for="settings_{{ $field }}">{{ $label }}</label>
                                    <div class="password-input-wrapper">
                                        <input id="settings_{{ $field }}" name="{{ $field }}" type="password" maxlength="64" autocomplete="{{ $autocomplete }}" aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}" aria-describedby="settings-{{ str_replace('_', '-', $field) }}-error" required>
                                        <button type="button" class="password-toggle" aria-label="Show password" aria-controls="settings_{{ $field }}" aria-pressed="false" title="Show password" data-password-toggle hidden>
                                            <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z"/><circle cx="12" cy="12" r="3"/><path class="password-toggle-slash" d="m3 3 18 18"/></svg>
                                        </button>
                                    </div>
                                    <span class="settings-field-error" id="settings-{{ str_replace('_', '-', $field) }}-error" data-settings-error-for="{{ $field }}">@error($field){{ $message }}@enderror</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="settings-inline-status" role="status" aria-live="polite" data-settings-password-status></div>
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="lock" size="17" /><span>Change Password</span></button>
                    </form>
                </div>
            </section>
        </section>

        <section class="application-modal-backdrop" data-settings-confirm-dialog hidden>
            <div class="application-modal settings-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="settings-confirm-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Cancel account change" data-settings-confirm-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="lock" size="24" /></span>
                    <div><h2 id="settings-confirm-title" data-settings-confirm-title>Confirm Account Change</h2><p data-settings-confirm-message>Confirm this security change.</p></div>
                </header>
                <div class="application-modal-actions">
                    <button class="dashboard-outline-action" type="button" data-settings-confirm-close>Cancel</button>
                    <button class="dashboard-primary-action" type="button" data-settings-confirm-submit>Confirm</button>
                </div>
            </div>
        </section>
    </div>
@endsection
