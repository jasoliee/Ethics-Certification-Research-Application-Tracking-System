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
        $profileHasErrors = $errors->getBag('signatory')->any() || collect(['first_name', 'middle_name', 'last_name', 'suffix', 'phone_number', 'institution', 'department', 'position_title', 'certificate_signatory_name', 'signature'])
            ->contains(fn (string $field): bool => $errors->has($field));
        $securityHasErrors = $errors->has('username') || $errors->has('email') || collect($passwordFields)->contains(
            fn (array $field): bool => $errors->has($field[0])
        );
        $optionsHaveErrors = $errors->has('option_field') || $errors->has('option_value');
        $backgroundsHaveErrors = $errors->getBag('certificateBackground')->any() || $errors->has('background') || $errors->has('background_type');
        $requestedTab = request('tab');
        $initialTab = old('settings_tab')
            ?: ($securityHasErrors ? 'security'
                : ($deadlineHasErrors ? 'deadlines'
                    : ($optionsHaveErrors ? 'options'
                        : ($backgroundsHaveErrors ? 'backgrounds'
                            : ($profileHasErrors ? 'profile'
                                : (in_array($requestedTab, ['profile', 'deadlines', 'options', 'backgrounds', 'security'], true) ? $requestedTab : 'profile'))))));
        $processIcons = [
            'application-submission' => 'file-text',
            'adviser-endorsement' => 'user-check',
            'res-screening' => 'search',
            'reviewer-submission' => 'users',
            'revision-period' => 'refresh',
            'reviewing-revision-period' => 'eye',
        ];
        $upcomingConfiguration = $upcomingDeadline['configuration'] ?? null;
        $activeDateRangeDays = $configuredTerm
            ? (int) $configuredTerm->starts_at->copy()->startOfDay()->diffInDays($configuredTerm->ends_at->copy()->startOfDay()) + 1
            : null;
        $termStartsOn = old('term_starts_on', $configuredTerm?->starts_at?->format('Y-m-d'));
        $termEndsOn = old('term_ends_on', $configuredTerm?->ends_at?->format('Y-m-d'));
        $termStartMinimum = $configuredTerm ? null : $minimumDate;
        $termEndMinimum = $termStartsOn ?: $termStartMinimum;
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
            <button id="settings-tab-options" type="button" role="tab" aria-controls="settings-panel-options" aria-selected="{{ $initialTab === 'options' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'options' ? '0' : '-1' }}" data-settings-tab="options">
                <x-dashboard.icon name="settings" size="18" />
                <span>Dropdown Options</span>
            </button>
            <button id="settings-tab-backgrounds" type="button" role="tab" aria-controls="settings-panel-backgrounds" aria-selected="{{ $initialTab === 'backgrounds' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'backgrounds' ? '0' : '-1' }}" data-settings-tab="backgrounds">
                <x-dashboard.icon name="image" size="18" />
                <span>Background Management</span>
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

                @include('settings.partials.profile-form')

                <form class="settings-account-form settings-signatory-form" method="POST" action="{{ route('res.settings.signatory.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_tab" value="profile">
                    <div>
                        <h3>Certificate Signatory</h3>
                        <p>The current authorized printed name and transparent signature are used only for certificates generated after this change.</p>
                    </div>
                    <div class="settings-signatory-grid">
                        <div class="settings-field">
                            <label for="certificate_signatory_name">Printed Signatory Name</label>
                            <input id="certificate_signatory_name" name="certificate_signatory_name" type="text" value="{{ old('certificate_signatory_name', $settingsUser->certificate_signatory_name ?: $settingsUser->name) }}" maxlength="120" required>
                            @error('certificate_signatory_name')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-field">
                            <label for="settings_signature">Transparent PNG Signature</label>
                            <input id="settings_signature" name="signature" type="file" accept="image/png,.png">
                            <small>PNG only, up to 2 MB, 64×32 through 2400×1200 pixels, with transparency.</small>
                            @error('signature')<span class="settings-field-error">{{ $message }}</span>@enderror
                            @error('signature', 'signatory')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-signature-preview">
                            <span>Current signature</span>
                            <img src="{{ route('res.settings.signatory.preview') }}" alt="Current authorized RES certificate signature">
                        </div>
                    </div>
                    <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="check" size="17" /><span>Save Signatory</span></button>
                </form>
            </section>
        </section>

        <section
            class="settings-tab-panel"
            id="settings-panel-options"
            role="tabpanel"
            aria-labelledby="settings-tab-options"
            data-settings-panel="options"
            @if ($initialTab !== 'options') hidden @endif
        >
            <section class="settings-section" aria-labelledby="dropdown-options-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="settings" size="23" /></span>
                    <div><h2 id="dropdown-options-title">Dropdown Options</h2><p>Manage controlled account-form and workbook values without changing historical account labels.</p></div>
                </div>

                <form class="identity-option-create" method="POST" action="{{ route('res.settings.profile-options.store') }}">
                    @csrf
                    <input type="hidden" name="settings_tab" value="options">
                    <div class="identity-field">
                        <label for="settings_option_field">Option Group</label>
                        <select id="settings_option_field" name="option_field" required>
                            @foreach (\App\Enums\ProfileOptionField::cases() as $field)
                                <option value="{{ $field->value }}" @selected(old('option_field') === $field->value)>{{ $field->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="identity-field identity-option-value-field">
                        <label for="settings_option_value">Option Value</label>
                        <input id="settings_option_value" name="option_value" type="text" value="{{ old('option_value') }}" maxlength="150" required>
                        @error('option_value')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <button class="identity-button identity-button-primary" type="submit"><x-dashboard.icon name="plus" size="18" /><span>Add Option</span></button>
                </form>

                <div class="identity-table-summary">
                    <strong>{{ $profileOptionRecords->count() }} configured options</strong>
                    <span>{{ $profileOptionCounts['active'] }} active · {{ $profileOptionCounts['inactive'] }} inactive</span>
                </div>
                <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Dropdown options" tabindex="0">
                    <table class="identity-user-table identity-option-table">
                        <thead><tr><th>Option Group</th><th>Option Value</th><th class="identity-col-usage">In Use</th><th class="identity-col-status">Status</th><th class="identity-col-action">Action</th></tr></thead>
                        <tbody>
                            @forelse ($profileOptionRecords as $option)
                                <tr>
                                    <td><strong>{{ $option->field->label() }}</strong></td>
                                    <td>
                                        <form class="identity-option-edit-form" method="POST" action="{{ route('res.settings.profile-options.update', $option) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="settings_tab" value="options">
                                            <label class="sr-only" for="settings-option-{{ $option->id }}">Edit {{ $option->field->label() }} option</label>
                                            <input id="settings-option-{{ $option->id }}" name="option_value" type="text" value="{{ $option->value }}" maxlength="150" required>
                                            <button class="identity-button identity-button-secondary" type="submit"><x-dashboard.icon name="edit" size="17" /><span>Save</span></button>
                                        </form>
                                    </td>
                                    <td class="identity-col-usage"><strong>{{ $profileOptionUsageCounts[$option->id] ?? 0 }}</strong></td>
                                    <td class="identity-col-status"><x-dashboard.status-badge :label="$option->is_active ? 'Active' : 'Inactive'" :tone="$option->is_active ? 'green' : 'neutral'" /></td>
                                    <td class="identity-col-action">
                                        <form method="POST" action="{{ route('res.settings.profile-options.status', $option) }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="settings_tab" value="options">
                                            <input type="hidden" name="is_active" value="{{ $option->is_active ? 0 : 1 }}">
                                            <button class="identity-view-link" type="submit">{{ $option->is_active ? 'Deactivate' : 'Restore' }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No dropdown options are configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </section>

        <section
            class="settings-tab-panel"
            id="settings-panel-backgrounds"
            role="tabpanel"
            aria-labelledby="settings-tab-backgrounds"
            data-settings-panel="backgrounds"
            @if ($initialTab !== 'backgrounds') hidden @endif
        >
            <section class="settings-section" aria-labelledby="background-management-title">
                <div class="settings-section-heading">
                    <span><x-dashboard.icon name="image" size="23" /></span>
                    <div><h2 id="background-management-title">Background Management</h2><p>Certificate and Review Worksheet assets are versioned independently. Activating one changes future output only.</p></div>
                </div>

                @if ($errors->getBag('certificateBackground')->any() || $errors->has('background') || $errors->has('background_type'))
                    <div class="identity-validation-summary settings-validation-summary" role="alert">
                        <strong>The background was not activated.</strong>
                        @foreach ($errors->getBag('certificateBackground')->all() as $message)<span>{{ $message }}</span>@endforeach
                        @error('background')<span>{{ $message }}</span>@enderror
                        @error('background_type')<span>{{ $message }}</span>@enderror
                    </div>
                @endif

                <div class="settings-background-grid">
                    @foreach ([
                        \App\Models\CertificateBackground::TYPE_CERTIFICATE => 'Certificate Background',
                        \App\Models\CertificateBackground::TYPE_REVIEW_WORKSHEET => 'Review Worksheet Background',
                    ] as $backgroundType => $backgroundLabel)
                        @php
                            $history = $managedBackgrounds->get($backgroundType, collect());
                            $activeBackground = $history->firstWhere('is_active', true);
                        @endphp
                        <section class="settings-background-card" aria-labelledby="{{ $backgroundType }}-background-title">
                            <header>
                                <div><h3 id="{{ $backgroundType }}-background-title">{{ $backgroundLabel }}</h3><p>{{ $activeBackground?->original_file_name ?: 'No active asset' }}</p></div>
                                @if ($activeBackground)<a class="dashboard-outline-action" href="{{ route('res.settings.backgrounds.preview', $activeBackground) }}" target="_blank" rel="noopener"><x-dashboard.icon name="eye" size="17" /><span>Preview</span></a>@endif
                            </header>

                            <form class="settings-background-upload" method="POST" action="{{ route('res.settings.backgrounds.store') }}" enctype="multipart/form-data">
                                @csrf
                                <input type="hidden" name="settings_tab" value="backgrounds">
                                <input type="hidden" name="background_type" value="{{ $backgroundType }}">
                                <label class="settings-background-file" data-managed-background-file>
                                    <input name="background" type="file" accept="application/pdf,image/jpeg,image/png,.pdf,.jpg,.jpeg,.png" required>
                                    <span data-managed-background-file-label>Choose File</span>
                                </label>
                                <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="upload" size="17" /><span>Validate and Activate</span></button>
                            </form>

                            <form method="POST" action="{{ route('res.settings.backgrounds.reset') }}">
                                @csrf
                                <input type="hidden" name="settings_tab" value="backgrounds">
                                <input type="hidden" name="background_type" value="{{ $backgroundType }}">
                                <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="refresh" size="17" /><span>Reset to Official Default</span></button>
                            </form>

                            <div class="dashboard-overflow-region settings-background-history" role="region" aria-label="{{ $backgroundLabel }} history" tabindex="0">
                                <table class="dashboard-table">
                                    <thead><tr><th>Version</th><th>File</th><th>Activated</th><th>Status</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        @foreach ($history as $background)
                                            <tr>
                                                <td>v{{ $background->asset_version }}</td>
                                                <td>{{ $background->original_file_name }}</td>
                                                <td>{{ $background->activated_at?->format('M j, Y g:i A') ?: 'Never' }}</td>
                                                <td><x-dashboard.status-badge :label="$background->is_active ? 'Active' : 'Available'" :tone="$background->is_active ? 'green' : 'neutral'" /></td>
                                                <td>
                                                    <a class="identity-view-link" href="{{ route('res.settings.backgrounds.preview', $background) }}" target="_blank" rel="noopener">Preview</a>
                                                    @unless ($background->is_active)
                                                        <form method="POST" action="{{ route('res.settings.backgrounds.activate', $background) }}">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="settings_tab" value="backgrounds">
                                                            <button class="identity-view-link" type="submit">Activate</button>
                                                        </form>
                                                    @endunless
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endforeach
                </div>
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
                                    <input id="term_starts_on" name="term_starts_on" type="date" @if ($termStartMinimum) min="{{ $termStartMinimum }}" @endif value="{{ $termStartsOn }}" required>
                                </div>
                                <div class="settings-field settings-date-field">
                                    <label for="term_ends_on">Ending Date</label>
                                    <input id="term_ends_on" name="term_ends_on" type="date" @if ($termEndMinimum) min="{{ $termEndMinimum }}" @endif value="{{ $termEndsOn }}" required>
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
                                    <th>Availability</th>
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
                                            <label class="settings-table-field" for="{{ $key }}_starts_at">
                                                <span class="sr-only">{{ $process['title'] }} opening date and time</span>
                                                <input
                                                    id="{{ $key }}_starts_at"
                                                    name="processes[{{ $key }}][starts_at]"
                                                    type="datetime-local"
                                                    value="{{ old("processes.{$key}.starts_at", $configuration?->starts_at?->format('Y-m-d\TH:i')) }}"
                                                    data-deadline-start
                                                    required
                                                >
                                            </label>
                                        </td>
                                        <td>
                                            <label class="settings-table-field" for="{{ $key }}_due_at">
                                                <span class="sr-only">{{ $process['title'] }} deadline date and time</span>
                                                <input
                                                    id="{{ $key }}_due_at"
                                                    name="processes[{{ $key }}][due_at]"
                                                    type="datetime-local"
                                                    value="{{ old("processes.{$key}.due_at", $configuration?->due_at?->format('Y-m-d\TH:i')) }}"
                                                    data-deadline-end
                                                    required
                                                >
                                            </label>
                                        </td>
                                        <td class="settings-manual-cell">
                                            <input type="hidden" name="processes[{{ $key }}][is_open]" value="0">
                                            <input type="hidden" name="processes[{{ $key }}][override_changed]" value="0" data-deadline-override-changed>
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
                                                <strong data-deadline-toggle-label>{{ $manualOpen ? 'On' : 'Off' }}</strong>
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
                        class="settings-account-form settings-email-form"
                        method="POST"
                        action="{{ route('res.settings.email.update') }}"
                        data-settings-confirm
                        data-confirm-title="Confirm Email Change"
                        data-confirm-message="Change your email address and revoke other signed-in sessions?"
                        data-confirm-action="Update Email"
                    >
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="settings_tab" value="security">
                        <div><h3>Change Email Address</h3><p>Your current email is <strong>{{ $settingsUser->email }}</strong>.</p></div>
                        <div class="settings-field">
                            <label for="settings_email">New Email Address</label>
                            <input id="settings_email" name="email" type="email" value="{{ old('email', $settingsUser->email) }}" maxlength="255" autocomplete="email" required>
                            @error('email')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-field">
                            <label for="settings_email_current_password">Current Password</label>
                            <input id="settings_email_current_password" name="current_password" type="password" maxlength="128" autocomplete="current-password" required>
                            @error('current_password')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="mail" size="17" /><span>Update Email</span></button>
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
