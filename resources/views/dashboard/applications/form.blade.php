@extends('layouts.dashboard')

@section('content')
    @php
        // Resolve shared old-input and persisted values once for every field.
        $fieldValue = fn (string $field, mixed $fallback = null) => old($field, $application?->{$field} ?? $fallback);
        $isEditing = $application !== null;
        $selectedResearchType = old('research_type', $application?->research_type?->value);
        $expectedStartDate = old('expected_start_date', $application?->expected_start_date?->format('Y-m-d'));
        $expectedEndDate = old('expected_end_date', $application?->expected_end_date?->format('Y-m-d'));
    @endphp

    <div class="dashboard-page application-workspace">
        {{-- The form heading follows the high-fidelity information hierarchy without introducing a separate design system. --}}
        <header class="dashboard-page-heading">
            <h1>Application Information Form</h1>
            <p>Enter the research details that will be reviewed with your submitted documents.</p>
        </header>

        {{-- The server chooses create or update semantics while preserving all validation input. --}}
        <form
            class="application-form application-information-form"
            method="POST"
            action="{{ $isEditing ? route('applicant.applications.update', $application) : route('applicant.applications.store') }}"
            data-application-submit-once
        >
            @csrf
            @if ($isEditing) @method('PUT') @endif

            @if ($errors->any())
                {{-- Validation summary links the failure state to field-level messages below. --}}
                <div class="identity-validation-summary" role="alert">
                    <strong>Review the highlighted application information.</strong>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- Research identity fields establish the application category and assigned Adviser. --}}
            <section class="application-form-section" aria-labelledby="research-information-title">
                <header class="application-form-section-heading">
                    <span aria-hidden="true"><x-dashboard.icon name="file-text" size="18" /></span>
                    <h2 id="research-information-title">Research Information</h2>
                </header>
                <div class="application-form-section-body">
                    <div class="application-form-grid">
                    <div class="application-field application-field-wide">
                        <label for="research_title">Research Title <span aria-hidden="true">*</span></label>
                        <input id="research_title" name="research_title" value="{{ $fieldValue('research_title') }}" maxlength="255" required>
                        @error('research_title')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field">
                        <label for="research_type">Research Type <span aria-hidden="true">*</span></label>
                        <select id="research_type" name="research_type" required>
                            <option value="">Select research type</option>
                            @foreach ($researchTypes as $researchType)
                                <option value="{{ $researchType->value }}" @selected($selectedResearchType === $researchType->value)>{{ $researchType->label() }}</option>
                            @endforeach
                        </select>
                        @error('research_type')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field">
                        <label for="applicant_type">Applicant Type</label>
                        <input id="applicant_type" value="{{ auth()->user()->displayRoleLabel() }}" readonly>
                    </div>
                    <div class="application-field">
                        <label for="research_category">Research Category <span aria-hidden="true">*</span></label>
                        <input id="research_category" name="research_category" value="{{ $fieldValue('research_category') }}" maxlength="150" required>
                        @error('research_category')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field application-field-full">
                        <label for="adviser_user_id">Research Adviser <span aria-hidden="true">*</span></label>
                        <select id="adviser_user_id" name="adviser_user_id" required>
                            <option value="">Select Adviser</option>
                            @foreach ($advisers as $adviser)
                                <option value="{{ $adviser->id }}" @selected((string) $fieldValue('adviser_user_id') === (string) $adviser->id)>{{ $adviser->name }} ({{ $adviser->email }})</option>
                            @endforeach
                        </select>
                        @if ($advisers->isEmpty())
                            <small class="application-field-help">{{ (auth()->user()->applicant_type ?? \App\Enums\ApplicantType::Student) === \App\Enums\ApplicantType::Student
                                ? 'No active eligible Research Adviser is currently available in your department.'
                                : 'No active eligible Research Adviser is currently available.' }}</small>
                        @endif
                        @error('adviser_user_id')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    </div>
                </div>
            </section>

            {{-- Institutional fields capture a submission-time snapshot from managed dropdown options. --}}
            <section class="application-form-section" aria-labelledby="institutional-information-title">
                <header class="application-form-section-heading">
                    <span aria-hidden="true"><x-dashboard.icon name="building-2" size="18" /></span>
                    <h2 id="institutional-information-title">Institutional Information</h2>
                </header>
                <div class="application-form-section-body">
                    <div class="application-form-grid application-form-grid-three">
                    <div class="application-field">
                        <label for="institution">Institution or College <span aria-hidden="true">*</span></label>
                        <select id="institution" name="institution" required>
                            <option value="">Select institution</option>
                            @foreach ($profileOptions[\App\Enums\ProfileOptionField::Institution->value] as $option)
                                <option value="{{ $option }}" @selected($fieldValue('institution', auth()->user()->institution) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('institution')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field">
                        <label for="department">Department <span aria-hidden="true">*</span></label>
                        <select id="department" name="department" required>
                            <option value="">Select department</option>
                            @foreach ($profileOptions[\App\Enums\ProfileOptionField::Department->value] as $option)
                                <option value="{{ $option }}" @selected($fieldValue('department', auth()->user()->department) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('department')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field">
                        <label for="program">Program @if ((auth()->user()->applicant_type ?? \App\Enums\ApplicantType::Student) === \App\Enums\ApplicantType::Student)<span aria-hidden="true">*</span>@endif</label>
                        <select id="program" name="program" @required((auth()->user()->applicant_type ?? \App\Enums\ApplicantType::Student) === \App\Enums\ApplicantType::Student)>
                            <option value="">Select program</option>
                            @foreach ($profileOptions[\App\Enums\ProfileOptionField::Program->value] as $option)
                                <option value="{{ $option }}" @selected($fieldValue('program', auth()->user()->program) === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('program')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    </div>
                </div>
            </section>

            {{-- Study-scope fields provide the concise information the assigned Adviser needs for initial review. --}}
            <section class="application-form-section" aria-labelledby="study-scope-title">
                <header class="application-form-section-heading">
                    <span aria-hidden="true"><x-dashboard.icon name="target" size="18" /></span>
                    <h2 id="study-scope-title">Study Scope</h2>
                </header>
                <div class="application-form-section-body">
                    <div class="application-form-grid">
                    <div class="application-field application-field-full">
                        <label for="abstract">Brief Description or Abstract <span aria-hidden="true">*</span></label>
                        <textarea id="abstract" name="abstract" rows="7" maxlength="5000" required>{{ $fieldValue('abstract') }}</textarea>
                        @error('abstract')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-field application-field-wide">
                        <label for="target_participants">Target Participants <span aria-hidden="true">*</span></label>
                        <textarea id="target_participants" name="target_participants" rows="4" maxlength="2000" required>{{ $fieldValue('target_participants') }}</textarea>
                        @error('target_participants')<span class="identity-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="application-duration-fields">
                        <div class="application-field">
                            <label for="expected_start_date">Starting Date <span aria-hidden="true">*</span></label>
                            <input id="expected_start_date" name="expected_start_date" type="date" value="{{ $expectedStartDate }}" required>
                            @error('expected_start_date')<span class="identity-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="application-field">
                            <label for="expected_end_date">Ending Date <span aria-hidden="true">*</span></label>
                            <input id="expected_end_date" name="expected_end_date" type="date" value="{{ $expectedEndDate }}" min="{{ $expectedStartDate }}" required data-expected-end-date>
                            @error('expected_end_date')<span class="identity-field-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    </div>
                </div>
            </section>

            {{-- Cancel never writes a draft; Save and Continue is the only state-changing command. --}}
            <div class="application-form-actions">
                <a class="dashboard-outline-action" href="{{ route('applicant.applications.index') }}">Cancel</a>
                <button class="dashboard-primary-action" type="submit">
                    <span>Save and Continue</span>
                    <x-dashboard.icon name="arrow-right" size="18" />
                </button>
            </div>
        </form>
    </div>
@endsection
