@extends('layouts.app')

@section('title', 'Recipes')
@section('page-title', 'Inventory Recipes')

@section('content')
<div class="row g-4">
    <div class="col-lg-5">
        <div class="panel p-4">
            <h2 class="h5">Set recipe</h2>
            <p class="text-muted">Ingredient quantities are consumed per one menu item and use each inventory item's usage unit.</p>
            @if($menuItems->isEmpty() || $inventoryItems->isEmpty())
                <div class="alert alert-warning">Add inventory items and mark menu items as inventory-tracked before defining recipes.</div>
            @else
                <form method="POST" action="{{ route('admin.recipes.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label for="menuItem" class="form-label">Menu item</label>
                        <select class="form-select" id="menuItem" name="menu_item_id" required>
                            @foreach($menuItems as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                        </select>
                    </div>
                    <div id="ingredients">
                        <div class="row g-2 mb-2 ingredient-row">
                            <div class="col-7"><select class="form-select" name="ingredients[0][inventory_item_id]" required>@foreach($inventoryItems as $item)<option value="{{ $item->id }}">{{ $item->name }} ({{ $item->usage_unit }})</option>@endforeach</select></div>
                            <div class="col-4"><input class="form-control" type="number" step="0.001" min="0.001" name="ingredients[0][quantity]" placeholder="Quantity" required></div>
                            <div class="col-1"><button class="btn btn-outline-danger remove-ingredient" type="button" aria-label="Remove ingredient">×</button></div>
                        </div>
                    </div>
                    <button class="btn btn-outline-secondary" type="button" id="addIngredient">Add ingredient</button>
                    <button class="btn btn-primary" type="submit">Save recipe</button>
                </form>
            @endif
        </div>
    </div>
    <div class="col-lg-7">
        <div class="panel p-4">
            <h2 class="h5">Recipes</h2>
            @forelse($recipes as $recipe)
                <div class="border-bottom py-3 d-flex justify-content-between gap-3">
                    <div>
                        <strong>{{ $recipe->menuItem?->name ?? 'Deleted menu item' }}</strong>
                        <ul class="mb-0">@foreach($recipe->ingredients as $ingredient)<li>{{ $ingredient->inventoryItem?->name ?? 'Deleted stock item' }}: {{ $ingredient->quantity }} {{ $ingredient->inventoryItem?->usage_unit }}</li>@endforeach</ul>
                    </div>
                    <form method="POST" action="{{ route('admin.recipes.destroy', $recipe->id) }}" onsubmit="return confirm('Delete this recipe?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                    </form>
                </div>
            @empty
                <p class="text-muted">No recipes configured yet.</p>
            @endforelse
            {{ $recipes->links() }}
        </div>
    </div>
</div>
<script>
document.getElementById('addIngredient')?.addEventListener('click', () => {
    const container = document.getElementById('ingredients');
    const index = container.querySelectorAll('.ingredient-row').length;
    const first = container.querySelector('.ingredient-row');
    const row = first.cloneNode(true);
    row.querySelectorAll('select, input').forEach((field) => {
        field.name = field.name.replace(/ingredients\[\d+\]/, `ingredients[${index}]`);
        if (field.tagName === 'INPUT') field.value = '';
    });
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
</script>
@endsection
