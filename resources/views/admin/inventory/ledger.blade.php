@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
<div class="panel p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">{{ $title }}</h2>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.inventory.products') }}">Inventory management</a>
    </div>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>Branch</th><th>Item</th><th>SKU</th><th>On hand</th><th>Unit</th><th>Minimum</th><th>Reorder level</th></tr></thead>
        <tbody>@forelse($stocks as $stock)<tr><td>{{ $stock->branch_name }}</td><td>{{ $stock->item_name }}</td><td>{{ $stock->sku ?? '—' }}</td><td>{{ number_format((float) $stock->quantity, 3) }}</td><td>{{ $stock->usage_unit }}</td><td>{{ number_format((float) $stock->minimum_stock, 3) }}</td><td>{{ number_format((float) $stock->reorder_level, 3) }}</td></tr>@empty<tr><td colspan="7" class="text-muted">No stock records match this view.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $stocks->links() }}
</div>
@endsection
