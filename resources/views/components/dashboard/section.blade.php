@props([
    'title',
    'viewAllRoute' => null,
    'viewAllParameters' => [],
    'headerMeta' => null,
    'headerMetaIcon' => null,
])

<section {{ $attributes->class('dashboard-panel') }}>
    <header class="dashboard-panel-header">
        <h2>{{ $title }}</h2>
        @if ($viewAllRoute)
            <a class="dashboard-view-all" href="{{ route($viewAllRoute, $viewAllParameters) }}">View All</a>
        @elseif ($headerMeta)
            <span class="dashboard-panel-header-meta">
                @if ($headerMetaIcon)<x-dashboard.icon :name="$headerMetaIcon" size="18" />@endif
                <span>{{ $headerMeta }}</span>
            </span>
        @endif
    </header>
    <div class="dashboard-panel-body">
        {{ $slot }}
    </div>
</section>
