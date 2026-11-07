@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
@php
    $currency = $brand->currency();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->explode(' ')->first();
    $trendMax = max(1, (float) $trend->max('total'));
    $mixTotal = max(1, (int) $orderMix->sum());
    $mixMeta = [
        'dine_in' => ['Dine-in', 'fas fa-utensils', '#e8590c'],
        'takeaway' => ['Takeaway', 'fas fa-bag-shopping', '#0284c7'],
        'delivery' => ['Delivery', 'fas fa-motorcycle', '#7c3aed'],
        'online' => ['Online', 'fas fa-globe', '#059669'],
    ];
    $statusTone = [
        'pending' => 'warning', 'confirmed' => 'info', 'preparing' => 'info', 'ready' => 'primary',
        'served' => 'primary', 'completed' => 'success', 'cancelled' => 'danger',
    ];
    $change = $insights['sales_change'];
@endphp

<div class="welcome-banner mb-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2>{{ $greeting }}{{ $firstName ? ', ' . $firstName : '' }} 👋</h2>
            <p>Here is what is happening at {{ $brand->name() }} today.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('admin.pos.index') }}"><i class="fas fa-cash-register me-2"></i>Open POS</a>
            <a class="btn btn-light" href="{{ url('/admin/kitchen') }}"><i class="fas fa-fire-burner me-2"></i>Kitchen</a>
        </div>
    </div>
</div>

<x-admin.stats :items="[
    [
        'label' => 'Today\'s sales', 'icon' => 'fas fa-sack-dollar', 'tone' => 'brand',
        'value' => $currency . ' ' . number_format($stats['sales']),
        'hint' => $change === null ? 'Excludes cancelled orders' : (($change >= 0 ? '▲ ' : '▼ ') . abs($change) . '% vs yesterday'),
        'href' => route('admin.orders.index'),
    ],
    [
        'label' => 'Orders today', 'icon' => 'fas fa-receipt', 'tone' => 'info',
        'value' => number_format($stats['orders']),
        'hint' => 'Avg. ' . $currency . ' ' . number_format($insights['average_order']) . ' per order',
        'href' => route('admin.orders.index'),
    ],
    [
        'label' => 'Net takings', 'icon' => 'fas fa-wallet', 'tone' => 'success',
        'value' => $currency . ' ' . number_format($stats['payments']),
        'hint' => 'Payments less refunds today',
        'href' => url('/admin/payments'),
    ],
    [
        'label' => 'Open orders', 'icon' => 'fas fa-hourglass-half', 'tone' => 'warning',
        'value' => number_format($stats['pending']),
        'hint' => 'Pending, confirmed or preparing',
        'href' => url('/admin/pos/current'),
    ],
]" />

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <x-admin.stat label="Available menu items" :value="number_format($stats['menu_items'])" icon="fas fa-burger" tone="violet" :href="route('admin.menu-items.index')" />
    </div>
    <div class="col-6 col-lg-4">
        <x-admin.stat label="Reservations today" :value="number_format($insights['reservations_today'])" icon="fas fa-calendar-check" tone="neutral" :href="url('/admin/reservations')" />
    </div>
    <div class="col-12 col-lg-4">
        <x-admin.stat label="Low-stock items" :value="number_format($insights['low_stock'])" icon="fas fa-triangle-exclamation" :tone="$insights['low_stock'] > 0 ? 'danger' : 'success'" :hint="$insights['low_stock'] > 0 ? 'Reorder soon' : 'Stock levels look healthy'" :href="url('/admin/inventory/low-stock')" />
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h5 class="mb-1">Sales — last 7 days</h5>
                    <p class="text-muted small mb-0">Order value excluding cancelled orders</p>
                </div>
                <span class="badge bg-primary">{{ $currency }} {{ number_format($trend->sum('total')) }}</span>
            </div>
            <div class="bar-chart" role="img" aria-label="Sales for the last seven days">
                @foreach($trend as $day)
                    <div class="bar-col" title="{{ $day['date'] }}: {{ $currency }} {{ number_format($day['total']) }}">
                        <span class="bar-value">{{ $day['total'] > 0 ? number_format($day['total'] / 1000, $day['total'] >= 10000 ? 0 : 1) . 'k' : '' }}</span>
                        <div class="bar" style="height: {{ max(2, round($day['total'] / $trendMax * 100)) }}%"></div>
                        <span class="bar-label">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel p-4 h-100">
            <h5 class="mb-1">Order mix</h5>
            <p class="text-muted small mb-3">Last 30 days by order type</p>
            @foreach($mixMeta as $type => [$label, $icon, $color])
                @php($count = (int) ($orderMix[$type] ?? 0))
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span><i class="{{ $icon }} me-2" style="color: {{ $color }}"></i>{{ $label }}</span>
                        <span class="fw-semibold">{{ number_format($count) }} <span class="text-muted fw-normal">· {{ round($count / $mixTotal * 100) }}%</span></span>
                    </div>
                    <div class="progress" style="height: 7px;">
                        <div class="progress-bar" style="width: {{ round($count / $mixTotal * 100) }}%; background: {{ $color }}"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="col-xl-8">
        <div class="panel p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Recent orders</h5>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-sm btn-outline-secondary">View all</a>
            </div>
            @if($recentOrders->isEmpty())
                <x-admin.empty icon="fas fa-receipt" title="No orders yet" message="New orders from the POS and website will appear here." />
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Order</th><th>Customer</th><th>Type</th><th>Status</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @foreach($recentOrders as $order)
                            <tr>
                                <td><a class="fw-semibold" href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a><small class="d-block text-muted">{{ $order->ordered_at?->diffForHumans() }}</small></td>
                                <td>{{ $order->customer?->name ?? 'Walk-in' }}</td>
                                <td>{{ \Illuminate\Support\Str::headline($order->order_type) }}</td>
                                <td><span class="badge bg-{{ $statusTone[$order->status] ?? 'secondary' }}">{{ ucfirst($order->status) }}</span></td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format((float) $order->total) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-xl-4">
        <div class="panel p-4 h-100">
            <h5 class="mb-1">Top sellers</h5>
            <p class="text-muted small mb-2">Last 30 days by quantity</p>
            @forelse($topItems as $index => $item)
                <div class="list-row">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="stat-icon tone-brand" style="width:34px;height:34px;flex-basis:34px;font-size:13px;font-weight:800">{{ $index + 1 }}</span>
                        <div class="text-truncate">
                            <div class="fw-semibold text-truncate">{{ $item->item_name }}</div>
                            <small class="text-muted">{{ number_format((float) $item->quantity) }} sold</small>
                        </div>
                    </div>
                    <span class="fw-semibold small text-nowrap">{{ $currency }} {{ number_format((float) $item->revenue) }}</span>
                </div>
            @empty
                <x-admin.empty icon="fas fa-fire" title="No sales yet" message="Best-selling dishes will show here." />
            @endforelse
        </div>
    </div>

    <div class="col-12">
        <div class="row g-3">
            @foreach([
                ['url' => route('admin.pos.index'), 'icon' => 'fas fa-cash-register', 'title' => 'New sale', 'text' => 'Take an order at the counter'],
                ['url' => route('admin.menu-items.index'), 'icon' => 'fas fa-burger', 'title' => 'Menu items', 'text' => 'Update dishes and prices'],
                ['url' => url('/admin/reservations'), 'icon' => 'fas fa-calendar-check', 'title' => 'Reservations', 'text' => 'Book and seat guests'],
                ['url' => url('/admin/settings/restaurant'), 'icon' => 'fas fa-palette', 'title' => 'Branding', 'text' => 'Logo, colours and website'],
            ] as $action)
                <div class="col-6 col-lg-3">
                    <a class="quick-action" href="{{ $action['url'] }}">
                        <i class="{{ $action['icon'] }}"></i>
                        <span>{{ $action['title'] }}<small class="d-block">{{ $action['text'] }}</small></span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
