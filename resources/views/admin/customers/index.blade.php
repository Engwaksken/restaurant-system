@extends('layouts.app')

@section('title', 'Customers')
@section('page-title', 'Customers')

@section('content')
<div class="row g-4">
    @if($canManage)
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add customer</h2>
        <form method="POST" action="{{ route('admin.customers.store') }}">
            @csrf
            @include('admin.customers._fields', ['customer' => null])
            <button class="btn btn-primary" type="submit">Create customer</button>
        </form>
    </div></div>
    @endif
    <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}"><div class="panel p-4">
        <form method="GET" class="d-flex gap-2 mb-3" role="search">
            <label class="visually-hidden" for="customerSearch">Search customers</label>
            <input class="form-control" id="customerSearch" name="search" value="{{ $search }}" placeholder="Name, phone or email" maxlength="100">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Customer</th><th>Contact</th><th class="text-end">Orders</th><th class="text-end">Spent</th><th>Last order</th></tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><a href="{{ route('admin.customers.show', $customer->id) }}"><strong>{{ $customer->name }}</strong></a></td>
                    <td>{{ $customer->phone }}@if($customer->email)<small class="d-block text-muted">{{ $customer->email }}</small>@endif</td>
                    <td class="text-end">{{ $customer->orders_count }}</td>
                    <td class="text-end">{{ config('restaurant.currency') }} {{ number_format((float) $customer->total_spent, 2) }}</td>
                    <td>{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('Y-m-d') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">{{ $search !== '' ? 'No customers match your search.' : 'No customers yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $customers->links() }}
    </div></div>
</div>
@endsection
