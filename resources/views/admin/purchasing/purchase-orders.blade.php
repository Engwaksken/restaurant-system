@extends('layouts.app')

@section('title', 'Purchase Orders')
@section('page-title', 'Purchase Orders')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $canCreate = ! ($branches->isEmpty() || $suppliers->isEmpty() || $inventoryItems->isEmpty());
    $orderRows = old('items', [[]]);
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! $canCreate)
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
            <h2 class="h5 mb-1">Purchase orders</h2>
            <p class="text-muted small mb-0">Order stock from suppliers, then receive the goods against each order.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ route('admin.purchases.index') }}">
                <i class="fas fa-receipt me-1"></i>
                Received purchases
            </a>

            @if($canCreate)
                <button type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#add-purchase-order">
                    <i class="fas fa-plus me-1"></i>
                    Create order
                </button>
            @else
                <span class="text-muted small align-self-center">Active branches, suppliers, and inventory items are required.</span>
            @endif
        </div>
    </div>

    <div class="panel p-4">
        <h2 class="h5">Order history</h2>
        @forelse($orders as $order)
            <details class="border-bottom py-3"><summary>{{ $order->order_number }} · {{ $order->supplier?->name }} · {{ $order->branch?->name }} · {{ ucfirst(str_replace('_', ' ', $order->status)) }}</summary>
                <div class="pt-2"><ul>@foreach($order->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->received_quantity, 3) }} / {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }} at {{ number_format((float) $line->unit_cost, 2) }}</li>@endforeach</ul>
                    @if($order->notes)<p>{{ $order->notes }}</p>@endif
                    @if(!in_array($order->status, ['received', 'cancelled'], true))<a class="btn btn-sm btn-primary" href="{{ route('admin.purchase-orders.receive', $order->id) }}">Receive goods</a>@endif
                    @if($order->status === 'ordered')
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#cancel-order-{{ $order->id }}">
                            <i class="fas fa-ban me-1"></i>
                            Cancel order
                        </button>
                    @endif
                </div>
            </details>
        @empty<p class="text-muted">No purchase orders created.</p>@endforelse
        {{ $orders->links() }}
    </div>

</div>


@if($canCreate)
{{-- =========================================================
     CREATE PURCHASE ORDER MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-purchase-order"
     tabindex="-1"
     aria-labelledby="add-purchase-order-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.purchase-orders.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-purchase-order-label">
                    <i class="fas fa-file-invoice me-2 text-primary"></i>
                    Create Purchase Order
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($errors->any())
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
                        <label class="form-label" for="poBranch">
                            Receiving branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('branch_id') is-invalid @enderror"
                                id="poBranch"
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
                        <label class="form-label" for="poSupplier">
                            Supplier <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('supplier_id') is-invalid @enderror"
                                id="poSupplier"
                                name="supplier_id"
                                required>
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

                    <div class="col-12">
                        <label class="form-label" for="poNotes">Notes (optional)</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror"
                                  id="poNotes"
                                  name="notes"
                                  rows="2"
                                  maxlength="2000"
                                  placeholder="e.g. Deliver before 8am on Friday">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <h3 class="h6 mb-2">Ordered items</h3>

                        @error('items')
                            <div class="alert alert-danger py-2 mb-2">{{ $message }}</div>
                        @enderror

                        <div id="poItems">
                            @foreach($orderRows as $i => $row)
                                <div class="po-row border rounded p-3 mb-2">
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
                                                   placeholder="e.g. 10"
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

                        <button class="btn btn-outline-secondary btn-sm" type="button" id="addPoItem">
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
                    Create Order
                </button>

            </div>

        </form>

    </div>
</div>
@endif


{{-- =========================================================
     CANCEL ORDER CONFIRMATIONS
     OUTSIDE THE LIST
========================================================= --}}
@foreach($orders as $order)
    @if($order->status === 'ordered')
        <div class="modal fade"
             id="cancel-order-{{ $order->id }}"
             tabindex="-1"
             aria-labelledby="cancel-order-label-{{ $order->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.purchase-orders.cancel', $order->id) }}">

                    @csrf
                    @method('PATCH')

                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title" id="cancel-order-label-{{ $order->id }}">
                            Cancel Purchase Order
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body text-center py-4">

                        <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3"
                             style="width:56px;height:56px;">
                            <i class="fas fa-ban fa-lg"></i>
                        </div>

                        <h5 class="mb-2">
                            Cancel {{ $order->order_number }}?
                        </h5>

                        <p class="text-muted mb-0">
                            This unreceived order will be marked as cancelled. Goods cannot be received against it afterwards.
                        </p>

                    </div>

                    <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                        <button type="button"
                                class="btn btn-light px-4"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
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
@endforeach

@endsection


@push('scripts')
<script>
document.getElementById('addPoItem')?.addEventListener('click', () => {
    const container = document.getElementById('poItems');
    const index = container.querySelectorAll('.po-row').length;
    const row = container.querySelector('.po-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        field.classList.remove('is-invalid');
        if (field.tagName === 'INPUT') field.value = '';
    });
    row.querySelectorAll('.invalid-feedback').forEach((feedback) => feedback.remove());
    container.append(row);
});

document.addEventListener('DOMContentLoaded', function () {
    @if($canCreate && $errors->any())
        const orderModal = document.getElementById('add-purchase-order');

        if (orderModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(orderModal).show();
        }
    @endif
});
</script>
@endpush
