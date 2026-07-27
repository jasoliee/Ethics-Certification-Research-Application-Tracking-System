@props(['label', 'count', 'icon', 'tone' => 'green', 'href'])

{{-- The shared card keeps the icon left of a right-side count-and-label group for every role. --}}
<a class="dashboard-summary-card tone-{{ $tone }}" href="{{ $href }}" aria-label="{{ $label }}: {{ $count }}">
    {{-- The icon remains the first DOM child and the vertically centered left-side visual anchor. --}}
    <span class="dashboard-summary-icon"><x-dashboard.icon :name="$icon" size="31" /></span>
    {{-- The right-side copy group centers the database count directly above its status label. --}}
    <span class="dashboard-summary-copy">
        <strong>{{ $count }}</strong>
        <span>{{ $label }}</span>
    </span>
</a>
