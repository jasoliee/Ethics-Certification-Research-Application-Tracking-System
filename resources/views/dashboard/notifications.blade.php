@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-notifications-page">
        <header class="dashboard-page-heading dashboard-page-heading-row">
            <div>
                <h1>{{ $binMode ? 'Notification Bin' : 'Notifications' }}</h1>
                @if ($binMode)<p>Deleted notifications are permanently removed after seven days.</p>@endif
            </div>
            <a class="dashboard-outline-action" href="{{ $binMode ? route($dashboardNotificationsRoute) : route('notifications.bin') }}">
                <x-dashboard.icon :name="$binMode ? 'arrow-left' : 'trash'" size="17" />
                <span>{{ $binMode ? 'Back to Notifications' : 'Bin' }}</span>
            </a>
        </header>

        <form class="notification-filter-bar" method="GET">
            <div class="notification-filter-field">
                <label for="notification-date">Date</label>
                <input id="notification-date" name="date" type="date" value="{{ $filters['date'] ?? '' }}">
            </div>
            <div class="notification-filter-field">
                <label for="notification-type">Type</label>
                <select id="notification-type" name="type">
                    <option value="">All types</option>
                    @foreach ($notificationTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="notification-filter-field">
                <label for="notification-read-status">Read status</label>
                <select id="notification-read-status" name="read_status">
                    <option value="">All statuses</option>
                    <option value="unread" @selected(($filters['read_status'] ?? '') === 'unread')>Unread</option>
                    <option value="read" @selected(($filters['read_status'] ?? '') === 'read')>Read</option>
                </select>
            </div>
            <button class="dashboard-primary-action" type="submit">Apply Filters</button>
            @if (array_filter($filters))<a class="dashboard-outline-action" href="{{ $binMode ? route('notifications.bin') : route($dashboardNotificationsRoute) }}">Clear</a>@endif
        </form>

        @if ($notifications->isNotEmpty())
            <div class="notification-all-actions" aria-label="All notification actions">
                @if ($binMode)
                    <form method="POST" action="{{ route('notifications.bin.all') }}" data-notification-confirm data-confirm-title="Restore All Notifications" data-confirm-message="Restore every notification currently in the Bin?" data-confirm-action="Restore All">
                        @csrf
                        <input type="hidden" name="action" value="restore">
                        <button class="dashboard-outline-action" type="submit"><x-dashboard.icon name="refresh" size="17" />Restore All</button>
                    </form>
                    <form method="POST" action="{{ route('notifications.bin.all') }}" data-notification-confirm data-confirm-title="Permanently Delete All" data-confirm-message="Permanently delete every notification in the Bin? This cannot be undone." data-confirm-action="Delete All" data-confirm-danger>
                        @csrf
                        <input type="hidden" name="action" value="force_delete">
                        <button class="dashboard-outline-action is-danger" type="submit"><x-dashboard.icon name="trash" size="17" />Delete All</button>
                    </form>
                @else
                    @foreach ([['mark_read', 'mail-open', 'Mark Read'], ['mark_unread', 'mail', 'Mark Unread']] as [$action, $icon, $label])
                        <form method="POST" action="{{ route('notifications.all') }}">
                            @csrf
                            <input type="hidden" name="action" value="{{ $action }}">
                            <button class="dashboard-outline-action" type="submit"><x-dashboard.icon :name="$icon" size="17" />{{ $label }}</button>
                        </form>
                    @endforeach
                    <form method="POST" action="{{ route('notifications.all') }}" data-notification-confirm data-confirm-title="Move All Notifications to Bin" data-confirm-message="Move every notification to the Bin? You can restore them for seven days." data-confirm-action="Move All">
                        @csrf
                        <input type="hidden" name="action" value="delete">
                        <button class="dashboard-outline-action is-danger" type="submit"><x-dashboard.icon name="trash" size="17" />Delete All</button>
                    </form>
                @endif
            </div>

            <form id="notification-bulk-form" class="notification-bulk-bar" method="POST" action="{{ $binMode ? route('notifications.bin.bulk') : route('notifications.bulk') }}" data-notification-confirm data-notification-confirm-mode="{{ $binMode ? 'bin-selected' : 'inbox-selected' }}">
                @csrf
                <label for="notification-bulk-action">Selected</label>
                <select id="notification-bulk-action" name="action" required>
                    <option value="">Action</option>
                    @if ($binMode)
                        <option value="restore">Restore</option>
                        <option value="force_delete">Delete permanently</option>
                    @else
                        <option value="mark_read">Mark read</option>
                        <option value="mark_unread">Mark unread</option>
                        <option value="delete">Move to Bin</option>
                    @endif
                </select>
                <button class="dashboard-primary-action" type="submit">Apply</button>
                @error('notification_ids')<span class="settings-field-error">{{ $message }}</span>@enderror
            </form>
        @endif

        <section class="dashboard-notification-list">
            @forelse ($notifications as $notification)
                <article class="dashboard-notification-row {{ $notification->read_at === null ? 'is-unread' : '' }}">
                    <label class="notification-select" title="Select notification">
                        <input form="notification-bulk-form" type="checkbox" name="notification_ids[]" value="{{ $notification->id }}">
                        <span class="sr-only">Select {{ $notification->data['title'] ?? 'notification' }}</span>
                    </label>
                    <span class="dashboard-notification-icon tone-{{ $notification->data['tone'] ?? 'green' }}">
                        <x-dashboard.icon :name="$notification->data['icon'] ?? 'bell'" size="21" />
                    </span>
                    <div class="notification-row-copy">
                        <h2>{{ $notification->data['title'] ?? 'ECRATS update' }}</h2>
                        <p>{{ $notification->data['message'] ?? 'There is a new update on your account.' }}</p>
                        <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
                    </div>
                    <div class="notification-row-actions">
                        @if ($binMode)
                            <form method="POST" action="{{ route('notifications.bin.restore', $notification->id) }}" data-notification-confirm data-confirm-title="Restore Notification" data-confirm-message="Restore this notification to the inbox?" data-confirm-action="Restore">
                                @csrf
                                @method('PATCH')
                                <button class="dashboard-icon-button" type="submit" aria-label="Restore notification" title="Restore"><x-dashboard.icon name="refresh" size="18" /></button>
                            </form>
                            <form method="POST" action="{{ route('notifications.bin.destroy', $notification->id) }}" data-notification-confirm data-confirm-title="Permanently Delete Notification" data-confirm-message="Permanently delete this notification? This cannot be undone." data-confirm-action="Delete" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button class="dashboard-icon-button is-danger" type="submit" aria-label="Permanently delete notification" title="Delete permanently"><x-dashboard.icon name="trash" size="18" /></button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('notifications.read-status', $notification) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="action" value="{{ $notification->read_at === null ? 'mark_read' : 'mark_unread' }}">
                                <button class="dashboard-icon-button" type="submit" aria-label="Mark notification as {{ $notification->read_at === null ? 'read' : 'unread' }}" title="Mark {{ $notification->read_at === null ? 'read' : 'unread' }}"><x-dashboard.icon :name="$notification->read_at === null ? 'mail-open' : 'mail'" size="18" /></button>
                            </form>
                            <form method="POST" action="{{ route('notifications.destroy', $notification) }}" data-notification-confirm data-confirm-title="Move Notification to Bin" data-confirm-message="Move this notification to the Bin? You can restore it for seven days." data-confirm-action="Move to Bin">
                                @csrf
                                @method('DELETE')
                                <button class="dashboard-icon-button is-danger" type="submit" aria-label="Move notification to Bin" title="Move to Bin"><x-dashboard.icon name="trash" size="18" /></button>
                            </form>
                        @endif
                    </div>
                    @if (! $binMode && $notification->read_at === null)<span class="dashboard-unread-dot" aria-label="Unread"></span>@endif
                </article>
            @empty
                <x-dashboard.empty-state image="no-notifications" alt="Empty notification bell" :title="$binMode ? 'Bin is empty' : 'No notifications found'" :message="$binMode ? 'Deleted notifications will appear here for seven days.' : 'No notifications match the selected filters.'" />
            @endforelse
        </section>

        <x-dashboard.pagination :paginator="$notifications" label="Notification pages" />

        <section class="application-modal-backdrop" data-notification-confirm-dialog hidden>
            <div class="application-modal notification-confirmation-modal" role="dialog" aria-modal="true" aria-labelledby="notification-confirm-title" aria-describedby="notification-confirm-message" tabindex="-1">
                <button class="application-modal-close" type="button" aria-label="Cancel notification action" data-notification-confirm-close><x-dashboard.icon name="x" size="20" /></button>
                <header class="application-modal-heading">
                    <span class="application-modal-icon"><x-dashboard.icon name="alert-triangle" size="24" /></span>
                    <div>
                        <h2 id="notification-confirm-title" data-notification-confirm-title>Confirm Notification Action</h2>
                        <p id="notification-confirm-message" data-notification-confirm-message>Confirm this notification action.</p>
                    </div>
                </header>
                <div class="application-modal-actions">
                    <button class="dashboard-outline-action" type="button" data-notification-confirm-close>Cancel</button>
                    <button class="dashboard-primary-action" type="button" data-notification-confirm-submit><span>Confirm</span></button>
                </div>
            </div>
        </section>
    </div>
@endsection
