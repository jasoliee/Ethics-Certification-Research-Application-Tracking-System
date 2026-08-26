@extends('layouts.dashboard')

@section('content')
    @php
        $securityHasErrors = collect(['username', 'email', 'current_password', 'password', 'password_confirmation'])->contains(fn ($field) => $errors->has($field));
        $worksheetHasErrors = $errors->worksheetSignatory->any();
        $requestedTab = request('tab');
        $initialTab = old('settings_tab') ?: ($worksheetHasErrors ? 'worksheet' : ($securityHasErrors ? 'security' : (in_array($requestedTab, ['profile', 'worksheet', 'security'], true) ? $requestedTab : 'profile')));
    @endphp
    <div class="dashboard-page res-settings-page" data-settings-tabs data-settings-active-tab="{{ $initialTab }}">
        <header class="dashboard-page-heading"><h1>Settings</h1></header>
        <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
            @foreach ([['profile', 'user', 'Profile'], ['worksheet', 'file-text', 'Worksheet Configuration'], ['security', 'lock', 'Security and Privacy']] as [$tab, $icon, $label])
                <button id="settings-tab-{{ $tab }}" type="button" role="tab" aria-controls="settings-panel-{{ $tab }}" aria-selected="{{ $initialTab === $tab ? 'true' : 'false' }}" tabindex="{{ $initialTab === $tab ? '0' : '-1' }}" data-settings-tab="{{ $tab }}"><x-dashboard.icon :name="$icon" size="18" /><span>{{ $label }}</span></button>
            @endforeach
        </nav>
        <section class="settings-tab-panel" id="settings-panel-profile" role="tabpanel" aria-labelledby="settings-tab-profile" data-settings-panel="profile" @if ($initialTab !== 'profile') hidden @endif><section class="settings-section"><div class="settings-section-heading"><span><x-dashboard.icon name="user" size="23" /></span><div><h2>Profile</h2></div></div>@include('settings.partials.profile-form')</section></section>
        <section class="settings-tab-panel" id="settings-panel-worksheet" role="tabpanel" aria-labelledby="settings-tab-worksheet" data-settings-panel="worksheet" @if ($initialTab !== 'worksheet') hidden @endif>
            @include('settings.partials.worksheet-configuration')
        </section>
        <section class="settings-tab-panel" id="settings-panel-security" role="tabpanel" aria-labelledby="settings-tab-security" data-settings-panel="security" @if ($initialTab !== 'security') hidden @endif><section class="settings-section"><div class="settings-section-heading"><span><x-dashboard.icon name="lock" size="23" /></span><div><h2>Security and Privacy</h2></div></div>@include('settings.partials.security-forms')</section></section>
        <section class="application-modal-backdrop" data-settings-confirm-dialog hidden><div class="application-modal settings-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="settings-confirm-title" tabindex="-1"><button class="application-modal-close" type="button" aria-label="Cancel account change" data-settings-confirm-close><x-dashboard.icon name="x" size="20" /></button><header class="application-modal-heading"><span class="application-modal-icon"><x-dashboard.icon name="lock" size="24" /></span><div><h2 id="settings-confirm-title" data-settings-confirm-title>Confirm Account Change</h2><p data-settings-confirm-message>Confirm this security change.</p></div></header><div class="application-modal-actions"><button class="dashboard-outline-action" type="button" data-settings-confirm-close>Cancel</button><button class="dashboard-primary-action" type="button" data-settings-confirm-submit>Confirm</button></div></div></section>
    </div>
@endsection
