<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Menu item variants (sizes/portions that set the price) and add-ons (extras
 * that add to it). Orders snapshot names and prices, so options can be removed
 * without changing history.
 */
class MenuOptionController extends Controller
{
    private const TYPES = [
        'variants' => ['model' => MenuItemVariant::class, 'table' => 'menu_item_variants', 'label' => 'Variant'],
        'addons' => ['model' => MenuItemAddon::class, 'table' => 'menu_item_addons', 'label' => 'Add-on'],
    ];

    public function index(Request $request, string $type): View
    {
        $restaurantId = $this->authorizeOptions($request);
        $items = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->with([$type, 'category:id,name'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . trim((string) $request->query('search')) . '%'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.menu-options.index', [
            'items' => $items,
            'type' => $type,
            'label' => self::TYPES[$type]['label'],
        ]);
    }

    public function store(Request $request, string $type, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeOptions($request);
        $item = MenuItem::query()->where('restaurant_id', $restaurantId)->findOrFail($request->integer('menu_item_id'));
        $data = $request->validate($this->rules($type, $item->id));
        $option = self::TYPES[$type]['model']::create([...$data, 'menu_item_id' => $item->id, 'is_available' => true]);
        $logger->log('menu.' . $type . '.created', $option, ['menu_item_id' => $item->id, 'name' => $option->name, 'price' => $option->price]);

        return back()->with('success', self::TYPES[$type]['label'] . ' "' . $option->name . '" added to ' . $item->name . '.');
    }

    public function update(Request $request, string $type, int $option, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeOptions($request);
        $record = $this->findOption($type, $option, $restaurantId);
        $data = $request->validate([
            ...$this->rules($type, $record->menu_item_id, $record->id),
            'is_available' => ['nullable', 'boolean'],
        ]);
        $record->update([...$data, 'is_available' => $request->boolean('is_available')]);
        $logger->log('menu.' . $type . '.updated', $record, ['fields' => array_keys($record->getChanges())]);

        return back()->with('success', self::TYPES[$type]['label'] . ' updated.');
    }

    public function destroy(Request $request, string $type, int $option, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeOptions($request);
        $record = $this->findOption($type, $option, $restaurantId);
        $record->delete();
        $logger->log('menu.' . $type . '.deleted', null, ['option_id' => $option, 'name' => $record->name]);

        return back()->with('success', self::TYPES[$type]['label'] . ' removed.');
    }

    private function rules(string $type, int $itemId, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(self::TYPES[$type]['table'], 'name')->where('menu_item_id', $itemId)->ignore($ignoreId)],
            'price' => ['required', 'numeric', $type === 'variants' ? 'gt:0' : 'min:0', 'max:100000000'],
            'display_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ];
    }

    private function findOption(string $type, int $id, int $restaurantId): Model
    {
        return self::TYPES[$type]['model']::query()
            ->whereKey($id)
            ->whereHas('menuItem', fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->firstOrFail();
    }

    private function authorizeOptions(Request $request): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless(
            $user->can('menu.options.manage') || $user->can('admin.menu-items.update')
                || $user->can('admin.menu-items.manage') || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
