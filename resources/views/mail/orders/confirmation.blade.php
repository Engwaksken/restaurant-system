<h1>We received your order</h1>
<p>Order <strong>{{ $order->order_number }}</strong> has been submitted.</p>
<p>Total: {{ config('restaurant.currency') }} {{ number_format((float) $order->total, 2) }}</p>
<p><a href="{{ route('order.track', $trackingToken) }}">Track your order securely</a></p>
<p>Keep this link private. Anyone with the link can view the current status of this order.</p>
