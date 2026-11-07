@php
    $placedAt = $order->ordered_at ?? $order->created_at;
    $minutes = $placedAt ? (int) $placedAt->diffInMinutes(now()) : null;
    $late = $minutes !== null && $minutes >= (int) config('restaurant.kitchen_late_minutes', 20) && in_array($order->status, ['pending', 'confirmed', 'preparing'], true);
    $next = $transitions[$order->status] ?? null;
@endphp
<article class="card mb-3 {{ $late ? 'border-danger' : '' }}" aria-label="Order {{ $order->order_number }}">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>{{ $order->order_number }}</strong>
        <span class="badge bg-secondary">{{ \Illuminate\Support\Str::headline((string) $order->order_type) }}</span>
    </div>
    <div class="card-body py-2">
        <ul class="list-unstyled mb-2">
            @foreach($order->items as $item)
                <li class="{{ $item->kitchen_status === 'cancelled' ? 'text-decoration-line-through text-muted' : '' }}">
                    <strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} ×</strong> {{ $item->item_name }}
                    @if($item->notes)<small class="d-block text-warning-emphasis">{{ $item->notes }}</small>@endif
                </li>
            @endforeach
        </ul>
        @if($order->notes)<p class="small mb-2"><i class="fas fa-note-sticky" aria-hidden="true"></i> {{ $order->notes }}</p>@endif
        <p class="small mb-0 {{ $late ? 'text-danger fw-bold' : 'text-muted' }}">
            @if($minutes !== null)
                <time datetime="{{ $placedAt->toIso8601String() }}">{{ $placedAt->format('H:i') }}</time> · {{ $minutes }} min ago
            @endif
            @if($order->status === 'confirmed')· confirmed @endif
        </p>
    </div>
    @if($canManage && $next)
        <div class="card-footer">
            <form method="POST" action="{{ route('admin.kitchen.advance', $order) }}" data-kitchen-action>
                @csrf
                <input type="hidden" name="from" value="{{ $order->status }}">
                <input type="hidden" name="to" value="{{ $next }}">
                <button type="submit" class="btn btn-sm w-100 {{ $next === 'ready' ? 'btn-success' : 'btn-primary' }}">
                    {{ $next === 'ready' ? 'Mark ready' : 'Start preparing' }}
                </button>
            </form>
        </div>
    @endif
</article>
