@props(['label', 'count', 'icon', 'tone' => 'green', 'href'])

{{-- The shared card keeps every role's count and label paired in one accessible link. --}}
<a class="dashboard-summary-card tone-{{ $tone }}" href="{{ $href }}" aria-label="{{ $label }}: {{ $count }}">
    {{-- The icon remains a separate first row so the visual order is icon, count, then label. --}}
    <span class="dashboard-summary-icon"><x-dashboard.icon :name="$icon" size="31" /></span>
    {{-- The copy group centers the prominent database count directly above its status label. --}}
    <span class="dashboard-summary-copy">
        <strong>{{ $count }}</strong>
        <span>{{ $label }}</span>
    </span>
</a>
