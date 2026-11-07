@extends('layouts.app')

@section('title', 'Wastage')
@section('page-title', 'Inventory Wastage')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $canRecord = ! ($branches->isEmpty() || $items->isEmpty());
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! $canRecord)
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
            <h2 class="h5 mb-1">Wasted stock</h2>
            <p class="text-muted small mb-0">Record spoiled, expired or damaged stock so balances and values stay accurate.</p>
        </div>

        @if($canRecord)
            <button type="button"
                    class="btn btn-warning"
                    data-bs-toggle="modal"
                    data-bs-target="#add-wastage">
                <i class="fas fa-plus me-1"></i>
                Record wastage
            </button>
        @else
            <span class="text-muted small">An active branch and inventory item are required to record wastage.</span>
        @endif
    </div>

    <div class="panel p-4">
        <h2 class="h5">Wastage history</h2>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Date</th><th>Branch</th><th>Item</th><th>Quantity</th><th>Reason</th><th>Value</th></tr></thead>
            <tbody>@forelse($wastages as $wastage)<tr><td>{{ $wastage->wasted_at->format('Y-m-d H:i') }}</td><td>{{ $wastage->branch?->name }}</td><td>{{ $wastage->inventoryItem?->name }}</td><td>{{ number_format((float) $wastage->quantity, 3) }} {{ $wastage->inventoryItem?->usage_unit }}</td><td>{{ $wastage->reason }} @if($wastage->reference)<small class="text-muted d-block">Ref: {{ $wastage->reference }}</small>@endif</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $wastage->quantity * (float) $wastage->unit_cost, 2) }}</td></tr>@empty<tr><td colspan="6" class="text-muted">No wastage recorded.</td></tr>@endforelse</tbody>
        </table></div>{{ $wastages->links() }}
    </div>

</div>


@if($canRecord)
{{-- =========================================================
     RECORD WASTAGE MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-wastage"
     tabindex="-1"
     aria-labelledby="add-wastage-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.inventory.wastage.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-wastage-label">
                    <i class="fas fa-trash-can me-2 text-warning"></i>
                    Record Wasted Stock
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
                        <label class="form-label" for="wastageBranch">
                            Branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('branch_id') is-invalid @enderror"
                                id="wastageBranch"
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
                        <label class="form-label" for="wastageItem">
                            Inventory item <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('inventory_item_id') is-invalid @enderror"
                                id="wastageItem"
                                name="inventory_item_id"
                                required>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}"
                                    @selected((string) old('inventory_item_id') === (string) $item->id)>
                                    {{ $item->name }} ({{ $item->usage_unit }})
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_item_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="wastageQuantity">
                            Quantity wasted <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input class="form-control @error('quantity') is-invalid @enderror"
                               type="number"
                               id="wastageQuantity"
                               name="quantity"
                               value="{{ old('quantity') }}"
                               min="0.001"
                               step="0.001"
                               placeholder="e.g. 2.500"
                               required>
                        @error('quantity')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="wastageReference">Reference (optional)</label>
                        <input class="form-control @error('reference') is-invalid @enderror"
                               id="wastageReference"
                               name="reference"
                               value="{{ old('reference') }}"
                               placeholder="e.g. WST-2026-014"
                               maxlength="255">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="wastageReason">
                            Reason <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <textarea class="form-control @error('reason') is-invalid @enderror"
                                  id="wastageReason"
                                  name="reason"
                                  rows="3"
                                  minlength="5"
                                  maxlength="1000"
                                  placeholder="e.g. Expired before service, dropped during prep"
                                  required>{{ old('reason') }}</textarea>
                        @error('reason')
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
                        class="btn btn-warning">
                    <i class="fas fa-floppy-disk me-1"></i>
                    Record Wastage
                </button>

            </div>

        </form>

    </div>
</div>
@endif

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    @if($canRecord && $errors->any())
        const wastageModal = document.getElementById('add-wastage');

        if (wastageModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(wastageModal).show();
        }
    @endif

});
</script>
@endpush
