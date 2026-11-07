{{--
    Payment options on the order success/track pages.
    Expects: $order, $trackingToken, $gateways [key => label], $manualMethod (?ManualPaymentMethod), $pendingPayment (?Payment)
--}}
@php
    $hasEmail = (bool) filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL);
    $hasMobileNumber = \App\Services\Payments\IotecGateway::payerNumber($order->customer?->phone) !== null;
    $eligible = collect($gateways)->filter(fn ($label, $key) => $key === 'iotec'
        ? $hasMobileNumber && (float) $order->balance >= \App\Services\Payments\IotecGateway::MINIMUM_AMOUNT
        : $hasEmail);
    $needsEmail = collect($gateways)->keys()->diff(['iotec'])->isNotEmpty() && !$hasEmail;
@endphp

@if((float) $order->balance > 0)
    @if($manualMethod)
        <div class="mt-3 p-3 rounded border" style="background: var(--cream)">
            <div class="small fw-semibold mb-1"><i class="{{ $manualMethod->icon }} me-1 text-brand"></i>How to pay: {{ $manualMethod->name }}</div>
            <div class="small" style="white-space: pre-line">{{ $manualMethod->instructions }}</div>
            <div class="small text-muted mt-2">Use <strong>{{ $order->order_number }}</strong> as the payment reference.</div>
        </div>
    @endif

    @if($pendingPayment && $pendingPayment->payment_method === 'iotec')
        <div class="mt-3 p-3 rounded border border-warning-subtle bg-warning-subtle">
            <div class="small fw-semibold mb-1"><i class="fas fa-mobile-screen-button me-1"></i>Waiting for your approval</div>
            <div class="small mb-2">We sent a mobile money prompt to your phone. Approve it with your PIN, then confirm below.</div>
            <a class="btn btn-primary btn-sm w-100" href="{{ route('order.payment.return', ['order' => $trackingToken, 'payment' => $pendingPayment->id]) }}">
                <i class="fas fa-rotate me-1"></i>I have approved the payment
            </a>
        </div>
    @elseif($eligible->isNotEmpty())
        <div class="mt-3">
            <div class="small fw-semibold mb-2">Pay securely online</div>
            @foreach($eligible as $gateway => $label)
                <form method="POST" action="{{ route('order.pay', $trackingToken) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="{{ $gateway }}">
                    <button class="btn btn-success w-100 mb-2" type="submit">
                        <i class="fas {{ $gateway === 'iotec' ? 'fa-mobile-screen-button' : 'fa-lock' }} me-1"></i>
                        {{ $gateway === 'iotec' ? 'Pay with mobile money (' . $label . ')' : 'Pay with ' . $label }}
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    @if($needsEmail && $eligible->isEmpty())
        <p class="small text-muted mt-3 mb-0">Add a valid email to your order to enable card payment, or contact the restaurant.</p>
    @endif
@endif
