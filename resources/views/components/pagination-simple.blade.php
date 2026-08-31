@if ($paginator->hasPages())
    <nav class="pagination">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sm disabled">← Sebelumnya</span>
        @else
            <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}">← Sebelumnya</a>
        @endif

        @if ($paginator->hasMorePages())
            <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}">Berikutnya →</a>
        @endif
    </nav>
@endif