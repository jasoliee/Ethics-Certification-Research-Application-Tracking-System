{{-- Shared normalized profile fields keep creation and editing behavior aligned. --}}
@php
    $profileRole = $managedUser?->role?->value ?? ($selectedType['role'] ?? null);
    $profileApplicantType = $managedUser?->applicant_type?->value ?? ($selectedType['applicant_type'] ?? null);
@endphp
<fieldset class="identity-form-section">
    <legend>Personal Information</legend>
    <div class="identity-form-grid identity-form-grid-four">
        <div class="identity-field">
            <label for="first_name">First Name <span aria-hidden="true">*</span></label>
            <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $managedUser?->first_name) }}" maxlength="100" autocomplete="given-name" required>
            @error('first_name')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="middle_name">Middle Name</label>
            <input id="middle_name" name="middle_name" type="text" value="{{ old('middle_name', $managedUser?->middle_name) }}" maxlength="100" autocomplete="additional-name">
            @error('middle_name')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="last_name">Last Name <span aria-hidden="true">*</span></label>
            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $managedUser?->last_name) }}" maxlength="100" autocomplete="family-name" required @readonly($lockIdentity ?? false)>
            @error('last_name')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="suffix">Suffix</label>
            <input id="suffix" name="suffix" type="text" value="{{ old('suffix', $managedUser?->suffix) }}" maxlength="30" placeholder="e.g., Jr.">
            @error('suffix')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
    </div>
</fieldset>

<fieldset class="identity-form-section">
    <legend>Institutional Information</legend>
    <div class="identity-form-grid">
        <div class="identity-field">
            <label for="email">Email Address <span aria-hidden="true">*</span></label>
            <input id="email" name="email" type="email" value="{{ old('email', $managedUser?->email) }}" maxlength="255" autocomplete="email" required>
            @error('email')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="institutional_identifier">{{ $identifierLabel }} <span aria-hidden="true">*</span></label>
            <input id="institutional_identifier" name="institutional_identifier" type="text" value="{{ old('institutional_identifier', $managedUser?->institutional_identifier) }}" maxlength="50" autocomplete="off" required @readonly($lockIdentity ?? false)>
            @error('institutional_identifier')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="phone_number">Phone Number</label>
            <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $managedUser?->phone_number) }}" maxlength="30" autocomplete="tel">
            @error('phone_number')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="institution">Institution / Affiliation</label>
            <select id="institution" name="institution" autocomplete="organization">
                <option value="">Select institution</option>
                @foreach ($profileOptions[\App\Enums\ProfileOptionField::Institution->value] ?? [] as $option)
                    <option value="{{ $option }}" @selected(old('institution', $managedUser?->institution) === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('institution')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="department">Department / Unit</label>
            <select id="department" name="department" autocomplete="organization-title">
                <option value="">Select department</option>
                @foreach ($profileOptions[\App\Enums\ProfileOptionField::Department->value] ?? [] as $option)
                    <option value="{{ $option }}" @selected(old('department', $managedUser?->department) === $option)>{{ $option }}</option>
                @endforeach
            </select>
            @error('department')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        @if ($profileRole === \App\Enums\UserRole::Applicant->value)
            <div class="identity-field">
                <label for="program">Program</label>
                <select id="program" name="program">
                    <option value="">Select program</option>
                    @foreach ($profileOptions[\App\Enums\ProfileOptionField::Program->value] ?? [] as $option)
                        <option value="{{ $option }}" @selected(old('program', $managedUser?->program) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('program')<span class="identity-field-error">{{ $message }}</span>@enderror
            </div>
            @if ($profileApplicantType === \App\Enums\ApplicantType::Student->value)
                <div class="identity-field">
                    <label for="year_level">Year Level <span aria-hidden="true">*</span></label>
                    <select id="year_level" name="year_level" required>
                        <option value="">Select year level</option>
                        @foreach ($profileOptions[\App\Enums\ProfileOptionField::YearLevel->value] ?? [] as $option)
                            <option value="{{ $option }}" @selected(old('year_level', $managedUser?->year_level) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('year_level')<span class="identity-field-error">{{ $message }}</span>@enderror
                </div>
            @endif
        @endif
        @if (in_array($profileRole, [\App\Enums\UserRole::Adviser->value, \App\Enums\UserRole::Reviewer->value], true) || $profileApplicantType === \App\Enums\ApplicantType::Faculty->value)
            <div class="identity-field">
                <label for="position_title">Position / Designation @if ($profileRole === \App\Enums\UserRole::Adviser->value)<span aria-hidden="true">*</span>@endif</label>
                <input id="position_title" name="position_title" type="text" value="{{ old('position_title', $managedUser?->position_title) }}" maxlength="150" @required($profileRole === \App\Enums\UserRole::Adviser->value)>
                @error('position_title')<span class="identity-field-error">{{ $message }}</span>@enderror
            </div>
        @endif
        @if ($profileRole === \App\Enums\UserRole::Reviewer->value)
            <div class="identity-field">
                <label for="reviewer_classification">Reviewer Classification <span aria-hidden="true">*</span></label>
                <select id="reviewer_classification" name="reviewer_classification" required>
                    <option value="">Select classification</option>
                    @foreach (\App\Enums\ReviewerClassification::cases() as $classification)
                        <option value="{{ $classification->value }}" @selected(old('reviewer_classification', $managedUser?->reviewer_classification?->value) === $classification->value)>{{ $classification->label() }}</option>
                    @endforeach
                </select>
                @error('reviewer_classification')<span class="identity-field-error">{{ $message }}</span>@enderror
            </div>
            <div class="identity-field">
                <label for="reviewer_capacity">Reviewer Capacity <span aria-hidden="true">*</span></label>
                <input id="reviewer_capacity" name="reviewer_capacity" type="number" value="{{ old('reviewer_capacity', $managedUser?->reviewer_capacity ?? 30) }}" min="1" max="30" required>
                @error('reviewer_capacity')<span class="identity-field-error">{{ $message }}</span>@enderror
            </div>
        @endif
    </div>
</fieldset>
