@php
    $editableProfileFields = [
        ['first_name', 'First Name', 'text', true, null, 100],
        ['middle_name', 'Middle Name', 'text', false, null, 100],
        ['last_name', 'Last Name', 'text', true, null, 100],
        ['suffix', 'Suffix', 'text', false, null, 30],
        ['phone_number', 'Phone Number', 'tel', true, null, 11],
        ['institution', 'Institute', 'select', false, $profileOptions[\App\Enums\ProfileOptionField::Institute->value] ?? [], null],
    ];

    if ($settingsUser->role === \App\Enums\UserRole::Applicant) {
        $editableProfileFields[] = ['program', 'Program', 'select', false, $profileOptions[\App\Enums\ProfileOptionField::Program->value] ?? [], null];
        if ($settingsUser->applicant_type === \App\Enums\ApplicantType::Student) {
            $editableProfileFields[] = ['year_level', 'Year Level', 'select', true, $profileOptions[\App\Enums\ProfileOptionField::YearLevel->value] ?? [], null];
        }
    }

    if ($settingsUser->role === \App\Enums\UserRole::Adviser
        || $settingsUser->role === \App\Enums\UserRole::ResLead
        || $settingsUser->applicant_type === \App\Enums\ApplicantType::Faculty) {
        $editableProfileFields[] = ['position_title', 'Position / Designation', 'text', $settingsUser->role === \App\Enums\UserRole::Adviser, null, 150];
    }

    if ($settingsUser->role === \App\Enums\UserRole::Adviser) {
        $editableProfileFields[] = ['expected_endorsement_count', 'Declared Expected Endorsements', 'number', false, null, null];
    }
@endphp

<section class="settings-inline-profile-card" aria-label="Account profile">
    <div class="dashboard-profile-summary settings-inline-profile-summary">
        <div class="profile-image-settings-control">
            <form class="profile-image-form" method="POST" action="{{ route('profile-image.store') }}" enctype="multipart/form-data" data-profile-image-form>
                @csrf
                <label class="profile-image-control" title="Replace profile image">
                    <span class="dashboard-avatar dashboard-profile-avatar" aria-hidden="true">@if ($dashboardHasProfileImage)<img src="{{ $dashboardProfileImageUrl }}" alt="">@else{{ $dashboardUserInitials }}@endif</span>
                    <span class="profile-image-replace">Replace</span>
                    <input name="profile_image" type="file" accept="image/png,image/jpeg,.png,.jpg,.jpeg" data-profile-image-input>
                </label>
            </form>
            @if ($dashboardHasProfileImage)<form method="POST" action="{{ route('profile-image.destroy') }}">@csrf @method('DELETE')<button class="profile-image-default" type="submit">Use initials</button></form>@endif
            @error('profile_image')<small class="settings-field-error">{{ $message }}</small>@enderror
        </div>
        <div>
            <h3 data-inline-profile-name>{{ $settingsUser->name }}</h3>
            <p>{{ $settingsUser->displayRoleLabel() }}</p>
        </div>
    </div>

    <form class="settings-inline-profile-form" method="POST" action="{{ route($settingsRouteBase.'.profile.update') }}" data-inline-profile-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="settings_tab" value="profile">

        <dl class="dashboard-profile-details settings-inline-profile-details">
            <div><dt>Full Name</dt><dd data-inline-profile-full-name>{{ $settingsUser->name }}</dd></div>
            <div><dt>Username</dt><dd>{{ $settingsUser->username }}</dd></div>
            <div><dt>Email Address</dt><dd>{{ $settingsUser->email }}</dd></div>
            <div><dt>Role</dt><dd>{{ $settingsUser->displayRoleLabel() }}</dd></div>
            <div><dt>Account Status</dt><dd>{{ Str::headline($settingsUser->account_status) }}</dd></div>
            <div><dt>{{ $settingsUser->institutionalIdentifierLabel() }}</dt><dd>{{ $settingsUser->institutional_identifier }}</dd></div>

            @foreach ($editableProfileFields as [$field, $label, $type, $required, $options, $maxlength])
                @php
                    $value = old($field, $settingsUser->{$field});
                    $hasFieldError = $errors->has($field);
                @endphp
                <div class="settings-inline-profile-field" data-inline-profile-field data-profile-field="{{ $field }}">
                    <dt>{{ $label }}</dt>
                    <dd>
                        <span data-inline-profile-value data-empty-label="Not provided" @if($hasFieldError) hidden @endif>{{ filled($value) ? $value : 'Not provided' }}</span>
                        @if ($type === 'select')
                            <select name="{{ $field }}" data-inline-profile-input @if(! $hasFieldError) hidden @endif @required($required)>
                                <option value="">{{ $required ? 'Select '.$label : 'Not provided' }}</option>
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                name="{{ $field }}"
                                type="{{ $type }}"
                                value="{{ $value }}"
                                data-inline-profile-input
                                @if(! $hasFieldError) hidden @endif
                                @if($maxlength) maxlength="{{ $maxlength }}" @endif
                                @if($field === 'phone_number') minlength="11" inputmode="numeric" pattern="[0-9]{11}" @endif
                                @if($field === 'expected_endorsement_count') min="0" max="10000" @endif
                                @required($required)
                            >
                        @endif
                        <button class="settings-inline-profile-edit" type="button" data-inline-profile-edit data-mode="{{ $hasFieldError ? 'save' : 'edit' }}" aria-label="{{ $hasFieldError ? 'Save' : 'Edit' }} {{ $label }}">
                            <span data-inline-edit-icon @if($hasFieldError) hidden @endif><x-dashboard.icon name="edit" size="17" /></span>
                            <span data-inline-save-icon @if(! $hasFieldError) hidden @endif><x-dashboard.icon name="check" size="17" /></span>
                        </button>
                        <small class="settings-field-error" data-inline-profile-error>@error($field){{ $message }}@enderror</small>
                    </dd>
                </div>
            @endforeach
        </dl>
    </form>
</section>

@if ($adviserStatistics ?? null)
    <section class="settings-section" aria-labelledby="endorsement-statistics-title">
        <div class="settings-section-heading"><span><x-dashboard.icon name="clipboard" size="23" /></span><div><h3 id="endorsement-statistics-title">Endorsement Overview</h3><p>Live counts derived from unique Applicant accounts assigned to you.</p></div></div>
        <dl class="settings-profile-summary">
            <div><dt>Declared Expected</dt><dd>{{ $adviserStatistics['declared'] }}</dd></div>
            <div><dt>Successfully Endorsed</dt><dd>{{ $adviserStatistics['endorsed'] }}</dd></div>
            <div><dt>Received, Awaiting Endorsement</dt><dd>{{ $adviserStatistics['awaiting'] }}</dd></div>
            <div><dt>Remaining Expected Total</dt><dd>{{ $adviserStatistics['remaining'] }}</dd></div>
            <div><dt>Not Yet Received</dt><dd>{{ $adviserStatistics['not_received'] }}</dd></div>
        </dl>
    </section>
@endif

@if (($reviewerProfile['enabled'] ?? false) === true)
    <section class="settings-section" aria-labelledby="reviewer-profile-title">
        <div class="settings-section-heading"><span><x-dashboard.icon name="clipboard" size="23" /></span><div><h3 id="reviewer-profile-title">Reviewer Capability</h3><p>These eligibility fields are read-only and managed by the REU Lead.</p></div></div>
        <dl class="settings-profile-summary">
            <div><dt>Reviewer Access</dt><dd>Enabled</dd></div>
            <div><dt>Capacity</dt><dd>{{ $reviewerProfile['capacity'] ?: 'Not configured' }}</dd></div>
            <div><dt>Active Load</dt><dd>{{ $reviewerProfile['active_load'] }}{{ $reviewerProfile['capacity'] ? ' / '.$reviewerProfile['capacity'] : '' }}</dd></div>
            <div><dt>Available Capacity</dt><dd>{{ $reviewerProfile['available_capacity'] }}</dd></div>
            <div><dt>Eligibility</dt><dd>{{ $reviewerProfile['eligibility_label'] }}</dd></div>
        </dl>
    </section>
@endif
