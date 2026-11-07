<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public function index(): View
    {
        $restaurantId = $this->authorizeRecipes('view');
        $recipes = Recipe::query()
            ->where('restaurant_id', $restaurantId)
            ->with(['menuItem', 'ingredients.inventoryItem'])
            ->latest()
            ->paginate(20);
        $menuItems = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->where('track_inventory', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $inventoryItems = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'usage_unit']);

        $withRecipe = Recipe::query()->where('restaurant_id', $restaurantId)->distinct()->count('menu_item_id');
        $pageStats = [
            ['label' => 'Recipes', 'value' => number_format($recipes->total()), 'icon' => 'fas fa-book', 'tone' => 'brand'],
            ['label' => 'Stock-tracked dishes', 'value' => number_format($menuItems->count()), 'icon' => 'fas fa-burger', 'tone' => 'info', 'hint' => 'Deduct ingredients when sold'],
            ['label' => 'Missing a recipe', 'value' => number_format(max(0, $menuItems->count() - $withRecipe)), 'icon' => 'fas fa-circle-exclamation', 'tone' => $menuItems->count() > $withRecipe ? 'warning' : 'success'],
            ['label' => 'Ingredients available', 'value' => number_format($inventoryItems->count()), 'icon' => 'fas fa-carrot', 'tone' => 'success'],
        ];

        return view('admin.recipes.index', compact('recipes', 'menuItems', 'inventoryItems', 'pageStats'));
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeRecipes('manage');
        $validated = $request->validate([
            'menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurantId)
                    ->where('track_inventory', true)),
            ],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.inventory_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurantId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            'ingredients.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999'],
        ]);

        DB::transaction(function () use ($validated, $restaurantId): void {
            $recipe = Recipe::query()->where('menu_item_id', $validated['menu_item_id'])->lockForUpdate()->first();
            abort_if($recipe && (int) $recipe->restaurant_id !== $restaurantId, 404);
            $recipe ??= new Recipe(['restaurant_id' => $restaurantId, 'menu_item_id' => $validated['menu_item_id']]);
            $recipe->save();
            $recipe->ingredients()->delete();
            foreach ($validated['ingredients'] as $ingredient) {
                $recipe->ingredients()->create($ingredient);
            }
        });

        return back()->with('success', 'Recipe saved successfully.');
    }

    public function destroy(int $recipe): RedirectResponse
    {
        $restaurantId = $this->authorizeRecipes('manage');
        Recipe::query()->where('restaurant_id', $restaurantId)->findOrFail($recipe)->delete();

        return back()->with('success', 'Recipe deleted successfully.');
    }

    private function authorizeRecipes(string $operation): int
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('menu.recipes.' . $operation)
                || $user->can('menu.recipes.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
