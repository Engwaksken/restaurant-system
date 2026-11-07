@extends('layouts.app')

@section('title', 'Returns / Refunds')
@section('page-title', 'Returns / Refunds')

@section('content')
<div class="panel p-4">
    <p class="text-muted small">Refunds are issued from an order's detail page against a specific payment. Food is not returned to stock.</p>
    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-auto"><label class="form-label" for="refundFrom">From</label><input class="form-control" type="date" id="refundFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="col-auto"><label class="form-label" for="refundTo">To</label><input class="form-control" type="date" id="refundTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Filter</button></div>
        <div class="col text-end"><span class="text-muted">Total refunded:</span> <strong>{{ config('restaurant.currency') }} {{ number_format($total, 2) }}</strong></div>
    </form>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Refund</th><th>Order</th><th>Method</th><th class="text-end">Amount</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        @forelse($refunds as $refund)
            <tr>
                <td>{{ $refund->refund_number }}<small class="d-block text-muted">{{ $refund->created_at->format('Y-m-d H:i') }} · {{ $refund->branch?->name }}</small></td>
                <td>@if($refund->order)<a href="{{ route('admin.orders.show', $refund->order) }}">{{ $refund->order->order_number }}</a>@endif</td>
                <td>{{ config('restaurant.payment_methods.' . $refund->method, $refund->method) }}@if($refund->reference)<small class="d-block text-muted">{{ $refund->reference }}</small>@endif</td>
                <td class="text-end">{{ number_format((float) $refund->amount, 2) }}</td>
                <td>{{ $refund->reason }}</td>
                <td>{{ $refund->refunder?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">No refunds recorded.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $refunds->links() }}
</div>
@endsection
