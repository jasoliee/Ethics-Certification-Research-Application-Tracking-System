<section
    class="settings-tab-panel"
    id="settings-panel-requirements"
    role="tabpanel"
    aria-labelledby="settings-tab-requirements"
    data-settings-panel="requirements"
    @if ($initialTab !== 'requirements') hidden @endif
>
    <section class="settings-section settings-requirements-section" aria-labelledby="requirements-configuration-title">
        <div class="settings-section-heading settings-requirements-heading">
            <span><x-dashboard.icon name="file-text" size="23" /></span>
            <div>
                <h2 id="requirements-configuration-title">Requirements Configuration</h2>
                <p>Manage the mandatory documents shown to Applicants and reviewed by authorized Advisers, Reviewers, and the RES Lead.</p>
            </div>
        </div>

        @if ($requirementsLockedTerm)
            <div class="settings-requirements-lock" role="status">
                <x-dashboard.icon name="lock" size="19" />
                <div>
                    <strong>Structural changes are locked for {{ $requirementsLockedTerm->label() }}.</strong>
                    <p>You may edit existing names and specifications, but requirements cannot be added or deleted while this term is active.</p>
                </div>
            </div>
        @else
            <div class="settings-requirements-open" role="status">
                <x-dashboard.icon name="check" size="19" />
                <span>No academic term is currently active. Requirements may be added, edited, or deleted.</span>
            </div>
        @endif

        @error('requirements', 'requirementConfiguration')
            <div class="identity-alert identity-alert-danger" role="alert">{{ $message }}</div>
        @enderror

        <form class="settings-requirement-create" method="POST" action="{{ route('res.settings.requirements.store') }}">
            @csrf
            <input type="hidden" name="settings_tab" value="requirements">
            <fieldset @disabled($requirementsLockedTerm !== null)>
                <legend>Add Requirement</legend>
                <div class="settings-field">
                    <label for="new_requirement_name">Requirement Name</label>
                    <input id="new_requirement_name" name="name" type="text" value="{{ old('name') }}" maxlength="255" required>
                </div>
                <div class="settings-field settings-requirement-specification">
                    <label for="new_requirement_description">Specification</label>
                    <textarea id="new_requirement_description" name="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                </div>
                <button class="dashboard-primary-action" type="submit" @disabled($requirementsLockedTerm !== null)>
                    <x-dashboard.icon name="plus" size="17" />
                    <span>Add Requirement</span>
                </button>
            </fieldset>
        </form>

        <div class="settings-requirement-summary">
            <strong>{{ $documentRequirements->count() }} active {{ Str::plural('requirement', $documentRequirements->count()) }}</strong>
            <span>Changes use the shared server-side requirement catalogue.</span>
        </div>

        <div class="settings-requirement-list">
            @forelse ($documentRequirements as $requirement)
                <article class="settings-requirement-card">
                    <form method="POST" action="{{ route('res.settings.requirements.update', $requirement) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="settings_tab" value="requirements">
                        <header>
                            <div>
                                <span class="settings-requirement-code">{{ $requirement->code }}</span>
                                <small>{{ $requirement->application_documents_count }} stored {{ Str::plural('document', $requirement->application_documents_count) }} linked</small>
                            </div>
                            <span class="settings-requirement-required">Required</span>
                        </header>
                        <div class="settings-requirement-edit-grid">
                            <div class="settings-field">
                                <label for="requirement_name_{{ $requirement->id }}">Requirement Name</label>
                                <input id="requirement_name_{{ $requirement->id }}" name="name" type="text" value="{{ $requirement->name }}" maxlength="255" required>
                            </div>
                            <div class="settings-field settings-requirement-specification">
                                <label for="requirement_description_{{ $requirement->id }}">Specification</label>
                                <textarea id="requirement_description_{{ $requirement->id }}" name="description" rows="3" maxlength="2000">{{ $requirement->description }}</textarea>
                            </div>
                        </div>
                        <div class="settings-requirement-actions">
                            <button class="dashboard-outline-action" type="submit">
                                <x-dashboard.icon name="edit" size="16" />
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('res.settings.requirements.destroy', $requirement) }}">
                        @csrf
                        @method('DELETE')
                        <button class="settings-requirement-delete" type="submit" @disabled($requirementsLockedTerm !== null) aria-label="Delete {{ $requirement->name }}">
                            <x-dashboard.icon name="trash" size="16" />
                            <span>Delete</span>
                        </button>
                    </form>
                </article>
            @empty
                <div class="dashboard-empty-state">
                    <x-dashboard.icon name="file-text" size="34" />
                    <h3>No active requirements</h3>
                    <p>Add at least one requirement before opening application submission.</p>
                </div>
            @endforelse
        </div>
    </section>
</section>
