@extends('layouts.app')

@section('title', 'Loyalty')
@section('page-title', 'Loyalty Programme')

@section('content')
@php $currency = config('restaurant.currency'); @endphp
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel p-3 h-100">
        <small class="text-muted d-block">Status</small>
        @if($enabled)<strong class="text-success">Enabled</strong>@else<strong class="text-secondary">Disabled</strong><small class="d-block text-muted">Set <code>LOYALTY_ENABLED=true</code> once the rates below are approved.</small>@endif
    </div></div>
    <div class="col-md-4"><div class="panel p-3 h-100 small">
        <span class="d-block">Earn 1 point per <strong>{{ $currency }} {{ number_format((float) $settings['earn_amount'], 2) }}</strong> net spend on completed orders.</span>
        <span class="d-block">1 point = <strong>{{ $currency }} {{ number_format((float) $settings['point_value'], 2) }}</strong>; minimum {{ $settings['min_redeem_points'] }} points; up to {{ rtrim(rtrim(number_format((float) $settings['max_redeem_percent'], 2), '0'), '.') }}% of an order.</span>
    </div></div>
    <div class="col-md-4"><div class="panel p-3 h-100">
        <small class="text-muted d-block">Points outstanding</small>
        <strong class="fs-4">{{ number_format($outstanding) }}</strong>
        <small class="d-block text-muted">≈ {{ $currency }} {{ number_format($outstanding * (float) $settings['point_value'], 2) }} liability</small>
    </div></div>
</div>
<div class="row g-4">
    <div class="col-lg-5">
        @if($canAdjust)
        <div class="panel p-4 mb-4">
            <h2 class="h5">Adjust points</h2>
            <form method="POST" action="{{ route('admin.loyalty.adjust') }}">
                @csrf
                <label class="form-label" for="loyaltyCustomer">Customer</label>
                <select class="form-select mb-2" id="loyaltyCustomer" name="customer_id" required>@foreach($allCustomers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }} ({{ $customer->loyalty_points }} pts)</option>@endforeach</select>
                <label class="form-label" for="loyaltyPoints">Points (+ to add, − to remove)</label>
                <input class="form-control mb-2" id="loyaltyPoints" type="number" name="points" step="1" required>
                <label class="form-label" for="loyaltyNote">Reason</label>
                <input class="form-control mb-3" id="loyaltyNote" name="note" minlength="5" maxlength="255" required>
                <button class="btn btn-primary" type="submit">Adjust</button>
            </form>
        </div>
        @endif
        <div class="panel p-4">
            <h2 class="h5">Top balances</h2>
            <ul class="list-group list-group-flush">
                @forelse($customers as $customer)
                    <li class="list-group-item px-0 d-flex justify-content-between"><a href="{{ route('admin.customers.show', $customer->id) }}">{{ $customer->name }}</a><strong>{{ number_format($customer->loyalty_points) }}</strong></li>
                @empty
                    <li class="list-group-item px-0 text-muted">No customer holds points yet.</li>
                @endforelse
            </ul>
            {{ $customers->links() }}
        </div>
    </div>
    <div class="col-lg-7"><div class="panel p-4">
        <h2 class="h5">Points ledger</h2>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th>When</th><th>Customer</th><th>Type</th><th class="text-end">Points</th><th class="text-end">Balance</th><th>Note</th></tr></thead>
            <tbody>
            @forelse($ledger as $entry)
                <tr>
                    <td class="text-nowrap">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                    <td>{{ $entry->customer?->name }}</td>
                    <td>{{ ucfirst($entry->type) }}</td>
                    <td class="text-end {{ $entry->points < 0 ? 'text-danger' : 'text-success' }}">{{ $entry->points > 0 ? '+' : '' }}{{ $entry->points }}</td>
                    <td class="text-end">{{ $entry->balance_after }}</td>
                    <td class="small">{{ $entry->note }}@if($entry->user)<span class="d-block text-muted">{{ $entry->user->name }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No loyalty activity yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $ledger->links() }}
    </div></div>
</div>
@endsection
