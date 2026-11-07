@extends('layouts.app')

@section('title', $order->order_number)
@section('page-title', 'Order ' . $order->order_number)

@section('content')
@php
    $currency = config('restaurant.currency');
    $money = fn ($value) => number_format((float) $value, 2);
    $statusClasses = ['pending' => 'warning', 'confirmed' => 'primary', 'preparing' => 'info', 'ready' => 'success', 'served' => 'success', 'completed' => 'dark', 'cancelled' => 'secondary'];
    $actionLabels = ['confirmed' => 'Confirm', 'preparing' => 'Start preparing', 'ready' => 'Mark ready', 'completed' => 'Complete'];
@endphp
<p><a href="{{ route('admin.orders.index') }}">← All orders</a></p>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="panel p-4 mb-4">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                    <span class="badge bg-{{ $statusClasses[$order->status] ?? 'secondary' }} fs-6">{{ ucfirst($order->status) }}</span>
                    <span class="text-muted ms-2">{{ \Illuminate\Support\Str::headline((string) $order->order_type) }} · {{ $order->branch?->name }}</span>
                </div>
                <small class="text-muted">Ordered {{ ($order->ordered_at ?? $order->created_at)?->format('Y-m-d H:i') }}@if($order->waiter) by {{ $order->waiter->name }}@endif</small>
            </div>
            @if($canManage && $order->status !== 'cancelled' && $order->status !== 'completed')
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach($statusOptions as $next)
                        @continue($next === 'cancelled')
                        <form method="POST" action="{{ route('admin.orders.status.update', $order) }}">
                            @csrf @method('PATCH')
                            <input type="hidden" name="status" value="{{ $next }}">
                            <button class="btn btn-sm btn-outline-primary" type="submit">{{ $actionLabels[$next] ?? ucfirst($next) }}</button>
                        </form>
                    @endforeach
                    @if(in_array('cancelled', $statusOptions, true))
                        <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('Cancel this order?')">
                            @csrf @method('PATCH')
                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel order</button>
                        </form>
                    @endif
                </div>
            @endif
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Unit price</th><th class="text-end">Total</th><th>Kitchen</th></tr></thead>
                <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td><strong>{{ $item->displayName() }}</strong>
                            @foreach((array) $item->addons as $addon)<small class="d-block text-muted">+ {{ $addon['name'] ?? '' }} ({{ $money($addon['price'] ?? 0) }})</small>@endforeach
                            @if($item->notes)<small class="d-block text-warning-emphasis">{{ $item->notes }}</small>@endif</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                        <td class="text-end">{{ $money($item->unit_price) }}</td>
                        <td class="text-end">{{ $money($item->total) }}</td>
                        <td>{{ ucfirst((string) $item->kitchen_status) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if($order->notes)<p class="mb-0"><strong>Notes:</strong> {{ $order->notes }}</p>@endif
        </div>

        <div class="panel p-4">
            <h2 class="h5">Payments</h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Receipt</th><th>Method</th><th class="text-end">Amount</th><th class="text-end">Refunded</th><th>Status</th>@if($canRefund)<th></th>@endif</tr></thead>
                <tbody>
                @forelse($order->payments as $payment)
                    @php $refunded = (float) $payment->refunds->sum('amount'); $refundable = max(0, (float) $payment->amount - $refunded); @endphp
                    <tr>
                        <td>{{ $payment->receipt_number }}<small class="d-block text-muted">{{ $payment->paid_at?->format('Y-m-d H:i') }}@if($payment->receiver) · {{ $payment->receiver->name }}@endif</small></td>
                        <td>{{ $paymentMethods[$payment->payment_method] ?? \Illuminate\Support\Str::headline($payment->payment_method) }}@if($payment->transaction_reference)<small class="d-block text-muted">{{ $payment->transaction_reference }}</small>@endif</td>
                        <td class="text-end">{{ $money($payment->amount) }}</td>
                        <td class="text-end">{{ $refunded > 0 ? $money($refunded) : '—' }}</td>
                        <td>{{ ucfirst($payment->status) }}</td>
                        @if($canRefund)
                        <td>
                            @if(in_array($payment->status, ['successful', 'refunded'], true) && $refundable > 0)
                                <details>
                                    <summary class="small">Refund</summary>
                                    <form method="POST" action="{{ route('admin.orders.refunds.store', $order) }}" class="mt-2" onsubmit="return confirm('Record this refund?')">
                                        @csrf
                                        <input type="hidden" name="payment_id" value="{{ $payment->id }}">
                                        <input class="form-control form-control-sm mb-1" type="number" name="amount" min="0.01" max="{{ $refundable }}" step="0.01" value="{{ $refundable }}" required aria-label="Refund amount">
                                        <input class="form-control form-control-sm mb-1" name="reason" minlength="5" maxlength="1000" required placeholder="Reason" aria-label="Refund reason">
                                        @if(!in_array($payment->payment_method, ['cash', 'manual'], true))
                                            <input class="form-control form-control-sm mb-1" name="reference" maxlength="255" required placeholder="Provider / bank reference" aria-label="Refund reference">
                                        @endif
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Refund via {{ $paymentMethods[$payment->payment_method] ?? $payment->payment_method }}</button>
                                    </form>
                                </details>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No payments recorded.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if($order->refunds->isNotEmpty())
                <h3 class="h6 mt-3">Refunds</h3>
                <ul class="list-unstyled small mb-0">
                    @foreach($order->refunds as $refund)
                        <li>{{ $refund->refund_number }} · {{ $money($refund->amount) }} · {{ $refund->reason }} <span class="text-muted">({{ $refund->refunder?->name }}, {{ $refund->created_at->format('Y-m-d H:i') }})</span></li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="panel p-4 mb-4">
            <h2 class="h5">Summary</h2>
            <dl class="row mb-0">
                <dt class="col-7 fw-normal">Subtotal</dt><dd class="col-5 text-end">{{ $money($order->subtotal) }}</dd>
                @if((float) $order->discount > 0)<dt class="col-7 fw-normal">Discount</dt><dd class="col-5 text-end">− {{ $money($order->discount) }}</dd>@endif
                @if((float) $order->loyalty_discount > 0)<dt class="col-7 fw-normal">Points ({{ $order->loyalty_points_redeemed }})</dt><dd class="col-5 text-end">− {{ $money($order->loyalty_discount) }}</dd>@endif
                <dt class="col-7 fw-normal">Tax</dt><dd class="col-5 text-end">{{ $money($order->tax) }}</dd>
                @if((float) $order->delivery_fee > 0)<dt class="col-7 fw-normal">Delivery</dt><dd class="col-5 text-end">{{ $money($order->delivery_fee) }}</dd>@endif
                <dt class="col-7 border-top pt-2">Total</dt><dd class="col-5 text-end border-top pt-2 fw-bold">{{ $currency }} {{ $money($order->total) }}</dd>
                <dt class="col-7 fw-normal">Paid</dt><dd class="col-5 text-end">{{ $money($order->paid_amount) }}</dd>
                @if((float) $order->refunded_amount > 0)<dt class="col-7 fw-normal">Refunded</dt><dd class="col-5 text-end text-danger">− {{ $money($order->refunded_amount) }}</dd>@endif
                <dt class="col-7">Balance due</dt><dd class="col-5 text-end fw-bold {{ (float) $order->balance > 0 ? 'text-danger' : '' }}">{{ $money($order->balance) }}</dd>
            </dl>
        </div>
        @if($order->customer)
            <div class="panel p-4 mb-4">
                <h2 class="h5">Customer</h2>
                <p class="mb-1"><a href="{{ route('admin.customers.show', $order->customer->id) }}">{{ $order->customer->name }}</a></p>
                <p class="small text-muted mb-0">{{ $order->customer->phone }} · {{ number_format((int) $order->customer->loyalty_points) }} points</p>
            </div>
        @endif
        @if($canPay && (float) $order->balance > 0 && $order->status !== 'cancelled')
            <div class="panel p-4">
                <h2 class="h5">Receive payment</h2>
                <form method="POST" action="{{ route('admin.orders.payments.store', $order) }}">
                    @csrf
                    <label class="form-label" for="paymentMethod">Method</label>
                    <select class="form-select mb-2" id="paymentMethod" name="payment_method" required>
                        @foreach($paymentMethods as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                    <label class="form-label" for="paymentAmount">Amount</label>
                    <input class="form-control mb-2" id="paymentAmount" type="number" name="amount" min="1" max="{{ $order->balance }}" step="0.01" value="{{ $order->balance }}" required>
                    <label class="form-label" for="paymentReference">Transaction reference <small class="text-muted">(required except cash/other)</small></label>
                    <input class="form-control mb-3" id="paymentReference" name="transaction_reference" maxlength="255">
                    <button class="btn btn-primary w-100" type="submit">Record payment</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
