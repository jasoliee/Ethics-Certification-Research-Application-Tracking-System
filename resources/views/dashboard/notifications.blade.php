@extends('layouts.dashboard')

@section('content')
    <div class="dashboard-page dashboard-notifications-page">
        <header class="dashboard-page-heading dashboard-page-heading-row">
            <div>
                <h1>Notifications</h1>
                <p>Application, review, deadline, and account updates.</p>
            </div>
            @if ($dashboardUnreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <button class="dashboard-outline-action" type="submit">Mark all as read</button>
                </form>
            @endif
        </header>

        <section class="dashboard-notification-list">
            @forelse ($notifications as $notification)
                <article class="dashboard-notification-row {{ $notification->read_at === null ? 'is-unread' : '' }}">
                    <span class="dashboard-notification-icon tone-{{ $notification->data['tone'] ?? 'green' }}">
                        <x-dashboard.icon :name="$notification->data['icon'] ?? 'bell'" size="21" />
                    </span>
                    <div>
                        <h2>{{ $notification->data['title'] ?? 'ECRATS update' }}</h2>
                        <p>{{ $notification->data['message'] ?? 'There is a new update on your account.' }}</p>
                        <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->diffForHumans() }}</time>
                    </div>
                    @if ($notification->read_at === null)<span class="dashboard-unread-dot" aria-label="Unread"></span>@endif
                </article>
            @empty
                <x-dashboard.empty-state
                    image="no-notifications"
                    alt="Empty notification bell"
                    title="No notifications yet"
                    message="You will see application and review updates here."
                />
            @endforelse
        </section>

        <x-dashboard.pagination :paginator="$notifications" label="Notification pages" />
    </div>
@endsection
