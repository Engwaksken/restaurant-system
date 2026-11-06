<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeInventoryService
{
    /** Consume recipe ingredient quantities; call inside the order transaction. */
    public function consume(int $restaurantId, int $branchId, array $orderLines, ?int $userId, int $orderId): void
    {
        $menuQuantities = [];
        foreach ($orderLines as $line) {
            $menuId = (int) $line['menu_item_id'];
            $menuQuantities[$menuId] = ($menuQuantities[$menuId] ?? 0) + (float) $line['quantity'];
        }

        $trackedMenuIds = DB::table('menu_items')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', array_keys($menuQuantities))
            ->where('track_inventory', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($trackedMenuIds === []) {
            return;
        }

        $recipeMap = DB::table('recipes')
            ->where('restaurant_id', $restaurantId)
            ->whereIn('menu_item_id', $trackedMenuIds)
            ->lockForUpdate()
            ->get(['id', 'menu_item_id'])
            ->keyBy('menu_item_id');
        $recipeRows = DB::table('recipes')
            ->join('recipe_items', 'recipe_items.recipe_id', '=', 'recipes.id')
            ->join('inventory_items', 'inventory_items.id', '=', 'recipe_items.inventory_item_id')
            ->where('recipes.restaurant_id', $restaurantId)
            ->whereIn('recipes.menu_item_id', $trackedMenuIds)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->whereNull('inventory_items.deleted_at')
            ->where('inventory_items.is_active', true)
            ->select('recipes.id as recipe_id', 'recipes.menu_item_id', 'recipe_items.inventory_item_id', 'recipe_items.quantity', 'inventory_items.unit_cost')
            ->get();

        $coveredMenuIds = $recipeRows->pluck('menu_item_id')->map(fn ($id) => (int) $id)->unique()->all();
        $missingRecipes = array_diff($trackedMenuIds, $coveredMenuIds);
        if ($missingRecipes !== []) {
            throw ValidationException::withMessages([
                'items' => 'Inventory-tracked menu items need a complete recipe before they can be sold.',
            ]);
        }

        $validCounts = $recipeRows->countBy('recipe_id');
        foreach ($recipeMap as $menuId => $recipe) {
            $expectedCount = DB::table('recipe_items')->where('recipe_id', $recipe->id)->count();
            if ($expectedCount === 0 || (int) $validCounts->get($recipe->id, 0) !== $expectedCount) {
                throw ValidationException::withMessages([
                    'items' => 'The recipe includes inactive or out-of-restaurant stock items and must be corrected.',
                ]);
            }
        }

        $requirements = [];
        $costs = [];
        foreach ($recipeRows as $row) {
            $inventoryId = (int) $row->inventory_item_id;
            $quantity = (float) $row->quantity * $menuQuantities[(int) $row->menu_item_id];
            $requirements[$inventoryId] = ($requirements[$inventoryId] ?? 0) + $quantity;
            $costs[$inventoryId] = (float) $row->unit_cost;
        }

        $stocks = DB::table('inventory_stocks')
            ->where('branch_id', $branchId)
            ->whereIn('inventory_item_id', array_keys($requirements))
            ->orderBy('inventory_item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('inventory_item_id');

        foreach ($requirements as $inventoryId => $needed) {
            $stock = $stocks->get($inventoryId);
            if (!$stock || (float) $stock->quantity + 0.000001 < $needed) {
                throw ValidationException::withMessages([
                    'items' => 'There is insufficient branch stock to fulfill this order.',
                ]);
            }
        }

        foreach ($requirements as $inventoryId => $needed) {
            DB::table('inventory_stocks')
                ->where('branch_id', $branchId)
                ->where('inventory_item_id', $inventoryId)
                ->decrement('quantity', $needed, ['updated_at' => now()]);

            DB::table('stock_movements')->insert([
                'branch_id' => $branchId,
                'inventory_item_id' => $inventoryId,
                'user_id' => $userId,
                'type' => 'sale',
                'quantity' => -$needed,
                'unit_cost' => $costs[$inventoryId],
                'reference_type' => 'order',
                'reference_id' => $orderId,
                'notes' => 'Recipe consumption for order',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
