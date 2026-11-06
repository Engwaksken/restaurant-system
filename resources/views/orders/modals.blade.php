@php
    $status = strtolower((string) ($order->status ?? 'pending'));

    $balance = (float) (
        $order->balance
        ?? $order->balance_due
        ?? 0
    );

    $orderNumber = $order->order_number
        ?? $order->reference
        ?? ('ORD-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT));
@endphp


{{-- =========================================================
     UPDATE STATUS
========================================================= --}}
<div class="modal fade"
     id="statusOrderModal{{ $order->id }}"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form method="POST"
              action="{{ route('admin.orders.status.update', $order) }}"
              class="modal-content">

            @csrf
            @method('PATCH')

            <div class="modal-header">

                <div>
                    <h5 class="modal-title">
                        <i class="fas fa-arrows-rotate text-primary me-2"></i>
                        Update Order Status
                    </h5>

                    <small class="text-muted">
                        {{ $orderNumber }}
                    </small>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <label class="form-label">
                    Status
                </label>

                <select name="status"
                        class="form-select"
                        required>

                    @foreach($statusOptions as $value => $label)
                        <option value="{{ $value }}"
                            @selected($status === $value)>
                            {{ $label }}
                        </option>
                    @endforeach

                </select>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-primary">
                    <i class="fas fa-floppy-disk me-1"></i>
                    Update Status
                </button>

            </div>

        </form>

    </div>

</div>


{{-- =========================================================
     RECEIVE PAYMENT
========================================================= --}}
@if($balance > 0 && Route::has('admin.orders.payments.store'))

<div class="modal fade"
     id="paymentOrderModal{{ $order->id }}"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form method="POST"
              action="{{ route('admin.orders.payments.store', $order) }}"
              class="modal-content">

            @csrf

            <div class="modal-header">

                <div>
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave text-success me-2"></i>
                        Receive Payment
                    </h5>

                    <small class="text-muted">
                        {{ $orderNumber }}
                    </small>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label">
                        Payment Method
                    </label>

                    <select name="payment_method"
                            class="form-select"
                            required>

                        <option value="cash">Cash</option>
                        <option value="mtn_mobile_money">MTN Mobile Money</option>
                        <option value="airtel_money">Airtel Money</option>
                        <option value="card">Card</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="flutterwave">Flutterwave</option>
                        <option value="pesapal">Pesapal</option>
                        <option value="manual">Manual</option>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">
                        Amount
                    </label>

                    <div class="input-group">
                        <span class="input-group-text">
                            {{ config('restaurant.currency', 'UGX') }}
                        </span>

                        <input type="number"
                               name="amount"
                               class="form-control"
                               min="1"
                               step="0.01"
                               max="{{ $balance }}"
                               value="{{ $balance }}"
                               required>
                    </div>
                </div>

                <div>
                    <label class="form-label">
                        Transaction Reference
                    </label>

                    <input type="text"
                           name="transaction_reference"
                           class="form-control"
                           maxlength="255"
                           placeholder="Optional transaction/reference number">
                </div>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-success">
                    <i class="fas fa-money-check-dollar me-1"></i>
                    Save Payment
                </button>

            </div>

        </form>

    </div>

</div>

@endif


{{-- =========================================================
     CANCEL ORDER
========================================================= --}}
@if($status !== 'cancelled')

<div class="modal fade"
     id="cancelOrderModal{{ $order->id }}"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form method="POST"
              action="{{ route('admin.orders.cancel', $order) }}"
              class="modal-content">

            @csrf
            @method('PATCH')

            <div class="modal-header border-0 pb-0">

                <h5 class="modal-title">
                    Cancel Order
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body text-center py-4">

                <div class="delete-icon-wrap bg-danger-subtle text-danger">
                    <i class="fas fa-ban fa-lg"></i>
                </div>

                <h5 class="mb-2">
                    Cancel {{ $orderNumber }}?
                </h5>

                <p class="text-muted">
                    The order will move to Cancelled Orders.
                </p>

                <div class="text-start">
                    <label class="form-label">
                        Cancellation Reason
                    </label>

                    <textarea name="cancellation_reason"
                              class="form-control"
                              rows="3"
                              maxlength="1000"
                              placeholder="Optional reason"></textarea>
                </div>

            </div>

            <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                <button type="button"
                        class="btn btn-light px-4"
                        data-bs-dismiss="modal">
                    Keep Order
                </button>

                <button type="submit"
                        class="btn btn-danger px-4">
                    <i class="fas fa-ban me-1"></i>
                    Cancel Order
                </button>

            </div>

        </form>

    </div>

</div>

@endif
