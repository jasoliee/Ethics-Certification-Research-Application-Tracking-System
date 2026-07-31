@extends('layouts.dashboard')

@section('content')
    @php
        $passwordFields = [
            ['current_password', 'Current Password', 'current-password'],
            ['password', 'New Password', 'new-password'],
            ['password_confirmation', 'Confirm New Password', 'new-password'],
        ];
        $deadlineHasErrors = collect($errors->keys())->contains(
            fn (string $key): bool => str_starts_with($key, 'processes.')
                || in_array($key, ['semester', 'academic_year', 'term_starts_on', 'term_ends_on'], true)
        );
        $securityHasErrors = $errors->has('username') || collect($passwordFields)->contains(
            fn (array $field): bool => $errors->has($field[0])
        );
        $initialTab = old('settings_tab')
            ?: ($securityHasErrors ? 'security' : ($deadlineHasErrors ? 'deadlines' : 'profile'));
        $processIcons = [
            'application-submission' => 'file-text',
            'adviser-endorsement' => 'user-check',
            'res-screening' => 'search',
            'reviewer-submission' => 'users',
            'revision-period' => 'refresh',
            'reviewing-revision-period' => 'eye',
            'result-release' => 'award',
        ];
        $upcomingConfiguration = $upcomingDeadline['configuration'] ?? null;
        $activeDateRangeDays = $configuredTerm
            ? (int) $configuredTerm->starts_at->copy()->startOfDay()->diffInDays($configuredTerm->ends_at->copy()->startOfDay()) + 1
            : null;
    @endphp

    <div class="dashboard-page res-settings-page" data-settings-tabs data-settings-active-tab="{{ $initialTab }}">
        <header class="dashboard-page-heading">
            <h1>Settings</h1>
            <p>Manage your RES Lead profile, active-term schedule, and account security.</p>
        </header>

        <nav class="settings-tabs" role="tablist" aria-label="Settings sections">
            <button id="settings-tab-profile" type="button" role="tab" aria-controls="settings-panel-profile" aria-selected="{{ $initialTab === 'profile' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'profile' ? '0' : '-1' }}" data-settings-tab="profile">
                <x-dashboard.icon name="user" size="18" />
                <span>Profile</span>
            </button>
            <button id="settings-tab-deadlines" type="button" role="tab" aria-controls="settings-panel-deadlines" aria-selected="{{ $initialTab === 'deadlines' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'deadlines' ? '0' : '-1' }}" data-settings-tab="deadlines">
                <x-dashboard.icon name="calendar" size="18" />
                <span>Deadline Configuration</span>
            </button>
            <button id="settings-tab-security" type="button" role="tab" aria-controls="settings-panel-security" aria-selected="{{ $initialTab === 'security' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'security' ? '0' : '-1' }}" data-settings-tab="security">
                <x-dashboard.icon name="lock" size="18" />
                <span>Security and Privacy</span>
            </button>
        </nav>

        <section
            class="settings-tab-panel"
            id="settings-panel-profile"
            role="tabpanel"
            aria-labelledby="settings-tab-profile"
            data-settings-panel="profile"
            @if ($initialTab !== 'profile') hidden @endif
        >
            <section class="settings-section" aria-labelledby="profile-settings-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="user" size="23" /></span>
                    <div>
                        <h2 id="profile-settings-title">Profile</h2>
                        <p>Review your managed account identity and current active term.</p>
                    </div>
                </div>

                <dl class="settings-profile-summary">
                    <div><dt>Name</dt><dd>{{ $settingsUser->name }}</dd></div>
                    <div><dt>Email Address</dt><dd>{{ $settingsUser->email }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $settingsUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Active Term</dt><dd>{{ $activeTermLabel }}</dd></div>
                </dl>
            </section>
        </section>

        <section
            class="settings-tab-panel"
            id="settings-panel-deadlines"
            role="tabpanel"
            aria-labelledby="settings-tab-deadlines"
            data-settings-panel="deadlines"
            @if ($initialTab !== 'deadlines') hidden @endif
        >
            <section class="settings-section settings-deadline-section" aria-labelledby="deadline-settings-title">
                <h2 class="sr-only" id="deadline-settings-title">Deadline Configuration</h2>

                @if ($deadlineHasErrors)
                    <div class="identity-validation-summary settings-validation-summary" role="alert">
                        <strong>Deadline configuration could not be saved.</strong>
                        @foreach ($errors->all() as $message)<span>{{ $message }}</span>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('res.settings.deadlines.update') }}" data-application-submit-once data-deadline-settings-form>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_tab" value="deadlines">

                    <div class="settings-deadline-overview">
                        <section class="settings-term-summary" aria-labelledby="term-settings-title">
                            <h3 id="term-settings-title">Semester and Academic Year</h3>
                            <div class="settings-term-fields">
                                <div class="settings-field">
                                    <label for="semester">Semester</label>
                                    <input id="semester" name="semester" type="text" value="{{ old('semester', $configuredTerm?->semester) }}" maxlength="50" placeholder="1st Semester" required>
                                </div>
                                <div class="settings-field">
                                    <label for="academic_year">Academic Year</label>
                                    <input id="academic_year" name="academic_year" type="text" value="{{ old('academic_year', $configuredTerm?->academic_year) }}" maxlength="20" inputmode="numeric" placeholder="2026-2027" required>
                                </div>
                                <div class="settings-field settings-date-field">
                                    <label for="term_starts_on">Starting Date</label>
                                    <input id="term_starts_on" name="term_starts_on" type="date" min="{{ $minimumDate }}" value="{{ old('term_starts_on', $configuredTerm?->starts_at?->format('Y-m-d')) }}" data-minimum-date="{{ $minimumDate }}" required>
                                </div>
                                <div class="settings-field settings-date-field">
                                    <label for="term_ends_on">Ending Date</label>
                                    <input id="term_ends_on" name="term_ends_on" type="date" min="{{ $minimumDate }}" value="{{ old('term_ends_on', $configuredTerm?->ends_at?->format('Y-m-d')) }}" data-minimum-date="{{ $minimumDate }}" required>
                                </div>
                            </div>
                        </section>

                        <section class="settings-deadline-summaries" aria-label="Deadline summary">
                            <div class="settings-deadline-summary">
                                <span><x-dashboard.icon name="calendar" size="22" /></span>
                                <div>
                                    <small>Upcoming Deadline</small>
                                    @if ($upcomingConfiguration)
                                        <strong>{{ $upcomingConfiguration->due_at->format('M j, Y g:i A') }}</strong>
                                        <span>{{ $upcomingDeadline['title'] }}</span>
                                    @else
                                        <strong>Not configured</strong>
                                        <span>No future process deadline is available.</span>
                                    @endif
                                </div>
                            </div>
                            <div class="settings-deadline-summary">
                                <span><x-dashboard.icon name="calendar" size="22" /></span>
                                <div>
                                    <small>Active Date Range</small>
                                    @if ($configuredTerm)
                                        <strong>{{ $configuredTerm->starts_at->format('M j, Y') }} - {{ $configuredTerm->ends_at->format('M j, Y') }}</strong>
                                        <span>{{ $activeDateRangeDays }} {{ Str::plural('day', $activeDateRangeDays) }}</span>
                                    @else
                                        <strong>Not configured</strong>
                                        <span>Save a semester date range to activate it.</span>
                                    @endif
                                </div>
                            </div>
                        </section>
                    </div>

                    <x-dashboard.overflow class="settings-deadline-table-wrap" label="Deadline configuration phases">
                        <table class="dashboard-table settings-deadline-table">
                            <thead>
                                <tr>
                                    <th class="settings-deadline-order">#</th>
                                    <th>Phase</th>
                                    <th>Description</th>
                                    <th>Open</th>
                                    <th>Deadline / Release Date</th>
                                    <th>Manual Open</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($settings as $key => $process)
                                    @php
                                        $configuration = $process['configuration'];
                                        $manualOpen = (bool) old("processes.{$key}.is_open", $process['is_open']);
                                    @endphp
                                    <tr data-deadline-process>
                                        <td class="settings-deadline-order"><span>{{ $loop->iteration }}</span></td>
                                        <td>
                                            <div class="settings-deadline-phase">
                                                <span><x-dashboard.icon :name="$processIcons[$key] ?? 'calendar'" size="19" /></span>
                                                <strong>{{ $process['title'] }}</strong>
                                            </div>
                                        </td>
                                        <td class="settings-deadline-description">{{ $process['description'] }}</td>
                                        <td>
                                            @if ($process['exact_date'])
                                                <span class="settings-not-applicable" aria-label="Not applicable">-</span>
                                            @else
                                                <label class="settings-table-field" for="{{ $key }}_starts_at">
                                                    <span class="sr-only">{{ $process['title'] }} opening date and time</span>
                                                    <input
                                                        id="{{ $key }}_starts_at"
                                                        name="processes[{{ $key }}][starts_at]"
                                                        type="datetime-local"
                                                        min="{{ $minimumDeadline }}"
                                                        value="{{ old("processes.{$key}.starts_at", $configuration?->starts_at?->format('Y-m-d\TH:i')) }}"
                                                        data-deadline-start
                                                        data-minimum-deadline="{{ $minimumDeadline }}"
                                                        required
                                                    >
                                                </label>
                                            @endif
                                        </td>
                                        <td>
                                            <label class="settings-table-field" for="{{ $key }}_due_at">
                                                <span class="sr-only">{{ $process['title'] }} {{ $process['exact_date'] ? 'release' : 'deadline' }} date and time</span>
                                                <input
                                                    id="{{ $key }}_due_at"
                                                    name="processes[{{ $key }}][due_at]"
                                                    type="datetime-local"
                                                    min="{{ $minimumDeadline }}"
                                                    value="{{ old("processes.{$key}.due_at", $configuration?->due_at?->format('Y-m-d\TH:i')) }}"
                                                    data-deadline-end
                                                    data-minimum-deadline="{{ $minimumDeadline }}"
                                                    required
                                                >
                                            </label>
                                        </td>
                                        <td class="settings-manual-cell">
                                            <input type="hidden" name="processes[{{ $key }}][is_open]" value="0">
                                            <label class="settings-switch" for="{{ $key }}_is_open">
                                                <input
                                                    id="{{ $key }}_is_open"
                                                    name="processes[{{ $key }}][is_open]"
                                                    type="checkbox"
                                                    value="1"
                                                    @checked($manualOpen)
                                                    data-deadline-toggle
                                                >
                                                <span aria-hidden="true"></span>
                                                <strong data-deadline-toggle-label>{{ $manualOpen ? 'On' : 'Auto' }}</strong>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-dashboard.overflow>

                    <div class="settings-form-actions">
                        <button class="dashboard-primary-action" type="submit">
                            <x-dashboard.icon name="check" size="18" />
                            <span>Save Deadline Configuration</span>
                        </button>
                    </div>
                </form>
            </section>
        </section>

        <section
            class="settings-tab-panel"
            id="settings-panel-security"
            role="tabpanel"
            aria-labelledby="settings-tab-security"
            data-settings-panel="security"
            @if ($initialTab !== 'security') hidden @endif
        >
            <section class="settings-section" aria-labelledby="security-settings-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="lock" size="23" /></span>
                    <div>
                        <h2 id="security-settings-title">Security and Privacy</h2>
                        <p>Manage the username and password used to access your RES Lead account.</p>
                    </div>
                </div>

                <div class="settings-account-grid">
                    <form
                        class="settings-account-form settings-username-form"
                        method="POST"
                        action="{{ route('res.settings.username.update') }}"
                        data-settings-confirm
                        data-confirm-title="Confirm Username Change"
                        data-confirm-message="Use this new username the next time you sign in?"
                        data-confirm-action="Update Username"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div>
                            <h3>Change Username</h3>
                            <p>Your current username is <strong>{{ $settingsUser->username }}</strong>.</p>
                        </div>
                        <div class="settings-field">
                            <label for="settings_username">New Username</label>
                            <input
                                id="settings_username"
                                name="username"
                                type="text"
                                value="{{ old('username', $settingsUser->username) }}"
                                minlength="6"
                                maxlength="30"
                                autocomplete="username"
                                aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}"
                                aria-describedby="settings-username-error"
                                required
                            >
                            <span class="settings-field-error" id="settings-username-error">@error('username'){{ $message }}@enderror</span>
                        </div>
                        <button class="dashboard-outline-action" type="submit">
                            <x-dashboard.icon name="edit" size="17" />
                            <span>Update Username</span>
                        </button>
                    </form>

                    <form
                        class="settings-account-form settings-password-form"
                        method="POST"
                        action="{{ route('res.settings.password.update') }}"
                        data-settings-password-form
                        data-settings-confirm
                        data-confirm-title="Confirm Password Change"
                        data-confirm-message="Change the password for your RES Lead account?"
                        data-confirm-action="Change Password"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div>
                            <h3>Change Password</h3>
                            <p>Confirm your current password before choosing a new password.</p>
                        </div>
                        <div class="settings-password-fields">
                            @foreach ($passwordFields as [$field, $label, $autocomplete])
                                <div class="settings-field">
                                    <label for="settings_{{ $field }}">{{ $label }}</label>
                                    <div class="password-input-wrapper">
                                        <input
                                            id="settings_{{ $field }}"
                                            name="{{ $field }}"
                                            type="password"
                                            maxlength="64"
                                            autocomplete="{{ $autocomplete }}"
                                            aria-invalid="{{ $errors->has($field) ? 'true' : 'false' }}"
                                            aria-describedby="settings-{{ str_replace('_', '-', $field) }}-error"
                                            required
                                        >
                                        <button type="button" class="password-toggle" aria-label="Show password" aria-controls="settings_{{ $field }}" aria-pressed="false" title="Show password" data-password-toggle hidden>
                                            <svg class="password-toggle-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M2.1 12s3.6-7 9.9-7 9.9 7 9.9 7-3.6 7-9.9 7-9.9-7-9.9-7Z"/><circle cx="12" cy="12" r="3"/><path class="password-toggle-slash" d="m3 3 18 18"/></svg>
                                        </button>
                                    </div>
                                    <span class="settings-field-error" id="settings-{{ str_replace('_', '-', $field) }}-error" data-settings-error-for="{{ $field }}">@error($field){{ $message }}@enderror</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="settings-inline-status" role="status" aria-live="polite" data-settings-password-status></div>
                        <button class="dashboard-outline-action" type="submit">
                            <x-dashboard.icon name="lock" size="17" />
                            <span>Change Password</span>
                        </button>
                    </form>
                </div>
            </section>
        </section>

        <section class="application-modal-backdrop" data-settings-confirm-dialog hidden>
            <div class="application-modal settings-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="settings-confirm-title" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Cancel account change" data-settings-confirm-close>
                    <x-dashboard.icon name="x" size="20" />
                </button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="lock" size="24" /></span>
                    <div>
                        <h2 id="settings-confirm-title" data-settings-confirm-title>Confirm Account Change</h2>
                        <p data-settings-confirm-message>Confirm this security change.</p>
                    </div>
                </header>
                <div class="application-modal-actions">
                    <button class="dashboard-outline-action" type="button" data-settings-confirm-close>Cancel</button>
                    <button class="dashboard-primary-action" type="button" data-settings-confirm-submit>Confirm</button>
                </div>
            </div>
        </section>
    </div>
@endsection
