@extends('layouts.app')

@section('title', 'Inventory')
@section('page-title', 'Inventory Management')

@section('content')
<div class="row g-4">
    <div class="col-xl-4">
        <div class="panel p-4 mb-4">
            <h2 class="h5">Add stock item</h2>
            <form method="POST" action="{{ route('admin.inventory.products.store') }}">
                @csrf
                <label class="form-label" for="inventoryName">Name</label>
                <input id="inventoryName" class="form-control mb-2" name="name" required maxlength="255">
                <label class="form-label" for="inventorySku">SKU (optional)</label>
                <input id="inventorySku" class="form-control mb-2" name="sku" maxlength="255">
                <label class="form-label" for="inventoryCategory">Category</label>
                <select id="inventoryCategory" class="form-select mb-2" name="inventory_category_id">
                    <option value="">Uncategorised</option>
                    @foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach
                </select>
                <div class="row g-2">
                    <div class="col"><label class="form-label">Purchase unit</label><input class="form-control" name="purchase_unit" value="unit" required maxlength="50"></div>
                    <div class="col"><label class="form-label">Recipe usage unit</label><input class="form-control" name="usage_unit" value="unit" required maxlength="50"></div>
                </div>
                <label class="form-label mt-2">Usage units per purchase unit</label>
                <input class="form-control mb-2" type="number" name="conversion_factor" value="1" min="0.0001" step="0.0001" required>
                <label class="form-label">Unit cost</label>
                <input class="form-control mb-3" type="number" name="unit_cost" value="0" min="0" step="0.01" required>
                <button class="btn btn-primary" type="submit">Add item</button>
            </form>
        </div>

        <div class="panel p-4 mb-4">
            <h2 class="h5">Add category</h2>
            <form method="POST" action="{{ route('admin.inventory.categories.store') }}">
                @csrf
                <label class="form-label" for="categoryName">Name</label>
                <input id="categoryName" class="form-control mb-2" name="name" required maxlength="255">
                <label class="form-label" for="categoryDescription">Description (optional)</label>
                <textarea id="categoryDescription" class="form-control mb-3" name="description" maxlength="1000"></textarea>
                <button class="btn btn-outline-primary" type="submit">Add category</button>
            </form>
        </div>

        <div class="panel p-4">
            <h2 class="h5">Record stock adjustment</h2>
            @if($branches->isEmpty() || $activeItems->isEmpty())
                <p class="text-muted">An active branch and stock item are required.</p>
            @else
                <form method="POST" action="{{ route('admin.inventory.stock.adjust') }}">
                    @csrf
                    <label class="form-label" for="stockBranch">Branch</label>
                    <select id="stockBranch" class="form-select mb-2" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                    <label class="form-label" for="stockItem">Stock item</label>
                    <select id="stockItem" class="form-select mb-2" name="inventory_item_id" required>@foreach($activeItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>
                    <label class="form-label" for="stockChange">Quantity change (+ add, - remove)</label>
                    <input id="stockChange" class="form-control mb-2" type="number" name="quantity_change" step="0.001" min="-1000000" max="1000000" required>
                    <label class="form-label" for="stockReason">Reason (required for audit)</label>
                    <textarea id="stockReason" class="form-control mb-3" name="reason" minlength="5" maxlength="1000" required></textarea>
                    <button class="btn btn-warning" type="submit">Record adjustment</button>
                </form>
            @endif
        </div>
    </div>

    <div class="col-xl-8">
        <div class="panel p-4 mb-4">
            <h2 class="h5">Stock balances</h2>
            <div class="table-responsive"><table class="table table-striped">
                <thead><tr><th>Branch</th><th>Item</th><th>On hand</th><th>Unit</th></tr></thead>
                <tbody>@forelse($stocks as $stock)<tr><td>{{ $stock->branch_name }}</td><td>{{ $stock->item_name }}</td><td>{{ number_format((float) $stock->quantity, 3) }}</td><td>{{ $stock->usage_unit }}</td></tr>@empty<tr><td colspan="4" class="text-muted">No stock has been recorded.</td></tr>@endforelse</tbody>
            </table></div>
        </div>

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
@endsection
