@extends('layouts.app')

@section('title', 'Received Purchases')
@section('page-title', 'Purchases & Stock Receipts')

@section('content')
<div class="row g-4">
    <div class="col-xl-4"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-start mb-3"><h2 class="h5 mb-0">Record received stock</h2><a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.purchase-orders.index') }}">Purchase orders</a></div>
        @if($branches->isEmpty() || $inventoryItems->isEmpty())<p class="text-muted">An active branch and inventory item are required before receiving stock.</p>@else
            <form method="POST" action="{{ route('admin.purchases.receive') }}">
                @csrf
                <label class="form-label" for="purchaseBranch">Branch</label>
                <select class="form-select mb-2" id="purchaseBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="purchaseSupplier">Supplier</label>
                <select class="form-select mb-2" id="purchaseSupplier" name="supplier_id"><option value="">Unspecified</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select>
                <label class="form-label" for="purchaseInvoice">Invoice number</label>
                <input class="form-control mb-2" id="purchaseInvoice" name="invoice_number" maxlength="255">
                <label class="form-label" for="purchaseReference">Reference (optional)</label>
                <input class="form-control mb-3" id="purchaseReference" name="reference" maxlength="255">
                <h3 class="h6">Received items</h3>
                <div id="purchaseItems"><div class="purchase-row border rounded p-2 mb-2">
                    <label class="form-label">Inventory item</label><select class="form-select mb-2" name="items[0][inventory_item_id]" required>@foreach($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->usage_unit }})</option>@endforeach</select>
                    <div class="row g-2"><div class="col"><label class="form-label">Quantity</label><input class="form-control" type="number" name="items[0][quantity]" min="0.001" step="0.001" required></div><div class="col"><label class="form-label">Unit cost</label><input class="form-control" type="number" name="items[0][unit_cost]" min="0" step="0.01" required></div></div>
                </div></div>
                <button class="btn btn-outline-secondary btn-sm mb-3" type="button" id="addPurchaseItem">Add item</button>
                <button class="btn btn-primary w-100" type="submit">Receive stock</button>
            </form>
        @endif
    </div></div>
    <div class="col-xl-8"><div class="panel p-4">
        <h2 class="h5">Receipt history</h2>
        @forelse($purchases as $purchase)
            <details class="border-bottom py-3"><summary class="d-flex justify-content-between gap-2"><span>{{ $purchase->received_at->format('Y-m-d H:i') }} · {{ $purchase->branch?->name }} · {{ $purchase->supplier?->name ?? 'Supplier not specified' }}</span><strong>{{ config('restaurant.currency') }} {{ number_format((float) $purchase->total, 2) }}</strong></summary>
                <div class="pt-2"><p class="mb-1">Invoice: {{ $purchase->invoice_number ?? '—' }} · Reference: {{ $purchase->reference ?? '—' }}</p>
                    <ul class="mb-2">@foreach($purchase->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }} × {{ number_format((float) $line->unit_cost, 2) }} = {{ number_format((float) $line->line_total, 2) }}</li>@endforeach</ul>
                    @php($paid = (float) $purchase->supplierPayments->sum('amount'))
                    <p class="mb-2">Paid {{ config('restaurant.currency') }} {{ number_format($paid, 2) }} · Due {{ config('restaurant.currency') }} {{ number_format(max(0, (float) $purchase->total - $paid), 2) }}</p>
                    @if($purchase->supplierPayments->isNotEmpty())<ul class="small">@foreach($purchase->supplierPayments as $payment)<li>{{ $payment->paid_at->format('Y-m-d H:i') }} · {{ $payment->receipt_number }} · {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }} · {{ config('restaurant.currency') }} {{ number_format((float) $payment->amount, 2) }}{{ $payment->reference ? ' · ' . $payment->reference : '' }}</li>@endforeach</ul>@endif
                    @if((float) $purchase->total > $paid)
                        <form class="row g-2 mt-2" method="POST" action="{{ route('admin.purchases.payments.store', $purchase->id) }}">
                            @csrf
                            <div class="col-sm-3"><label class="form-label">Payment method</label><select class="form-select" name="payment_method" required><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="card">Card</option><option value="other">Other</option></select></div>
                            <div class="col-sm-3"><label class="form-label">Amount</label><input class="form-control" type="number" name="amount" min="0.01" max="{{ number_format(max(0, (float) $purchase->total - $paid), 2, '.', '') }}" step="0.01" required></div>
                            <div class="col-sm-4"><label class="form-label">Reference</label><input class="form-control" name="reference" maxlength="255"></div>
                            <div class="col-sm-2 d-flex align-items-end"><button class="btn btn-outline-primary" type="submit">Record payment</button></div>
                        </form>
                    @endif
                </div>
            </details>
        @empty<p class="text-muted">No received purchases yet.</p>@endforelse
        {{ $purchases->links() }}
    </div></div>
</div>
<script>
document.getElementById('addPurchaseItem')?.addEventListener('click', () => {
    const container = document.getElementById('purchaseItems');
    const index = container.querySelectorAll('.purchase-row').length;
    const row = container.querySelector('.purchase-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        if (field.tagName === 'INPUT') field.value = '';
    });
    container.append(row);
});
</script>
@endsection
