@props(['label', 'count', 'icon', 'tone' => 'green', 'href'])

<a class="dashboard-summary-card tone-{{ $tone }}" href="{{ $href }}" aria-label="{{ $label }}: {{ $count }}">
    <span class="dashboard-summary-icon"><x-dashboard.icon :name="$icon" size="31" /></span>
    <span class="dashboard-summary-copy">
        <strong>{{ $count }}</strong>
        <span>{{ $label }}</span>
    </span>
</a>
