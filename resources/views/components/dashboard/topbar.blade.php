@props(['title' => 'Dashboard', 'breadcrumbs' => []])

<header class="dashboard-topbar">
    <div class="dashboard-topbar-heading">
        <button class="dashboard-menu-button" type="button" aria-label="Open navigation" aria-controls="dashboard-sidebar" aria-expanded="false" data-sidebar-open>
            <x-dashboard.icon name="menu" />
        </button>
        @if (count($breadcrumbs) > 0)
            <x-dashboard.breadcrumbs :items="$breadcrumbs" />
        @else
            <span class="dashboard-topbar-title">{{ $title }}</span>
        @endif
    </div>

    <div class="dashboard-topbar-actions">
        @if (request()->routeIs('dashboard'))
            <button class="dashboard-guide-button" type="button" data-guide-open @if ($dashboardRequiresOnboarding) hidden @endif>
                <x-dashboard.icon name="circle-help" size="19" />
                <span>Guide</span>
            </button>
        @endif
        <div class="dashboard-menu-wrap">
            <button
                class="dashboard-icon-button"
                type="button"
                aria-label="Open notifications"
                aria-controls="dashboard-notification-menu"
                aria-expanded="false"
                data-menu-toggle="notifications"
            >
                <x-dashboard.icon name="bell" />
                @if ($dashboardUnreadCount > 0)
                    <span class="dashboard-notification-count" aria-label="{{ $dashboardUnreadCount }} unread notifications">
                        {{ $dashboardUnreadCount > 9 ? '9+' : $dashboardUnreadCount }}
                    </span>
                @endif
            </button>

            <section class="dashboard-dropdown dashboard-notification-menu" id="dashboard-notification-menu" data-menu="notifications" hidden>
                <div class="dashboard-dropdown-header">
                    <strong>Notifications</strong>
                    @if ($dashboardUnreadCount > 0)
                        <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                            @csrf
                            <button class="dashboard-text-button" type="submit">Mark all as read</button>
                        </form>
                    @endif
                </div>

                @forelse ($dashboardNotifications as $notification)
                    <a class="dashboard-notification-item {{ $notification['unread'] ? 'is-unread' : '' }}" href="{{ $notification['url'] }}">
                        <span class="dashboard-notification-icon tone-{{ $notification['tone'] }}">
                            <x-dashboard.icon :name="$notification['icon']" size="20" />
                        </span>
                        <span class="dashboard-notification-copy">
                            <strong>{{ $notification['title'] }}</strong>
                            <span>{{ $notification['message'] }}</span>
                            <small>{{ $notification['time'] }}</small>
                        </span>
                        @if ($notification['unread'])
                            <span class="dashboard-unread-dot" aria-label="Unread"></span>
                        @endif
                    </a>
                @empty
                    <div class="dashboard-dropdown-empty">
                        <img class="dashboard-empty-asset" src="{{ asset('assets/empty-states/no-notifications.png') }}" alt="Empty notification bell">
                        <strong>No notifications yet</strong>
                        <span>You will see application and review updates here.</span>
                    </div>
                @endforelse

                <a class="dashboard-dropdown-footer" href="{{ route($dashboardNotificationsRoute) }}">View all notifications</a>
            </section>
        </div>

        <div class="dashboard-menu-wrap">
            <button
                class="dashboard-profile-button"
                type="button"
                aria-label="Open profile menu"
                aria-controls="dashboard-profile-menu"
                aria-expanded="false"
                data-menu-toggle="profile"
            >
                <span class="dashboard-avatar" aria-hidden="true">{{ $dashboardUserInitials }}</span>
                <span class="dashboard-profile-name">{{ auth()->user()->name }}</span>
                <x-dashboard.icon name="chevron-down" size="18" />
            </button>

            <div class="dashboard-dropdown dashboard-profile-menu" id="dashboard-profile-menu" data-menu="profile" hidden>
                <a href="{{ route($dashboardProfileRoute) }}">
                    <x-dashboard.icon name="user" size="19" />
                    <span>Profile</span>
                </a>
                <a href="{{ route($dashboardSettingsRoute) }}">
                    <x-dashboard.icon name="settings" size="19" />
                    <span>Settings</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">
                        <x-dashboard.icon name="logout" size="19" />
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
