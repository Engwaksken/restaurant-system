@extends('layouts.app')

@section('title', 'Wastage')
@section('page-title', 'Inventory Wastage')

@section('content')
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Record wasted stock</h2>
        @if($branches->isEmpty() || $items->isEmpty())<p class="text-muted">An active branch and inventory item are required.</p>@else
            <form method="POST" action="{{ route('admin.inventory.wastage.store') }}">
                @csrf
                <label class="form-label" for="wastageBranch">Branch</label>
                <select class="form-select mb-2" id="wastageBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="wastageItem">Inventory item</label>
                <select class="form-select mb-2" id="wastageItem" name="inventory_item_id" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->usage_unit }})</option>@endforeach</select>
                <label class="form-label" for="wastageQuantity">Quantity wasted</label>
                <input class="form-control mb-2" type="number" id="wastageQuantity" name="quantity" min="0.001" step="0.001" required>
                <label class="form-label" for="wastageReason">Reason</label>
                <textarea class="form-control mb-2" id="wastageReason" name="reason" minlength="5" maxlength="1000" required></textarea>
                <label class="form-label" for="wastageReference">Reference (optional)</label>
                <input class="form-control mb-3" id="wastageReference" name="reference" maxlength="255">
                <button class="btn btn-warning" type="submit">Record wastage</button>
            </form>
        @endif
    </div></div>
    <div class="col-lg-8"><div class="panel p-4">
        <h2 class="h5">Wastage history</h2>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Branch</th><th>Item</th><th>Quantity</th><th>Reason</th><th>Value</th></tr></thead>
            <tbody>@forelse($wastages as $wastage)<tr><td>{{ $wastage->wasted_at->format('Y-m-d H:i') }}</td><td>{{ $wastage->branch?->name }}</td><td>{{ $wastage->inventoryItem?->name }}</td><td>{{ number_format((float) $wastage->quantity, 3) }} {{ $wastage->inventoryItem?->usage_unit }}</td><td>{{ $wastage->reason }} @if($wastage->reference)<small class="text-muted d-block">Ref: {{ $wastage->reference }}</small>@endif</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $wastage->quantity * (float) $wastage->unit_cost, 2) }}</td></tr>@empty<tr><td colspan="6" class="text-muted">No wastage recorded.</td></tr>@endforelse</tbody>
        </table></div>{{ $wastages->links() }}
    </div></div>
</div>
@endsection
