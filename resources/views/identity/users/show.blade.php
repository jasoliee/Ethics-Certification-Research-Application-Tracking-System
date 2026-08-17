@extends('layouts.dashboard')

@section('content')
    @php
        $initials = Str::of($managedUser->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
        $statusIsActive = $managedUser->account_status === 'active';
        $statusIsPending = $managedUser->account_status === 'pending_setup';
        $canActivate = $managedUser->password_setup_completed_at !== null;
    @endphp
    <div class="dashboard-page identity-management-page">
        @if ($wasCreated)
            {{-- Success details expose the generated username once without ever displaying the password. --}}
            <section class="identity-success-panel" role="status">
                <span class="identity-success-icon"><x-dashboard.icon name="check" size="42" /></span>
                <div>
                    <h1>Account Created Successfully</h1>
                    <p>The account is pending password setup. No password was created or disclosed by the account creator.</p>
                </div>
                <dl>
                    <div><dt>Full Name</dt><dd>{{ $managedUser->name }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $managedUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Email Address</dt><dd>{{ $managedUser->email }}</dd></div>
                    <div><dt>{{ $managedUser->institutionalIdentifierLabel() }}</dt><dd>{{ $managedUser->institutional_identifier }}</dd></div>
                    <div><dt>Username</dt><dd><strong>{{ $managedUser->username }}</strong></dd></div>
                    <div><dt>Setup Email</dt><dd>{{ Str::headline($setupDeliveryStatus) }}</dd></div>
                    <div><dt>Date Created</dt><dd>{{ $managedUser->created_at?->format('F j, Y') }}</dd></div>
                </dl>
                <div class="identity-success-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.create') }}">Create Another</a>
                    <a class="identity-button identity-button-primary" href="{{ route($routeBase.'.index') }}">Done</a>
                </div>
            </section>
        @else
        {{-- Profile summary restores the approved left identity, centered count, and right navigation hierarchy. --}}
        <section class="identity-profile-hero">
            {{-- Identity details stay grouped on the left instead of stretching across the complete header. --}}
            <div class="identity-profile-person">
                <span class="identity-profile-avatar" aria-hidden="true">{{ $initials }}</span>
                <div>
                    <h1>{{ $managedUser->name }}</h1>
                    <div class="identity-profile-basics">
                        <x-dashboard.status-badge :label="$managedUser->displayRoleLabel()" tone="green" />
                        <span>
                            <x-dashboard.icon name="mail" size="18" />
                            <span class="identity-profile-basic-value identity-table-truncate" data-table-tooltip="{{ $managedUser->email }}">{{ $managedUser->email }}</span>
                        </span>
                        <span>
                            <x-dashboard.icon name="id-card" size="18" />
                            <span class="identity-profile-basic-value identity-table-truncate" data-table-tooltip="{{ $managedUser->institutionalIdentifierLabel() }}: {{ $managedUser->institutional_identifier }}">{{ $managedUser->institutionalIdentifierLabel() }}: {{ $managedUser->institutional_identifier }}</span>
                        </span>
                    </div>
                </div>
            </div>

            {{-- Workflow metrics remain centered and continue using the controller-provided application totals. --}}
            <div class="identity-profile-metrics" aria-label="Account application totals">
                @foreach ($metrics as $metric)
                    <div><span><x-dashboard.icon :name="$metric['icon']" size="22" /></span><strong>{{ $metric['value'] }}</strong><small>{{ $metric['label'] }}</small></div>
                @endforeach
            </div>

            {{-- Back navigation occupies the right section and stacks beneath the summary on narrow screens. --}}
            <div class="identity-profile-actions">
                <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}">
                    <x-dashboard.icon name="arrow-left" size="18" />
                    <span>Back to User Management</span>
                </a>
            </div>
        </section>

        {{-- Detailed profile and security panels preserve the approved account-management information hierarchy. --}}
        <div class="identity-profile-grid">
            {{-- Profile information displays editable identity fields without exposing authentication secrets. --}}
            <section class="identity-detail-panel">
                <div class="identity-panel-heading">
                    <h2>Profile Information</h2>
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.edit', $managedUser) }}"><x-dashboard.icon name="edit" size="18" /><span>Edit Details</span></a>
                </div>
                <dl class="identity-detail-list">
                    <div><dt>First Name</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->first_name }}">{{ $managedUser->first_name }}</dd></div>
                    <div><dt>Middle Name</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->middle_name ?: 'Not provided' }}">{{ $managedUser->middle_name ?: 'Not provided' }}</dd></div>
                    <div><dt>Last Name</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->last_name }}">{{ $managedUser->last_name }}</dd></div>
                    <div><dt>Suffix</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->suffix ?: 'Not provided' }}">{{ $managedUser->suffix ?: 'Not provided' }}</dd></div>
                    <div><dt>Role</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->displayRoleLabel() }}">{{ $managedUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Email Address</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->email }}">{{ $managedUser->email }}</dd></div>
                    <div><dt>{{ $managedUser->institutionalIdentifierLabel() }}</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->institutional_identifier }}">{{ $managedUser->institutional_identifier }}</dd></div>
                    <div><dt>Phone Number</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->phone_number ?: 'Not provided' }}">{{ $managedUser->phone_number ?: 'Not provided' }}</dd></div>
                    <div><dt>Institution / Affiliation</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->institution ?: 'Not provided' }}">{{ $managedUser->institution ?: 'Not provided' }}</dd></div>
                    <div><dt>Department / Unit</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->department ?: 'Not provided' }}">{{ $managedUser->department ?: 'Not provided' }}</dd></div>
                    @if ($managedUser->role === \App\Enums\UserRole::Applicant)
                        <div><dt>Program</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->program ?: 'Not provided' }}">{{ $managedUser->program ?: 'Not provided' }}</dd></div>
                        @if ($managedUser->applicant_type === \App\Enums\ApplicantType::Student)<div><dt>Year Level</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->year_level }}">{{ $managedUser->year_level }}</dd></div>@endif
                    @endif
                    <div><dt>Position / Designation</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->position_title ?: 'Not provided' }}">{{ $managedUser->position_title ?: 'Not provided' }}</dd></div>
                    @if ($reviewerProfile)
                        <div><dt>Reviewer Access</dt><dd><x-dashboard.status-badge :label="$reviewerProfile['enabled'] ? 'Shown' : 'Hidden'" :tone="$reviewerProfile['enabled'] ? 'green' : 'neutral'" /></dd></div>
                        <div><dt>Reviewer Classifications</dt><dd class="identity-table-truncate" data-table-tooltip="{{ implode(', ', $reviewerProfile['classifications']) ?: 'Not configured' }}">{{ implode(', ', $reviewerProfile['classifications']) ?: 'Not configured' }}</dd></div>
                        <div><dt>Reviewer Capacity</dt><dd>{{ $reviewerProfile['capacity'] > 0 ? $reviewerProfile['capacity'] : 'Not configured' }}</dd></div>
                        <div><dt>Active Review Load</dt><dd>{{ $reviewerProfile['active_load'] }}{{ $reviewerProfile['capacity'] > 0 ? ' / '.$reviewerProfile['capacity'] : '' }}</dd></div>
                        <div><dt>Available Review Capacity</dt><dd>{{ $reviewerProfile['available_capacity'] }}</dd></div>
                        <div><dt>Assignment Eligibility</dt><dd><x-dashboard.status-badge :label="$reviewerProfile['eligibility_label']" :tone="$reviewerProfile['eligible'] ? 'green' : 'orange'" /></dd></div>
                    @endif
                    <div><dt>Date Created</dt><dd class="identity-table-truncate" data-table-tooltip="{{ $managedUser->created_at?->format('F j, Y') }}">{{ $managedUser->created_at?->format('F j, Y') }}</dd></div>
                </dl>
            </section>

            {{-- Security actions retain authorization, CSRF protection, and safe setup-delivery status. --}}
            <section class="identity-detail-panel identity-security-panel">
                <div class="identity-panel-heading"><h2>Account Security</h2></div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="user" size="22" /></span>
                    <div><strong>Username</strong><small>{{ $managedUser->username }}</small></div>
                </div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="lock" size="22" /></span>
                    <div><strong>Password</strong><small>{{ $managedUser->password_setup_completed_at ? 'Last updated '.$managedUser->password_changed_at?->format('M d, Y') : 'Waiting for the user to complete secure setup' }}</small></div>
                    @if ($canResetPassword)
                        {{-- The shared green-outline action submits only to the authorized, throttled reset route. --}}
                        <form method="POST" action="{{ route($routeBase.'.password-reset', $managedUser) }}">
                            @csrf
                            <button class="identity-button identity-button-secondary" type="submit">
                                <x-dashboard.icon name="mail" size="17" />
                                <span>{{ $managedUser->password_setup_completed_at ? 'Send Reset Link' : 'Resend Setup Link' }}</span>
                            </button>
                        </form>
                    @endif
                </div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="user-check" size="22" /></span>
                    <div><strong>Account Status</strong><small>{{ $statusIsActive ? 'This account can sign in.' : ($statusIsPending ? 'Sign-in unlocks after password setup.' : 'Sign-in is currently disabled.') }}</small></div>
                    <x-dashboard.status-badge :label="Str::headline($managedUser->account_status)" :tone="$statusIsActive ? 'green' : ($statusIsPending ? 'orange' : 'neutral')" dot />
                </div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="mail" size="22" /></span>
                    <div><strong>Setup Email</strong><small>{{ $managedUser->setup_email_sent_at?->format('M d, Y g:i A') ?? 'No successful delivery recorded' }}</small></div>
                    <x-dashboard.status-badge :label="Str::headline($managedUser->setup_email_status)" :tone="$managedUser->setup_email_status === 'sent' ? 'green' : ($managedUser->setup_email_status === 'failed' ? 'red' : 'neutral')" />
                </div>

                @if ($canChangeStatus || $canDelete)
                    <div class="identity-account-lifecycle-actions">
                        @if ($canChangeStatus)
                            @php
                                $nextStatus = $statusIsActive || $statusIsPending ? 'inactive' : 'active';
                            @endphp
                            @if ($nextStatus === 'inactive' || $canActivate)
                            <form class="identity-status-form" method="POST" action="{{ route($routeBase.'.status', $managedUser) }}" data-confirm-status="{{ $nextStatus === 'inactive' ? 'Deactivate this account and prevent future sign-ins?' : 'Reactivate this account and allow sign-in?' }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="account_status" value="{{ $nextStatus }}">
                                <button class="identity-button {{ $nextStatus === 'inactive' ? 'identity-button-danger' : 'identity-button-reactivate' }}" type="submit">
                                    {{ $nextStatus === 'inactive' ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                            @endif
                        @endif
                        @if ($canDelete)
                            <form method="POST" action="{{ route('res.users.destroy', $managedUser) }}" data-confirm-account-archive>
                                @csrf
                                @method('DELETE')
                                <button class="identity-button identity-button-danger" type="submit">Delete Account</button>
                            </form>
                        @endif
                    </div>
                @endif
            </section>
        </div>
        @endif
    </div>
@endsection
