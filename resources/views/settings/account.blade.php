@extends('layouts.dashboard')

@section('content')
    @php
        $profileFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'phone_number', 'institution', 'program', 'year_level', 'position_title', 'expected_endorsement_count'];
        $securityFields = ['username', 'email', 'current_password', 'password', 'password_confirmation'];
        $profileHasErrors = collect($profileFields)->contains(fn (string $field): bool => $errors->has($field));
        $securityHasErrors = collect($securityFields)->contains(fn (string $field): bool => $errors->has($field));
        $canConfigureWorksheet = $settingsUser->hasReviewerAccess();
        $worksheetHasErrors = $canConfigureWorksheet && $errors->getBag('worksheetSignatory')->any();
        $requestedTab = request('tab');
        $availableTabs = $canConfigureWorksheet ? ['profile', 'worksheet', 'security'] : ['profile', 'security'];
        $initialTab = old('settings_tab') ?: ($worksheetHasErrors ? 'worksheet' : ($securityHasErrors ? 'security' : ($profileHasErrors ? 'profile' : (in_array($requestedTab, $availableTabs, true) ? $requestedTab : 'profile'))));
    @endphp

    <div class="dashboard-page res-settings-page" data-settings-tabs data-settings-active-tab="{{ $initialTab }}">
        <header class="dashboard-page-heading"><h1>Settings</h1></header>
        <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
            <button id="settings-tab-profile" type="button" role="tab" aria-controls="settings-panel-profile" aria-selected="{{ $initialTab === 'profile' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'profile' ? '0' : '-1' }}" data-settings-tab="profile"><x-dashboard.icon name="user" size="18" /><span>Profile</span></button>
            @if ($canConfigureWorksheet)<button id="settings-tab-worksheet" type="button" role="tab" aria-controls="settings-panel-worksheet" aria-selected="{{ $initialTab === 'worksheet' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'worksheet' ? '0' : '-1' }}" data-settings-tab="worksheet"><x-dashboard.icon name="file-text" size="18" /><span>Worksheet Configuration</span></button>@endif
            <button id="settings-tab-security" type="button" role="tab" aria-controls="settings-panel-security" aria-selected="{{ $initialTab === 'security' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'security' ? '0' : '-1' }}" data-settings-tab="security"><x-dashboard.icon name="lock" size="18" /><span>Security and Privacy</span></button>
        </nav>

        <section class="settings-tab-panel" id="settings-panel-profile" role="tabpanel" aria-labelledby="settings-tab-profile" data-settings-panel="profile" @if ($initialTab !== 'profile') hidden @endif>
            <section class="settings-section">
                <div class="settings-section-heading"><span><x-dashboard.icon name="user" size="23" /></span><div><h2>Profile</h2></div></div>
                @include('settings.partials.profile-form')
            </section>
        </section>

        @if ($canConfigureWorksheet)
            <section class="settings-tab-panel" id="settings-panel-worksheet" role="tabpanel" aria-labelledby="settings-tab-worksheet" data-settings-panel="worksheet" @if ($initialTab !== 'worksheet') hidden @endif>
                @include('settings.partials.worksheet-configuration')
            </section>
        @endif

        <section class="settings-tab-panel" id="settings-panel-security" role="tabpanel" aria-labelledby="settings-tab-security" data-settings-panel="security" @if ($initialTab !== 'security') hidden @endif>
            <section class="settings-section"><div class="settings-section-heading settings-security-heading"><span><x-dashboard.icon name="lock" size="23" /></span><div><h2>Security and Privacy</h2><p>Manage your account security and personal information.</p></div></div>@include('settings.partials.security-forms')</section>
        </section>

        <section class="application-modal-backdrop" data-settings-confirm-dialog hidden><div class="application-modal settings-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="settings-confirm-title" tabindex="-1"><button class="application-modal-close" type="button" aria-label="Cancel account change" data-settings-confirm-close><x-dashboard.icon name="x" size="20" /></button><header class="application-modal-heading"><span class="application-modal-icon"><x-dashboard.icon name="lock" size="24" /></span><div><h2 id="settings-confirm-title" data-settings-confirm-title>Confirm Account Change</h2><p data-settings-confirm-message>Confirm this security change.</p></div></header><div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-settings-confirm-close>Cancel</button><button class="dashboard-primary-action" type="button" data-settings-confirm-submit>Confirm</button></div></div></section>
    </div>
@endsection
