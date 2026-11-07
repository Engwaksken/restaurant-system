@extends('layouts.app')

@section('title', 'Customers')
@section('page-title', 'Customers')
@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
<div class="row g-4">
    <div class="col-12"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 mb-0">Customers</h2>
            @if($canManage)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-customer">
                    <i class="fas fa-plus me-1"></i> Add customer
                </button>
            @endif
        </div>
        <form method="GET" class="d-flex gap-2 mb-3" role="search">
            <label class="visually-hidden" for="customerSearch">Search customers</label>
            <input class="form-control" id="customerSearch" name="search" value="{{ $search }}" placeholder="Name, phone or email" maxlength="100">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Customer</th><th>Contact</th><th class="text-end">Orders</th><th class="text-end">Spent</th><th>Last order</th>@if($canManage)<th class="text-end"><span class="visually-hidden">Actions</span></th>@endif</tr></thead>
            <tbody>
            @forelse($customers as $customer)
                <tr>
                    <td><a href="{{ route('admin.customers.show', $customer->id) }}"><strong>{{ $customer->name }}</strong></a></td>
                    <td>{{ $customer->phone }}@if($customer->email)<small class="d-block text-muted">{{ $customer->email }}</small>@endif</td>
                    <td class="text-end">{{ $customer->orders_count }}</td>
                    <td class="text-end">{{ config('restaurant.currency') }} {{ number_format((float) $customer->total_spent, 2) }}</td>
                    <td>{{ $customer->last_order_at ? \Illuminate\Support\Carbon::parse($customer->last_order_at)->format('Y-m-d') : '—' }}</td>
                    @if($canManage)
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#edit-customer-list-{{ $customer->id }}" title="Edit {{ $customer->name }}" aria-label="Edit {{ $customer->name }}">
                                <i class="fas fa-pen me-1"></i> Edit
                            </button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 6 : 5 }}" class="text-muted">{{ $search !== '' ? 'No customers match your search.' : 'No customers yet.' }}</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $customers->links() }}
    </div></div>
</div>

@if($canManage)
    {{-- =========================================================
         ADD CUSTOMER MODAL
         OUTSIDE THE TABLE
    ========================================================= --}}
    <div class="modal fade"
         id="add-customer"
         tabindex="-1"
         aria-labelledby="add-customerLabel"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.customers.store') }}">

                @csrf

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title"
                            id="add-customerLabel">
                            <i class="fas fa-user-plus me-2 text-primary"></i>
                            Add customer
                        </h5>
                        <small class="text-muted">
                            Create a customer record for orders and reservations.
                        </small>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @include('admin.customers._fields', ['customer' => null])
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Create customer
                    </button>
                </div>

            </form>

        </div>
    </div>

    {{-- =========================================================
         EDIT CUSTOMER MODALS (ONE PER ROW)
         OUTSIDE THE TABLE
    ========================================================= --}}
    @foreach($customers as $customer)
        <div class="modal fade"
             id="edit-customer-list-{{ $customer->id }}"
             tabindex="-1"
             aria-labelledby="edit-customer-list-{{ $customer->id }}Label"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.customers.update', $customer->id) }}">

                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_modal" value="edit-customer-list-{{ $customer->id }}">

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"
                                id="edit-customer-list-{{ $customer->id }}Label">
                                <i class="fas fa-pen me-2 text-primary"></i>
                                Edit customer
                            </h5>
                            <small class="text-muted">
                                {{ $customer->name }}
                            </small>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @include('admin.customers._fields', ['customer' => $customer])
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save changes
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endforeach

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.bootstrap) {
            return;
        }
        @if($errors->any() && old('_edit_modal'))
            var editModal = document.getElementById(@js(old('_edit_modal')));
            if (editModal) {
                bootstrap.Modal.getOrCreateInstance(editModal).show();
            }
        @elseif($errors->hasAny(['name', 'phone', 'email', 'date_of_birth', 'address', 'notes']))
            bootstrap.Modal.getOrCreateInstance(document.getElementById('add-customer')).show();
        @endif
    });
    </script>
    @endpush
@endif
@endsection
