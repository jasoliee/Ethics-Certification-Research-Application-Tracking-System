@props([
    'description' => 'Refine the displayed results.',
    'resetHref',
    'applyLabel' => 'Apply Filters',
    'resetLabel' => 'Reset Filters',
    'actionsClass' => '',
    'primaryClass' => '',
])

<header class="unified-filter-heading">
    <div class="unified-filter-title">
        <span class="unified-filter-icon"><x-dashboard.icon name="filter" size="22" /></span>
        <span>
            <strong>Filters</strong>
        </span>
    </div>
    <div class="unified-filter-actions {{ $actionsClass }}">
        <button class="dashboard-primary-action {{ $primaryClass }}" type="submit">
            <x-dashboard.icon name="filter" size="17" />
            <span>{{ $applyLabel }}</span>
        </button>
        <a class="dashboard-outline-action" href="{{ $resetHref }}">
            <x-dashboard.icon name="refresh" size="17" />
            <span>{{ $resetLabel }}</span>
        </a>
    </div>
</header>
