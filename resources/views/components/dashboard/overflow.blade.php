@props(['label', 'wide' => false])

{{-- The shared focusable region contains wide structured content without widening the complete page. --}}
<div
    {{ $attributes->class(['dashboard-overflow-region', 'is-wide' => $wide]) }}
    role="region"
    aria-label="{{ $label }}"
    tabindex="0"
>
    {{ $slot }}
</div>
