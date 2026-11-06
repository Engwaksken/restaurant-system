<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuCategoryController extends Controller
{
    public function index(): View
    {
        $restaurantId = $this->authorizeCategories('view');
        $categories = MenuCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->withCount('items')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('admin.menu-categories.index', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeCategories('manage');
        $data = $this->validated($request, $restaurantId);
        MenuCategory::create([
            'restaurant_id' => $restaurantId,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($restaurantId, $data['name']),
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Menu category created.');
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $restaurantId = $this->authorizeCategories('manage');
        $record = MenuCategory::query()->where('restaurant_id', $restaurantId)->findOrFail($category);
        $data = $this->validated($request, $restaurantId, $record->id);
        $record->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($restaurantId, $data['name'], $record->id),
            'description' => $data['description'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Menu category updated.');
    }

    public function destroy(int $category): RedirectResponse
    {
        $restaurantId = $this->authorizeCategories('manage');
        $record = MenuCategory::query()->where('restaurant_id', $restaurantId)->findOrFail($category);
        if ($record->items()->exists()) {
            return back()->withErrors(['category' => 'Move or delete the category items before removing this category.']);
        }
        $record->delete();

        return back()->with('success', 'Menu category deleted.');
    }

    private function validated(Request $request, int $restaurantId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['nullable', Rule::in([0, 1, '0', '1', true, false])],
        ]);
    }

    private function uniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 1;
        while (MenuCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function authorizeCategories(string $operation): int
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('menu.categories.' . $operation)
                || $user->can('menu.categories.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
