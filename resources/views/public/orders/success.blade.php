<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Order received</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h3">Order received</h1>
        @if(session('error') || $errors->any())
            <div class="alert alert-warning">{{ session('error') ?? $errors->first() }}</div>
        @endif
        <p>Your order <strong>{{ $order->order_number }}</strong> has been submitted.</p>
        <p>Status: <strong>{{ ucfirst($order->status) }}</strong></p>
        <p>Subtotal: {{ config('restaurant.currency') }} {{ number_format((float) $order->subtotal, 2) }}</p>
        <p>Tax: {{ config('restaurant.currency') }} {{ number_format((float) $order->tax, 2) }}</p>
        @if((float) $order->delivery_fee > 0)<p>Delivery: {{ config('restaurant.currency') }} {{ number_format((float) $order->delivery_fee, 2) }}</p>@endif
        <p>Total: <strong>{{ config('restaurant.currency') }} {{ number_format((float) $order->total, 2) }}</strong></p>
        <h2 class="h5 mt-4">Items</h2>
        <ul>@foreach($order->items as $item)<li>{{ $item->item_name }} × {{ $item->quantity }}</li>@endforeach</ul>
        <a class="btn btn-primary" href="{{ route('order.track', $trackingToken) }}">Track this order</a>
        @if((float) $order->balance > 0 && $gateways !== [] && filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
            <div class="mt-4">
                <h2 class="h5">Pay securely online</h2>
                @foreach($gateways as $gateway => $label)
                    <form class="d-inline" method="POST" action="{{ route('order.pay', $trackingToken) }}">
                        @csrf
                        <input type="hidden" name="gateway" value="{{ $gateway }}">
                        <button class="btn btn-success" type="submit">Pay with {{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
        @if((float) $order->balance > 0 && $gateways !== [] && !filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
            <p class="text-muted mt-3">Add a valid email to your order to enable online payment, or contact the restaurant.</p>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('home') }}">Back to menu</a>
    </div></div>
</main>
</body>
</html>
