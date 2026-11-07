@extends('layouts.app')

@section('title', 'Loyalty')
@section('page-title', 'Loyalty Programme')
@section('own-validation-errors', '1')

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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h5 mb-0">Manual adjustments</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#adjust-points">
                    <i class="fas fa-plus-minus me-1"></i> Adjust points
                </button>
            </div>
            <p class="small text-muted mb-0">Add or remove points for a customer. A reason is always recorded in the ledger.</p>
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

@if($canAdjust)
    {{-- =========================================================
         ADJUST POINTS MODAL
         OUTSIDE THE TABLE
    ========================================================= --}}
    <div class="modal fade"
         id="adjust-points"
         tabindex="-1"
         aria-labelledby="adjust-pointsLabel"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.loyalty.adjust') }}">

                @csrf

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title"
                            id="adjust-pointsLabel">
                            <i class="fas fa-plus-minus me-2 text-primary"></i>
                            Adjust points
                        </h5>
                        <small class="text-muted">
                            Use a positive number to add points and a negative number to remove them.
                        </small>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label"
                               for="loyaltyCustomer">
                            Customer <span class="text-danger" aria-hidden="true">*</span>
                        </label>

                        <select class="form-select @error('customer_id') is-invalid @enderror"
                                id="loyaltyCustomer"
                                name="customer_id"
                                required>
                            @foreach($allCustomers as $customer)
                                <option value="{{ $customer->id }}"
                                    @selected((string) old('customer_id') === (string) $customer->id)>
                                    {{ $customer->name }} · {{ $customer->phone }} ({{ $customer->loyalty_points }} pts)
                                </option>
                            @endforeach
                        </select>

                        @error('customer_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label"
                               for="loyaltyPoints">
                            Points (+ to add, − to remove) <span class="text-danger" aria-hidden="true">*</span>
                        </label>

                        <input class="form-control @error('points') is-invalid @enderror"
                               id="loyaltyPoints"
                               type="number"
                               name="points"
                               step="1"
                               value="{{ old('points') }}"
                               placeholder="e.g. 50 or -20"
                               required>

                        @error('points')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label"
                               for="loyaltyNote">
                            Reason <span class="text-danger" aria-hidden="true">*</span>
                        </label>

                        <input class="form-control @error('note') is-invalid @enderror"
                               id="loyaltyNote"
                               name="note"
                               value="{{ old('note') }}"
                               minlength="5"
                               maxlength="255"
                               placeholder="e.g. Birthday bonus"
                               required>

                        @error('note')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <small class="text-muted">At least 5 characters.</small>
                    </div>

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
                        Adjust
                    </button>
                </div>

            </form>

        </div>
    </div>

    @if($errors->hasAny(['customer_id', 'points', 'note']))
        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.bootstrap) {
                bootstrap.Modal.getOrCreateInstance(document.getElementById('adjust-points')).show();
            }
        });
        </script>
        @endpush
    @endif
@endif
@endsection
