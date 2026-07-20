@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page identity-management-page">
        {{-- Page actions remain compact so the data table stays the primary work surface. --}}
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading">
                <h1>{{ $isResLead ? 'User Management' : 'Applicant Accounts' }}</h1>
                <p>{{ $isResLead ? 'Manage researcher, adviser, and reviewer accounts.' : 'Manage the student and faculty researcher accounts assigned to you.' }}</p>
            </div>

            <div class="identity-heading-actions">
                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.import.form') }}">
                    <x-dashboard.icon name="upload" size="19" />
                    <span>Bulk Import</span>
                </a>
                <a class="identity-button identity-button-primary" href="{{ route($routeBase.'.create') }}">
                    <x-dashboard.icon name="plus" size="19" />
                    <span>Add New User</span>
                </a>
            </div>
        </header>

        {{-- Role tabs provide one-click filtering while preserving a clear all-records view. --}}
        <nav class="identity-role-tabs {{ $isResLead ? 'is-four' : 'is-two' }}" aria-label="Account category filters">
            <a class="{{ empty($filters['role']) ? 'is-active' : '' }}" href="{{ route($routeBase.'.index') }}">
                <x-dashboard.icon name="users" size="20" />
                <span>All Users</span>
                <small>{{ $counts['all'] }}</small>
            </a>
            @if ($isResLead)
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
            <a class="{{ ($filters['role'] ?? null) === \App\Enums\UserRole::Applicant->value ? 'is-active' : '' }}" href="{{ route($routeBase.'.index', ['role' => \App\Enums\UserRole::Applicant->value]) }}">
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
                        <option value="{{ \App\Enums\UserRole::Reviewer->value }}" @selected(($filters['role'] ?? null) === \App\Enums\UserRole::Reviewer->value)>Reviewers</option>
                    </select>
                </div>
            @endif

            <div class="identity-filter">
                <label for="institution-filter">Institution</label>
                <select id="institution-filter" name="institution">
                    <option value="">All institutions</option>
                    @foreach ($institutions as $institution)
                        <option value="{{ $institution }}" @selected(($filters['institution'] ?? null) === $institution)>{{ $institution }}</option>
                    @endforeach
                </select>
            </div>

            <div class="identity-filter">
                <label for="status-filter">Status</label>
                <select id="status-filter" name="account_status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['account_status'] ?? null) === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['account_status'] ?? null) === 'inactive')>Inactive</option>
                </select>
            </div>

            <div class="identity-filter-actions">
                <button class="identity-button identity-button-primary" type="submit">Apply</button>
                <a href="{{ route($routeBase.'.index') }}">Reset</a>
            </div>
        </form>

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

            <div class="identity-table-scroll">
                <table class="identity-user-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Institutional ID</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Institution / Unit</th>
                            <th scope="col">Date Created</th>
                            <th scope="col">Status</th>
                            <th scope="col">Action</th>
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
                            @endphp
                            <tr>
                                <td>
                                    <span class="identity-table-person">
                                        <span class="identity-mini-avatar" aria-hidden="true">{{ $initials }}</span>
                                        <strong>{{ $managedUser->name }}</strong>
                                    </span>
                                </td>
                                <td>{{ $managedUser->institutional_identifier }}</td>
                                <td><a href="mailto:{{ $managedUser->email }}">{{ $managedUser->email }}</a></td>
                                <td><x-dashboard.status-badge :label="$managedUser->displayRoleLabel()" :tone="$roleTone" /></td>
                                <td>
                                    <span class="identity-table-unit">
                                        <strong>{{ $managedUser->institution ?: 'Not provided' }}</strong>
                                        @if ($managedUser->department)<small>{{ $managedUser->department }}</small>@endif
                                    </span>
                                </td>
                                <td><time datetime="{{ $managedUser->created_at?->toDateString() }}">{{ $managedUser->created_at?->format('M d, Y') }}</time></td>
                                <td><x-dashboard.status-badge :label="Str::headline($managedUser->account_status)" :tone="$managedUser->account_status === 'active' ? 'green' : 'neutral'" dot /></td>
                                <td><a class="identity-view-link" href="{{ route($routeBase.'.show', $managedUser) }}">View</a></td>
                            </tr>
                        @empty
                            <tr class="identity-empty-row">
                                <td colspan="8">
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

            @if ($users->hasPages())
                <nav class="identity-pagination" aria-label="User result pages">
                    @if ($users->onFirstPage())
                        <span aria-disabled="true">Previous</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif
                    <strong>Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</strong>
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span aria-disabled="true">Next</span>
                    @endif
                </nav>
            @endif
        </section>
    </div>
@endsection
