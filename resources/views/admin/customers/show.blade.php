@extends('layouts.app')

@section('title', $customer->name)
@section('page-title', 'Customer · ' . $customer->name)
@section('own-validation-errors', '1')

@section('content')
@php $currency = config('restaurant.currency'); @endphp
<p class="mb-3"><a href="{{ route('admin.customers.index') }}" class="btn btn-sm btn-light"><i class="fas fa-arrow-left me-1"></i>All customers</a></p>
<x-admin.stats :items="[
    ['label' => 'Orders', 'value' => number_format((int) $summary->orders_count), 'icon' => 'fas fa-receipt', 'tone' => 'info'],
    ['label' => 'Total spent', 'value' => $currency . ' ' . number_format((float) $summary->total_spent, 2), 'icon' => 'fas fa-sack-dollar', 'tone' => 'success'],
    ['label' => 'Outstanding', 'value' => $currency . ' ' . number_format((float) $summary->outstanding, 2), 'icon' => 'fas fa-file-invoice-dollar', 'tone' => (float) $summary->outstanding > 0 ? 'danger' : 'neutral'],
    ['label' => 'Loyalty points', 'value' => number_format((int) $customer->loyalty_points), 'icon' => 'fas fa-star', 'tone' => 'warning'],
]" />
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h5 mb-0">Details</h2>
            @if($canManage)
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-customer-{{ $customer->id }}" title="Edit">
                    <i class="fas fa-pen-to-square me-1"></i> Edit
                </button>
            @endif
        </div>
        <dl class="mb-0">
            <dt>Phone</dt><dd>{{ $customer->phone ?? '—' }}</dd>
            <dt>Email</dt><dd>{{ $customer->email ?? '—' }}</dd>
            <dt>Address</dt><dd>{{ $customer->address ?? '—' }}</dd>
            <dt>Notes</dt><dd>{{ $customer->notes ?? '—' }}</dd>
        </dl>
        <p class="small text-muted mt-3 mb-0">Customer since {{ $customer->created_at?->format('j M Y') }}</p>
        @if($canErase)
            <div class="mt-3 border-top pt-3">
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-customer-{{ $customer->id }}" title="Erase personal data">
                    <i class="fas fa-trash me-1"></i> Erase personal data
                </button>
            </div>
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

@if($canManage)
    {{-- =========================================================
         EDIT CUSTOMER MODAL
         OUTSIDE THE TABLE
    ========================================================= --}}
    <div class="modal fade"
         id="edit-customer-{{ $customer->id }}"
         tabindex="-1"
         aria-labelledby="edit-customer-{{ $customer->id }}Label"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.customers.update', $customer->id) }}">

                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title"
                            id="edit-customer-{{ $customer->id }}Label">
                            <i class="fas fa-pen-to-square me-2 text-primary"></i>
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
@endif

@if($canErase)
    {{-- =========================================================
         ERASE CONFIRMATION
    ========================================================= --}}
    <div class="modal fade"
         id="delete-customer-{{ $customer->id }}"
         tabindex="-1"
         aria-labelledby="delete-customer-{{ $customer->id }}Label"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.customers.erase', $customer->id) }}">

                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title"
                        id="delete-customer-{{ $customer->id }}Label">
                        <i class="fas fa-trash me-2 text-danger"></i>
                        Erase personal data
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="small text-muted">
                        Removes name, contact details, notes, loyalty points, reservation guest details and feedback comments. Orders and payments are kept for accounting but no longer identify this person. This cannot be undone.
                    </p>

                    <label class="form-label"
                           for="eraseConfirm{{ $customer->id }}">
                        Type ERASE to confirm <span class="text-danger" aria-hidden="true">*</span>
                    </label>

                    <input class="form-control @error('confirm') is-invalid @enderror"
                           id="eraseConfirm{{ $customer->id }}"
                           name="confirm"
                           placeholder="ERASE"
                           required
                           autocomplete="off">

                    @error('confirm')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Erase customer
                    </button>
                </div>

            </form>

        </div>
    </div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.bootstrap) {
        return;
    }
    @if($canManage && $errors->hasAny(['name', 'phone', 'email', 'date_of_birth', 'address', 'notes']))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('edit-customer-{{ $customer->id }}')).show();
    @endif
    @if($canErase && $errors->has('confirm'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('delete-customer-{{ $customer->id }}')).show();
    @endif
});
</script>
@endpush
@endsection
