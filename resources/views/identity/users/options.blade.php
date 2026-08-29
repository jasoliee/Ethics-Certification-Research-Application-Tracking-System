@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading">
                <h1>Dropdown Option Management</h1>
                <p>Manage the controlled values used by account forms and newly generated Excel templates.</p>
            </div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back to User Management</span></a>
        </header>

        @if ($errors->any())
            <div class="identity-validation-summary" role="alert"><strong>The option was not saved.</strong><span>{{ $errors->first() }}</span></div>
        @endif

        <section class="identity-option-create" aria-labelledby="add-option-heading">
            <div><h2 id="add-option-heading">Add Dropdown Option</h2><p>Spacing and capitalization variants are treated as duplicates.</p></div>
            @php
                $selectedOptionField = old('option_field', $filters['field'] ?? \App\Enums\ProfileOptionField::YearLevel->value);
                $creatingInstituteOption = $selectedOptionField === \App\Enums\ProfileOptionField::Institute->value;
            @endphp
            <form method="POST" action="{{ route($routeBase.'.profile-options.store') }}" data-profile-option-create>
                @csrf
                <div class="identity-field">
                    <label for="option_field">Option Group</label>
                    <select id="option_field" name="option_field" required data-profile-option-field>
                        @foreach (\App\Enums\ProfileOptionField::managedCases() as $field)
                            <option value="{{ $field->value }}" @selected($selectedOptionField === $field->value)>{{ $field->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="identity-field identity-option-value-field">
                    <label for="option_value">Option Value</label>
                    <input id="option_value" name="option_value" type="text" value="{{ old('option_value') }}" maxlength="150" placeholder="Enter a clear display value" required>
                </div>
                <div class="identity-field identity-option-acronym-field" data-profile-option-acronym-field @if (! $creatingInstituteOption) hidden @endif>
                    <label for="option_acronym">Institute Acronym</label>
                    <input
                        id="option_acronym"
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
                </div>
                <button class="identity-button identity-button-primary" type="submit"><x-dashboard.icon name="plus" size="18" /><span>Add Option</span></button>
            </form>
        </section>

        <form class="identity-filter-bar identity-option-filters unified-filter-panel" method="GET" action="{{ route($routeBase.'.profile-options.index') }}">
            <x-dashboard.filter-header description="Refine configured dropdown options." :reset-href="route($routeBase.'.profile-options.index')" />
            <div class="unified-filter-fields">
            <div class="identity-filter identity-filter-search">
                <label for="option-search">Search</label>
                <div class="identity-input-icon"><x-dashboard.icon name="search" size="19" /><input id="option-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Option value" maxlength="100"></div>
            </div>
            <div class="identity-filter">
                <label for="option-group-filter">Option Group</label>
                <select id="option-group-filter" name="field">
                    <option value="">All groups</option>
                    @foreach (\App\Enums\ProfileOptionField::managedCases() as $field)
                        <option value="{{ $field->value }}" @selected(($filters['field'] ?? null) === $field->value)>{{ $field->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter">
                <label for="option-status-filter">Status</label>
                <select id="option-status-filter" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? null) === 'active')>Active ({{ $counts['active'] }})</option>
                    <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Inactive ({{ $counts['inactive'] }})</option>
                </select>
            </div>
            </div>
        </form>

        <section class="identity-table-panel" aria-labelledby="option-results-heading">
            <div class="identity-table-summary">
                <strong id="option-results-heading">{{ $options->total() > 0 ? 'Showing '.$options->firstItem().' to '.$options->lastItem().' of '.$options->total().' options' : 'Showing 0 options' }}</strong>
                <span>Inactive values remain on existing accounts but are unavailable for new selections.</span>
            </div>
            {{-- Option-management columns use the shared focusable horizontal-scroll region. --}}
            <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Dropdown option results" tabindex="0">
                <table class="identity-user-table identity-option-table">
                    <thead><tr><th>Option Group</th><th>Option Value</th><th class="identity-col-acronym">Institute Acronym</th><th class="identity-col-usage">In Use</th><th class="identity-col-status">Status</th><th class="identity-col-action">Action</th></tr></thead>
                    <tbody>
                        @forelse ($options as $option)
                            <tr>
                                <td><strong>{{ $option->field->label() }}</strong></td>
                                <td>
                                    <form id="option-edit-form-{{ $option->id }}" method="POST" action="{{ route($routeBase.'.profile-options.update', $option) }}">
                                        @csrf
                                        @method('PUT')
                                    </form>
                                    <label class="sr-only" for="option-value-{{ $option->id }}">Edit {{ $option->field->label() }} option</label>
                                    <input id="option-value-{{ $option->id }}" class="identity-option-table-input" name="option_value" type="text" value="{{ $option->value }}" maxlength="150" form="option-edit-form-{{ $option->id }}" required>
                                </td>
                                <td class="identity-col-acronym">
                                    @if ($option->field === \App\Enums\ProfileOptionField::Institute)
                                        <label class="sr-only" for="option-acronym-{{ $option->id }}">Edit {{ $option->value }} acronym</label>
                                        <input id="option-acronym-{{ $option->id }}" class="identity-option-table-input identity-option-acronym-input" name="option_acronym" type="text" value="{{ $option->acronym }}" maxlength="12" form="option-edit-form-{{ $option->id }}" required>
                                    @else
                                        <span aria-label="Not applicable">&mdash;</span>
                                    @endif
                                </td>
                                <td class="identity-col-usage"><strong>{{ $usageCounts[$option->id] ?? 0 }}</strong></td>
                                <td class="identity-col-status"><x-dashboard.status-badge class="identity-status-badge" :label="$option->is_active ? 'Active' : 'Inactive'" :tone="$option->is_active ? 'green' : 'neutral'" dot /></td>
                                <td class="identity-col-action">
                                    <div class="identity-option-row-actions">
                                        <button class="identity-button identity-button-secondary" type="submit" form="option-edit-form-{{ $option->id }}"><x-dashboard.icon name="edit" size="17" /><span>Save</span></button>
                                        <form method="POST" action="{{ route($routeBase.'.profile-options.status', $option) }}" data-confirm-option-status="{{ $option->is_active ? 'Deactivate this option for new forms and Excel templates?' : 'Restore this option for new forms and Excel templates?' }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="is_active" value="{{ $option->is_active ? 0 : 1 }}">
                                            <button class="identity-view-link" type="submit">{{ $option->is_active ? 'Deactivate' : 'Restore' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="identity-empty-row"><td colspan="6"><div class="identity-empty-state"><span><x-dashboard.icon name="settings" size="42" /></span><strong>No dropdown options found</strong><p>Adjust the filters or add a new option.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-dashboard.pagination :paginator="$options" label="Dropdown option pages" />
        </section>
    </div>
@endsection
