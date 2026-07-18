@props(['items' => []])

@if (count($items) > 0)
    <nav class="dashboard-breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($items as $item)
                <li>
                    @if (! $loop->last && isset($item['route']))
                        <a href="{{ route($item['route'], $item['parameters'] ?? []) }}">{{ $item['label'] }}</a>
                    @else
                        <span aria-current="page">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
