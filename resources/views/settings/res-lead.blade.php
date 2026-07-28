@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page res-settings-page">
        {{-- The heading keeps the administrative purpose clear without turning Settings into a marketing surface. --}}
        <header class="dashboard-page-heading">
            <h1>Settings</h1>
            <p>Manage semester process availability and your RES Lead account credentials.</p>
        </header>

        @if ($errors->any())
            <div class="identity-validation-summary" role="alert">
                <strong>Settings could not be saved.</strong>
                @foreach ($errors->all() as $message)<span>{{ $message }}</span>@endforeach
            </div>
        @endif

        {{-- One bounded form updates every established process and prevents a partial semester schedule. --}}
        <section class="settings-section" aria-labelledby="deadline-settings-title">
            <div class="settings-section-heading">
                <span><x-dashboard.icon name="calendar" size="23" /></span>
                <div>
                    <h2 id="deadline-settings-title">Deadline Configuration</h2>
                    <p>Set the current semester schedule and manually open or close each process.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('res.settings.deadlines.update') }}" data-application-submit-once>
                @csrf
                @method('PUT')

                <div class="settings-semester-field">
                    <label for="semester_label">Semester and Academic Year</label>
                    <input id="semester_label" name="semester_label" type="text" value="{{ old('semester_label', $semesterLabel) }}" maxlength="100" placeholder="1st Semester, A.Y. 2026-2027" required>
                    <small>This label appears with the configured application timeline.</small>
                </div>

                <div class="settings-process-list">
                    @foreach ($settings as $key => $process)
                        @php($configuration = $process['configuration'])
                        <fieldset class="settings-process-row">
                            <legend>
                                <strong>{{ $process['title'] }}</strong>
                                <span>{{ $process['description'] }}</span>
                            </legend>

                            <div class="settings-process-fields">
                                <div class="settings-field">
                                    <label for="{{ $key }}_starts_at">Opens</label>
                                    <input id="{{ $key }}_starts_at" name="processes[{{ $key }}][starts_at]" type="datetime-local" value="{{ old("processes.{$key}.starts_at", $configuration?->starts_at?->format('Y-m-d\TH:i')) }}" required>
                                </div>
                                <div class="settings-field">
                                    <label for="{{ $key }}_due_at">Deadline</label>
                                    <input id="{{ $key }}_due_at" name="processes[{{ $key }}][due_at]" type="datetime-local" value="{{ old("processes.{$key}.due_at", $configuration?->due_at?->format('Y-m-d\TH:i')) }}" required>
                                </div>
                                <div class="settings-process-toggle">
                                    <span>Manual availability</span>
                                    <input type="hidden" name="processes[{{ $key }}][is_open]" value="0">
                                    <label class="settings-switch" for="{{ $key }}_is_open">
                                        <input id="{{ $key }}_is_open" name="processes[{{ $key }}][is_open]" type="checkbox" value="1" @checked((bool) old("processes.{$key}.is_open", $process['is_open']))>
                                        <span aria-hidden="true"></span>
                                        <strong>Open</strong>
                                    </label>
                                </div>
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div class="settings-form-actions">
                    <button class="dashboard-primary-action" type="submit">
                        <x-dashboard.icon name="check" size="18" />
                        <span>Save Deadline Configuration</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- Credential forms remain separated so validation errors never submit an unrelated secret field. --}}
        <section class="settings-section" aria-labelledby="account-settings-title">
            <div class="settings-section-heading">
                <span><x-dashboard.icon name="user" size="23" /></span>
                <div>
                    <h2 id="account-settings-title">Personal Account Management</h2>
                    <p>Update your own login username or password using separate secure actions.</p>
                </div>
            </div>

            <div class="settings-account-grid">
                <form class="settings-account-form" method="POST" action="{{ route('res.settings.username.update') }}" data-application-submit-once>
                    @csrf
                    @method('PATCH')
                    <div>
                        <h3>Change Username</h3>
                        <p>Your current username is <strong>{{ $settingsUser->username }}</strong>.</p>
                    </div>
                    <div class="settings-field">
                        <label for="settings_username">New Username</label>
                        <input id="settings_username" name="username" type="text" value="{{ old('username', $settingsUser->username) }}" minlength="6" maxlength="30" autocomplete="username" required>
                    </div>
                    <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="edit" size="17" /><span>Update Username</span></button>
                </form>

                <form class="settings-account-form" method="POST" action="{{ route('res.settings.password.update') }}" data-application-submit-once>
                    @csrf
                    @method('PATCH')
                    <div>
                        <h3>Change Password</h3>
                        <p>Confirm your current password before choosing a new one.</p>
                    </div>
                    @foreach ([
                        ['current_password', 'Current Password', 'current-password'],
                        ['password', 'New Password', 'new-password'],
                        ['password_confirmation', 'Confirm New Password', 'new-password'],
                    ] as [$field, $label, $autocomplete])
                        <div class="settings-field">
                            <label for="settings_{{ $field }}">{{ $label }}</label>
                            <div class="password-input-wrapper">
                                <input id="settings_{{ $field }}" name="{{ $field }}" type="password" maxlength="64" autocomplete="{{ $autocomplete }}" required>
                                <button type="button" class="password-toggle" aria-label="Show password" aria-controls="settings_{{ $field }}" aria-pressed="false" title="Show password" data-password-toggle hidden>
                                    <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z"/><circle cx="12" cy="12" r="3"/><path class="password-toggle-slash" d="m3 3 18 18"/></svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                    <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="lock" size="17" /><span>Change Password</span></button>
                </form>
            </div>
        </section>
    </div>
@endsection
