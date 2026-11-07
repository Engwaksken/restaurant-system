@php($brand = \App\Support\Brand::for($order->branch?->restaurant))
<!doctype html>
<html lang="en">
<head>
    @include('public.orders.partials.styles')
    <meta name="referrer" content="no-referrer">
    <title>Track order · {{ $brand->name() }}</title>
</head>
<body class="order-hero">

@include('public.orders.partials.brand')

<main class="container py-4 py-md-5" style="max-width: 920px;">

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-warning">{{ session('error') }}</div>@endif

    <section class="card order-card p-4 p-md-5 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <div class="small text-muted">Order number</div>
                <h1 class="h4 fw-bold mb-1">{{ $order->order_number }}</h1>
                <div class="small text-muted">
                    {{ $order->branch?->name ?? 'Restaurant' }}
                    · Last updated {{ $order->updated_at?->format('M j, Y g:i A') }}
                </div>
            </div>
            <span class="badge rounded-pill fs-6 px-3 py-2 {{ $order->status === 'cancelled' ? 'text-bg-danger' : 'text-bg-primary' }}">
                {{ ucfirst($order->status) }}
            </span>
        </div>

        @if($order->status === 'cancelled')
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-0">
                <i class="fas fa-circle-xmark"></i>
                <span>This order has been cancelled. Please contact the restaurant if you have questions.</span>
            </div>
        @else
            @include('public.orders.partials.timeline')
        @endif
    </section>

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
            </section>
        </div>

        <div class="col-lg-5">
            <section class="card order-card p-4 mb-4">
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Total</span>
                    <span class="fw-semibold">{{ $brand->currency() }} {{ number_format((float) $order->total, 2) }}</span>
                </div>
                <div class="d-flex justify-content-between small mb-2">
                    <span class="text-muted">Amount due</span>
                    <span class="fw-semibold {{ (float) $order->balance > 0 ? 'text-danger' : 'text-success' }}">
                        {{ $brand->currency() }} {{ number_format((float) $order->balance, 2) }}
                    </span>
                </div>
                @if($order->payment_preference)
                    <div class="d-flex justify-content-between small mb-2">
                        <span class="text-muted">Payment method</span>
                        <span>{{ $paymentLabel }}</span>
                    </div>
                @endif

                @include('public.orders.partials.pay-online')
            </section>

            @if($order->feedback)
                <section class="card order-card p-4">
                    <p class="mb-0">Thank you — you rated this order {{ $order->feedback->rating }}/5.</p>
                </section>
            @elseif(in_array($order->status, \App\Http\Controllers\Public\PublicOrderFeedbackController::RATEABLE_STATUSES, true))
                <section class="card order-card p-4">
                    <h2 class="h6 fw-bold mb-3">How was your order?</h2>
                    <form method="POST" action="{{ route('order.feedback', $trackingToken) }}">
                        @csrf
                        @if($errors->any())<div class="alert alert-warning small">{{ $errors->first() }}</div>@endif
                        <fieldset class="mb-3">
                            <legend class="form-label fs-6">Rating <span class="text-danger" aria-hidden="true">*</span></legend>
                            @for($score = 5; $score >= 1; $score--)
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="rating" id="rating{{ $score }}" value="{{ $score }}" required @checked((int) old('rating') === $score)>
                                    <label class="form-check-label" for="rating{{ $score }}">{{ $score }} <i class="fas fa-star"></i></label>
                                </div>
                            @endfor
                        </fieldset>
                        <label class="form-label" for="feedbackComment">Comments <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea class="form-control form-control-sm mb-3" id="feedbackComment" name="comment" maxlength="2000" rows="3" placeholder="Tell us what you enjoyed or what we could do better">{{ old('comment') }}</textarea>
                        <button class="btn btn-primary w-100" type="submit">Send feedback</button>
                    </form>
                </section>
            @endif
        </div>

    </div>

</main>

</body>
</html>
