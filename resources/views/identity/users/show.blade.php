@extends('layouts.dashboard')

@section('content')
    @php
        $initials = Str::of($managedUser->name)->explode(' ')->filter()->take(2)->map(fn ($part) => Str::upper(Str::substr($part, 0, 1)))->implode('');
        $statusIsActive = $managedUser->account_status === 'active';
    @endphp
    <div class="dashboard-page identity-management-page">
        @if ($wasCreated)
            {{-- Success details expose the generated username once without ever displaying the password. --}}
            <section class="identity-success-panel" role="status">
                <span class="identity-success-icon"><x-dashboard.icon name="check" size="42" /></span>
                <div>
                    <h1>Account Created Successfully</h1>
                    <p>The {{ Str::lower($managedUser->displayRoleLabel()) }} account is active and ready to use.</p>
                </div>
                <dl>
                    <div><dt>Full Name</dt><dd>{{ $managedUser->name }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $managedUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Email Address</dt><dd>{{ $managedUser->email }}</dd></div>
                    <div><dt>{{ $managedUser->institutionalIdentifierLabel() }}</dt><dd>{{ $managedUser->institutional_identifier }}</dd></div>
                    <div><dt>Username</dt><dd><strong>{{ $managedUser->username }}</strong></dd></div>
                    <div><dt>Date Created</dt><dd>{{ $managedUser->created_at?->format('F j, Y') }}</dd></div>
                </dl>
                <div class="identity-success-actions">
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.create') }}">Create Another</a>
                    <a class="identity-button identity-button-primary" href="{{ route($routeBase.'.index') }}">Done</a>
                </div>
            </section>
        @else
        {{-- Profile summary combines identity, role, status, and workflow counts without exposing confidential data. --}}
        <section class="identity-profile-hero">
            <div class="identity-profile-person">
                <span class="identity-profile-avatar" aria-hidden="true">{{ $initials }}</span>
                <div>
                    <h1>{{ $managedUser->name }}</h1>
                    <x-dashboard.status-badge :label="$managedUser->displayRoleLabel()" tone="green" />
                    <span><x-dashboard.icon name="mail" size="18" />{{ $managedUser->email }}</span>
                    <span><x-dashboard.icon name="id-card" size="18" />{{ $managedUser->institutionalIdentifierLabel() }}: {{ $managedUser->institutional_identifier }}</span>
                </div>
            </div>

            <div class="identity-profile-metrics">
                @foreach ($metrics as $metric)
                    <div><span><x-dashboard.icon :name="$metric['icon']" size="22" /></span><strong>{{ $metric['value'] }}</strong><small>{{ $metric['label'] }}</small></div>
                @endforeach
            </div>

            <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.index') }}">
                <x-dashboard.icon name="arrow-left" size="18" />
                <span>Back to User Management</span>
            </a>
        </section>

        <div class="identity-profile-grid">
            <section class="identity-detail-panel">
                <div class="identity-panel-heading">
                    <h2>Profile Information</h2>
                    <a class="identity-button identity-button-secondary" href="{{ route($routeBase.'.edit', $managedUser) }}"><x-dashboard.icon name="edit" size="18" /><span>Edit Details</span></a>
                </div>
                <dl class="identity-detail-list">
                    <div><dt>First Name</dt><dd>{{ $managedUser->first_name }}</dd></div>
                    <div><dt>Middle Name</dt><dd>{{ $managedUser->middle_name ?: 'Not provided' }}</dd></div>
                    <div><dt>Last Name</dt><dd>{{ $managedUser->last_name }}</dd></div>
                    <div><dt>Suffix</dt><dd>{{ $managedUser->suffix ?: 'Not provided' }}</dd></div>
                    <div><dt>Role</dt><dd>{{ $managedUser->displayRoleLabel() }}</dd></div>
                    <div><dt>Email Address</dt><dd>{{ $managedUser->email }}</dd></div>
                    <div><dt>{{ $managedUser->institutionalIdentifierLabel() }}</dt><dd>{{ $managedUser->institutional_identifier }}</dd></div>
                    <div><dt>Phone Number</dt><dd>{{ $managedUser->phone_number ?: 'Not provided' }}</dd></div>
                    <div><dt>Institution / Affiliation</dt><dd>{{ $managedUser->institution ?: 'Not provided' }}</dd></div>
                    <div><dt>Department / Unit</dt><dd>{{ $managedUser->department ?: 'Not provided' }}</dd></div>
                    <div><dt>Position / Designation</dt><dd>{{ $managedUser->position_title ?: 'Not provided' }}</dd></div>
                    <div><dt>Date Created</dt><dd>{{ $managedUser->created_at?->format('F j, Y') }}</dd></div>
                </dl>
            </section>

            <section class="identity-detail-panel identity-security-panel">
                <div class="identity-panel-heading"><h2>Account Security</h2></div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="user" size="22" /></span>
                    <div><strong>Username</strong><small>{{ $managedUser->username }}</small></div>
                </div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="lock" size="22" /></span>
                    <div><strong>Password</strong><small>Last updated {{ $managedUser->password_changed_at?->format('M d, Y') ?? 'date unavailable' }}</small></div>
                    @if ($canResetPassword)
                        <form method="POST" action="{{ route($routeBase.'.password-reset', $managedUser) }}">
                            @csrf
                            <button class="identity-button identity-button-secondary" type="submit">Send Reset Link</button>
                        </form>
                    @endif
                </div>
                <div class="identity-security-item">
                    <span><x-dashboard.icon name="user-check" size="22" /></span>
                    <div><strong>Account Status</strong><small>{{ $statusIsActive ? 'This account can sign in.' : 'Sign-in is currently disabled.' }}</small></div>
                    <x-dashboard.status-badge :label="$statusIsActive ? 'Active' : 'Inactive'" :tone="$statusIsActive ? 'green' : 'neutral'" dot />
                </div>

                @if ($canChangeStatus)
                    <form class="identity-status-form" method="POST" action="{{ route($routeBase.'.status', $managedUser) }}" data-confirm-status="{{ $statusIsActive ? 'Deactivate this account and prevent future sign-ins?' : 'Activate this account and allow sign-in?' }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="account_status" value="{{ $statusIsActive ? 'inactive' : 'active' }}">
                        <button class="identity-button {{ $statusIsActive ? 'identity-button-danger' : 'identity-button-primary' }}" type="submit">
                            {{ $statusIsActive ? 'Deactivate Account' : 'Activate Account' }}
                        </button>
                    </form>
                @endif
            </section>
        </div>
        @endif
    </div>
@endsection
