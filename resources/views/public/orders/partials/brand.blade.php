<header class="brand-bar">
    <div class="container d-flex align-items-center justify-content-between py-3">
        <a class="brand-link" href="{{ route('home') }}">
            @if(!empty($order->branch?->restaurant?->logo_path))
                <img src="{{ asset('storage/' . $order->branch->restaurant->logo_path) }}" alt="" height="34" class="rounded">
            @else
                <i class="fas fa-utensils"></i>
            @endif
            <span>{{ $order->branch?->restaurant?->name ?? 'Restaurant' }}</span>
        </a>
        <a class="btn btn-sm btn-outline-light" href="{{ route('home') }}">
            <i class="fas fa-arrow-left me-1"></i>Back to menu
        </a>
    </div>
</header>
