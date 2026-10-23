@if ($paginator->hasPages())
    <nav class="pager" aria-label="Paginering">
        <span>
            @if (method_exists($paginator, 'total'))
                {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} van {{ $paginator->total() }}
            @endif
        </span>
        <span class="pages">
            @if (! $paginator->onFirstPage())
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Vorige</a>
            @endif
            @if (method_exists($paginator, 'lastPage'))
                @foreach (range(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page)
                    @if ($page === $paginator->currentPage())
                        <span class="cur" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Volgende</a>
            @endif
        </span>
    </nav>
@endif
