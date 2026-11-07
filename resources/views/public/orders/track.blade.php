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
        @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif
        <p>Order <strong>{{ $order->order_number }}</strong></p>
        <p>Current status: <strong>{{ ucfirst($order->status) }}</strong></p>
        <p>Branch: {{ $order->branch?->name ?? 'Restaurant' }}</p>
        <p>Last updated: {{ $order->updated_at?->format('M j, Y g:i A') }}</p>
        <p>Amount due: <strong>{{ config('restaurant.currency') }} {{ number_format((float) $order->balance, 2) }}</strong></p>
        @if((float) $order->balance > 0 && $gateways !== [] && filter_var($order->customer?->email, FILTER_VALIDATE_EMAIL))
            <h2 class="h5">Pay securely online</h2>
            @foreach($gateways as $gateway => $label)
                <form class="d-inline" method="POST" action="{{ route('order.pay', $trackingToken) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="{{ $gateway }}">
                    <button class="btn btn-success" type="submit">Pay with {{ $label }}</button>
                </form>
            @endforeach
        @endif
        @if($order->feedback)
            <p class="text-muted">Thank you — you rated this order {{ $order->feedback->rating }}/5.</p>
        @elseif(in_array($order->status, \App\Http\Controllers\Public\PublicOrderFeedbackController::RATEABLE_STATUSES, true))
            <h2 class="h5 mt-4">How was your order?</h2>
            <form method="POST" action="{{ route('order.feedback', $trackingToken) }}" class="mb-3">
                @csrf
                @if($errors->any())<div class="alert alert-warning">{{ $errors->first() }}</div>@endif
                <fieldset class="mb-2">
                    <legend class="form-label fs-6">Rating</legend>
                    @for($score = 5; $score >= 1; $score--)
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="rating" id="rating{{ $score }}" value="{{ $score }}" required @checked((int) old('rating') === $score)>
                            <label class="form-check-label" for="rating{{ $score }}">{{ $score }} <i class="fas fa-star"></i></label>
                        </div>
                    @endfor
                </fieldset>
                <label class="form-label" for="feedbackComment">Comments (optional)</label>
                <textarea class="form-control mb-2" id="feedbackComment" name="comment" maxlength="2000" rows="3">{{ old('comment') }}</textarea>
                <button class="btn btn-primary" type="submit">Send feedback</button>
            </form>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('home') }}">Back to menu</a>
    </div></div>
</main>
</body>
</html>
