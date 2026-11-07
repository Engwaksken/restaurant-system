<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function stock(Request $request): View
    {
        $restaurantId = $this->authorizeInventory('view');
        $user = $request->user();
        $stocks = DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.inventory_item_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->whereNull('inventory_items.deleted_at')
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->select('inventory_stocks.quantity', 'branches.name as branch_name', 'inventory_items.name as item_name', 'inventory_items.sku', 'inventory_items.usage_unit', 'inventory_items.minimum_stock', 'inventory_items.reorder_level')
            ->orderBy('branches.name')->orderBy('inventory_items.name')->paginate(30)->withQueryString();

        return view('admin.inventory.ledger', ['title' => 'Stock Balances', 'stocks' => $stocks, 'pageStats' => $this->inventoryStats($restaurantId, $user)]);
    }

    public function adjustments(Request $request): View
    {
        $restaurantId = $this->authorizeInventory('view');
        $user = $request->user();
        $movements = DB::table('stock_movements')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'stock_movements.inventory_item_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->select('stock_movements.*', 'branches.name as branch_name', 'inventory_items.name as item_name', 'inventory_items.usage_unit', 'users.name as actor_name')
            ->orderByDesc('stock_movements.id')->paginate(30)->withQueryString();

        $pageStats = $this->inventoryStats($restaurantId, $user);

        return view('admin.inventory.adjustments', compact('movements', 'pageStats'));
    }

    public function lowStock(Request $request): View
    {
        $restaurantId = $this->authorizeInventory('view');
        $user = $request->user();
        $stocks = DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.inventory_item_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->whereNull('inventory_items.deleted_at')
            ->where('inventory_items.is_active', true)
            ->whereRaw('inventory_stocks.quantity <= CASE WHEN inventory_items.minimum_stock >= inventory_items.reorder_level THEN inventory_items.minimum_stock ELSE inventory_items.reorder_level END')
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->select('inventory_stocks.quantity', 'branches.name as branch_name', 'inventory_items.name as item_name', 'inventory_items.usage_unit', 'inventory_items.minimum_stock', 'inventory_items.reorder_level')
            ->orderBy('branches.name')->orderBy('inventory_items.name')->paginate(30)->withQueryString();

        return view('admin.inventory.ledger', ['title' => 'Low Stock', 'stocks' => $stocks, 'pageStats' => $this->inventoryStats($restaurantId, $user)]);
    }

    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeInventory('view');
        $user = $request->user();
        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')
            ->get();
        $categories = InventoryCategory::query()->where('restaurant_id', $restaurantId)->orderBy('name')->get();
        $search = trim((string) $request->query('search', ''));
        $items = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->withTrashed()
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('sku', $search)
                ->orWhere('barcode', $search)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();
        $activeItems = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $stocks = DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.inventory_item_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('inventory_items.name', 'like', '%' . $search . '%')
                ->orWhere('inventory_items.sku', $search)
                ->orWhere('inventory_items.barcode', $search)))
            ->select('inventory_stocks.*', 'branches.name as branch_name', 'inventory_items.name as item_name', 'inventory_items.usage_unit', 'inventory_items.minimum_stock', 'inventory_items.reorder_level', 'inventory_items.barcode')
            ->orderBy('branches.name')
            ->orderBy('inventory_items.name')
            ->paginate(30, ['*'], 'stock_page')
            ->withQueryString();

        $itemIds = $items->pluck('id');
        $stockedItemIds = DB::table('inventory_stocks')->whereIn('inventory_item_id', $itemIds)->pluck('inventory_item_id')
            ->merge(DB::table('stock_movements')->whereIn('inventory_item_id', $itemIds)->pluck('inventory_item_id'))
            ->unique()->values()->all();

        $pageStats = $this->inventoryStats($restaurantId, $user);

        $tab = $request->query('tab') === 'stock' || $request->has('stock_page') ? 'stock' : 'items';

        return view('admin.inventory.index', compact('branches', 'categories', 'items', 'activeItems', 'stocks', 'stockedItemIds', 'pageStats', 'search', 'tab'));
    }

    /**
     * Find an inventory item by scanned barcode (or SKU) for this restaurant,
     * with on-hand stock per branch the user may see.
     */
    public function lookup(Request $request): JsonResponse
    {
        $restaurantId = $this->authorizeInventory('view');
        $user = $request->user();
        $code = trim((string) $request->validate(['code' => ['required', 'string', 'max:255']])['code']);

        $item = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->where(fn ($match) => $match->where('barcode', $code)->orWhere('sku', $code))
            ->first();
        if (!$item) {
            return response()->json(['found' => false, 'code' => $code]);
        }

        $stock = DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_stocks.inventory_item_id', $item->id)
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->orderBy('branches.name')
            ->get(['branches.name as branch', 'inventory_stocks.quantity']);

        return response()->json([
            'found' => true,
            'code' => $code,
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'usage_unit' => $item->usage_unit,
                'unit_cost' => (float) $item->unit_cost,
                'is_active' => (bool) $item->is_active,
                'stock' => $stock->map(fn ($row) => ['branch' => $row->branch, 'quantity' => (float) $row->quantity])->values(),
            ],
        ]);
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('manage');
        $data = $request->validate($this->itemRules($restaurantId));

        InventoryItem::create([
            ...$data,
            'restaurant_id' => $restaurantId,
            'is_active' => true,
            'track_expiry' => false,
        ]);

        return back()->with('success', 'Inventory item added successfully.');
    }

    public function updateItem(Request $request, int $item): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('manage');
        $record = InventoryItem::query()->where('restaurant_id', $restaurantId)->whereKey($item)->firstOrFail();
        $rules = $this->itemRules($restaurantId, $record->id);
        if ($this->hasStockHistory($record->id)) {
            unset($rules['purchase_unit'], $rules['usage_unit'], $rules['conversion_factor']);
        }
        $data = $request->validateWithBag('editItem' . $record->id, $rules);
        $record->update($data);

        return back()->with('success', 'Inventory item updated.');
    }

    public function destroyItem(int $item): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('manage');
        $record = InventoryItem::query()->where('restaurant_id', $restaurantId)->whereKey($item)->firstOrFail();
        $onHand = (float) DB::table('inventory_stocks')->where('inventory_item_id', $record->id)->sum('quantity');
        if (abs($onHand) > 0.0005) {
            return back()->with('error', 'This item still has stock on hand. Use a stock adjustment or transfer to bring every branch to zero before deleting it.');
        }
        $record->delete();

        return back()->with('success', 'Inventory item deleted.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_categories', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        InventoryCategory::create([...$data, 'restaurant_id' => $restaurantId]);

        return back()->with('success', 'Inventory category added successfully.');
    }

    public function adjustStock(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('adjust');
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurantId)
                    ->when($user->branch_id, fn ($branchQuery) => $branchQuery->where('id', $user->branch_id))),
            ],
            'inventory_item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurantId)
                    ->whereNull('deleted_at')
                    ->where('is_active', true)),
            ],
            'quantity_change' => ['required', 'numeric', 'not_in:0', 'between:-1000000,1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($data, $user): void {
            $now = now();
            DB::table('inventory_stocks')->insertOrIgnore([
                'branch_id' => $data['branch_id'],
                'inventory_item_id' => $data['inventory_item_id'],
                'quantity' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $stock = DB::table('inventory_stocks')
                ->where('branch_id', $data['branch_id'])
                ->where('inventory_item_id', $data['inventory_item_id'])
                ->lockForUpdate()
                ->first();
            $change = round((float) $data['quantity_change'], 3);
            abort_if($change === 0.0, 422, 'Quantity change must be at least 0.001.');
            $newQuantity = round((float) $stock->quantity + $change, 3);
            abort_if($newQuantity < 0, 422, 'Stock adjustment cannot reduce stock below zero.');

            DB::table('inventory_stocks')->where('id', $stock->id)->update([
                'quantity' => $newQuantity,
                'updated_at' => $now,
            ]);
            DB::table('stock_movements')->insert([
                'branch_id' => $data['branch_id'],
                'inventory_item_id' => $data['inventory_item_id'],
                'user_id' => $user->id,
                'type' => 'adjustment',
                'quantity' => $change,
                'unit_cost' => DB::table('inventory_items')->where('id', $data['inventory_item_id'])->value('unit_cost') ?? 0,
                'reference_type' => 'manual_adjustment',
                'reference_id' => null,
                'notes' => $data['reason'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return back()->with('success', 'Stock adjustment recorded.');
    }

    /** @return array<string, mixed> */
    private function itemRules(int $restaurantId, ?int $ignoreId = null): array
    {
        $skuRule = Rule::unique('inventory_items', 'sku');
        $barcodeRule = Rule::unique('inventory_items', 'barcode')->where('restaurant_id', $restaurantId);
        if ($ignoreId !== null) {
            $skuRule->ignore($ignoreId);
            $barcodeRule->ignore($ignoreId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', $skuRule],
            'barcode' => ['nullable', 'string', 'max:64', 'regex:/^[\x21-\x7E]+$/', $barcodeRule],
            'inventory_category_id' => [
                'nullable',
                'integer',
                Rule::exists('inventory_categories', 'id')->where('restaurant_id', $restaurantId),
            ],
            'purchase_unit' => ['required', 'string', 'max:50'],
            'usage_unit' => ['required', 'string', 'max:50'],
            'conversion_factor' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'minimum_stock' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ];
    }

    private function hasStockHistory(int $itemId): bool
    {
        return DB::table('inventory_stocks')->where('inventory_item_id', $itemId)->exists()
            || DB::table('stock_movements')->where('inventory_item_id', $itemId)->exists();
    }

    private function authorizeInventory(string $operation): int
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('inventory.' . $operation)
                || $user->can('inventory.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }

    /** Headline inventory figures shown on every inventory page. */
    private function inventoryStats(int $restaurantId, $user): array
    {
        $stocks = DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.inventory_item_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->where('inventory_items.restaurant_id', $restaurantId)
            ->whereNull('inventory_items.deleted_at')
            ->where('inventory_items.is_active', true)
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id));
        $low = (clone $stocks)
            ->whereRaw('inventory_stocks.quantity <= CASE WHEN inventory_items.minimum_stock >= inventory_items.reorder_level THEN inventory_items.minimum_stock ELSE inventory_items.reorder_level END')
            ->count();
        $out = (clone $stocks)->where('inventory_stocks.quantity', '<=', 0)->count();
        $movementsToday = DB::table('stock_movements')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->where('branches.restaurant_id', $restaurantId)
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->where('stock_movements.created_at', '>=', now()->startOfDay())
            ->count();

        return [
            ['label' => 'Active items', 'value' => number_format(InventoryItem::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->count()), 'icon' => 'fas fa-boxes-stacked', 'tone' => 'brand', 'href' => route('admin.inventory.products')],
            ['label' => 'Low stock', 'value' => number_format($low), 'icon' => 'fas fa-triangle-exclamation', 'tone' => $low > 0 ? 'warning' : 'success', 'hint' => $low > 0 ? 'At or below reorder level' : 'All above reorder level', 'href' => route('admin.inventory.low-stock')],
            ['label' => 'Out of stock', 'value' => number_format($out), 'icon' => 'fas fa-ban', 'tone' => $out > 0 ? 'danger' : 'neutral', 'href' => route('admin.inventory.stock')],
            ['label' => 'Movements today', 'value' => number_format($movementsToday), 'icon' => 'fas fa-right-left', 'tone' => 'info', 'href' => route('admin.inventory.adjustments')],
        ];
    }
}
