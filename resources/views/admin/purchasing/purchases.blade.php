@extends('layouts.app')

@section('title', 'Received Purchases')
@section('page-title', 'Purchases & Stock Receipts')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $canReceive = ! ($branches->isEmpty() || $inventoryItems->isEmpty());
    $receiptRows = old('items', [[]]);
    $failedFields = collect($errors->keys())->map(fn ($key) => explode('.', $key)[0])->unique();
    $receiveFailed = $failedFields->intersect(['branch_id', 'supplier_id', 'invoice_number', 'reference', 'items', 'purchase_order_id'])->isNotEmpty();
    $paymentFailed = $failedFields->intersect(['payment_method', 'amount'])->isNotEmpty();
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! ($receiveFailed || $paymentFailed))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex gap-2">
                <i class="fas fa-triangle-exclamation mt-1"></i>
                <div>
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="panel p-4 mb-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h2 class="h5 mb-1">Received stock</h2>
            <p class="text-muted small mb-0">Record goods received from suppliers and track what is still owed on each receipt.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.purchase-orders.index') }}">
                <i class="fas fa-file-invoice me-1"></i>
                Purchase orders
            </a>

            @if($canReceive)
                <button type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#add-receipt">
                    <i class="fas fa-plus me-1"></i>
                    Record received stock
                </button>
            @else
                <span class="text-muted small align-self-center">An active branch and inventory item are required before receiving stock.</span>
            @endif
        </div>
    </div>

    <div class="panel p-4">
        <h2 class="h5">Receipt history</h2>
        @forelse($purchases as $purchase)
            @php($paid = (float) $purchase->supplierPayments->sum('amount'))
            <details class="border-bottom py-3"><summary class="d-flex justify-content-between gap-2"><span>{{ $purchase->received_at->format('Y-m-d H:i') }} · {{ $purchase->branch?->name }} · {{ $purchase->supplier?->name ?? 'Supplier not specified' }}</span><strong>{{ config('restaurant.currency') }} {{ number_format((float) $purchase->total, 2) }}</strong></summary>
                <div class="pt-2"><p class="mb-1">Invoice: {{ $purchase->invoice_number ?? '-' }} · Reference: {{ $purchase->reference ?? '-' }}</p>
                    <ul class="mb-2">@foreach($purchase->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }} × {{ number_format((float) $line->unit_cost, 2) }} = {{ number_format((float) $line->line_total, 2) }}</li>@endforeach</ul>
                    <p class="mb-2">Paid {{ config('restaurant.currency') }} {{ number_format($paid, 2) }} · Due {{ config('restaurant.currency') }} {{ number_format(max(0, (float) $purchase->total - $paid), 2) }}</p>
                    @if($purchase->supplierPayments->isNotEmpty())<ul class="small">@foreach($purchase->supplierPayments as $payment)<li>{{ $payment->paid_at->format('Y-m-d H:i') }} · {{ $payment->receipt_number }} · {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }} · {{ config('restaurant.currency') }} {{ number_format((float) $payment->amount, 2) }}{{ $payment->reference ? ' · ' . $payment->reference : '' }}</li>@endforeach</ul>@endif
                    @if((float) $purchase->total > $paid)
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#add-payment-{{ $purchase->id }}">
                            <i class="fas fa-plus me-1"></i>
                            Record payment
                        </button>
                    @endif
                </div>
            </details>
        @empty<p class="text-muted">No received purchases yet.</p>@endforelse
        {{ $purchases->links() }}
    </div>

</div>


@if($canReceive)
{{-- =========================================================
     RECORD RECEIVED STOCK MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-receipt"
     tabindex="-1"
     aria-labelledby="add-receipt-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.purchases.receive') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-receipt-label">
                    <i class="fas fa-box-open me-2 text-primary"></i>
                    Record Received Stock
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($receiveFailed)
                    <div class="alert alert-danger py-2" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label" for="purchaseBranch">
                            Branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('branch_id') is-invalid @enderror"
                                id="purchaseBranch"
                                name="branch_id"
                                required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    @selected((string) old('branch_id') === (string) $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="purchaseSupplier">Supplier (optional)</label>
                        <select class="form-select @error('supplier_id') is-invalid @enderror"
                                id="purchaseSupplier"
                                name="supplier_id">
                            <option value="">Unspecified</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}"
                                    @selected((string) old('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="purchaseInvoice">Invoice number</label>
                        <input class="form-control @error('invoice_number') is-invalid @enderror"
                               id="purchaseInvoice"
                               name="invoice_number"
                               value="{{ old('invoice_number') }}"
                               placeholder="e.g. INV-88421"
                               maxlength="255">
                        @error('invoice_number')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="purchaseReference">Reference (optional)</label>
                        <input class="form-control @error('reference') is-invalid @enderror"
                               id="purchaseReference"
                               name="reference"
                               value="{{ old('reference') }}"
                               placeholder="e.g. Delivery note 0412"
                               maxlength="255">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <h3 class="h6 mb-2">Received items</h3>

                        @error('items')
                            <div class="alert alert-danger py-2 mb-2">{{ $message }}</div>
                        @enderror

                        <div id="purchaseItems">
                            @foreach($receiptRows as $i => $row)
                                <div class="purchase-row border rounded p-3 mb-2">
                                    <div class="row g-2">

                                        <div class="col-12">
                                            <label class="form-label">
                                                Inventory item <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <select class="form-select @error("items.$i.inventory_item_id") is-invalid @enderror"
                                                    name="items[{{ $i }}][inventory_item_id]"
                                                    required>
                                                @foreach($inventoryItems as $item)
                                                    <option value="{{ $item->id }}"
                                                        @selected((string) ($row['inventory_item_id'] ?? '') === (string) $item->id)>
                                                        {{ $item->name }} ({{ $item->usage_unit }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error("items.$i.inventory_item_id")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-sm-6">
                                            <label class="form-label">
                                                Quantity <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <input class="form-control @error("items.$i.quantity") is-invalid @enderror"
                                                   type="number"
                                                   name="items[{{ $i }}][quantity]"
                                                   value="{{ $row['quantity'] ?? '' }}"
                                                   min="0.001"
                                                   step="0.001"
                                                   placeholder="e.g. 12"
                                                   required>
                                            @error("items.$i.quantity")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="col-sm-6">
                                            <label class="form-label">
                                                Unit cost <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <input class="form-control @error("items.$i.unit_cost") is-invalid @enderror"
                                                   type="number"
                                                   name="items[{{ $i }}][unit_cost]"
                                                   value="{{ $row['unit_cost'] ?? '' }}"
                                                   min="0"
                                                   step="0.01"
                                                   placeholder="e.g. 4500"
                                                   required>
                                            @error("items.$i.unit_cost")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button class="btn btn-outline-secondary btn-sm" type="button" id="addPurchaseItem">
                            <i class="fas fa-plus me-1"></i>
                            Add item
                        </button>
                    </div>

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
                    Receive Stock
                </button>

            </div>

        </form>

    </div>
</div>
@endif


{{-- =========================================================
     RECORD PAYMENT MODALS
     One per purchase with an outstanding balance.
     OUTSIDE THE LIST
========================================================= --}}
@foreach($purchases as $purchase)
    @php($paidAmount = (float) $purchase->supplierPayments->sum('amount'))
    @php($dueAmount = max(0, (float) $purchase->total - $paidAmount))

    @if((float) $purchase->total > $paidAmount)
        <div class="modal fade"
             id="add-payment-{{ $purchase->id }}"
             tabindex="-1"
             aria-labelledby="add-payment-label-{{ $purchase->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.purchases.payments.store', $purchase->id) }}"
                      data-payment-form
                      data-payment-modal="add-payment-{{ $purchase->id }}">

                    @csrf

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="add-payment-label-{{ $purchase->id }}">
                                <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                                Record Payment
                            </h5>
                            <small class="text-muted">
                                {{ $purchase->supplier?->name ?? 'Supplier not specified' }} · Due {{ config('restaurant.currency') }} {{ number_format($dueAmount, 2) }}
                            </small>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        @if($paymentFailed)
                            <div class="alert alert-danger py-2" role="alert">
                                <strong>Please correct the following:</strong>
                                <ul class="mb-0 mt-1 ps-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row g-3">

                            <div class="col-12">
                                <label class="form-label" for="paymentMethod{{ $purchase->id }}">
                                    Payment method <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <select class="form-select @error('payment_method') is-invalid @enderror"
                                        id="paymentMethod{{ $purchase->id }}"
                                        name="payment_method"
                                        required>
                                    <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                    <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile money</option>
                                    <option value="bank" @selected(old('payment_method') === 'bank')>Bank</option>
                                    <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                                    <option value="other" @selected(old('payment_method') === 'other')>Other</option>
                                </select>
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="paymentAmount{{ $purchase->id }}">
                                    Amount <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ config('restaurant.currency') }}</span>
                                    <input class="form-control @error('amount') is-invalid @enderror"
                                           id="paymentAmount{{ $purchase->id }}"
                                           type="number"
                                           name="amount"
                                           value="{{ old('amount') }}"
                                           min="0.01"
                                           max="{{ number_format($dueAmount, 2, '.', '') }}"
                                           step="0.01"
                                           placeholder="e.g. {{ number_format($dueAmount, 2, '.', '') }}"
                                           required>
                                </div>
                                <small class="text-muted">Cannot exceed the amount due.</small>
                                @error('amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="paymentReference{{ $purchase->id }}">Reference (optional)</label>
                                <input class="form-control @error('reference') is-invalid @enderror"
                                       id="paymentReference{{ $purchase->id }}"
                                       name="reference"
                                       value="{{ old('reference') }}"
                                       placeholder="e.g. Mobile money transaction ID"
                                       maxlength="255">
                                @error('reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

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
                            Record Payment
                        </button>

                    </div>

                </form>

            </div>
        </div>
    @endif
@endforeach

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    function openModal(id) {
        const element = document.getElementById(id);

        if (element && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(element).show();
        }
    }

    @if($receiveFailed)
        openModal('add-receipt');
    @endif

    const pendingPaymentModal = sessionStorage.getItem('pendingPaymentModal');
    sessionStorage.removeItem('pendingPaymentModal');

    @if($paymentFailed)
        if (pendingPaymentModal) {
            openModal(pendingPaymentModal);
        }
    @endif

    document.querySelectorAll('[data-payment-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            sessionStorage.setItem('pendingPaymentModal', form.dataset.paymentModal);
        });
    });

});

document.getElementById('addPurchaseItem')?.addEventListener('click', () => {
    const container = document.getElementById('purchaseItems');
    const index = container.querySelectorAll('.purchase-row').length;
    const row = container.querySelector('.purchase-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        field.classList.remove('is-invalid');
        if (field.tagName === 'INPUT') field.value = '';
    });
    row.querySelectorAll('.invalid-feedback').forEach((feedback) => feedback.remove());
    container.append(row);
});
</script>
@endpush
