@extends('layouts.app')

@section('title', 'Recipes')
@section('page-title', 'Inventory Recipes')

@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $canSetRecipe = ! ($menuItems->isEmpty() || $inventoryItems->isEmpty());
    $ingredientRows = old('ingredients', [[]]);
@endphp

<div class="container-fluid px-0">

    @if($errors->any() && ! $canSetRecipe)
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
            <h2 class="h5 mb-1">Recipes</h2>
            <p class="text-muted small mb-0">Ingredient quantities are consumed per one menu item and use each inventory item's usage unit.</p>
        </div>

        @if($canSetRecipe)
            <button type="button"
                    class="btn btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#add-recipe">
                <i class="fas fa-plus me-1"></i>
                Set recipe
            </button>
        @else
            <div class="alert alert-warning mb-0 py-2">
                Add inventory items and mark menu items as inventory-tracked before defining recipes.
            </div>
        @endif
    </div>

    <div class="panel p-4">
        @forelse($recipes as $recipe)
            <div class="border-bottom py-3 d-flex justify-content-between gap-3">
                <div>
                    <strong>{{ $recipe->menuItem?->name ?? 'Deleted menu item' }}</strong>
                    <ul class="mb-0">@foreach($recipe->ingredients as $ingredient)<li>{{ $ingredient->inventoryItem?->name ?? 'Deleted stock item' }}: {{ $ingredient->quantity }} {{ $ingredient->inventoryItem?->usage_unit }}</li>@endforeach</ul>
                </div>
                <div>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            data-bs-toggle="modal"
                            data-bs-target="#delete-recipe-{{ $recipe->id }}"
                            title="Delete">
                        <i class="fas fa-trash me-1"></i>
                        Delete
                    </button>
                </div>
            </div>
        @empty
            <p class="text-muted">No recipes configured yet.</p>
        @endforelse
        {{ $recipes->links() }}
    </div>

</div>


@if($canSetRecipe)
{{-- =========================================================
     SET RECIPE MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade"
     id="add-recipe"
     tabindex="-1"
     aria-labelledby="add-recipe-label"
     aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.recipes.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title" id="add-recipe-label">
                    <i class="fas fa-book-open me-2 text-primary"></i>
                    Set Recipe
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">

                <p class="text-muted small">Saving a recipe for a menu item replaces its existing ingredient list.</p>

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

                <div class="mb-3">
                    <label for="menuItem" class="form-label">
                        Menu item <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <select class="form-select @error('menu_item_id') is-invalid @enderror"
                            id="menuItem"
                            name="menu_item_id"
                            required>
                        @foreach($menuItems as $item)
                            <option value="{{ $item->id }}"
                                @selected((string) old('menu_item_id') === (string) $item->id)>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('menu_item_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <label class="form-label">
                    Ingredients <span class="text-danger" aria-hidden="true">*</span>
                </label>

                @error('ingredients')
                    <div class="alert alert-danger py-2 mb-2">{{ $message }}</div>
                @enderror

                <div id="ingredients">
                    @foreach($ingredientRows as $i => $row)
                        <div class="row g-2 mb-2 ingredient-row">
                            <div class="col-7">
                                <select class="form-select @error("ingredients.$i.inventory_item_id") is-invalid @enderror"
                                        name="ingredients[{{ $i }}][inventory_item_id]"
                                        aria-label="Ingredient"
                                        required>
                                    @foreach($inventoryItems as $item)
                                        <option value="{{ $item->id }}"
                                            @selected((string) ($row['inventory_item_id'] ?? '') === (string) $item->id)>
                                            {{ $item->name }} ({{ $item->usage_unit }})
                                        </option>
                                    @endforeach
                                </select>
                                @error("ingredients.$i.inventory_item_id")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-4">
                                <input class="form-control @error("ingredients.$i.quantity") is-invalid @enderror"
                                       type="number"
                                       step="0.001"
                                       min="0.001"
                                       name="ingredients[{{ $i }}][quantity]"
                                       value="{{ $row['quantity'] ?? '' }}"
                                       placeholder="e.g. 150"
                                       aria-label="Quantity"
                                       required>
                                @error("ingredients.$i.quantity")
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-1">
                                <button class="btn btn-outline-danger remove-ingredient w-100"
                                        type="button"
                                        aria-label="Remove ingredient">
                                    <i class="fas fa-xmark"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <button class="btn btn-outline-secondary btn-sm" type="button" id="addIngredient">
                    <i class="fas fa-plus me-1"></i>
                    Add ingredient
                </button>

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
                    Save Recipe
                </button>

            </div>

        </form>

    </div>
</div>
@endif


{{-- =========================================================
     DELETE RECIPE CONFIRMATIONS
     OUTSIDE THE LIST
========================================================= --}}
@foreach($recipes as $recipe)
    <div class="modal fade"
         id="delete-recipe-{{ $recipe->id }}"
         tabindex="-1"
         aria-labelledby="delete-recipe-label-{{ $recipe->id }}"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.recipes.destroy', $recipe->id) }}">

                @csrf
                @method('DELETE')

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="delete-recipe-label-{{ $recipe->id }}">
                        Delete Recipe
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body text-center py-4">

                    <div class="rounded-circle bg-danger-subtle text-danger d-inline-flex align-items-center justify-content-center mb-3"
                         style="width:56px;height:56px;">
                        <i class="fas fa-trash fa-lg"></i>
                    </div>

                    <h5 class="mb-2">
                        Delete recipe for {{ $recipe->menuItem?->name ?? 'this deleted menu item' }}?
                    </h5>

                    <p class="text-muted mb-0">
                        Its ingredient list will be removed. The menu item and stock items are not changed.
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
                        <i class="fas fa-trash me-1"></i>
                        Delete
                    </button>

                </div>

            </form>

        </div>
    </div>
@endforeach

@endsection


@push('scripts')
<script>
document.getElementById('addIngredient')?.addEventListener('click', () => {
    const container = document.getElementById('ingredients');
    const index = container.querySelectorAll('.ingredient-row').length;
    const first = container.querySelector('.ingredient-row');
    const row = first.cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/ingredients\[\d+\]/, `ingredients[${index}]`);
        field.classList.remove('is-invalid');
        if (field.tagName === 'INPUT') field.value = '';
    });
    row.querySelectorAll('.invalid-feedback').forEach((feedback) => feedback.remove());
    container.append(row);
});
document.getElementById('ingredients')?.addEventListener('click', (event) => {
    if (event.target.closest('.remove-ingredient') && document.querySelectorAll('.ingredient-row').length > 1) {
        event.target.closest('.ingredient-row').remove();
        document.querySelectorAll('.ingredient-row').forEach((row, index) => row.querySelectorAll('select, input').forEach((field) => {
            field.name = field.name.replace(/ingredients\[\d+\]/, `ingredients[${index}]`);
        }));
    }
});

document.addEventListener('DOMContentLoaded', function () {
    @if($canSetRecipe && $errors->any())
        const recipeModal = document.getElementById('add-recipe');

        if (recipeModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(recipeModal).show();
        }
    @endif
});
</script>
@endpush
