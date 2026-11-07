@extends('layouts.app')

@section('title', 'Stock Transfers')
@section('page-title', 'Branch Stock Transfers')

@section('own-validation-errors', '1')

@section('content')
@php
    $canTransfer = ! ($branches->count() < 2 || $items->isEmpty());
    $transferRows = old('items', [[]]);
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! $canTransfer)
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
            <h2 class="h5 mb-1">Transfer stock between branches</h2>
            <p class="text-muted small mb-0">Move inventory from one active branch to another. Each transfer is recorded in the stock ledger.</p>
        </div>

        @if($canTransfer)
            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#add-transfer">
                <i class="fas fa-plus me-1"></i>
                Transfer stock
            </button>
        @else
            <span class="text-muted small">Two active branches and an active inventory item are required to transfer stock.</span>
        @endif
    </div>

    <div class="panel p-4">
        <h2 class="h5">Transfer history</h2>
        @forelse($transfers as $transfer)
            <details class="border-bottom py-3"><summary>{{ $transfer->transferred_at->format('Y-m-d H:i') }} · {{ $transfer->fromBranch?->name }} → {{ $transfer->toBranch?->name }}{{ $transfer->reference ? ' · ' . $transfer->reference : '' }}</summary>
                @if($transfer->notes)<p class="mt-2 mb-1">{{ $transfer->notes }}</p>@endif
                <ul class="mb-0">@foreach($transfer->items as $line)<li>{{ $line->inventoryItem?->name }}: {{ number_format((float) $line->quantity, 3) }} {{ $line->inventoryItem?->usage_unit }}</li>@endforeach</ul>
            </details>
        @empty<p class="text-muted">No stock transfers recorded.</p>@endforelse
        {{ $transfers->links() }}
    </div>

</div>


@if($canTransfer)
{{-- =========================================================
     TRANSFER STOCK MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-transfer"
     tabindex="-1"
     aria-labelledby="add-transfer-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.inventory.transfers.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-transfer-label">
                    <i class="fas fa-right-left me-2 text-primary"></i>
                    Transfer Stock
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
                        <label class="form-label" for="fromBranch">
                            From branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('from_branch_id') is-invalid @enderror"
                                id="fromBranch"
                                name="from_branch_id"
                                required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    @selected(old('from_branch_id') !== null
                                        ? (string) old('from_branch_id') === (string) $branch->id
                                        : auth()->user()->branch_id == $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('from_branch_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="toBranch">
                            To branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select class="form-select @error('to_branch_id') is-invalid @enderror"
                                id="toBranch"
                                name="to_branch_id"
                                required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    @selected(old('to_branch_id') !== null
                                        ? (string) old('to_branch_id') === (string) $branch->id
                                        : (auth()->user()->branch_id && auth()->user()->branch_id != $branch->id))>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('to_branch_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="transferReference">Reference (optional)</label>
                        <input class="form-control @error('reference') is-invalid @enderror"
                               id="transferReference"
                               name="reference"
                               value="{{ old('reference') }}"
                               placeholder="e.g. TRF-0412"
                               maxlength="255">
                        @error('reference')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="transferNotes">Notes (optional)</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror"
                                  id="transferNotes"
                                  name="notes"
                                  rows="2"
                                  maxlength="1000"
                                  placeholder="e.g. Weekend event stock">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <h3 class="h6 mb-2">Items to transfer</h3>

                        @error('items')
                            <div class="alert alert-danger py-2 mb-2">{{ $message }}</div>
                        @enderror

                        <div id="transferItems">
                            @foreach($transferRows as $i => $row)
                                <div class="transfer-row border rounded p-3 mb-2">
                                    <div class="row g-2">

                                        <div class="col-md-7">
                                            <label class="form-label">
                                                Inventory item <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <select class="form-select @error("items.$i.inventory_item_id") is-invalid @enderror"
                                                    name="items[{{ $i }}][inventory_item_id]"
                                                    required>
                                                @foreach($items as $item)
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

                                        <div class="col-md-5">
                                            <label class="form-label">
                                                Quantity <span class="text-danger" aria-hidden="true">*</span>
                                            </label>
                                            <input class="form-control @error("items.$i.quantity") is-invalid @enderror"
                                                   type="number"
                                                   name="items[{{ $i }}][quantity]"
                                                   value="{{ $row['quantity'] ?? '' }}"
                                                   min="0.001"
                                                   step="0.001"
                                                   placeholder="e.g. 5"
                                                   required>
                                            @error("items.$i.quantity")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <button class="btn btn-outline-secondary btn-sm" type="button" id="addTransferItem">
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
                    <i class="fas fa-paper-plane me-1"></i>
                    Complete Transfer
                </button>

            </div>

        </form>

    </div>
</div>
@endif

@endsection


@push('scripts')
<script>
document.getElementById('addTransferItem')?.addEventListener('click', () => {
    const container = document.getElementById('transferItems');
    const index = container.querySelectorAll('.transfer-row').length;
    const row = container.querySelector('.transfer-row').cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
        field.classList.remove('is-invalid');
        if (field.tagName === 'INPUT') field.value = '';
    });
    row.querySelectorAll('.invalid-feedback').forEach((feedback) => feedback.remove());
    container.append(row);
});
const from = document.getElementById('fromBranch');
const to = document.getElementById('toBranch');
from?.addEventListener('change', () => {
    if (from.value === to.value) to.value = [...to.options].find(option => option.value !== from.value)?.value ?? '';
});

document.addEventListener('DOMContentLoaded', function () {
    @if($canTransfer && $errors->any())
        const transferModal = document.getElementById('add-transfer');

        if (transferModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(transferModal).show();
        }
    @endif
});
</script>
@endpush
