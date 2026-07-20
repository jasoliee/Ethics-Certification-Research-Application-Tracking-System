{{-- Shared normalized profile fields keep creation and editing behavior aligned. --}}
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
            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $managedUser?->last_name) }}" maxlength="100" autocomplete="family-name" required>
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
            <input id="institutional_identifier" name="institutional_identifier" type="text" value="{{ old('institutional_identifier', $managedUser?->institutional_identifier) }}" maxlength="50" autocomplete="off" required>
            @error('institutional_identifier')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="phone_number">Phone Number</label>
            <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $managedUser?->phone_number) }}" maxlength="30" autocomplete="tel">
            @error('phone_number')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="institution">Institution / Affiliation</label>
            <input id="institution" name="institution" type="text" value="{{ old('institution', $managedUser?->institution) }}" maxlength="150" autocomplete="organization">
            @error('institution')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="department">Department / Unit</label>
            <input id="department" name="department" type="text" value="{{ old('department', $managedUser?->department) }}" maxlength="150" autocomplete="organization-title">
            @error('department')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
        <div class="identity-field">
            <label for="position_title">Position / Designation</label>
            <input id="position_title" name="position_title" type="text" value="{{ old('position_title', $managedUser?->position_title) }}" maxlength="150">
            @error('position_title')<span class="identity-field-error">{{ $message }}</span>@enderror
        </div>
    </div>
</fieldset>
