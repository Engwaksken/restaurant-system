@extends('layouts.app')

@section('title', $customer->name)
@section('page-title', 'Customer · ' . $customer->name)

@section('content')
@php $currency = config('restaurant.currency'); @endphp
<p><a href="{{ route('admin.customers.index') }}">← All customers</a></p>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="panel p-3"><small class="text-muted d-block">Orders</small><strong class="fs-4">{{ (int) $summary->orders_count }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="panel p-3"><small class="text-muted d-block">Total spent</small><strong class="fs-4">{{ $currency }} {{ number_format((float) $summary->total_spent, 2) }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="panel p-3"><small class="text-muted d-block">Outstanding</small><strong class="fs-4 {{ (float) $summary->outstanding > 0 ? 'text-danger' : '' }}">{{ $currency }} {{ number_format((float) $summary->outstanding, 2) }}</strong></div></div>
    <div class="col-6 col-md-3"><div class="panel p-3"><small class="text-muted d-block">Loyalty points</small><strong class="fs-4">{{ number_format((int) $customer->loyalty_points) }}</strong></div></div>
</div>
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Details</h2>
        @if($canManage)
            <form method="POST" action="{{ route('admin.customers.update', $customer->id) }}">
                @csrf @method('PUT')
                @include('admin.customers._fields', ['customer' => $customer])
                <button class="btn btn-primary" type="submit">Save changes</button>
            </form>
        @else
            <dl class="mb-0">
                <dt>Phone</dt><dd>{{ $customer->phone ?? '—' }}</dd>
                <dt>Email</dt><dd>{{ $customer->email ?? '—' }}</dd>
                <dt>Address</dt><dd>{{ $customer->address ?? '—' }}</dd>
                <dt>Notes</dt><dd>{{ $customer->notes ?? '—' }}</dd>
            </dl>
        @endif
        <p class="small text-muted mt-3 mb-0">Customer since {{ $customer->created_at?->format('j M Y') }}</p>
        @if($canErase)
            <details class="mt-3 border-top pt-3">
                <summary class="text-danger small">Erase personal data</summary>
                <p class="small text-muted mt-2">Removes name, contact details, notes, loyalty points, reservation guest details and feedback comments. Orders and payments are kept for accounting but no longer identify this person. This cannot be undone.</p>
                <form method="POST" action="{{ route('admin.customers.erase', $customer->id) }}">
                    @csrf @method('DELETE')
                    <label class="form-label small" for="eraseConfirm">Type ERASE to confirm</label>
                    <input class="form-control form-control-sm mb-2" id="eraseConfirm" name="confirm" required autocomplete="off">
                    <button class="btn btn-sm btn-danger" type="submit">Erase customer</button>
                </form>
            </details>
        @endif
    </div></div>
    <div class="col-lg-8">
        <div class="panel p-4 mb-4">
            <h2 class="h5">Orders</h2>
            <div class="table-responsive"><table class="table table-sm align-middle">
                <thead><tr><th>Order</th><th>Date</th><th>Branch</th><th>Status</th><th class="text-end">Total</th><th class="text-end">Balance</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ ($order->ordered_at ?? $order->created_at)?->format('Y-m-d H:i') }}</td>
                        <td>{{ $order->branch?->name }}</td>
                        <td>{{ ucfirst($order->status) }}</td>
                        <td class="text-end">{{ number_format((float) $order->total, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $order->balance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">No orders.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            {{ $orders->links() }}
        </div>
        <div class="row g-4">
            <div class="col-md-6"><div class="panel p-4 h-100">
                <h2 class="h5">Reservations</h2>
                <ul class="list-unstyled mb-0">
                @forelse($reservations as $reservation)
                    <li class="mb-2">{{ $reservation->reserved_at->format('Y-m-d H:i') }} · {{ $reservation->guest_count }} guests
                        <small class="d-block text-muted">{{ \Illuminate\Support\Str::headline($reservation->status) }} · {{ $reservation->table?->name ?? 'No table' }} · {{ $reservation->branch?->name }}</small></li>
                @empty
                    <li class="text-muted">No reservations.</li>
                @endforelse
                </ul>
            </div></div>
            <div class="col-md-6"><div class="panel p-4 h-100">
                <h2 class="h5">Feedback</h2>
                <ul class="list-unstyled mb-0">
                @forelse($feedback as $entry)
                    <li class="mb-2"><strong aria-label="{{ $entry->rating }} out of 5">@for($star = 1; $star <= 5; $star++)<i class="{{ $star <= $entry->rating ? 'fas' : 'far' }} fa-star"></i>@endfor</strong>
                        <small class="text-muted">{{ $entry->order?->order_number }} · {{ $entry->created_at->format('Y-m-d') }}</small>
                        @if($entry->comment)<span class="d-block">{{ $entry->comment }}</span>@endif</li>
                @empty
                    <li class="text-muted">No feedback.</li>
                @endforelse
                </ul>
            </div></div>
        </div>
    </div>
</div>
@endsection
