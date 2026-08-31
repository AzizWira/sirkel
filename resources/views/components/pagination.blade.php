@if ($paginator->hasPages())
    <nav class="pagination" aria-label="Pagination">

        @if ($paginator->onFirstPage())
            <span class="btn btn-sm disabled">← Sebelumnya</span>
        @else
            <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Sebelumnya</a>
        @endif
        <span class="text-sm muted">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya →</a>
        @else
            <span class="btn btn-sm disabled">Berikutnya →</span>
        @endif
    </nav>
@endif