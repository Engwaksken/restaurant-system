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
        <p>Your order <strong>{{ $order->order_number }}</strong> has been submitted.</p>
        <p>Status: <strong>{{ ucfirst($order->status) }}</strong></p>
        <p>Total: <strong>{{ $order->branch?->restaurant?->currency ?? 'UGX' }} {{ number_format((float) $order->total, 2) }}</strong></p>
        <h2 class="h5 mt-4">Items</h2>
        <ul>@foreach($order->items as $item)<li>{{ $item->item_name }} × {{ $item->quantity }}</li>@endforeach</ul>
        <a class="btn btn-primary" href="{{ route('order.track', $trackingToken) }}">Track this order</a>
        <a class="btn btn-outline-secondary" href="{{ route('home') }}">Back to menu</a>
    </div></div>
</main>
</body>
</html>
