@extends('layouts.app')

@section('title', 'Menu Items')
@section('page-title', 'Menu Items')

@section('own-validation-errors', '1')

@section('content')
<div class="container-fluid px-0">

    {{-- Validation only.
         Success/error flash messages should be rendered once in layouts.app. --}}
    @if($errors->any())
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
            <button type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Close"></button>
        </div>
    @endif

    {{-- Summary --}}
    <div class="row g-3 mb-3">

        <div class="col-6 col-xl-3">
            <div class="card menu-stat-card h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-primary-subtle text-primary">
                        <i class="fas fa-burger"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Menu Items</small>
                        <strong>
                            {{ method_exists($items, 'total') ? number_format($items->total()) : number_format($items->count()) }}
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card menu-stat-card h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-success-subtle text-success">
                        <i class="fas fa-circle-check"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Available</small>
                        <strong>{{ $items->where('is_available', true)->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card menu-stat-card h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-secondary-subtle text-secondary">
                        <i class="fas fa-circle-pause"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Unavailable</small>
                        <strong>{{ $items->where('is_available', false)->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <div class="card menu-stat-card h-100">
                <div class="card-body py-3 d-flex align-items-center gap-3">
                    <div class="menu-stat-icon bg-info-subtle text-info">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Categories</small>
                        <strong>{{ number_format($categories->count()) }}</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- Main Panel --}}
    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">

                <form method="GET"
                      action="{{ route('admin.menu-items.index') }}"
                      class="row g-2 flex-grow-1">

                    <div class="col-12 col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-magnifying-glass text-muted"></i>
                            </span>
                            <input type="search"
                                   class="form-control"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search name, description or price">
                        </div>
                    </div>

                    <div class="col-6 col-md-3">
                        <select name="category"
                                class="form-select">
                            <option value="">All categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    @selected((string) request('category') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-6 col-md-2">
                        <select name="status"
                                class="form-select">
                            <option value="">All status</option>
                            <option value="available"
                                @selected(request('status') === 'available')>
                                Available
                            </option>
                            <option value="unavailable"
                                @selected(request('status') === 'unavailable')>
                                Unavailable
                            </option>
                        </select>
                    </div>

                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit"
                                class="btn btn-outline-primary flex-fill">
                            <i class="fas fa-filter me-1"></i>
                            Filter
                        </button>

                        @if(request()->hasAny(['search', 'category', 'status']))
                            <a href="{{ route('admin.menu-items.index') }}"
                               class="btn btn-outline-secondary"
                               title="Clear filters">
                                <i class="fas fa-rotate-left"></i>
                            </a>
                        @endif
                    </div>

                </form>

                <div class="d-flex flex-wrap gap-2">

                    @if(Route::has('admin.menu-items.csv'))
                        <a href="{{ route('admin.menu-items.csv', request()->query()) }}"
                           class="btn btn-outline-success">
                            <i class="fas fa-file-csv me-1"></i>
                            CSV
                        </a>
                    @endif

                    @if(Route::has('admin.menu-items.pdf'))
                        <a href="{{ route('admin.menu-items.pdf', request()->query()) }}"
                           class="btn btn-outline-danger">
                            <i class="fas fa-file-pdf me-1"></i>
                            PDF
                        </a>
                    @endif

                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#addItemModal">
                        <i class="fas fa-plus me-1"></i>
                        Add Item
                    </button>

                </div>

            </div>
        </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle"
                       style="min-width: 920px;">

                    <thead>
                    <tr>
                        <th class="ps-3" style="width:78px;">Image</th>
                        <th>Item</th>
                        <th>Category</th>
                        <th class="text-end">Selling Price</th>
                        <th class="text-end">Cost Price</th>
                        <th class="text-center">Prep</th>
                        <th class="text-center">Inventory</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                    </thead>

                    <tbody>
                    @forelse($items as $item)

                        <tr>
                            <td class="ps-3">
                                @if($item->image_path)
                                    <img src="{{ $item->image_url }}"
                                         alt="{{ $item->name }}"
                                         class="menu-item-image border"
                                         loading="lazy">
                                @else
                                    <div class="menu-item-placeholder">
                                        <i class="fas fa-image"></i>
                                    </div>
                                @endif
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    {{ $item->name }}
                                </div>

                                @if($item->description)
                                    <small class="text-muted d-block text-truncate"
                                           style="max-width:250px;"
                                           title="{{ $item->description }}">
                                        {{ $item->description }}
                                    </small>
                                @endif
                            </td>

                            <td>
                                @if($item->category)
                                    <span class="badge text-bg-light border">
                                        <i class="fas fa-layer-group me-1"></i>
                                        {{ $item->category->name }}
                                    </span>
                                @else
                                    <span class="text-muted">
                                        Uncategorised
                                    </span>
                                @endif
                            </td>

                            <td class="text-end fw-semibold">
                                {{ config('restaurant.currency', 'UGX') }}
                                {{ number_format((float) $item->price) }}
                            </td>

                            <td class="text-end">
                                @if(!is_null($item->cost_price))
                                    {{ config('restaurant.currency', 'UGX') }}
                                    {{ number_format((float) $item->cost_price) }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>

                            <td class="text-center">
                                <span class="badge text-bg-light border">
                                    <i class="fas fa-clock me-1"></i>
                                    {{ (int) ($item->preparation_minutes ?? 0) }} min
                                </span>
                            </td>

                            <td class="text-center">
                                @if($item->track_inventory)
                                    <span class="badge text-bg-info">
                                        <i class="fas fa-boxes-stacked me-1"></i>
                                        Tracked
                                    </span>
                                @else
                                    <span class="badge text-bg-light border text-muted">
                                        <i class="fas fa-box-open me-1"></i>
                                        Not tracked
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($item->is_available)
                                    <span class="badge text-bg-success">
                                        <i class="fas fa-circle-check me-1"></i>
                                        Available
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">
                                        <i class="fas fa-circle-pause me-1"></i>
                                        Unavailable
                                    </span>
                                @endif
                            </td>

                            <td class="text-end pe-3">
                                <div class="d-inline-flex gap-1">

                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editItemModal{{ $item->id }}"
                                            title="Edit">
                                        <i class="fas fa-pen-to-square"></i>
                                    </button>

                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteItemModal{{ $item->id }}"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>

                                </div>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="9"
                                class="text-center py-5">

                                <div class="text-muted">
                                    <i class="fas fa-burger fa-2x mb-3 d-block"></i>

                                    <strong class="d-block">
                                        No menu items found
                                    </strong>

                                    <small>
                                        Add your first menu item or change the current filters.
                                    </small>
                                </div>

                            </td>
                        </tr>

                    @endforelse
                    </tbody>

                </table>

            </div>

        </div>

        @if(method_exists($items, 'links'))
            <div class="card-footer bg-white border-top">
                {{ $items->withQueryString()->links() }}
            </div>
        @endif

    </div>
</div>


{{-- =========================================================
     ADD ITEM MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="addItemModal"
     tabindex="-1"
     aria-labelledby="addItemModalLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.menu-items.store') }}"
              enctype="multipart/form-data">

            @csrf

            <div class="modal-header">

                <div>
                    <h5 class="modal-title"
                        id="addItemModalLabel">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>
                        Add Menu Item
                    </h5>

                    <small class="text-muted">
                        Create a food, drink or service item.
                    </small>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <input type="hidden"
                       name="restaurant_id"
                       value="{{ old('restaurant_id', 1) }}">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">
                            Item Name <span class="text-danger">*</span>
                        </label>

                        <input type="text"
                               class="form-control"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               maxlength="255">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Category <span class="text-danger">*</span>
                        </label>

                        <select class="form-select"
                                name="menu_category_id"
                                required>

                            <option value="">Select category</option>

                            @foreach($categories as $category)
                                <option value="{{ $category->id }}"
                                    @selected((string) old('menu_category_id') === (string) $category->id)>
                                    {{ $category->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Selling Price <span class="text-danger">*</span>
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">
                                {{ config('restaurant.currency', 'UGX') }}
                            </span>

                            <input type="number"
                                   min="0"
                                   step="0.01"
                                   class="form-control"
                                   name="price"
                                   value="{{ old('price') }}"
                                   required>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Cost Price
                        </label>

                        <div class="input-group">
                            <span class="input-group-text">
                                {{ config('restaurant.currency', 'UGX') }}
                            </span>

                            <input type="number"
                                   min="0"
                                   step="0.01"
                                   class="form-control"
                                   name="cost_price"
                                   value="{{ old('cost_price', 0) }}">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Preparation Time
                        </label>

                        <div class="input-group">
                            <input type="number"
                                   min="0"
                                   class="form-control"
                                   name="preparation_minutes"
                                   value="{{ old('preparation_minutes', 0) }}">

                            <span class="input-group-text">
                                minutes
                            </span>
                        </div>
                    </div>

                    <div class="col-md-7">
                        <label class="form-label">
                            Image
                        </label>

                        <input type="file"
                               class="form-control"
                               id="addItemImage"
                               name="image"
                               accept="image/jpeg,image/png,image/webp">

                        <small class="text-muted">
                            JPG, PNG or WebP.
                        </small>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">
                            Preview
                        </label>

                        <div class="menu-image-preview d-flex align-items-center justify-content-center">
                            <img id="addItemImagePreview"
                                 alt="Image preview"
                                 class="d-none">

                            <span id="addItemImagePlaceholder"
                                  class="text-muted">
                                <i class="fas fa-image me-1"></i>
                                No image selected
                            </span>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">
                            Description
                        </label>

                        <textarea class="form-control"
                                  name="description"
                                  rows="3"
                                  maxlength="2000"
                                  placeholder="Short description for customers and staff">{{ old('description') }}</textarea>
                    </div>

                    <div class="col-12">
                        <div class="border rounded p-3 bg-light">

                            <div class="form-check form-switch mb-3">
                                <input type="hidden"
                                       name="is_available"
                                       value="0">

                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_available"
                                       value="1"
                                       id="addItemAvailable"
                                       @checked((bool) old('is_available', 1))>

                                <label class="form-check-label fw-semibold"
                                       for="addItemAvailable">
                                    Available for sale
                                </label>
                            </div>

                            <div class="form-check form-switch">
                                <input type="hidden"
                                       name="track_inventory"
                                       value="0">

                                <input class="form-check-input"
                                       type="checkbox"
                                       name="track_inventory"
                                       value="1"
                                       id="addItemTrackInventory"
                                       @checked((bool) old('track_inventory', 0))>

                                <label class="form-check-label fw-semibold"
                                       for="addItemTrackInventory">
                                    Track inventory for this item
                                </label>
                            </div>

                        </div>
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
     EDIT / DELETE MODALS
     IMPORTANT: rendered outside <table>/<tbody>.
========================================================= --}}
@foreach($items as $item)
    @include('menu-items.modals', [
        'item' => $item,
        'categories' => $categories,
    ])
@endforeach

@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const addInput = document.getElementById('addItemImage');
    const addPreview = document.getElementById('addItemImagePreview');
    const addPlaceholder = document.getElementById('addItemImagePlaceholder');

    if (addInput && addPreview && addPlaceholder) {
        addInput.addEventListener('change', function () {
            const file = this.files && this.files[0];

            if (!file) {
                addPreview.removeAttribute('src');
                addPreview.classList.add('d-none');
                addPlaceholder.classList.remove('d-none');
                return;
            }

            addPreview.src = URL.createObjectURL(file);
            addPreview.classList.remove('d-none');
            addPlaceholder.classList.add('d-none');
        });
    }

    document.querySelectorAll('[data-image-input]').forEach(function (input) {

        input.addEventListener('change', function () {

            const file = this.files && this.files[0];
            const previewId = this.dataset.previewTarget;
            const placeholderId = this.dataset.placeholderTarget;

            const preview = document.getElementById(previewId);
            const placeholder = placeholderId
                ? document.getElementById(placeholderId)
                : null;

            if (!file || !preview) {
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.classList.remove('d-none');

            if (placeholder) {
                placeholder.classList.add('d-none');
            }
        });

    });

    setTimeout(function () {
        document.querySelectorAll('.alert-dismissible').forEach(function (element) {
            if (window.bootstrap) {
                bootstrap.Alert.getOrCreateInstance(element).close();
            }
        });
    }, 5000);

});
</script>
@endpush
