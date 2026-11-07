@props(['paginator'])
@if($paginator instanceof \Illuminate\Contracts\Pagination\Paginator)
    {{ $paginator->onEachSide(1)->links() }}
@endif
