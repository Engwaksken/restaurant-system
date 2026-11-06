<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Track order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-5" style="max-width: 720px">
    <div class="card shadow-sm"><div class="card-body p-4">
        <h1 class="h3">Order status</h1>
        <p>Order <strong>{{ $order->order_number }}</strong></p>
        <p>Current status: <strong>{{ ucfirst($order->status) }}</strong></p>
        <p>Branch: {{ $order->branch?->name ?? 'Restaurant' }}</p>
        <p>Last updated: {{ $order->updated_at?->format('M j, Y g:i A') }}</p>
        <a class="btn btn-outline-secondary" href="{{ route('home') }}">Back to menu</a>
    </div></div>
</main>
</body>
</html>
