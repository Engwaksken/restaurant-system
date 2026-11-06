<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Wastage;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WastageController extends Controller
{
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeWastage($request, 'view');
        $user = $request->user();
        $wastages = Wastage::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->with(['branch:id,name', 'inventoryItem:id,name,usage_unit'])
            ->latest('wasted_at')->latest('id')->paginate(25)->withQueryString();
        $branches = Branch::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))->orderBy('name')->get(['id', 'name']);
        $items = InventoryItem::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'usage_unit']);

        return view('admin.inventory.wastage', compact('wastages', 'branches', 'items'));
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeWastage($request, 'manage');
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)
                ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id)))],
            'inventory_item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $wastage = DB::transaction(function () use ($data, $user): Wastage {
            $stock = DB::table('inventory_stocks')
                ->where('branch_id', $data['branch_id'])
                ->where('inventory_item_id', $data['inventory_item_id'])
                ->lockForUpdate()->first();
            $quantity = round((float) $data['quantity'], 3);
            if (!$stock || (float) $stock->quantity + 0.000001 < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'There is insufficient stock to record this wastage.']);
            }

            $now = now();
            $item = InventoryItem::query()->whereKey($data['inventory_item_id'])->firstOrFail();
            $wastage = Wastage::create([
                'branch_id' => $data['branch_id'],
                'inventory_item_id' => $item->id,
                'created_by' => $user->id,
                'quantity' => $quantity,
                'unit_cost' => $item->unit_cost,
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
                'wasted_at' => $now,
            ]);
            DB::table('inventory_stocks')->where('id', $stock->id)->update([
                'quantity' => round((float) $stock->quantity - $quantity, 3), 'updated_at' => $now,
            ]);
            DB::table('stock_movements')->insert([
                'branch_id' => $data['branch_id'], 'inventory_item_id' => $item->id, 'user_id' => $user->id,
                'type' => 'wastage', 'quantity' => -$quantity, 'unit_cost' => $item->unit_cost,
                'reference_type' => 'wastage', 'reference_id' => $wastage->id,
                'notes' => $data['reason'], 'created_at' => $now, 'updated_at' => $now,
            ]);

            return $wastage;
        });

        $logger->log('inventory.wastage.recorded', $wastage, [
            'branch_id' => $wastage->branch_id,
            'inventory_item_id' => $wastage->inventory_item_id,
            'quantity' => $wastage->quantity,
        ]);

        return back()->with('success', 'Wastage recorded and stock balance updated.');
    }

    private function authorizeWastage(Request $request, string $operation): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('inventory.wastage.' . $operation)
                || $user->can('inventory.wastage.manage')
                || $user->can('inventory.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
