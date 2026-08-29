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
                || in_array($key, ['semester', 'academic_year', 'academic_year_start', 'academic_year_end', 'term_starts_on', 'term_ends_on', 'academic_term'], true)
        );
        $profileHasErrors = collect(['first_name', 'middle_name', 'last_name', 'suffix', 'phone_number', 'institution', 'position_title'])
            ->contains(fn (string $field): bool => $errors->has($field));
        $certificateHasErrors = $errors->getBag('signatory')->any() || collect(['certificate_signatory_name', 'certificate_valid_until', 'signature', 'qr_image'])
            ->contains(fn (string $field): bool => $errors->has($field));
        $securityHasErrors = $errors->has('username') || $errors->has('email') || collect($passwordFields)->contains(
            fn (array $field): bool => $errors->has($field[0])
        );
        $optionsHaveErrors = $errors->has('option_field') || $errors->has('option_value') || $errors->has('option_acronym');
        $backgroundsHaveErrors = $errors->getBag('certificateBackground')->any() || $errors->has('background') || $errors->has('background_type');
        $requirementsHaveErrors = $errors->getBag('requirementConfiguration')->any();
        $requestedTab = request('tab');
        $initialTab = old('settings_tab')
            ?: ($requirementsHaveErrors ? 'requirements'
                : ($certificateHasErrors ? 'certificate'
                : ($securityHasErrors ? 'security'
                : ($deadlineHasErrors ? 'deadlines'
                    : ($optionsHaveErrors ? 'options'
                        : ($backgroundsHaveErrors ? 'backgrounds'
                            : ($profileHasErrors ? 'profile'
                                : (in_array($requestedTab, ['profile', 'requirements', 'deadlines', 'options', 'backgrounds', 'certificate', 'security'], true) ? $requestedTab : 'profile'))))))));
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
        $termStartMinimum = null;
        $termEndMinimum = $termStartsOn;
    @endphp

    <div class="dashboard-page res-settings-page" data-settings-tabs data-settings-active-tab="{{ $initialTab }}">
        <header class="dashboard-page-heading">
            <h1>Settings</h1>
        </header>

        <nav class="settings-tabs settings-tabs-seven" role="tablist" aria-label="Settings sections">
            <button id="settings-tab-profile" type="button" role="tab" aria-controls="settings-panel-profile" aria-selected="{{ $initialTab === 'profile' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'profile' ? '0' : '-1' }}" data-settings-tab="profile">
                <x-dashboard.icon name="user" size="18" />
                <span>Profile</span>
            </button>
            <button id="settings-tab-requirements" type="button" role="tab" aria-controls="settings-panel-requirements" aria-selected="{{ $initialTab === 'requirements' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'requirements' ? '0' : '-1' }}" data-settings-tab="requirements">
                <x-dashboard.icon name="file-text" size="18" />
                <span>Requirements Configuration</span>
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
            <button id="settings-tab-certificate" type="button" role="tab" aria-controls="settings-panel-certificate" aria-selected="{{ $initialTab === 'certificate' ? 'true' : 'false' }}" tabindex="{{ $initialTab === 'certificate' ? '0' : '-1' }}" data-settings-tab="certificate">
                <x-dashboard.icon name="award" size="18" />
                <span>Certificate Configuration</span>
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
                    </div>
                </div>

                @include('settings.partials.profile-form')

            </section>
        </section>

        @include('settings.partials.requirements-configuration')

        <section
            class="settings-tab-panel"
            id="settings-panel-certificate"
            role="tabpanel"
            aria-labelledby="settings-tab-certificate"
            data-settings-panel="certificate"
            @if ($initialTab !== 'certificate') hidden @endif
        >
            <section class="settings-section" aria-labelledby="certificate-configuration-title">
                <div class="settings-section-heading settings-certificate-heading">
                    <span><x-dashboard.icon name="award" size="23" /></span>
                    <div><h2 id="certificate-configuration-title">Certificate Configuration</h2></div>
                </div>

                <form class="settings-account-form settings-signatory-form" method="POST" action="{{ route('res.settings.signatory.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_tab" value="certificate">
                    <div class="settings-certificate-grid">
                        <div class="settings-field">
                            <label for="certificate_signatory_name">Printed Signatory Name</label>
                            <input id="certificate_signatory_name" name="certificate_signatory_name" type="text" value="{{ old('certificate_signatory_name', $settingsUser->certificate_signatory_name ?: $settingsUser->name) }}" maxlength="120" required>
                            @error('certificate_signatory_name')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-field">
                            <label for="certificate_valid_until">Valid Until</label>
                            <input id="certificate_valid_until" name="certificate_valid_until" type="date" value="{{ old('certificate_valid_until', $settingsUser->certificate_valid_until?->format('Y-m-d') ?? now()->addYearNoOverflow()->format('Y-m-d')) }}" min="{{ now()->format('Y-m-d') }}" data-settings-date-picker required>
                            @error('certificate_valid_until')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-certificate-asset">
                            <div class="settings-certificate-current">
                                <span>Current Signature</span>
                                <img class="settings-current-signature" src="{{ route('res.settings.signatory.preview') }}" alt="Current authorized REU certificate signature">
                                <small class="settings-certificate-warning">The signature must be a transparent PNG file without a background.</small>
                            </div>
                            <label class="settings-file-control settings-certificate-replace" for="settings_signature" data-settings-file-control>
                                <x-dashboard.icon name="upload" size="17" />
                                <span data-settings-file-label data-settings-default-label="Replace Signature">Replace Signature</span>
                                <input id="settings_signature" name="signature" type="file" accept="image/png,.png">
                            </label>
                            @error('signature')<span class="settings-field-error">{{ $message }}</span>@enderror
                            @error('signature', 'signatory')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="settings-certificate-asset">
                            <div class="settings-certificate-current">
                                <span>Current QR Code</span>
                                <img class="settings-qr-preview" src="{{ route('res.settings.certificate-qr.preview') }}" alt="Current certificate QR code">
                                @unless($settingsUser->certificate_qr_path)<small>Official fallback: {{ \App\Services\Certificates\DefaultCertificateQrService::URL }}</small>@endunless
                            </div>
                            <label class="settings-file-control settings-certificate-replace" for="settings_qr_image" data-settings-file-control>
                                <x-dashboard.icon name="upload" size="17" />
                                <span data-settings-file-label data-settings-default-label="Replace QR">Replace QR</span>
                                <input id="settings_qr_image" name="qr_image" type="file" accept="image/png,.png">
                            </label>
                            @error('qr_image')<span class="settings-field-error">{{ $message }}</span>@enderror
                            @error('qr_image', 'signatory')<span class="settings-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="check" size="17" /><span>Save Certificate Configuration</span></button>
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
                    <div><h2 id="dropdown-options-title">Dropdown Options</h2></div>
                </div>

                @php
                    $selectedOptionField = old('option_field', \App\Enums\ProfileOptionField::YearLevel->value);
                    $creatingInstituteOption = $selectedOptionField === \App\Enums\ProfileOptionField::Institute->value;
                @endphp
                <form class="identity-option-create" method="POST" action="{{ route('res.settings.profile-options.store') }}" data-profile-option-create>
                    @csrf
                    <input type="hidden" name="settings_tab" value="options">
                    <div class="identity-field">
                        <label for="settings_option_field">Option Group</label>
                        <select id="settings_option_field" name="option_field" required data-profile-option-field>
                            @foreach (\App\Enums\ProfileOptionField::managedCases() as $field)
                                <option value="{{ $field->value }}" @selected($selectedOptionField === $field->value)>{{ $field->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="identity-field identity-option-value-field">
                        <label for="settings_option_value">Option Value</label>
                        <input id="settings_option_value" name="option_value" type="text" value="{{ old('option_value') }}" maxlength="150" required>
                        @error('option_value')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="identity-field identity-option-acronym-field" data-profile-option-acronym-field @if (! $creatingInstituteOption) hidden @endif>
                        <label for="settings_option_acronym">Institute Acronym</label>
                        <input
                            id="settings_option_acronym"
                            name="option_acronym"
                            type="text"
                            value="{{ old('option_acronym') }}"
                            maxlength="12"
                            placeholder="e.g. ICDI"
                            autocomplete="off"
                            data-profile-option-acronym
                            @disabled(! $creatingInstituteOption)
                            @required($creatingInstituteOption)
                        >
                        @error('option_acronym')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <button class="identity-button identity-button-primary" type="submit"><x-dashboard.icon name="plus" size="18" /><span>Add Option</span></button>
                </form>

                <div class="identity-table-summary">
                    <strong>{{ $profileOptionRecords->count() }} configured options</strong>
                    <span>{{ $profileOptionCounts['active'] }} active · {{ $profileOptionCounts['inactive'] }} inactive</span>
                </div>
                <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Dropdown options" tabindex="0">
                    <table class="identity-user-table identity-option-table">
                        <thead><tr><th>Option Group</th><th>Option Value</th><th class="identity-col-acronym">Institute Acronym</th><th class="identity-col-usage">In Use</th><th class="identity-col-status">Status</th><th class="identity-col-action">Action</th></tr></thead>
                        <tbody>
                            @forelse ($profileOptionRecords as $option)
                                <tr>
                                    <td><strong>{{ $option->field->label() }}</strong></td>
                                    <td>
                                        <form id="settings-option-form-{{ $option->id }}" method="POST" action="{{ route('res.settings.profile-options.update', $option) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="settings_tab" value="options">
                                        </form>
                                        <label class="sr-only" for="settings-option-{{ $option->id }}">Edit {{ $option->field->label() }} option</label>
                                        <input id="settings-option-{{ $option->id }}" class="identity-option-table-input" name="option_value" type="text" value="{{ $option->value }}" maxlength="150" form="settings-option-form-{{ $option->id }}" required>
                                    </td>
                                    <td class="identity-col-acronym">
                                        @if ($option->field === \App\Enums\ProfileOptionField::Institute)
                                            <label class="sr-only" for="settings-option-acronym-{{ $option->id }}">Edit {{ $option->value }} acronym</label>
                                            <input id="settings-option-acronym-{{ $option->id }}" class="identity-option-table-input identity-option-acronym-input" name="option_acronym" type="text" value="{{ $option->acronym }}" maxlength="12" form="settings-option-form-{{ $option->id }}" required>
                                        @else
                                            <span aria-label="Not applicable">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="identity-col-usage"><strong>{{ $profileOptionUsageCounts[$option->id] ?? 0 }}</strong></td>
                                    <td class="identity-col-status"><x-dashboard.status-badge :label="$option->is_active ? 'Active' : 'Inactive'" :tone="$option->is_active ? 'green' : 'neutral'" /></td>
                                    <td class="identity-col-action">
                                        <div class="identity-option-row-actions">
                                            <button class="identity-button identity-button-secondary" type="submit" form="settings-option-form-{{ $option->id }}"><x-dashboard.icon name="edit" size="17" /><span>Save</span></button>
                                            <form method="POST" action="{{ route('res.settings.profile-options.status', $option) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="settings_tab" value="options">
                                                <input type="hidden" name="is_active" value="{{ $option->is_active ? 0 : 1 }}">
                                                <button class="identity-view-link" type="submit">{{ $option->is_active ? 'Deactivate' : 'Restore' }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6">No dropdown options are configured.</td></tr>
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
                <div class="settings-section-heading settings-background-heading">
                    <span><x-dashboard.icon name="image" size="23" /></span>
                    <div><h2 id="background-management-title">Background Management</h2></div>
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
                                <div class="settings-background-header-actions">
                                    @if ($activeBackground)<a class="settings-background-link" href="{{ route('res.settings.backgrounds.preview', $activeBackground) }}" target="_blank" rel="noopener">Preview</a>@endif
                                    <form method="POST" action="{{ route('res.settings.backgrounds.reset') }}">@csrf<input type="hidden" name="settings_tab" value="backgrounds"><input type="hidden" name="background_type" value="{{ $backgroundType }}"><button class="settings-background-link is-danger" type="submit">Reset to Default</button></form>
                                </div>
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

                            @if(false)<form method="POST" action="{{ route('res.settings.backgrounds.reset') }}">
                                @csrf
                                <input type="hidden" name="settings_tab" value="backgrounds">
                                <input type="hidden" name="background_type" value="{{ $backgroundType }}">
                                <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="refresh" size="17" /><span>Reset to Official Default</span></button>
                            </form>@endif

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

                @if ($configuredTerm)
                    <section class="settings-term-lifecycle" aria-labelledby="configured-term-title">
                        <div>
                            <span class="status-badge {{ $configuredTerm->isPaused() ? 'status-badge-orange' : 'status-badge-green' }}">{{ $configuredTerm->status->label() }}</span>
                            <h3 id="configured-term-title">{{ $configuredTerm->label() }}</h3>
                            <p>{{ $configuredTerm->starts_at->format('M j, Y') }} – {{ $configuredTerm->ends_at->format('M j, Y') }}</p>
                        </div>
                        <div class="settings-term-lifecycle-actions">
                            <a class="dashboard-outline-action" href="#academic-term-form"><x-dashboard.icon name="edit" size="17" /><span>Edit</span></a>
                            @if ($configuredTerm->isPaused())
                                <form method="POST" action="{{ route('res.settings.academic-terms.reactivate', $configuredTerm) }}">@csrf @method('PATCH')<input type="hidden" name="confirmation" value="reactivate"><button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="refresh" size="17" /><span>Reactivate</span></button></form>
                            @else
                                <form method="POST" action="{{ route('res.settings.academic-terms.pause', $configuredTerm) }}">@csrf @method('PATCH')<input type="hidden" name="confirmation" value="pause"><button class="dashboard-outline-action settings-term-pause-action" type="submit"><x-dashboard.icon name="clock" size="17" /><span>Pause</span></button></form>
                            @endif
                            <form method="POST" action="{{ route('res.settings.academic-terms.end', $configuredTerm) }}" data-settings-confirm-form data-confirm-title="End academic term?" data-confirm-message="This ends the term and closes its deadlines. Applications, files, and audit records will be preserved.">@csrf @method('PATCH')<input type="hidden" name="confirmation" value="end"><button class="dashboard-danger-action" type="submit"><x-dashboard.icon name="x" size="17" /><span>End</span></button></form>
                        </div>
                    </section>
                @else
                    <details class="settings-add-term-disclosure" @if ($deadlineHasErrors) open @endif>
                        <summary><x-dashboard.icon name="plus" size="18" />Add Active Academic Term</summary>
                @endif

                @if ($deadlineHasErrors)
                    <div class="identity-validation-summary settings-validation-summary" role="alert">
                        <strong>Deadline configuration could not be saved.</strong>
                        @foreach ($errors->all() as $message)<span>{{ $message }}</span>@endforeach
                    </div>
                @endif

                <form id="academic-term-form" method="POST" action="{{ route('res.settings.deadlines.update') }}" data-application-submit-once data-deadline-settings-form>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="settings_tab" value="deadlines">

                    <div class="settings-deadline-overview">
                        <section class="settings-term-summary" aria-labelledby="term-settings-title">
                            <h3 id="term-settings-title">Semester and Academic Year</h3>
                            <div class="settings-term-fields">
                                <div class="settings-field">
                                    <label for="semester">Academic Semester</label>
                                    <select id="semester" name="semester" required>
                                        <option value="">Select semester</option>
                                        @foreach ($semesterOptions as $semester)<option value="{{ $semester }}" @selected(old('semester', $configuredTerm?->semester) === $semester)>{{ $semester }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="settings-field">
                                    @php
                                        $configuredSchoolYears = explode('-', (string) ($configuredTerm?->academic_year ?? ''));
                                    @endphp
                                    <label for="academic_year_start">School Year</label>
                                    <div class="settings-school-year-fields">
                                        <select id="academic_year_start" name="academic_year_start" aria-label="School year starting year" required>
                                            <option value="">Start year</option>
                                            @foreach ($schoolYearOptions as $year)<option value="{{ $year }}" @selected((string) old('academic_year_start', $configuredSchoolYears[0] ?? '') === (string) $year)>{{ $year }}</option>@endforeach
                                        </select>
                                        <span aria-hidden="true">to</span>
                                        <select id="academic_year_end" name="academic_year_end" aria-label="School year ending year" required>
                                            <option value="">End year</option>
                                            @foreach ($schoolYearOptions as $year)<option value="{{ $year }}" @selected((string) old('academic_year_end', $configuredSchoolYears[1] ?? '') === (string) $year)>{{ $year }}</option>@endforeach
                                        </select>
                                    </div>
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
                                    <th>Deadline</th>
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
                                                    min="{{ $minimumDeadline }}"
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
                                                    min="{{ $minimumDeadline }}"
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

                @unless ($configuredTerm)
                    </details>
                    @if ($termHistory->where('status', \App\Enums\AcademicTermStatus::Ended)->isNotEmpty())
                        <section class="settings-term-history" aria-labelledby="ended-terms-title">
                            <h3 id="ended-terms-title">Ended Academic Terms</h3>
                            @foreach ($termHistory->where('status', \App\Enums\AcademicTermStatus::Ended) as $historicalTerm)
                                <article><div><strong>{{ $historicalTerm->label() }}</strong><span>{{ $historicalTerm->starts_at->format('M j, Y') }} – {{ $historicalTerm->ends_at->format('M j, Y') }}</span></div><form method="POST" action="{{ route('res.settings.academic-terms.reactivate', $historicalTerm) }}">@csrf @method('PATCH')<input type="hidden" name="confirmation" value="reactivate"><button class="dashboard-secondary-action" type="submit"><x-dashboard.icon name="refresh" size="17" />Reactivate</button></form></article>
                            @endforeach
                        </section>
                    @endif
                @endunless
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
                <div class="settings-section-heading settings-security-heading">
                    <span><x-dashboard.icon name="lock" size="23" /></span>
                    <div>
                        <h2 id="security-settings-title">Security and Privacy</h2>
                    </div>
                </div>

                @include('settings.partials.security-forms')
                @if(false)<div class="settings-account-grid">
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
                        data-confirm-message="Change the password for your REU Lead account?"
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
                </div>@endif
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
