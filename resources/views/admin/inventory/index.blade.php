@extends('layouts.app')

@section('title', 'Inventory')
@section('page-title', 'Inventory Management')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $failedFields = collect($errors->keys())->map(fn ($key) => explode('.', $key)[0])->unique();
    $itemFailed = $failedFields->intersect(['sku', 'barcode', 'inventory_category_id', 'purchase_unit', 'usage_unit', 'conversion_factor', 'unit_cost', 'minimum_stock'])->isNotEmpty()
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

            <button type="button" class="btn btn-dark" id="scanInventoryButton">
                <i class="fas fa-barcode me-1"></i>
                Scan barcode
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

    <div class="panel p-0">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 px-3 pt-2 border-bottom">
            <ul class="nav nav-tabs settings-tabs border-0" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $tab === 'items' ? 'active' : '' }}" id="tab-items" data-bs-toggle="tab" data-bs-target="#pane-items" type="button" role="tab" aria-controls="pane-items" aria-selected="{{ $tab === 'items' ? 'true' : 'false' }}" data-tab-key="items">
                        <i class="fas fa-box me-2"></i>Inventory items <span class="badge bg-light ms-1">{{ number_format($items->total()) }}</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $tab === 'stock' ? 'active' : '' }}" id="tab-stock" data-bs-toggle="tab" data-bs-target="#pane-stock" type="button" role="tab" aria-controls="pane-stock" aria-selected="{{ $tab === 'stock' ? 'true' : 'false' }}" data-tab-key="stock">
                        <i class="fas fa-warehouse me-2"></i>Stock balances <span class="badge bg-light ms-1">{{ number_format($stocks->total()) }}</span>
                    </button>
                </li>
            </ul>
            <form method="GET" class="d-flex gap-2 pb-2 pb-lg-0" role="search" id="inventorySearchForm">
                <input type="hidden" name="tab" value="{{ $tab }}" id="inventorySearchTab">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="fas fa-magnifying-glass"></i></span>
                    <input type="search" class="form-control" name="search" id="inventorySearch" value="{{ $search }}" placeholder="Name, SKU or barcode" aria-label="Search inventory" maxlength="100">
                    <button class="btn btn-outline-secondary" type="button" data-scan-search title="Scan a barcode to search" aria-label="Scan a barcode to search"><i class="fas fa-barcode"></i></button>
                </div>
                @if($search !== '')
                    <a href="{{ route('admin.inventory.products', ['tab' => $tab]) }}" class="btn btn-sm btn-light border text-nowrap">Clear</a>
                @endif
            </form>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade {{ $tab === 'items' ? 'show active' : '' }} p-4" id="pane-items" role="tabpanel" aria-labelledby="tab-items" tabindex="0">
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Name</th><th>SKU</th><th>Barcode</th><th>Usage unit</th><th class="text-end">Cost</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td class="fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->sku ?? '-' }}</td>
                            <td>@if($item->barcode)<span class="font-monospace small"><i class="fas fa-barcode text-muted me-1"></i>{{ $item->barcode }}</span>@else<span class="text-muted">-</span>@endif</td>
                            <td>{{ $item->usage_unit }}</td>
                            <td class="text-end">{{ config('restaurant.currency') }} {{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td>
                                @if($item->deleted_at)<span class="badge bg-secondary">Archived</span>
                                @elseif($item->is_active)<span class="badge bg-success">Active</span>
                                @else<span class="badge bg-warning">Inactive</span>@endif
                            </td>
                            <td class="text-end text-nowrap">
                                @if($item->deleted_at)
                                    <span class="text-muted small">Archived</span>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#edit-item-{{ $item->id }}" title="Edit {{ $item->name }}" aria-label="Edit {{ $item->name }}"><i class="fas fa-pen"></i></button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-item-{{ $item->id }}" title="Delete {{ $item->name }}" aria-label="Delete {{ $item->name }}"><i class="fas fa-trash"></i></button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">{{ $search !== '' ? 'No inventory items match "' . $search . '".' : 'No inventory items configured.' }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                {{ $items->links() }}
            </div>

            <div class="tab-pane fade {{ $tab === 'stock' ? 'show active' : '' }} p-4" id="pane-stock" role="tabpanel" aria-labelledby="tab-stock" tabindex="0">
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th>Branch</th><th>Item</th><th class="text-end">On hand</th><th>Unit</th><th>Level</th></tr></thead>
                    <tbody>
                    @forelse($stocks as $stock)
                        @php
                            $reorderAt = max((float) $stock->minimum_stock, (float) $stock->reorder_level);
                        @endphp
                        <tr>
                            <td>{{ $stock->branch_name }}</td>
                            <td class="fw-semibold">{{ $stock->item_name }}@if($stock->barcode)<small class="d-block text-muted font-monospace">{{ $stock->barcode }}</small>@endif</td>
                            <td class="text-end">{{ number_format((float) $stock->quantity, 3) }}</td>
                            <td>{{ $stock->usage_unit }}</td>
                            <td>
                                @if((float) $stock->quantity <= 0)<span class="badge bg-danger">Out of stock</span>
                                @elseif($reorderAt > 0 && (float) $stock->quantity <= $reorderAt)<span class="badge bg-warning">Low</span>
                                @else<span class="badge bg-success">OK</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">{{ $search !== '' ? 'No stock matches "' . $search . '".' : 'No stock has been recorded.' }}</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                {{ $stocks->links() }}
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
                        <label class="form-label" for="inventoryBarcode">Barcode (optional)</label>
                        <div class="input-group has-validation">
                            <input id="inventoryBarcode"
                                   class="form-control font-monospace @error('barcode') is-invalid @enderror"
                                   name="barcode"
                                   value="{{ old('barcode') }}"
                                   placeholder="Scan or type"
                                   maxlength="64"
                                   autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" data-scan-target="#inventoryBarcode" aria-label="Scan barcode"><i class="fas fa-barcode"></i></button>
                            @error('barcode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
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


{{-- =========================================================
     EDIT AND DELETE STOCK ITEM MODALS
     ONE PER ROW, OUTSIDE THE TABLE
========================================================= --}}
@foreach($items as $item)
    @continue($item->deleted_at)
    @php
        $editBagName = 'editItem' . $item->id;
        $editBag = $errors->getBag($editBagName);
        $editFailed = $editBag->isNotEmpty();
        $itemLocked = in_array($item->id, $stockedItemIds, true);
        $editValue = fn (string $key, $default) => $editFailed ? old($key) : $default;
        $editPrefix = 'edit-item-' . $item->id;
    @endphp

    <div class="modal fade"
         id="edit-item-{{ $item->id }}"
         tabindex="-1"
         aria-labelledby="edit-item-{{ $item->id }}-label"
         aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.inventory.products.update', $item) }}">

                @csrf
                @method('PUT')

                <div class="modal-header">
                    <h5 class="modal-title" id="edit-item-{{ $item->id }}-label">
                        <i class="fas fa-pen me-2 text-primary"></i>
                        Edit Stock Item
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    @if($editFailed)
                        <div class="alert alert-danger py-2" role="alert">
                            <strong>Please correct the following:</strong>
                            <ul class="mb-0 mt-1 ps-3">
                                @foreach($editBag->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($itemLocked)
                        <div class="alert alert-warning py-2" role="alert">
                            <i class="fas fa-lock me-1"></i>
                            Purchase unit, recipe usage unit and conversion factor are locked because this item already has stock history. Changing them now would corrupt stock balances.
                        </div>
                    @endif

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-name">
                                Name <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-name"
                                   class="form-control @error('name', $editBagName) is-invalid @enderror"
                                   name="name"
                                   value="{{ $editValue('name', $item->name) }}"
                                   placeholder="e.g. Beef fillet"
                                   required
                                   maxlength="255">
                            @error('name', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-sku">SKU (optional)</label>
                            <input id="{{ $editPrefix }}-sku"
                                   class="form-control @error('sku', $editBagName) is-invalid @enderror"
                                   name="sku"
                                   value="{{ $editValue('sku', $item->sku) }}"
                                   placeholder="e.g. BEEF-FIL-01"
                                   maxlength="255">
                            @error('sku', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-barcode">Barcode (optional)</label>
                            <div class="input-group has-validation">
                                <input id="{{ $editPrefix }}-barcode"
                                       class="form-control font-monospace @error('barcode', $editBagName) is-invalid @enderror"
                                       name="barcode"
                                       value="{{ $editValue('barcode', $item->barcode) }}"
                                       placeholder="Scan or type"
                                       maxlength="64"
                                       autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" data-scan-target="#{{ $editPrefix }}-barcode" aria-label="Scan barcode"><i class="fas fa-barcode"></i></button>
                                @error('barcode', $editBagName)
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-category">Category</label>
                            <select id="{{ $editPrefix }}-category"
                                    class="form-select @error('inventory_category_id', $editBagName) is-invalid @enderror"
                                    name="inventory_category_id">
                                <option value="">Uncategorised</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                        @selected((string) $editValue('inventory_category_id', $item->inventory_category_id) === (string) $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('inventory_category_id', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="{{ $editPrefix }}-purchase-unit">
                                Purchase unit <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-purchase-unit"
                                   class="form-control @error('purchase_unit', $editBagName) is-invalid @enderror"
                                   name="purchase_unit"
                                   value="{{ $editValue('purchase_unit', $item->purchase_unit) }}"
                                   placeholder="e.g. box"
                                   @if($itemLocked) readonly @else required @endif
                                   maxlength="50">
                            @error('purchase_unit', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label" for="{{ $editPrefix }}-usage-unit">
                                Recipe usage unit <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-usage-unit"
                                   class="form-control @error('usage_unit', $editBagName) is-invalid @enderror"
                                   name="usage_unit"
                                   value="{{ $editValue('usage_unit', $item->usage_unit) }}"
                                   placeholder="e.g. g"
                                   @if($itemLocked) readonly @else required @endif
                                   maxlength="50">
                            @error('usage_unit', $editBagName)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-conversion">
                                Usage units per purchase unit <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-conversion"
                                   class="form-control @error('conversion_factor', $editBagName) is-invalid @enderror"
                                   type="number"
                                   name="conversion_factor"
                                   value="{{ $editValue('conversion_factor', (float) $item->conversion_factor) }}"
                                   min="0.0001"
                                   step="0.0001"
                                   placeholder="e.g. 1000"
                                   @if($itemLocked) readonly @else required @endif>
                            <small class="text-muted">For example, one 1 kg box is 1000 g.</small>
                            @error('conversion_factor', $editBagName)
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="{{ $editPrefix }}-unit-cost">
                                Unit cost <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input id="{{ $editPrefix }}-unit-cost"
                                   class="form-control @error('unit_cost', $editBagName) is-invalid @enderror"
                                   type="number"
                                   name="unit_cost"
                                   value="{{ $editValue('unit_cost', (float) $item->unit_cost) }}"
                                   min="0"
                                   step="0.01"
                                   placeholder="e.g. 5000"
                                   required>
                            @error('unit_cost', $editBagName)
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
                        Save Changes
                    </button>

                </div>

            </form>

        </div>
    </div>

    <div class="modal fade"
         id="delete-item-{{ $item->id }}"
         tabindex="-1"
         aria-labelledby="delete-item-{{ $item->id }}-label"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.inventory.products.destroy', $item) }}">

                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="delete-item-{{ $item->id }}-label">
                        <i class="fas fa-trash me-2 text-danger"></i>
                        Delete Stock Item
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-2">
                        Remove <strong>{{ $item->name }}</strong> from the inventory list?
                    </p>
                    <p class="text-muted small mb-0">
                        The item is archived rather than erased, so past purchases, sales and stock movements keep their history.
                        Deletion is refused while any branch still has stock on hand for this item.
                    </p>
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
                        Delete Item
                    </button>

                </div>

            </form>

        </div>
    </div>
@endforeach

<x-admin.barcode-scanner />

{{-- Scan result --}}
<div class="modal fade" id="scanResultModal" tabindex="-1" aria-labelledby="scanResultTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scanResultTitle"><i class="fas fa-barcode me-2 text-primary"></i>Scanned item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="scanResultBody"></div>
            <div class="modal-footer" id="scanResultActions"></div>
        </div>
    </div>
</div>

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

    @foreach($items as $item)
        @if(! $item->deleted_at && $errors->getBag('editItem' . $item->id)->isNotEmpty())
            openModal('edit-item-{{ $item->id }}');
        @endif
    @endforeach

    // Keep the open tab in the URL so search and paging return to it.
    document.querySelectorAll('[data-tab-key]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function () {
            document.getElementById('inventorySearchTab').value = tab.dataset.tabKey;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab.dataset.tabKey);
            window.history.replaceState({}, '', url);
        });
    });

    // Scan from the search box: fills the search and filters the list.
    document.querySelector('[data-scan-search]')?.addEventListener('click', function () {
        BarcodeScanner.open(function (code) {
            document.getElementById('inventorySearch').value = code;
            document.getElementById('inventorySearchForm').submit();
        });
    });

    // Scan barcode: look the item up and offer the next step.
    const resultModal = document.getElementById('scanResultModal');
    const resultBody = document.getElementById('scanResultBody');
    const resultActions = document.getElementById('scanResultActions');
    const lookupUrl = @json(route('admin.inventory.lookup'));
    const listUrl = @json(route('admin.inventory.products'));
    const currency = @json(config('restaurant.currency'));

    function el(tag, className, text) {
        const node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function actionButton(label, icon, className, handler) {
        const button = el('button', 'btn ' + className);
        button.type = 'button';
        button.append(el('i', icon + ' me-1'), document.createTextNode(label));
        button.addEventListener('click', function () {
            bootstrap.Modal.getOrCreateInstance(resultModal).hide();
            resultModal.addEventListener('hidden.bs.modal', handler, { once: true });
        });
        return button;
    }

    function showResult(data) {
        resultBody.replaceChildren();
        resultActions.replaceChildren();

        if (!data.found) {
            resultBody.append(
                el('p', 'mb-1', 'No inventory item has this barcode yet:'),
                el('p', 'font-monospace fw-semibold mb-0', data.code)
            );
            resultActions.append(
                actionButton('Close', 'fas fa-xmark', 'btn-light', function () {}),
                actionButton('Add new item', 'fas fa-plus', 'btn-primary', function () {
                    document.getElementById('inventoryBarcode').value = data.code;
                    openModal('add-inventory-item');
                })
            );
        } else {
            const item = data.item;
            resultBody.append(
                el('h6', 'mb-1', item.name),
                el('div', 'small text-muted font-monospace mb-3', [item.barcode, item.sku].filter(Boolean).join(' · ')),
                el('div', 'small text-muted mb-2', 'Unit cost: ' + currency + ' ' + Number(item.unit_cost).toFixed(2) + ' per ' + item.usage_unit)
            );
            const list = el('ul', 'list-unstyled mb-0');
            if (!item.stock.length) {
                list.append(el('li', 'text-muted small', 'No stock recorded yet.'));
            }
            item.stock.forEach(function (row) {
                const line = el('li', 'd-flex justify-content-between border-bottom py-1');
                line.append(el('span', '', row.branch), el('strong', '', Number(row.quantity).toFixed(3) + ' ' + item.usage_unit));
                list.append(line);
            });
            resultBody.append(list);

            const adjustSelect = document.getElementById('stockItem');
            if (adjustSelect && item.is_active && adjustSelect.querySelector('option[value="' + item.id + '"]')) {
                resultActions.append(actionButton('Adjust stock', 'fas fa-sliders', 'btn-warning', function () {
                    adjustSelect.value = String(item.id);
                    openModal('add-stock-adjustment');
                }));
            }
            if (document.getElementById('edit-item-' + item.id)) {
                resultActions.append(actionButton('Edit item', 'fas fa-pen', 'btn-outline-primary', function () {
                    openModal('edit-item-' + item.id);
                }));
            }
            resultActions.append(actionButton('Show in list', 'fas fa-list', 'btn-primary', function () {
                window.location.href = listUrl + '?search=' + encodeURIComponent(data.code);
            }));
        }
        bootstrap.Modal.getOrCreateInstance(resultModal).show();
    }

    document.getElementById('scanInventoryButton')?.addEventListener('click', function () {
        BarcodeScanner.open(function (code) {
            fetch(lookupUrl + '?code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' } })
                .then(function (response) {
                    if (!response.ok) { throw new Error('Lookup failed'); }
                    return response.json();
                })
                .then(showResult)
                .catch(function () {
                    resultBody.replaceChildren(el('p', 'mb-0 text-danger', 'Could not look up that barcode. Please try again.'));
                    resultActions.replaceChildren(actionButton('Close', 'fas fa-xmark', 'btn-light', function () {}));
                    bootstrap.Modal.getOrCreateInstance(resultModal).show();
                });
        });
    });

});
</script>
@endpush
