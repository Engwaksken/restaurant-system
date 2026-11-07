<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MenuItemController extends Controller
{
    /**
     * Display menu items.
     */
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeMenu('view');
        $query = MenuItem::query()
            ->with('category')
            ->where('restaurant_id', $restaurantId)
            ->latest();

        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%')
                    ->orWhere('price', 'like', '%' . $search . '%');
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Category Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('category')) {
            $query->where(
                'menu_category_id',
                $request->integer('category')
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Availability Filter
        |--------------------------------------------------------------------------
        */

        if ($request->filled('status')) {
            if ($request->input('status') === 'available') {
                $query->where('is_available', true);
            }

            if ($request->input('status') === 'unavailable') {
                $query->where('is_available', false);
            }
        }

        $items = $query
            ->paginate(25)
            ->withQueryString();

        $categories = MenuCategory::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('menu-items.index', compact(
            'items',
            'categories'
        ));
    }

    /**
     * Store a menu item.
     */
    public function store(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeMenu('create');
        $validated = $this->validateRequest($request);

        /*
        |--------------------------------------------------------------------------
        | Defaults / Normalisation
        |--------------------------------------------------------------------------
        */

        $validated['restaurant_id'] = $restaurantId;

        $validated['preparation_minutes'] =
            (int) ($validated['preparation_minutes'] ?? 0);

        $validated['cost_price'] =
            isset($validated['cost_price'])
                ? (float) $validated['cost_price']
                : 0;

        $validated['is_available'] =
            $request->boolean('is_available');

        $validated['track_inventory'] =
            $request->boolean('track_inventory');

        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {
            $validated['image_path'] = $this->storeMenuImage($request);
        }

        unset($validated['image']);

        /*
        |--------------------------------------------------------------------------
        | Slug
        |--------------------------------------------------------------------------
        */

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['name']
            );
        }

        $item = MenuItem::create($validated);

        /*
        |--------------------------------------------------------------------------
        | Activity Log
        |--------------------------------------------------------------------------
        */

        if (class_exists(ActivityLogger::class)) {
            try {
                app(ActivityLogger::class)->log(
                    'menu_item.created',
                    'Created menu item: ' . $item->name,
                    $item
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('admin.menu-items.index')
            ->with(
                'success',
                'Menu item added successfully.'
            );
    }

    /**
     * Update menu item.
     */
    public function update(
        Request $request,
        MenuItem $menuItem
    ): RedirectResponse {
        $restaurantId = $this->authorizeMenu('update');
        abort_unless((int) $menuItem->restaurant_id === $restaurantId, 404);
        $validated = $this->validateRequest(
            $request,
            $menuItem
        );

        /*
        |--------------------------------------------------------------------------
        | Defaults / Normalisation
        |--------------------------------------------------------------------------
        */

        $validated['restaurant_id'] = $restaurantId;

        $validated['preparation_minutes'] =
            (int) (
                $validated['preparation_minutes']
                ?? $menuItem->preparation_minutes
                ?? 0
            );

        $validated['cost_price'] =
            isset($validated['cost_price'])
                ? (float) $validated['cost_price']
                : (float) ($menuItem->cost_price ?? 0);

        $validated['is_available'] =
            $request->boolean('is_available');

        $validated['track_inventory'] =
            $request->boolean('track_inventory');

        /*
        |--------------------------------------------------------------------------
        | Image
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('image')) {
            // Store first so an upload failure never removes the existing image.
            $newImagePath = $this->storeMenuImage($request);
            $validated['image_path'] = $newImagePath;

            if ($menuItem->image_path && $menuItem->image_path !== $newImagePath) {
                Storage::disk('public')->delete($this->normaliseImagePath($menuItem->image_path));
            }
        }

        unset($validated['image']);

        /*
        |--------------------------------------------------------------------------
        | Slug
        |--------------------------------------------------------------------------
        */

        if (array_key_exists('slug', $validated)) {
            $validated['slug'] = $this->generateUniqueSlug(
                $validated['name'],
                $menuItem->id
            );
        }

        $menuItem->update($validated);

        /*
        |--------------------------------------------------------------------------
        | Activity Log
        |--------------------------------------------------------------------------
        */

        if (class_exists(ActivityLogger::class)) {
            try {
                app(ActivityLogger::class)->log(
                    'menu_item.updated',
                    'Updated menu item: ' . $menuItem->name,
                    $menuItem
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('admin.menu-items.index')
            ->with(
                'success',
                'Menu item updated successfully.'
            );
    }

    /**
     * Delete menu item.
     */
    public function destroy(
        MenuItem $menuItem
    ): RedirectResponse {
        $restaurantId = $this->authorizeMenu('delete');
        abort_unless((int) $menuItem->restaurant_id === $restaurantId, 404);

        $itemName = $menuItem->name;

        /*
        |--------------------------------------------------------------------------
        | Remove Image
        |--------------------------------------------------------------------------
        */

        if (
            $menuItem->image_path
            && Storage::disk('public')
                ->exists($menuItem->image_path)
        ) {
            Storage::disk('public')
                ->delete($menuItem->image_path);
        }

        $menuItem->delete();

        /*
        |--------------------------------------------------------------------------
        | Activity Log
        |--------------------------------------------------------------------------
        */

        if (class_exists(ActivityLogger::class)) {
            try {
                app(ActivityLogger::class)->log(
                    'menu_item.deleted',
                    'Deleted menu item: ' . $itemName
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()
            ->route('admin.menu-items.index')
            ->with(
                'success',
                'Menu item deleted successfully.'
            );
    }

    /**
     * Validate create/update request.
     */
    private function validateRequest(
        Request $request,
        ?MenuItem $menuItem = null
    ): array {

        return $request->validate([
            'menu_category_id' => [
                'required',
                'integer',
                Rule::exists('menu_categories', 'id')->where('restaurant_id', auth()->user()->restaurant_id),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'cost_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'preparation_minutes' => [
                'nullable',
                'integer',
                'min:0',
                'max:1440',
            ],

            'is_available' => [
                'nullable',
                Rule::in([
                    0,
                    1,
                    '0',
                    '1',
                    true,
                    false,
                ]),
            ],

            'track_inventory' => [
                'nullable',
                Rule::in([
                    0,
                    1,
                    '0',
                    '1',
                    true,
                    false,
                ]),
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:4096',
            ],
        ]);
    }

    private function storeMenuImage(Request $request): string
    {
        $path = $request->file('image')?->storePublicly('menu-items', 'public');

        if (!is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'image' => 'The image could not be saved. Check that public storage is writable and try again.',
            ]);
        }

        return $path;
    }

    private function normaliseImagePath(string $path): string
    {
        $path = ltrim($path, '/');

        foreach (['storage/', 'public/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return substr($path, strlen($prefix));
            }
        }

        return $path;
    }

    private function authorizeMenu(string $operation): int
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless(
            $user->can('admin.menu-items.' . $operation)
                || $user->can('admin.menu-items.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }

    /**
     * Generate a unique slug.
     */
    private function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {

        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'menu-item';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            MenuItem::query()
                ->when(
                    $ignoreId,
                    fn ($query) =>
                        $query->where('id', '!=', $ignoreId)
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug =
                $baseSlug . '-' . $counter;

            $counter++;
        }

        return $slug;
    }
}
