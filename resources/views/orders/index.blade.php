@extends('layouts.app')

@section('title', $pageTitle)
@section('page-title', $pageTitle)

@section('content')
<div class="container-fluid px-0">

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fas fa-triangle-exclamation me-2"></i>
            {{ $errors->first() }}
            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- Statistics --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-primary-subtle text-primary">
                        <i class="{{ $pageIcon }}"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Total Orders</small>
                        <strong class="fs-5">{{ number_format($statistics['total']) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-warning-subtle text-warning">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Pending</small>
                        <strong class="fs-5">{{ number_format($statistics['pending']) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-info-subtle text-info">
                        <i class="fas fa-fire-burner"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Preparing</small>
                        <strong class="fs-5">{{ number_format($statistics['preparing']) }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-success-subtle text-success">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Completed</small>
                        <strong class="fs-5">{{ number_format($statistics['completed']) }}</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white border-bottom py-3">

            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">

                <form method="GET"
                      action="{{ url()->current() }}"
                      class="row g-2 flex-grow-1">

                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-magnifying-glass text-muted"></i>
                            </span>

                            <input type="search"
                                   name="search"
                                   class="form-control"
                                   value="{{ request('search') }}"
                                   placeholder="Search order number, customer or phone">
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <select name="status"
                                class="form-select">

                            <option value="">All statuses</option>

                            @foreach($statusOptions as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(request('status') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <select name="payment_status"
                                class="form-select">

                            <option value="">All payments</option>

                            @foreach($paymentStatusOptions as $value => $label)
                                <option value="{{ $value }}"
                                    @selected(request('payment_status') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-fill">
                            <i class="fas fa-filter me-1"></i>
                            Filter
                        </button>

                        @if(request()->hasAny(['search', 'status', 'payment_status']))
                            <a href="{{ url()->current() }}"
                               class="btn btn-outline-secondary"
                               title="Clear filters">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        @endif
                    </div>

                </form>

            </div>

        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle"
                       style="min-width:1100px;">

                    <thead>
                    <tr>
                        <th class="ps-3">Order</th>
                        <th>Type</th>
                        <th>Customer</th>
                        <th>Table</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Balance</th>
                        <th>Date</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($orders as $order)

                        @php
                            $orderNumber = $order->order_number
                                ?? $order->reference
                                ?? ('ORD-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT));

                            $orderType = $order->order_type
                                ?? $order->type
                                ?? '—';

                            $customerName = data_get($order, 'customer.name')
                                ?? $order->customer_name
                                ?? 'Walk-in Customer';

                            $tableName = data_get($order, 'table.name')
                                ?? data_get($order, 'table.table_number')
                                ?? $order->table_number
                                ?? '—';

                            $status = strtolower((string) ($order->status ?? 'pending'));
                            $paymentStatus = strtolower((string) ($order->payment_status ?? 'unpaid'));

                            $total = (float) (
                                $order->grand_total
                                ?? $order->total_amount
                                ?? $order->total
                                ?? 0
                            );

                            $balance = (float) (
                                $order->balance
                                ?? $order->balance_due
                                ?? 0
                            );
                        @endphp

                        <tr>

                            <td class="ps-3">
                                <div class="fw-semibold">
                                    {{ $orderNumber }}
                                </div>

                                <small class="text-muted">
                                    #{{ $order->id }}
                                </small>
                            </td>

                            <td>
                                <span class="badge text-bg-light border">
                                    @if(in_array($orderType, ['dine_in', 'dine-in', 'dine in']))
                                        <i class="fas fa-utensils me-1"></i>
                                    @elseif(in_array($orderType, ['takeaway', 'pickup']))
                                        <i class="fas fa-bag-shopping me-1"></i>
                                    @elseif($orderType === 'delivery')
                                        <i class="fas fa-motorcycle me-1"></i>
                                    @elseif(in_array($orderType, ['online', 'web']))
                                        <i class="fas fa-globe me-1"></i>
                                    @else
                                        <i class="fas fa-receipt me-1"></i>
                                    @endif

                                    {{ ucwords(str_replace(['_', '-'], ' ', $orderType)) }}
                                </span>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $customerName }}
                                </div>

                                @if($order->customer_phone)
                                    <small class="text-muted">
                                        {{ $order->customer_phone }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                {{ $tableName }}
                            </td>

                            <td>
                                @php
                                    $statusClass = match($status) {
                                        'completed', 'served', 'delivered' => 'success',
                                        'preparing', 'processing' => 'info',
                                        'ready' => 'primary',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    };
                                @endphp

                                <span class="badge text-bg-{{ $statusClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </span>
                            </td>

                            <td>
                                @php
                                    $paymentClass = match($paymentStatus) {
                                        'paid' => 'success',
                                        'partial', 'partially_paid' => 'warning',
                                        default => 'secondary',
                                    };
                                @endphp

                                <span class="badge text-bg-{{ $paymentClass }}">
                                    {{ ucfirst(str_replace('_', ' ', $paymentStatus)) }}
                                </span>
                            </td>

                            <td class="text-end fw-semibold">
                                {{ config('restaurant.currency', 'UGX') }}
                                {{ number_format($total) }}
                            </td>

                            <td class="text-end">
                                {{ config('restaurant.currency', 'UGX') }}
                                {{ number_format($balance) }}
                            </td>

                            <td>
                                {{ optional($order->created_at)->format('d M Y') }}
                                <small class="text-muted d-block">
                                    {{ optional($order->created_at)->format('H:i') }}
                                </small>
                            </td>

                            <td class="text-end pe-3">

                                <div class="d-inline-flex gap-1">

                                    @if(Route::has('admin.orders.show'))
                                        <a href="{{ route('admin.orders.show', $order) }}"
                                           class="btn btn-sm btn-outline-secondary"
                                           title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    @endif

                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#statusOrderModal{{ $order->id }}"
                                            title="Update status">
                                        <i class="fas fa-arrows-rotate"></i>
                                    </button>

                                    @if($balance > 0 && Route::has('admin.orders.payments.store'))
                                        <button type="button"
                                                class="btn btn-sm btn-outline-success"
                                                data-bs-toggle="modal"
                                                data-bs-target="#paymentOrderModal{{ $order->id }}"
                                                title="Receive payment">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                    @endif

                                    @if($status !== 'cancelled')
                                        <button type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="modal"
                                                data-bs-target="#cancelOrderModal{{ $order->id }}"
                                                title="Cancel order">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="10"
                                class="text-center py-5">

                                <i class="{{ $pageIcon }} fa-2x text-muted mb-3 d-block"></i>

                                <strong class="d-block">
                                    No orders found
                                </strong>

                                <small class="text-muted">
                                    There are no matching orders for the selected filters.
                                </small>

                            </td>
                        </tr>

                    @endforelse
                    </tbody>

                </table>

            </div>

        </div>

        @if(method_exists($orders, 'links'))
            <div class="card-footer bg-white border-top">
                {{ $orders->links() }}
            </div>
        @endif

    </div>

</div>


{{-- IMPORTANT: all modals are outside <table>/<tbody> --}}
@foreach($orders as $order)
    @include('orders.modals', [
        'order' => $order,
        'statusOptions' => $statusOptions,
    ])
@endforeach

@endsection
