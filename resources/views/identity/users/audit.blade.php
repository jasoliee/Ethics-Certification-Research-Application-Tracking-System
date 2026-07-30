@extends('layouts.dashboard')

@section('content')
    @php
        $semesterOptions = $termOptions->pluck('semester')->unique()->values();
        $academicYearOptions = $termOptions->pluck('academic_year')->unique()->values();
    @endphp
    <div class="dashboard-page identity-management-page">
        <header class="dashboard-page-heading-row identity-page-heading">
            <div class="dashboard-page-heading"><h1>Account Audit Log</h1><p>Review security-relevant account and access events without exposing credentials or tokens.</p></div>
            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}"><x-dashboard.icon name="arrow-left" size="18" /><span>Back</span></a>
        </header>

        <form class="identity-filter-bar identity-audit-filters" method="GET" action="{{ route($routeBase.'.audit.index') }}">
            <div class="identity-filter identity-filter-search">
                <label for="audit-search">Search</label>
                <div class="identity-input-icon">
                    <x-dashboard.icon name="search" size="19" />
                    <input id="audit-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Actor or action" maxlength="100">
                </div>
            </div>
            <div class="identity-filter">
                <label for="audit-role">Actor Role</label>
                <select id="audit-role" name="role">
                    <option value="">All roles</option>
                    @foreach (\App\Enums\UserRole::cases() as $role)
                        <option value="{{ $role->value }}" @selected(($filters['role'] ?? null) === $role->value)>{{ $role->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter">
                <label for="audit-action">Action</label>
                <select id="audit-action" name="action">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ Str::headline(str_replace('.', ' ', $action)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter">
                <label for="audit-result">Result</label>
                <input id="audit-result" name="result" type="search" value="{{ $filters['result'] ?? '' }}" placeholder="e.g., completed" maxlength="100">
            </div>
            <div class="identity-filter">
                <label for="audit-target">Target Type</label>
                <select id="audit-target" name="target_type">
                    <option value="">All targets</option>
                    @foreach ($targetTypes as $targetType)
                        <option value="{{ $targetType }}" @selected(($filters['target_type'] ?? null) === $targetType)>{{ class_basename($targetType) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter">
                <label for="audit-semester">Semester</label>
                <select id="audit-semester" name="semester">
                    <option value="">All semesters</option>
                    @foreach ($semesterOptions as $semester)
                        <option value="{{ $semester }}" @selected(($filters['semester'] ?? null) === $semester)>{{ $semester }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter">
                <label for="audit-academic-year">Academic Year</label>
                <select id="audit-academic-year" name="academic_year">
                    <option value="">All academic years</option>
                    @foreach ($academicYearOptions as $academicYear)
                        <option value="{{ $academicYear }}" @selected(($filters['academic_year'] ?? null) === $academicYear)>{{ $academicYear }}</option>
                    @endforeach
                </select>
            </div>
            <div class="identity-filter"><label for="audit-date-from">From</label><input id="audit-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="identity-filter"><label for="audit-date-to">To</label><input id="audit-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="identity-filter-actions">
                <button class="identity-button identity-button-primary" type="submit">Apply</button>
                <a class="identity-button identity-button-warning" href="{{ route($routeBase.'.audit.index') }}" aria-label="Reset audit-log filters">Reset</a>
            </div>
        </form>

        <section class="identity-table-panel">
            {{-- Audit columns scroll within this focusable region instead of widening the complete dashboard page. --}}
            <div class="identity-table-scroll dashboard-overflow-region" role="region" aria-label="Account audit results" tabindex="0">
                <table class="identity-user-table identity-audit-table">
                    <thead><tr><th class="identity-col-date-time">Date and Time</th><th class="identity-col-actor">Actor</th><th class="identity-col-role">Role</th><th class="identity-col-audit-action">Action</th><th class="identity-col-status">Result</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $actorRole = $log->actor?->role;
                                $roleTone = match ($actorRole) {
                                    \App\Enums\UserRole::Reviewer => 'purple',
                                    \App\Enums\UserRole::Adviser => 'blue',
                                    default => 'green',
                                };
                                $actionLabel = Str::headline(str_replace('.', ' ', $log->action));
                                $resultLabel = Str::headline($log->metadata['result'] ?? 'recorded');
                            @endphp
                            <tr>
                                <td class="identity-col-date-time">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                                <td class="identity-col-actor"><span class="identity-table-truncate" data-table-tooltip="{{ $log->actor?->name ?? 'System / unauthenticated' }}">{{ $log->actor?->name ?? 'System / unauthenticated' }}</span></td>
                                <td class="identity-col-role">
                                    @if ($actorRole)
                                        <x-dashboard.status-badge class="identity-role-badge" :label="$actorRole->label()" :tone="$roleTone" />
                                    @else
                                        <x-dashboard.status-badge class="identity-role-badge" label="System" tone="neutral" />
                                    @endif
                                </td>
                                <td class="identity-col-audit-action"><span class="identity-table-truncate" data-table-tooltip="{{ $actionLabel }}">{{ $actionLabel }}</span></td>
                                <td class="identity-col-status"><x-dashboard.status-badge class="identity-result-badge" :label="$resultLabel" :tone="($log->metadata['result'] ?? null) === 'failed' ? 'red' : 'green'" /></td>
                            </tr>
                        @empty
                            <tr class="identity-empty-row"><td colspan="5"><div class="identity-empty-state"><strong>No audit events found</strong><p>No events match the current filters.</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{-- Audit pagination remains within the responsive result panel. --}}
            <x-dashboard.pagination :paginator="$logs" label="Audit log pages" />
        </section>
    </div>
@endsection
