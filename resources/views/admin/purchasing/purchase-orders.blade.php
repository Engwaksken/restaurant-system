@extends('layouts.app')

@section('title', 'Purchase Orders')
@section('page-title', 'Purchase Orders')

@section('content')
<div class="row g-4">
    <div class="col-xl-4"><div class="panel p-4">
        <h2 class="h5">Create purchase order</h2>
        @if($branches->isEmpty() || $suppliers->isEmpty() || $inventoryItems->isEmpty())<p class="text-muted">Active branches, suppliers, and inventory items are required.</p>@else
            <form method="POST" action="{{ route('admin.purchase-orders.store') }}">
                @csrf
                <label class="form-label" for="poBranch">Receiving branch</label>
                <select class="form-select mb-2" id="poBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="poSupplier">Supplier</label>
                <select class="form-select mb-2" id="poSupplier" name="supplier_id" required>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
                <label class="form-label" for="poNotes">Notes</label>
                <textarea class="form-control mb-3" id="poNotes" name="notes" maxlength="2000"></textarea>
                <div id="poItems"><div class="po-row border rounded p-2 mb-2">
                    <label class="form-label">Inventory item</label><select class="form-select mb-2" name="items[0][inventory_item_id]" required>@foreach($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->usage_unit }})</option>@endforeach</select>
                    <div class="row g-2"><div class="col"><label class="form-label">Quantity</label><input class="form-control" type="number" name="items[0][quantity]" min="0.001" step="0.001" required></div><div class="col"><label class="form-label">Unit cost</label><input class="form-control" type="number" name="items[0][unit_cost]" min="0" step="0.01" required></div></div>
                </div></div>
                <button class="btn btn-outline-secondary btn-sm mb-3" type="button" id="addPoItem">Add item</button>
                <button class="btn btn-primary w-100" type="submit">Create order</button>
            </form>
        @endif
    </div></div>
    <div class="col-xl-8"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Order history</h2><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.purchases.index') }}">Received purchases</a></div>
        @forelse($orders as $order)
            <details class="border-bottom py-3"><summary>{{ $order->order_number }} · {{ $order->supplier?->name }} · {{ $order->branch?->name }} · {{ ucfirst(str_replace('_', ' ', $order->status)) }}</summary>
                <div class="pt-2"><ul>@foreach($order->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->received_quantity, 3) }} / {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }} at {{ number_format((float) $line->unit_cost, 2) }}</li>@endforeach</ul>
                    @if($order->notes)<p>{{ $order->notes }}</p>@endif
                    @if(!in_array($order->status, ['received', 'cancelled'], true))<a class="btn btn-sm btn-primary" href="{{ route('admin.purchase-orders.receive', $order->id) }}">Receive goods</a>@endif
                    @if($order->status === 'ordered')<form class="d-inline" method="POST" action="{{ route('admin.purchase-orders.cancel', $order->id) }}" onsubmit="return confirm('Cancel this unreceived purchase order?')">@csrf @method('PATCH')<button class="btn btn-sm btn-outline-danger" type="submit">Cancel order</button></form>@endif
                </div>
            </details>
        @empty<p class="text-muted">No purchase orders created.</p>@endforelse
        {{ $orders->links() }}
    </div></div>
</div>
<script>
document.getElementById('addPoItem')?.addEventListener('click', () => {
    const container = document.getElementById('poItems');
    const index = container.querySelectorAll('.po-row').length;
    const row = container.querySelector('.po-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        if (field.tagName === 'INPUT') field.value = '';
    });
    container.append(row);
});
</script>
@endsection
