@extends('layouts.app')

@section('title', 'Stock Movement Ledger')
@section('page-title', 'Stock Movement Ledger')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
<div class="panel p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0">Audited stock movements</h2>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.inventory.products') }}">Record adjustment</a>
    </div>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>Date</th><th>Branch</th><th>Item</th><th>Type</th><th>Change</th><th>Unit cost</th><th>Recorded by</th><th>Reason / reference</th></tr></thead>
        <tbody>@forelse($movements as $movement)<tr><td>{{ $movement->created_at }}</td><td>{{ $movement->branch_name }}</td><td>{{ $movement->item_name }}</td><td>{{ ucfirst(str_replace('_', ' ', $movement->type)) }}</td><td>{{ number_format((float) $movement->quantity, 3) }} {{ $movement->usage_unit }}</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $movement->unit_cost, 2) }}</td><td>{{ $movement->actor_name ?? 'System' }}</td><td>{{ $movement->notes ?? ($movement->reference_type ? $movement->reference_type . ' #' . $movement->reference_id : '—') }}</td></tr>@empty<tr><td colspan="8" class="text-muted">No stock movements have been recorded.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $movements->links() }}
</div>
@endsection
