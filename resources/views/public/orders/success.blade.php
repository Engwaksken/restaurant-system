@php($brand = \App\Support\Brand::for($order->branch?->restaurant))
<!doctype html>
<html lang="en">
<head>
    @include('public.orders.partials.styles')
    <meta name="referrer" content="no-referrer">
    <title>Order placed · {{ $brand->name() }}</title>
</head>
<body class="order-hero">

@include('public.orders.partials.brand')

<main class="container py-4 py-md-5" style="max-width: 920px;">

    @if(session('error') || $errors->any())
        <div class="alert alert-warning">{{ session('error') ?? $errors->first() }}</div>
    @endif

    <section class="card order-card text-center p-4 p-md-5 mb-4">
        <div class="success-icon mx-auto mb-3">
            <i class="fas fa-circle-check"></i>
        </div>
        <h1 class="h3 fw-bold mb-2">Thank you! Your order is placed</h1>
        <p class="text-muted mb-1">{{ $brand->name() }} has received your order and will start preparing it shortly.</p>
        <p class="text-muted mb-4">Keep your order number to track it at any time.</p>
        <div class="order-number-chip mx-auto">
            <span class="small text-muted">Order number</span>
            <strong>{{ $order->order_number }}</strong>
        </div>
    </section>

    @if($startPaymentGateway && (float) $order->balance > 0 && filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
        <section class="card order-card p-3 mb-4">
            <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                <div>
                    <div class="fw-semibold">Continue to secure payment</div>
                    <div class="small text-muted">Your order is placed. Complete payment with {{ $gateways[$startPaymentGateway] }} to confirm it.</div>
                </div>
                <form id="startOnlinePayment" method="POST" action="{{ route('order.pay', $trackingToken) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="{{ $startPaymentGateway }}">
                    <button class="btn btn-success text-nowrap" type="submit">Continue to payment</button>
                </form>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.getElementById('startOnlinePayment')?.requestSubmit();
                });
            </script>
        </section>
    @endif

    @if($order->status === 'cancelled')
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="fas fa-circle-xmark"></i>
            <span>This order has been cancelled. Please contact the restaurant if you have questions.</span>
        </div>
    @else
        <section class="card order-card p-4 mb-4">
            <h2 class="h6 fw-bold mb-4">Order status</h2>
            @include('public.orders.partials.timeline')
        </section>
    @endif

    <div class="row g-4">

        <div class="col-lg-7">
            <section class="card order-card p-4 h-100">
                <h2 class="h6 fw-bold mb-3">Items ({{ $order->items->sum('quantity') }})</h2>
                <ul class="list-unstyled mb-0">
                    @foreach($order->items as $item)
                        <li class="d-flex justify-content-between gap-3 py-2 border-bottom small">
                            <span>{{ $item->quantity }} × {{ $item->item_name }}</span>
                            <span class="text-nowrap">{{ $brand->currency() }} {{ number_format((float) $item->total, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                @if(!empty($order->notes))
                    <div class="small text-muted mt-3"><strong>Notes:</strong> {{ $order->notes }}</div>
                @endif
            </section>
        </div>

        <div class="col-lg-5">
            <section class="card order-card p-4 h-100">
                <h2 class="h6 fw-bold mb-3">Payment summary</h2>

                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Subtotal</span>
                    <span>{{ $brand->currency() }} {{ number_format((float) $order->subtotal, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Tax</span>
                    <span>{{ $brand->currency() }} {{ number_format((float) $order->tax, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between small mb-1">
                    <span class="text-muted">Payment method</span>
                    <span>{{ $order->payment_preference === 'cash_on_delivery' ? 'Pay on delivery or pickup' : ucfirst((string) $order->payment_preference) }}</span>
                </div>
                @if((float) $order->delivery_fee > 0)
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Delivery</span>
                        <span>{{ $brand->currency() }} {{ number_format((float) $order->delivery_fee, 2) }}</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
                    <span>Total</span>
                    <span>{{ $brand->currency() }} {{ number_format((float) $order->total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between small mt-2">
                    <span class="text-muted">Amount due</span>
                    <span class="fw-semibold {{ (float) $order->balance > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $brand->currency() }} {{ number_format((float) $order->balance, 2) }}
                    </span>
                </div>

                @if((float) $order->balance > 0 && $gateways !== [] && filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
                    <div class="mt-4">
                        <div class="small fw-semibold mb-2">Pay securely online</div>
                        @foreach($gateways as $gateway => $label)
                            <form method="POST" action="{{ route('order.pay', $trackingToken) }}">
                                @csrf
                                <input type="hidden" name="gateway" value="{{ $gateway }}">
                                <button class="btn btn-success w-100 mb-2" type="submit">Pay with {{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif

                @if((float) $order->balance > 0 && $gateways !== [] && !filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
                    <p class="small text-muted mt-3 mb-0">Add a valid email to your order to enable online payment, or contact the restaurant.</p>
                @endif

                <div class="d-grid gap-2 mt-4">
                    <a class="btn btn-primary" href="{{ route('order.track', $trackingToken) }}">
                        <i class="fas fa-location-dot me-1"></i>Track this order
                    </a>
                    <a class="btn btn-outline-secondary" href="{{ route('home') }}">Continue shopping</a>
                </div>
            </section>
        </div>

    </div>

</main>

</body>
</html>
