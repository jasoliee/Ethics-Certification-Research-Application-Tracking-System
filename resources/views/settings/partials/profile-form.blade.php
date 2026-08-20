<form class="settings-account-form settings-profile-form" method="POST" action="{{ route($settingsRouteBase.'.profile.update') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="settings_tab" value="profile">

    <div>
        <h3>Editable Profile Information</h3>
        <p>Your role, account status, institutional identifier, and Reviewer eligibility are controlled by authorized RES workflows.</p>
    </div>

    <div class="identity-form-grid">
        @foreach ([
            ['first_name', 'First Name', true, 'given-name'],
            ['middle_name', 'Middle Name', false, 'additional-name'],
            ['last_name', 'Last Name', true, 'family-name'],
            ['suffix', 'Suffix', false, 'honorific-suffix'],
        ] as [$field, $label, $required, $autocomplete])
            <div class="settings-field">
                <label for="settings_{{ $field }}">{{ $label }} @if ($required)<span aria-hidden="true">*</span>@endif</label>
                <input id="settings_{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field, $settingsUser->{$field}) }}" maxlength="{{ $field === 'suffix' ? 30 : 100 }}" autocomplete="{{ $autocomplete }}" @required($required)>
                @error($field)<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
        @endforeach

        <div class="settings-field">
            <label for="settings_phone_number">Phone Number <span aria-hidden="true">*</span></label>
            <input id="settings_phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $settingsUser->phone_number) }}" minlength="11" maxlength="11" inputmode="numeric" pattern="[0-9]{11}" autocomplete="tel" required>
            @error('phone_number')<span class="settings-field-error">{{ $message }}</span>@enderror
        </div>

        @foreach ([
            [\App\Enums\ProfileOptionField::Institution, 'institution', 'Institution / Affiliation'],
            [\App\Enums\ProfileOptionField::Department, 'department', 'Department / Unit'],
        ] as [$optionField, $field, $label])
            <div class="settings-field">
                <label for="settings_{{ $field }}">{{ $label }}</label>
                <select id="settings_{{ $field }}" name="{{ $field }}">
                    <option value="">Not provided</option>
                    @foreach ($profileOptions[$optionField->value] ?? [] as $option)
                        <option value="{{ $option }}" @selected(old($field, $settingsUser->{$field}) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error($field)<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
        @endforeach

        @if ($settingsUser->role === \App\Enums\UserRole::Applicant)
            <div class="settings-field">
                <label for="settings_program">Program</label>
                <select id="settings_program" name="program">
                    <option value="">Not provided</option>
                    @foreach ($profileOptions[\App\Enums\ProfileOptionField::Program->value] ?? [] as $option)
                        <option value="{{ $option }}" @selected(old('program', $settingsUser->program) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('program')<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
            @if ($settingsUser->applicant_type === \App\Enums\ApplicantType::Student)
                <div class="settings-field">
                    <label for="settings_year_level">Year Level <span aria-hidden="true">*</span></label>
                    <select id="settings_year_level" name="year_level" required>
                        <option value="">Select year level</option>
                        @foreach ($profileOptions[\App\Enums\ProfileOptionField::YearLevel->value] ?? [] as $option)
                            <option value="{{ $option }}" @selected(old('year_level', $settingsUser->year_level) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('year_level')<span class="settings-field-error">{{ $message }}</span>@enderror
                </div>
            @endif
        @endif

        @if ($settingsUser->role === \App\Enums\UserRole::Adviser || $settingsUser->role === \App\Enums\UserRole::ResLead || $settingsUser->applicant_type === \App\Enums\ApplicantType::Faculty)
            <div class="settings-field">
                <label for="settings_position_title">Position / Designation @if ($settingsUser->role === \App\Enums\UserRole::Adviser)<span aria-hidden="true">*</span>@endif</label>
                <input id="settings_position_title" name="position_title" type="text" value="{{ old('position_title', $settingsUser->position_title) }}" maxlength="150" @required($settingsUser->role === \App\Enums\UserRole::Adviser)>
                @error('position_title')<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
        @endif

        @if ($settingsUser->role === \App\Enums\UserRole::Adviser)
            <div class="settings-field">
                <label for="settings_expected_endorsement_count">Expected Endorsements</label>
                <input id="settings_expected_endorsement_count" name="expected_endorsement_count" type="number" value="{{ old('expected_endorsement_count', $settingsUser->expected_endorsement_count) }}" min="0" max="10000">
                <small>Declare the number of applications you expect to endorse.</small>
                @error('expected_endorsement_count')<span class="settings-field-error">{{ $message }}</span>@enderror
            </div>
        @endif
    </div>

    <button class="dashboard-primary-action" type="submit"><x-dashboard.icon name="check" size="17" /><span>Save Profile</span></button>
</form>

@if ($adviserStatistics ?? null)
    <section class="settings-section" aria-labelledby="endorsement-statistics-title">
        <div class="settings-section-heading"><span><x-dashboard.icon name="clipboard" size="23" /></span><div><h3 id="endorsement-statistics-title">Endorsement Overview</h3><p>Live counts derived from applications assigned to your account.</p></div></div>
        <dl class="settings-profile-summary">
            <div><dt>Declared Expected</dt><dd>{{ $adviserStatistics['declared'] }}</dd></div>
            <div><dt>Successfully Endorsed</dt><dd>{{ $adviserStatistics['endorsed'] }}</dd></div>
            <div><dt>Received, Awaiting Endorsement</dt><dd>{{ $adviserStatistics['awaiting'] }}</dd></div>
            <div><dt>Remaining Expected Total</dt><dd>{{ $adviserStatistics['remaining'] }}</dd></div>
            <div><dt>Not Yet Received</dt><dd>{{ $adviserStatistics['not_received'] }}</dd></div>
        </dl>
    </section>
@endif

@if ($reviewerProfile ?? null)
    <section class="settings-section" aria-labelledby="reviewer-profile-title">
        <div class="settings-section-heading"><span><x-dashboard.icon name="clipboard" size="23" /></span><div><h3 id="reviewer-profile-title">Reviewer Capability</h3><p>These eligibility fields are read-only and managed by the RES Lead.</p></div></div>
        <dl class="settings-profile-summary">
            <div><dt>Reviewer Access</dt><dd>{{ $reviewerProfile['enabled'] ? 'Enabled' : 'Disabled' }}</dd></div>
            <div><dt>Capacity</dt><dd>{{ $reviewerProfile['capacity'] ?: 'Not configured' }}</dd></div>
            <div><dt>Active Load</dt><dd>{{ $reviewerProfile['active_load'] }}{{ $reviewerProfile['capacity'] ? ' / '.$reviewerProfile['capacity'] : '' }}</dd></div>
            <div><dt>Available Capacity</dt><dd>{{ $reviewerProfile['available_capacity'] }}</dd></div>
            <div><dt>Eligibility</dt><dd>{{ $reviewerProfile['eligibility_label'] }}</dd></div>
        </dl>
    </section>
@endif
