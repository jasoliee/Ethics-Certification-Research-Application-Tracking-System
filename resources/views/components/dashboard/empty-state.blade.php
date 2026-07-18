@props([
    'image',
    'alt',
    'title',
    'message',
    'actionLabel' => null,
    'actionHref' => null,
    'actionIcon' => null,
    'compact' => false,
])

<div {{ $attributes->class(['dashboard-empty-state', 'is-compact' => $compact]) }}>
    <img class="dashboard-empty-asset" src="{{ asset('assets/empty-states/'.$image.'.png') }}" alt="{{ $alt }}">
    <strong>{{ $title }}</strong>
    <p>{{ $message }}</p>
    @if ($actionLabel && $actionHref)
        <a class="dashboard-primary-action" href="{{ $actionHref }}">
            @if ($actionIcon)<x-dashboard.icon :name="$actionIcon" size="18" />@endif
            <span>{{ $actionLabel }}</span>
        </a>
    @endif
</div>
