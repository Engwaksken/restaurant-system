@extends('layouts.app')

@section('title', 'Stock Transfers')
@section('page-title', 'Branch Stock Transfers')

@section('content')
<div class="row g-4">
    <div class="col-xl-4"><div class="panel p-4">
        <h2 class="h5">Transfer stock</h2>
        @if($branches->count() < 2 || $items->isEmpty())<p class="text-muted">Two active branches and an active inventory item are required.</p>@else
            <form method="POST" action="{{ route('admin.inventory.transfers.store') }}">
                @csrf
                <label class="form-label" for="fromBranch">From branch</label>
                <select class="form-select mb-2" id="fromBranch" name="from_branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(auth()->user()->branch_id == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="toBranch">To branch</label>
                <select class="form-select mb-2" id="toBranch" name="to_branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(auth()->user()->branch_id && auth()->user()->branch_id != $branch->id)>{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="transferReference">Reference</label>
                <input class="form-control mb-2" id="transferReference" name="reference" maxlength="255">
                <label class="form-label" for="transferNotes">Notes</label>
                <textarea class="form-control mb-3" id="transferNotes" name="notes" maxlength="1000"></textarea>
                <div id="transferItems"><div class="transfer-row border rounded p-2 mb-2">
                    <label class="form-label">Inventory item</label><select class="form-select mb-2" name="items[0][inventory_item_id]" required>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->usage_unit }})</option>@endforeach</select>
                    <label class="form-label">Quantity</label><input class="form-control" type="number" name="items[0][quantity]" min="0.001" step="0.001" required>
                </div></div>
                <button class="btn btn-outline-secondary btn-sm mb-3" type="button" id="addTransferItem">Add item</button>
                <button class="btn btn-primary w-100" type="submit">Complete transfer</button>
            </form>
        @endif
    </div></div>
    <div class="col-xl-8"><div class="panel p-4">
        <h2 class="h5">Transfer history</h2>
        @forelse($transfers as $transfer)
            <details class="border-bottom py-3"><summary>{{ $transfer->transferred_at->format('Y-m-d H:i') }} · {{ $transfer->fromBranch?->name }} → {{ $transfer->toBranch?->name }}{{ $transfer->reference ? ' · ' . $transfer->reference : '' }}</summary>
                @if($transfer->notes)<p class="mt-2 mb-1">{{ $transfer->notes }}</p>@endif
                <ul class="mb-0">@foreach($transfer->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }}</li>@endforeach</ul>
            </details>
        @empty<p class="text-muted">No stock transfers recorded.</p>@endforelse
        {{ $transfers->links() }}
    </div></div>
</div>
<script>
document.getElementById('addTransferItem')?.addEventListener('click', () => {
    const container = document.getElementById('transferItems');
    const index = container.querySelectorAll('.transfer-row').length;
    const row = container.querySelector('.transfer-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        if (field.tagName === 'INPUT') field.value = '';
    });
    container.append(row);
});
const from = document.getElementById('fromBranch');
const to = document.getElementById('toBranch');
from?.addEventListener('change', () => {
    if (from.value === to.value) to.value = [...to.options].find(option => option.value !== from.value)?.value ?? '';
});
</script>
@endsection
