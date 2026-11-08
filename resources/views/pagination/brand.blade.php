{{-- Default paginator view: result summary plus compact numbered links. --}}
@if($paginator->total() > 0)
    <div class="pager-bar">
        <span class="pager-summary">
            Showing <strong>{{ number_format($paginator->firstItem()) }}</strong>-<strong>{{ number_format($paginator->lastItem()) }}</strong>
            of <strong>{{ number_format($paginator->total()) }}</strong>
        </span>

        @if($paginator->hasPages())
            <nav role="navigation" aria-label="Pagination">
                <ul class="pagination pagination-sm">
                    @if($paginator->onFirstPage())
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-hidden="true"><i class="fas fa-chevron-left"></i></span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><i class="fas fa-chevron-left"></i></a></li>
                    @endif

                    @foreach($elements as $element)
                        @if(is_string($element))
                            <li class="page-item disabled d-none d-sm-block" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                        @endif

                        @if(is_array($element))
                            @foreach($element as $page => $url)
                                @if($page == $paginator->currentPage())
                                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                                @else
                                    <li class="page-item d-none d-sm-block"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                                @endif
                            @endforeach
                        @endif
                    @endforeach

                    @if($paginator->hasMorePages())
                        <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><i class="fas fa-chevron-right"></i></a></li>
                    @else
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link" aria-hidden="true"><i class="fas fa-chevron-right"></i></span></li>
                    @endif
                </ul>
            </nav>
        @endif
    </div>
@endif
