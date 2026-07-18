@props(['href'])

<a {{ $attributes->class('dashboard-action-link') }} href="{{ $href }}">{{ $slot }}</a>
