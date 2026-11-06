<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
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

        return view('admin.inventory.ledger', ['title' => 'Stock Balances', 'stocks' => $stocks]);
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

        return view('admin.inventory.adjustments', compact('movements'));
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

        return view('admin.inventory.ledger', ['title' => 'Low Stock', 'stocks' => $stocks]);
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
        $items = InventoryItem::query()
            ->where('restaurant_id', $restaurantId)
            ->withTrashed()
            ->orderBy('name')
            ->paginate(25);
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
            ->select('inventory_stocks.*', 'branches.name as branch_name', 'inventory_items.name as item_name', 'inventory_items.usage_unit')
            ->orderBy('branches.name')
            ->orderBy('inventory_items.name')
            ->get();

        return view('admin.inventory.index', compact('branches', 'categories', 'items', 'activeItems', 'stocks'));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeInventory('manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('inventory_items', 'sku')],
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
        ]);

        InventoryItem::create([
            ...$data,
            'restaurant_id' => $restaurantId,
            'is_active' => true,
            'track_expiry' => false,
        ]);

        return back()->with('success', 'Inventory item added successfully.');
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
}
