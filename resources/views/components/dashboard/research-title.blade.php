@props(['title', 'href' => null])

@if ($href)
    <a
        href="{{ $href }}"
        {{ $attributes->class('dashboard-research-title') }}
        data-research-title-tooltip
        data-full-title="{{ $title }}"
    >{{ $title }}</a>
@else
    <span
        {{ $attributes->class('dashboard-research-title') }}
        data-research-title-tooltip
        data-full-title="{{ $title }}"
        tabindex="0"
    >{{ $title }}</span>
@endif
