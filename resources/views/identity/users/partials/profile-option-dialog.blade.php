@if ($canManageProfileOptions ?? false)
    <section
        class="identity-mode-overlay"
        data-profile-option-dialog
        @if ($errors->has('option_field') || $errors->has('option_value')) data-open-on-load @else hidden @endif
    >
        <div class="identity-mode-dialog identity-option-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-option-title" tabindex="-1">
            <button class="identity-mode-close" type="button" aria-label="Close dropdown option form" data-profile-option-close>
                <x-dashboard.icon name="x" size="20" />
            </button>
            <h2 id="profile-option-title">Add Dropdown Option</h2>
            <p>The new value will be available in account forms and template validation.</p>

            <form method="POST" action="{{ route('res.users.profile-options.store') }}" data-profile-option-form>
                @csrf
                <div class="identity-field">
                    <label for="option_field">Dropdown Field</label>
                    <select id="option_field" name="option_field" required>
                        <option value="">Select a field</option>
                        @foreach (\App\Enums\ProfileOptionField::cases() as $field)
                            <option value="{{ $field->value }}" @selected(old('option_field') === $field->value)>{{ $field->label() }}</option>
                        @endforeach
                    </select>
                    @error('option_field')<span class="identity-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="identity-field">
                    <label for="option_value">New Option Value</label>
                    <input id="option_value" name="option_value" type="text" value="{{ old('option_value') }}" maxlength="150" required>
                    @error('option_value')<span class="identity-field-error">{{ $message }}</span>@enderror
                </div>
                <div class="identity-dialog-actions">
                    <button class="identity-button identity-button-secondary" type="button" data-profile-option-close>Cancel</button>
                    <button class="identity-button identity-button-primary" type="submit">Add Option</button>
                </div>
            </form>
        </div>
    </section>
@endif
