@extends('layouts.app')

@section('title', 'Receive Purchase Order')
@section('page-title', 'Receive ' . $order->order_number)

@section('content')
<div class="panel p-4">
    <a class="btn btn-outline-secondary btn-sm mb-3" href="{{ route('admin.purchase-orders.index') }}">Back to purchase orders</a>
    <p>Supplier: <strong>{{ $order->supplier?->name }}</strong> · Branch: <strong>{{ $order->branch?->name }}</strong></p>
    <form method="POST" action="{{ route('admin.purchases.receive') }}" id="receivePoForm">
        @csrf
        <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
        <input type="hidden" name="branch_id" value="{{ $order->branch_id }}">
        <input type="hidden" name="supplier_id" value="{{ $order->supplier_id }}">
        <label class="form-label" for="receiveInvoice">Supplier invoice number</label>
        <input class="form-control mb-3" id="receiveInvoice" name="invoice_number" maxlength="255">
        <div class="table-responsive"><table class="table">
            <thead><tr><th>Item</th><th>Ordered</th><th>Previously received</th><th>Remaining</th><th>Receive now</th></tr></thead>
            <tbody>@foreach($order->items as $line)
                @php($remaining = max(0, (float) $line->quantity - (float) $line->received_quantity))
                @if($remaining > 0)<tr class="receipt-line">
                    <td>{{ $line->inventoryItem?->name }} <small class="text-muted">({{ $line->inventoryItem?->usage_unit }})</small><input type="hidden" name="items[{{ $loop->index }}][inventory_item_id]" value="{{ $line->inventory_item_id }}"></td>
                    <td>{{ number_format((float) $line->quantity, 3) }}</td><td>{{ number_format((float) $line->received_quantity, 3) }}</td><td>{{ number_format($remaining, 3) }}</td>
                    <td><input class="form-control receive-quantity" type="number" name="items[{{ $loop->index }}][quantity]" min="0.001" max="{{ number_format($remaining, 3, '.', '') }}" step="0.001"></td>
                </tr>@endif
            @endforeach</tbody>
        </table></div>
        <button class="btn btn-primary" type="submit">Record received goods</button>
    </form>
</div>
<script>
document.getElementById('receivePoForm')?.addEventListener('submit', (event) => {
    const lines = [...document.querySelectorAll('.receipt-line')];
    lines.forEach((row) => {
        const quantity = row.querySelector('.receive-quantity');
        if (!quantity.value) row.remove();
    });
    if (!document.querySelector('.receipt-line')) {
        event.preventDefault();
        alert('Enter a receive quantity for at least one order line.');
    }
});
</script>
@endsection
