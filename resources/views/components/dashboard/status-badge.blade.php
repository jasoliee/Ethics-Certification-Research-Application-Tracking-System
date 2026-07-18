@props(['label', 'tone' => 'neutral', 'dot' => false])

<span {{ $attributes->class(['dashboard-status-badge', 'tone-'.$tone]) }}>
    @if ($dot)<span class="dashboard-status-dot" aria-hidden="true"></span>@endif
    {{ $label }}
</span>
