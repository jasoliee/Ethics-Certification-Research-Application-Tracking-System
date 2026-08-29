@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        {{-- Page actions remain compact so the data table stays the primary work surface. --}}
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading">
                <h1>{{ $isResLead ? 'User Management' : 'Applicant Accounts' }}</h1>
            </div>

            <div class="identity-heading-actions">
                <a class="identity-button identity-button-primary" href="{{ route($routeBase.'.create') }}">
                    <x-dashboard.icon name="plus" size="19" />
                    <span>Add Account</span>
                </a>
            </div>
        </header>

        @if ($adviserStatistics)
            <div class="dashboard-summary-grid dashboard-summary-grid-five" aria-label="Adviser endorsement workload">
                <x-dashboard.summary-card label="Declared Expected" :count="$adviserStatistics['declared']" icon="clipboard" tone="blue" :href="route('adviser.settings.index', ['tab' => 'profile'])" />
                <x-dashboard.summary-card label="Successfully Endorsed" :count="$adviserStatistics['endorsed']" icon="check" tone="green" :href="route('adviser.applications.index')" />
                <x-dashboard.summary-card label="Received, Awaiting Endorsement" :count="$adviserStatistics['awaiting']" icon="file-text" tone="orange" :href="route('adviser.applications.index', ['status' => \App\Enums\ApplicationStatus::SubmittedToAdviser->value])" />
                <x-dashboard.summary-card label="Remaining Expected Total" :count="$adviserStatistics['remaining']" icon="refresh" tone="violet" :href="route('adviser.applications.index')" />
                <x-dashboard.summary-card label="Not Yet Received" :count="$adviserStatistics['not_received']" icon="clock" tone="neutral" :href="route('adviser.applicants.index')" />
            </div>
        @endif

        @if ($isResLead && $identityReconciliations->isNotEmpty())
            <section class="identity-table-panel" aria-labelledby="reviewer-reconciliation-heading">
                <div class="identity-table-summary">
                    <span>
                        <strong id="reviewer-reconciliation-heading">Reviewer identity reconciliation</strong>
                        <small>{{ $identityReconciliations->count() }} possible duplicate {{ str('identity')->plural($identityReconciliations->count()) }} require an REU decision. No record is merged automatically.</small>
                    </span>
                </div>
                <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Pending Reviewer identity reconciliations" tabindex="0">
                    <table class="identity-user-table">
                        <thead>
                            <tr><th>Preserved Reviewer identity</th><th>Existing Adviser identity</th><th>Matched fields</th><th>Resolution</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($identityReconciliations as $reconciliation)
                                <tr>
                                    <td><strong>{{ $reconciliation->sourceUser->name }}</strong><small>{{ $reconciliation->sourceUser->email }} · {{ $reconciliation->sourceUser->institutional_identifier }}</small></td>
                                    <td><strong>{{ $reconciliation->targetAdviser->name }}</strong><small>{{ $reconciliation->targetAdviser->email }} · {{ $reconciliation->targetAdviser->institutional_identifier }}</small></td>
                                    <td>{{ collect($reconciliation->matched_fields)->map(fn ($field) => Str::headline($field))->implode(', ') }}</td>
                                    <td>
                                        <div class="identity-heading-actions">
                                            <form method="POST" action="{{ route('res.users.reviewer-reconciliations.keep-separate', $reconciliation) }}">
                                                @csrf
                                                <button class="identity-button identity-button-secondary" type="submit">Keep separate</button>
                                            </form>
                                            <form method="POST" action="{{ route('res.users.reviewer-reconciliations.merge', $reconciliation) }}" onsubmit="return window.confirm('Merge preserved Reviewer history into this Adviser account? The duplicate account will be retained as inactive.');">
                                                @csrf
                                                <input type="hidden" name="confirm_merge" value="1">
                                                <button class="identity-button identity-button-warning" type="submit">Merge history</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- REU Lead retains role tabs; Adviser receives one accurate applicant-count header. --}}
        <nav class="identity-role-tabs {{ $isResLead ? 'is-four' : 'is-one' }}" aria-label="Account category filters">
            @if ($isResLead)
                <a class="{{ empty($filters['role']) ? 'is-active' : '' }}" href="{{ route($routeBase.'.index') }}">
                    <x-dashboard.icon name="users" size="20" />
                    <span>All Users</span>
                    <small>{{ $counts['all'] }}</small>
                </a>
                <a class="{{ ($filters['role'] ?? null) === \App\Enums\UserRole::Adviser->value ? 'is-active' : '' }}" href="{{ route($routeBase.'.index', ['role' => \App\Enums\UserRole::Adviser->value]) }}">
                    <x-dashboard.icon name="user-check" size="20" />
                    <span>Advisers</span>
                    <small>{{ $counts['advisers'] }}</small>
                </a>
                <a class="{{ ($filters['role'] ?? null) === \App\Enums\UserRole::Reviewer->value ? 'is-active' : '' }}" href="{{ route($routeBase.'.index', ['role' => \App\Enums\UserRole::Reviewer->value]) }}">
                    <x-dashboard.icon name="file-search" size="20" />
                    <span>Reviewers</span>
                    <small>{{ $counts['reviewers'] }}</small>
                </a>
            @endif
            <a class="{{ ! $isResLead || ($filters['role'] ?? null) === \App\Enums\UserRole::Applicant->value ? 'is-active' : '' }}" href="{{ route($routeBase.'.index', ['role' => \App\Enums\UserRole::Applicant->value]) }}">
                <x-dashboard.icon name="user" size="20" />
                <span>Applicants</span>
                <small>{{ $counts['applicants'] }}</small>
            </a>
        </nav>

        {{-- Filters use GET parameters so views are bookmarkable and pagination retains the current search. --}}
        <form class="identity-filter-bar {{ $isResLead ? 'has-role-filter' : 'is-adviser-filter' }}" method="GET" action="{{ route($routeBase.'.index') }}">
            <div class="identity-filter identity-filter-search">
                <label for="user-search">Search</label>
                <div class="identity-input-icon">
                    <x-dashboard.icon name="search" size="19" />
                    <input id="user-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, email, or ID" maxlength="100">
                </div>
            </div>

            @if ($isResLead)
                <div class="identity-filter">
                    <label for="role-filter">Role</label>
                    <select id="role-filter" name="role">
                        <option value="">All roles</option>
                        <option value="{{ \App\Enums\UserRole::Applicant->value }}" @selected(($filters['role'] ?? null) === \App\Enums\UserRole::Applicant->value)>Applicants</option>
                        <option value="{{ \App\Enums\UserRole::Adviser->value }}" @selected(($filters['role'] ?? null) === \App\Enums\UserRole::Adviser->value)>Advisers</option>
                        <option value="{{ \App\Enums\UserRole::Reviewer->value }}" @selected(($filters['role'] ?? null) === \App\Enums\UserRole::Reviewer->value)>Reviewer-enabled Advisers</option>
                    </select>
                </div>
            @endif

            <div class="identity-filter">
                <label for="institution-filter">Institute</label>
                <select id="institution-filter" name="institution">
                    <option value="">All institutes</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution }}" @selected(($filters['institution'] ?? null) === $institution)>{{ $institution }}</option>
                    @endforeach
                </select>
            </div>

            @unless ($isResLead)
                <div class="identity-filter">
                    <label for="applicant-type-filter">Filter by Role</label>
                    <select id="applicant-type-filter" name="applicant_type">
                        <option value="">All applicants</option>
                        <option value="{{ \App\Enums\ApplicantType::Student->value }}" @selected(($filters['applicant_type'] ?? null) === \App\Enums\ApplicantType::Student->value)>Student Researcher</option>
                        <option value="{{ \App\Enums\ApplicantType::Faculty->value }}" @selected(($filters['applicant_type'] ?? null) === \App\Enums\ApplicantType::Faculty->value)>Faculty Researcher</option>
                    </select>
                </div>
            @endunless

            <div class="identity-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="account_status">
                    <option value="">All statuses</option>
                    <option value="pending_setup" @selected(($filters['account_status'] ?? null) === 'pending_setup')>Pending Setup</option>
                    <option value="active" @selected(($filters['account_status'] ?? null) === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['account_status'] ?? null) === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="identity-filter-actions">
                <button class="identity-button identity-button-primary" type="submit">Apply</button>
                <a class="identity-button identity-button-warning" href="{{ route($routeBase.'.index') }}" aria-label="Reset user filters">Reset</a>
            </div>
        </form>

        @if ($isResLead)
            <form method="POST" action="{{ route($routeBase.'.mass-action') }}" data-managed-mass-action>
                @csrf
                <div class="identity-mass-toolbar">
                    <label for="mass-action">Selected accounts</label>
                    <select id="mass-action" required data-mass-action-select>
                        <option value="">Choose action</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="archive">Delete from active records</option>
                        <option value="resend_setup">Resend setup link</option>
                        <option value="show_reviewer">Show Reviewer</option>
                        <option value="hide_reviewer">Hide Reviewer</option>
                    </select>
                    <input type="hidden" name="action" value="" data-mass-action-value>
                    <input type="hidden" name="confirm_active_assignments" value="0" data-reviewer-assignment-confirmation>
                    <button class="identity-button identity-button-secondary" type="submit" data-mass-submit="selected">Apply Action</button>
                    <button class="identity-button identity-button-secondary" type="submit" data-mass-submit="resend_all_pending">Resend All Pending</button>
                </div>
        @endif

        {{-- The table preserves a stable header and a purpose-built empty state for zero results. --}}
        <section class="identity-table-panel" aria-labelledby="user-results-heading">
            <div class="identity-table-summary">
                <strong id="user-results-heading">
                    @if ($users->total() > 0)
                        Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} users
                    @else
                        Showing 0 users
                    @endif
                </strong>
                <a href="{{ request()->fullUrl() }}" aria-label="Refresh user results">
                    <x-dashboard.icon name="refresh" size="18" />
                    <span>Refresh</span>
                </a>
            </div>

            {{-- The focusable region contains wide columns and exposes native horizontal keyboard and touch scrolling. --}}
            <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="User account results" tabindex="0">
                <table class="identity-user-table">
                    <thead>
                        <tr>
                            @if ($isResLead)<th scope="col" class="identity-checkbox-cell"><input type="checkbox" aria-label="Select all visible accounts" data-select-all-users></th>@endif
                            <th scope="col" class="identity-col-name identity-col-name-heading">Name</th>
                            <th scope="col" class="identity-col-identifier">Institutional ID</th>
                            <th scope="col" class="identity-col-email">Email</th>
                            <th scope="col" class="identity-col-role">Role</th>
                            <th scope="col" class="identity-col-unit">Institute</th>
                            <th scope="col" class="identity-col-date">Date Created</th>
                            <th scope="col" class="identity-col-status">Status</th>
                            <th scope="col" class="identity-col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $managedUser)
                            @php
                                $initials = Str::of($managedUser->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
                                $roleTone = match ($managedUser->role) {
                                    \App\Enums\UserRole::Reviewer => 'purple',
                                    \App\Enums\UserRole::Adviser => 'blue',
                                    default => 'green',
                                };
                                $roleLabel = $managedUser->role === \App\Enums\UserRole::Adviser && $managedUser->reviewer_enabled
                                    ? 'Adviser / Reviewer'
                                    : $managedUser->displayRoleLabel();
                                if ($managedUser->reviewer_enabled) {
                                    $roleTone = 'purple';
                                }
                            @endphp
                            <tr>
                                @if ($isResLead)<td class="identity-checkbox-cell"><input type="checkbox" name="user_ids[]" value="{{ $managedUser->id }}" aria-label="Select {{ $managedUser->name }}" data-select-user></td>@endif
                                <td class="identity-col-name">
                                    <span class="identity-table-person">
                                        <span class="identity-mini-avatar" aria-hidden="true">{{ $initials }}</span>
                                        <strong class="identity-table-truncate" data-table-tooltip="{{ $managedUser->name }}">{{ $managedUser->name }}</strong>
                                    </span>
                                </td>
                                <td class="identity-col-identifier"><span class="identity-table-truncate" data-table-tooltip="{{ $managedUser->institutional_identifier }}">{{ $managedUser->institutional_identifier }}</span></td>
                                <td class="identity-col-email"><a class="identity-table-truncate" data-table-tooltip="{{ $managedUser->email }}" href="mailto:{{ $managedUser->email }}">{{ $managedUser->email }}</a></td>
                                <td class="identity-col-role"><x-dashboard.status-badge class="identity-role-badge" :label="$roleLabel" :tone="$roleTone" /></td>
                                <td class="identity-col-unit">
                                    <span class="identity-table-unit">
                                        <strong class="identity-table-truncate" data-table-tooltip="{{ $managedUser->institution ?: 'Not provided' }}">{{ $managedUser->institution ?: 'Not provided' }}</strong>
                                    </span>
                                </td>
                                <td class="identity-col-date"><time datetime="{{ $managedUser->created_at?->toDateString() }}">{{ $managedUser->created_at?->format('M d, Y') }}</time></td>
                                <td class="identity-col-status"><x-dashboard.status-badge class="identity-status-badge" :label="Str::headline($managedUser->account_status)" :tone="$managedUser->account_status === 'active' ? 'green' : ($managedUser->account_status === 'pending_setup' ? 'orange' : 'neutral')" dot /></td>
                                <td class="identity-col-action"><a class="identity-view-link" href="{{ route($routeBase.'.show', $managedUser) }}">View</a></td>
                            </tr>
                        @empty
                            <tr class="identity-empty-row">
                                <td colspan="{{ $isResLead ? 9 : 8 }}">
                                    <div class="identity-empty-state">
                                        <span><x-dashboard.icon name="users" size="48" /></span>
                                        <strong>No users found</strong>
                                        <p>No accounts match the current filters.</p>
                                        <a href="{{ route($routeBase.'.index') }}">Clear filters</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination stays inside the same bounded result panel as the scrollable table. --}}
            <x-dashboard.pagination :paginator="$users" label="User result pages" />
        </section>
        @if ($isResLead)
            </form>
        @endif
    </div>
@endsection
