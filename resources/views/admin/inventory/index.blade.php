@extends('layouts.app')

@section('title', 'Inventory')
@section('page-title', 'Inventory Management')

@section('own-validation-errors', '1')

@section('content')
@php
    $failedFields = collect($errors->keys())->map(fn ($key) => explode('.', $key)[0])->unique();
    $itemFailed = $failedFields->intersect(['sku', 'inventory_category_id', 'purchase_unit', 'usage_unit', 'conversion_factor', 'unit_cost', 'minimum_stock'])->isNotEmpty()
        || ($failedFields->contains('name') && old('usage_unit') !== null);
    $categoryFailed = ! $itemFailed && $failedFields->intersect(['name', 'description'])->isNotEmpty();
    $adjustFailed = $failedFields->intersect(['branch_id', 'inventory_item_id', 'quantity_change', 'reason'])->isNotEmpty();
    $canAdjust = ! ($branches->isEmpty() || $activeItems->isEmpty());
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! ($itemFailed || $categoryFailed || $adjustFailed))
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

    <div class="panel p-4 mb-4 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h2 class="h5 mb-1">Stock setup and adjustments</h2>
            <p class="text-muted small mb-0">Add stock items and categories, or record a stock adjustment with a reason.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#add-inventory-item">
                <i class="fas fa-plus me-1"></i>
                Add stock item
            </button>

            <button type="button"
                    class="btn btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#add-inventory-category">
                <i class="fas fa-plus me-1"></i>
                Add category
            </button>

            @if($canAdjust)
                <button type="button"
                        class="btn btn-warning"
                        data-bs-toggle="modal"
                        data-bs-target="#add-stock-adjustment">
                    <i class="fas fa-plus me-1"></i>
                    Record adjustment
                </button>
            @else
                <span class="text-muted small align-self-center">
                    An active branch and stock item are required to record adjustments.
                </span>
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="panel p-4 mb-4">
                <h2 class="h5">Stock balances</h2>
                <div class="table-responsive"><table class="table table-striped">
                    <thead><tr><th>Branch</th><th>Item</th><th>On hand</th><th>Unit</th></tr></thead>
                    <tbody>@forelse($stocks as $stock)<tr><td>{{ $stock->branch_name }}</td><td>{{ $stock->item_name }}</td><td>{{ number_format((float) $stock->quantity, 3) }}</td><td>{{ $stock->usage_unit }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No stock has been recorded.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>

        <div class="col-12">
            <div class="panel p-4">
                <h2 class="h5">Inventory items</h2>
                <div class="table-responsive"><table class="table table-striped">
                    <thead><tr><th>Name</th><th>SKU</th><th>Usage unit</th><th>Cost</th><th>Status</th></tr></thead>
                    <tbody>@forelse($items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->sku ?? '—' }}</td><td>{{ $item->usage_unit }}</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $item->unit_cost, 2) }}</td><td>{{ $item->deleted_at ? 'Archived' : ($item->is_active ? 'Active' : 'Inactive') }}</td></tr>@empty<tr><td colspan="5" class="text-muted">No inventory items configured.</td></tr>@endforelse</tbody>
                </table></div>
                {{ $items->links() }}
            </div>
        </div>
    </div>

</div>


{{-- =========================================================
     ADD STOCK ITEM MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-inventory-item"
     tabindex="-1"
     aria-labelledby="add-inventory-item-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.inventory.products.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-inventory-item-label">
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Add Stock Item
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($itemFailed)
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
                        <label class="form-label" for="inventoryName">
                            Name <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="inventoryName"
                               class="form-control @error('name') is-invalid @enderror"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="e.g. Beef fillet"
                               required
                               maxlength="255">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="inventorySku">SKU (optional)</label>
                        <input id="inventorySku"
                               class="form-control @error('sku') is-invalid @enderror"
                               name="sku"
                               value="{{ old('sku') }}"
                               placeholder="e.g. BEEF-FIL-01"
                               maxlength="255">
                        @error('sku')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="inventoryCategory">Category</label>
                        <select id="inventoryCategory"
                                class="form-select @error('inventory_category_id') is-invalid @enderror"
                                name="inventory_category_id">
                            <option value="">Uncategorised</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    @selected((string) old('inventory_category_id') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_category_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="inventoryPurchaseUnit">
                            Purchase unit <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="inventoryPurchaseUnit"
                               class="form-control @error('purchase_unit') is-invalid @enderror"
                               name="purchase_unit"
                               value="{{ old('purchase_unit', 'unit') }}"
                               placeholder="e.g. box"
                               required
                               maxlength="50">
                        @error('purchase_unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="inventoryUsageUnit">
                            Recipe usage unit <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="inventoryUsageUnit"
                               class="form-control @error('usage_unit') is-invalid @enderror"
                               name="usage_unit"
                               value="{{ old('usage_unit', 'unit') }}"
                               placeholder="e.g. g"
                               required
                               maxlength="50">
                        @error('usage_unit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="inventoryConversion">
                            Usage units per purchase unit <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="inventoryConversion"
                               class="form-control @error('conversion_factor') is-invalid @enderror"
                               type="number"
                               name="conversion_factor"
                               value="{{ old('conversion_factor', 1) }}"
                               min="0.0001"
                               step="0.0001"
                               placeholder="e.g. 1000"
                               required>
                        <small class="text-muted">For example, one 1 kg box is 1000 g.</small>
                        @error('conversion_factor')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="inventoryUnitCost">
                            Unit cost <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="inventoryUnitCost"
                               class="form-control @error('unit_cost') is-invalid @enderror"
                               type="number"
                               name="unit_cost"
                               value="{{ old('unit_cost', 0) }}"
                               min="0"
                               step="0.01"
                               placeholder="e.g. 5000"
                               required>
                        @error('unit_cost')
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
                    Save Item
                </button>

            </div>

        </form>

    </div>
</div>


{{-- =========================================================
     ADD CATEGORY MODAL
========================================================= --}}
<div class="modal fade"
     id="add-inventory-category"
     tabindex="-1"
     aria-labelledby="add-inventory-category-label"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.inventory.categories.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-inventory-category-label">
                    <i class="fas fa-layer-group me-2 text-primary"></i>
                    Add Category
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($categoryFailed)
                    <div class="alert alert-danger py-2" role="alert">
                        <strong>Please correct the following:</strong>
                        <ul class="mb-0 mt-1 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="categoryName">
                        Name <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input id="categoryName"
                           class="form-control @error('name') is-invalid @enderror"
                           name="name"
                           value="{{ old('name') }}"
                           placeholder="e.g. Dairy"
                           required
                           maxlength="255">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label class="form-label" for="categoryDescription">Description (optional)</label>
                    <textarea id="categoryDescription"
                              class="form-control @error('description') is-invalid @enderror"
                              name="description"
                              rows="3"
                              maxlength="1000"
                              placeholder="Short note about what this category covers">{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
                    Save Category
                </button>

            </div>

        </form>

    </div>
</div>


@if($canAdjust)
{{-- =========================================================
     RECORD STOCK ADJUSTMENT MODAL
========================================================= --}}
<div class="modal fade"
     id="add-stock-adjustment"
     tabindex="-1"
     aria-labelledby="add-stock-adjustment-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.inventory.stock.adjust') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-stock-adjustment-label">
                    <i class="fas fa-sliders me-2 text-warning"></i>
                    Record Stock Adjustment
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                @if($adjustFailed)
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
                        <label class="form-label" for="stockBranch">
                            Branch <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="stockBranch"
                                class="form-select @error('branch_id') is-invalid @enderror"
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
                        <label class="form-label" for="stockItem">
                            Stock item <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <select id="stockItem"
                                class="form-select @error('inventory_item_id') is-invalid @enderror"
                                name="inventory_item_id"
                                required>
                            @foreach($activeItems as $item)
                                <option value="{{ $item->id }}"
                                    @selected((string) old('inventory_item_id') === (string) $item->id)>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('inventory_item_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="stockChange">
                            Quantity change <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input id="stockChange"
                               class="form-control @error('quantity_change') is-invalid @enderror"
                               type="number"
                               name="quantity_change"
                               value="{{ old('quantity_change') }}"
                               step="0.001"
                               min="-1000000"
                               max="1000000"
                               placeholder="e.g. 5 or -2"
                               required>
                        <small class="text-muted">Positive adds stock, negative removes it.</small>
                        @error('quantity_change')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="stockReason">
                            Reason (required for audit) <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <textarea id="stockReason"
                                  class="form-control @error('reason') is-invalid @enderror"
                                  name="reason"
                                  rows="3"
                                  minlength="5"
                                  maxlength="1000"
                                  placeholder="e.g. Stock count correction after spoilage check"
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
                    Record Adjustment
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

    function openModal(id) {
        const element = document.getElementById(id);

        if (element && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(element).show();
        }
    }

    @if($itemFailed)
        openModal('add-inventory-item');
    @endif

    @if($categoryFailed)
        openModal('add-inventory-category');
    @endif

    @if($adjustFailed)
        openModal('add-stock-adjustment');
    @endif

});
</script>
@endpush
