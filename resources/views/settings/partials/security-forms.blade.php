@php
    $passwordFields = [
        ['current_password', 'Current Password', 'current-password'],
        ['password', 'New Password', 'new-password'],
        ['password_confirmation', 'Confirm New Password', 'new-password'],
    ];
@endphp

<div class="settings-security-stack">
    <details class="settings-security-card" @if($errors->has('username')) open @endif>
        <summary><span><x-dashboard.icon name="user" size="19" />Change Username</span><x-dashboard.icon name="chevron-down" size="18" /></summary>
        <form class="settings-account-form" method="POST" action="{{ route($settingsRouteBase.'.username.update') }}" data-settings-confirm data-confirm-title="Confirm Username Change" data-confirm-message="Use this username the next time you sign in?" data-confirm-action="Update Username">
            @csrf @method('PATCH')<input type="hidden" name="settings_tab" value="security">
            <div class="settings-field"><label for="settings_username">New Username</label><input id="settings_username" name="username" type="text" value="{{ old('username', $settingsUser->username) }}" minlength="6" maxlength="30" autocomplete="username" required>@error('username')<span class="settings-field-error">{{ $message }}</span>@enderror</div>
            <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="edit" size="17" /><span>Update Username</span></button>
        </form>
    </details>
    <details class="settings-security-card" @if($errors->has('email')) open @endif>
        <summary><span><x-dashboard.icon name="mail" size="19" />Change Email Address</span><x-dashboard.icon name="chevron-down" size="18" /></summary>
        <form class="settings-account-form" method="POST" action="{{ route($settingsRouteBase.'.email.update') }}" data-settings-confirm data-confirm-title="Confirm Email Change" data-confirm-message="Change the email address and revoke other sessions?" data-confirm-action="Update Email">
            @csrf @method('PATCH')<input type="hidden" name="settings_tab" value="security">
            <div class="settings-field"><label for="settings_email">New Email Address</label><input id="settings_email" name="email" type="email" value="{{ old('email', $settingsUser->email) }}" maxlength="255" autocomplete="email" required>@error('email')<span class="settings-field-error">{{ $message }}</span>@enderror</div>
            <div class="settings-field"><label for="settings_email_current_password">Current Password</label><input id="settings_email_current_password" name="current_password" type="password" maxlength="128" autocomplete="current-password" required>@error('current_password')<span class="settings-field-error">{{ $message }}</span>@enderror</div>
            <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="mail" size="17" /><span>Update Email</span></button>
        </form>
    </details>
    <details class="settings-security-card" @if($errors->has('password')) open @endif>
        <summary><span><x-dashboard.icon name="lock" size="19" />Change Password</span><x-dashboard.icon name="chevron-down" size="18" /></summary>
        <form class="settings-account-form settings-password-form" method="POST" action="{{ route($settingsRouteBase.'.password.update') }}" data-settings-password-form data-settings-confirm data-confirm-title="Confirm Password Change" data-confirm-message="Change this password and revoke other sessions?" data-confirm-action="Change Password">
            @csrf @method('PATCH')<input type="hidden" name="settings_tab" value="security">
            <div class="settings-password-fields">@foreach ($passwordFields as [$field, $label, $autocomplete])<div class="settings-field"><label for="settings_password_{{ $field }}">{{ $label }}</label><div class="password-input-wrapper"><input id="settings_password_{{ $field }}" name="{{ $field }}" type="password" maxlength="64" autocomplete="{{ $autocomplete }}" required><button type="button" class="password-toggle" aria-label="Show password" aria-controls="settings_password_{{ $field }}" aria-pressed="false" title="Show password" data-password-toggle hidden><svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z"/><circle cx="12" cy="12" r="3"/><path class="password-toggle-slash" d="m3 3 18 18"/></svg></button></div>@error($field)<span class="settings-field-error">{{ $message }}</span>@enderror</div>@endforeach</div>
            <div class="settings-inline-status" role="status" aria-live="polite" data-settings-password-status></div>
            <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="lock" size="17" /><span>Change Password</span></button>
        </form>
    </details>
</div>
