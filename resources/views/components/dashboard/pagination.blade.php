@props(['paginator', 'label' => 'Result pages'])

@if ($paginator->hasPages())
    @php
        $startPage = max(1, $paginator->currentPage() - 2);
        $endPage = min($paginator->lastPage(), $paginator->currentPage() + 2);
    @endphp
    <nav {{ $attributes->class(['identity-pagination']) }} aria-label="{{ $label }}">
        @if ($paginator->onFirstPage())
            <span class="identity-pagination-direction" aria-disabled="true">Previous</span>
        @else
            <a class="identity-pagination-direction" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        <div class="identity-pagination-pages">
            @if ($startPage > 1)
                <a href="{{ $paginator->url(1) }}" aria-label="Go to page 1">1</a>
                @if ($startPage > 2)<span class="identity-pagination-ellipsis" aria-hidden="true">&hellip;</span>@endif
            @endif

            @foreach (range($startPage, $endPage) as $page)
                @if ($page === $paginator->currentPage())
                    <span class="is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($endPage < $paginator->lastPage())
                @if ($endPage < $paginator->lastPage() - 1)<span class="identity-pagination-ellipsis" aria-hidden="true">&hellip;</span>@endif
                <a href="{{ $paginator->url($paginator->lastPage()) }}" aria-label="Go to page {{ $paginator->lastPage() }}">{{ $paginator->lastPage() }}</a>
            @endif
        </div>

        @if ($paginator->hasMorePages())
            <a class="identity-pagination-direction" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="identity-pagination-direction" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
