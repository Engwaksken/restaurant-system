<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quick "86" switch for menu items. Requests carry the desired state rather
 * than toggling, so two screens acting at once cannot undo each other.
 * Unavailable items are rejected by POS and public checkout.
 */
class MenuAvailabilityController extends Controller
{
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeAvailability($request);
        $filters = $request->validate([
            'category' => ['nullable', 'integer'],
            'show' => ['nullable', 'in:all,available,unavailable'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $show = $filters['show'] ?? 'all';

        $items = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->with('category:id,name')
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('menu_category_id', $category))
            ->when($show !== 'all', fn ($query) => $query->where('is_available', $show === 'available'))
            ->when(trim((string) ($filters['search'] ?? '')) !== '', fn ($query) => $query->where('name', 'like', '%' . trim($filters['search']) . '%'))
            ->orderBy('menu_category_id')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        $categories = MenuCategory::query()->where('restaurant_id', $restaurantId)->orderBy('display_order')->orderBy('name')->get(['id', 'name']);
        $unavailableCount = MenuItem::query()->where('restaurant_id', $restaurantId)->where('is_available', false)->count();

        $total = MenuItem::query()->where('restaurant_id', $restaurantId)->count();
        $pageStats = [
            ['label' => 'Menu items', 'value' => number_format($total), 'icon' => 'fas fa-burger', 'tone' => 'brand'],
            ['label' => 'Available', 'value' => number_format($total - $unavailableCount), 'icon' => 'fas fa-circle-check', 'tone' => 'success', 'hint' => $total ? round(($total - $unavailableCount) / $total * 100) . '% of the menu' : null],
            ['label' => 'Unavailable', 'value' => number_format($unavailableCount), 'icon' => 'fas fa-circle-pause', 'tone' => $unavailableCount ? 'danger' : 'neutral', 'hint' => 'Cannot be ordered'],
            ['label' => 'Categories', 'value' => number_format($categories->count()), 'icon' => 'fas fa-layer-group', 'tone' => 'info'],
        ];

        return view('admin.menu-availability.index', compact('items', 'categories', 'filters', 'show', 'unavailableCount', 'pageStats'));
    }

    public function update(Request $request, int $item, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeAvailability($request);
        $record = MenuItem::query()->where('restaurant_id', $restaurantId)->findOrFail($item);
        $data = $request->validate(['is_available' => ['required', 'boolean']]);
        $available = (bool) $data['is_available'];

        if ($record->is_available !== $available) {
            $record->update(['is_available' => $available]);
            $logger->log('menu.availability.updated', $record, ['name' => $record->name, 'is_available' => $available]);
        }

        return back()->with('success', $record->name . ($available ? ' is available again.' : ' is now unavailable.'));
    }

    private function authorizeAvailability(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless(
            $user->can('menu.availability.manage')
                || $user->can('admin.menu-items.update')
                || $user->can('admin.menu-items.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
