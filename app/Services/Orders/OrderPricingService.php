<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Server-side pricing shared by POS and public checkout.
 *
 * unit price = chosen variant price (or the item price) + selected add-on prices.
 * Items with available variants require one. Client prices are never trusted.
 * Call inside the order transaction: menu items are locked while pricing.
 */
class OrderPricingService
{
    /**
     * @param  array<int, array{menu_item_id: int|string, quantity: int|float|string, variant_id?: int|string|null, addon_ids?: array<int, int|string>|null, notes?: string|null}>  $lines
     * @param  callable(Builder): void|null  $scope  extra item constraints (e.g. active category)
     * @return array{lines: array<int, array<string, mixed>>, subtotal: float}
     */
    public function price(int $restaurantId, array $lines, ?callable $scope = null): array
    {
        $ids = collect($lines)->pluck('menu_item_id')->map(fn ($id) => (int) $id)->unique()->values();
        $items = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_available', true)
            ->whereIn('id', $ids)
            ->when($scope, fn (Builder $query) => $scope($query))
            ->with(['variants', 'addons'])
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages(['items' => 'One or more menu items are no longer available.']);
        }

        $priced = [];
        $subtotal = 0.0;
        foreach (array_values($lines) as $index => $line) {
            /** @var MenuItem $item */
            $item = $items[(int) $line['menu_item_id']];
            $availableVariants = $item->variants->where('is_available', true)->keyBy('id');
            $variantId = isset($line['variant_id']) && $line['variant_id'] !== '' ? (int) $line['variant_id'] : null;

            $variant = null;
            if ($variantId !== null) {
                $variant = $availableVariants->get($variantId);
                if (!$variant) {
                    throw ValidationException::withMessages(["items.$index.variant_id" => 'The selected option for ' . $item->name . ' is not available.']);
                }
            } elseif ($availableVariants->isNotEmpty()) {
                throw ValidationException::withMessages(["items.$index.variant_id" => 'Choose an option for ' . $item->name . '.']);
            }

            $availableAddons = $item->addons->where('is_available', true)->keyBy('id');
            $addonIds = collect($line['addon_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            $addons = [];
            foreach ($addonIds as $addonId) {
                $addon = $availableAddons->get($addonId);
                if (!$addon) {
                    throw ValidationException::withMessages(["items.$index.addon_ids" => 'An extra chosen for ' . $item->name . ' is not available.']);
                }
                $addons[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => (float) $addon->price];
            }

            $quantity = (float) $line['quantity'];
            $unit = round((float) ($variant?->price ?? $item->price) + array_sum(array_column($addons, 'price')), 2);
            $total = round($unit * $quantity, 2);
            $subtotal += $total;

            $priced[] = [
                'menu_item_id' => $item->id,
                'menu_item_variant_id' => $variant?->id,
                'item_name' => $item->name,
                'variant_name' => $variant?->name,
                'addons' => $addons === [] ? null : $addons,
                'unit_price' => $unit,
                'quantity' => $quantity,
                'total' => $total,
                'notes' => isset($line['notes']) && trim((string) $line['notes']) !== '' ? trim((string) $line['notes']) : null,
            ];
        }

        return ['lines' => $priced, 'subtotal' => round($subtotal, 2)];
    }

    /** Validation rules for the line payload shared by POS and public checkout. */
    public static function lineRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.addon_ids' => ['nullable', 'array', 'max:20'],
            'items.*.addon_ids.*' => ['integer'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
