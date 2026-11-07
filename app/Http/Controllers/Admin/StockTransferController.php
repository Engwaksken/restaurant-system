<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeTransfers($request, 'view');
        $user = $request->user();
        $transfers = StockTransfer::query()
            ->whereHas('fromBranch', fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->whereHas('toBranch', fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->when($user->branch_id, fn ($query) => $query->where(fn ($branches) => $branches
                ->where('from_branch_id', $user->branch_id)->orWhere('to_branch_id', $user->branch_id)))
            ->with(['fromBranch:id,name', 'toBranch:id,name', 'items.inventoryItem:id,name,usage_unit'])
            ->latest('transferred_at')->latest('id')->paginate(25)->withQueryString();
        $branches = Branch::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name']);
        $items = InventoryItem::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->orderBy('name')->get(['id', 'name', 'usage_unit']);

        $scope = StockTransfer::query()
            ->whereHas('fromBranch', fn ($query) => $query->where('restaurant_id', $restaurantId))
            ->when($user->branch_id, fn ($query) => $query->where(fn ($branches) => $branches
                ->where('from_branch_id', $user->branch_id)->orWhere('to_branch_id', $user->branch_id)));
        $pageStats = [
            ['label' => 'Transfers', 'value' => number_format($transfers->total()), 'icon' => 'fas fa-right-left', 'tone' => 'brand', 'hint' => 'All time'],
            ['label' => 'This month', 'value' => number_format((clone $scope)->where('transferred_at', '>=', now()->startOfMonth())->count()), 'icon' => 'fas fa-calendar', 'tone' => 'info'],
            ['label' => 'Last 7 days', 'value' => number_format((clone $scope)->where('transferred_at', '>=', now()->subDays(7))->count()), 'icon' => 'fas fa-calendar-week', 'tone' => 'success'],
            ['label' => 'Branches', 'value' => number_format($branches->count()), 'icon' => 'fas fa-code-branch', 'tone' => 'violet', 'hint' => 'Active locations'],
        ];

        return view('admin.inventory.transfers', compact('transfers', 'branches', 'items', 'pageStats'));
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeTransfers($request, 'manage');
        $user = $request->user();
        $branchRule = fn () => Rule::exists('branches', 'id')->where(fn ($query) => $query
            ->where('restaurant_id', $restaurantId)->where('is_active', true));
        $data = $request->validate([
            'from_branch_id' => ['required', 'integer', $branchRule()],
            'to_branch_id' => ['required', 'integer', $branchRule(), 'different:from_branch_id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
        if ($user->branch_id && !in_array((int) $user->branch_id, [(int) $data['from_branch_id'], (int) $data['to_branch_id']], true)) {
            throw ValidationException::withMessages(['from_branch_id' => 'Branch staff may only transfer stock to or from their assigned branch.']);
        }

        $transfer = DB::transaction(function () use ($data, $user): StockTransfer {
            $transfer = StockTransfer::create([
                'from_branch_id' => $data['from_branch_id'],
                'to_branch_id' => $data['to_branch_id'],
                'created_by' => $user->id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transferred_at' => now(),
            ]);
            $branchIds = [(int) $data['from_branch_id'], (int) $data['to_branch_id']];
            sort($branchIds);

            foreach ($data['items'] as $line) {
                $itemId = (int) $line['inventory_item_id'];
                $quantity = round((float) $line['quantity'], 3);
                $now = now();
                DB::table('inventory_stocks')->insertOrIgnore([
                    'branch_id' => $data['to_branch_id'], 'inventory_item_id' => $itemId,
                    'quantity' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $stocks = DB::table('inventory_stocks')
                    ->whereIn('branch_id', $branchIds)
                    ->where('inventory_item_id', $itemId)
                    ->orderBy('branch_id')->lockForUpdate()->get()->keyBy('branch_id');
                $source = $stocks->get($data['from_branch_id']);
                $destination = $stocks->get($data['to_branch_id']);
                if (!$source || !$destination || (float) $source->quantity + 0.000001 < $quantity) {
                    throw ValidationException::withMessages(['items' => 'There is insufficient source-branch stock for this transfer.']);
                }

                $cost = (float) DB::table('inventory_items')->where('id', $itemId)->value('unit_cost');
                DB::table('inventory_stocks')->where('id', $source->id)->update([
                    'quantity' => round((float) $source->quantity - $quantity, 3), 'updated_at' => $now,
                ]);
                DB::table('inventory_stocks')->where('id', $destination->id)->update([
                    'quantity' => round((float) $destination->quantity + $quantity, 3), 'updated_at' => $now,
                ]);
                $transfer->items()->create(['inventory_item_id' => $itemId, 'quantity' => $quantity, 'unit_cost' => $cost]);

                foreach ([
                    [$data['from_branch_id'], 'transfer_out', -$quantity],
                    [$data['to_branch_id'], 'transfer_in', $quantity],
                ] as [$branchId, $type, $movementQuantity]) {
                    DB::table('stock_movements')->insert([
                        'branch_id' => $branchId, 'inventory_item_id' => $itemId, 'user_id' => $user->id,
                        'type' => $type, 'quantity' => $movementQuantity, 'unit_cost' => $cost,
                        'reference_type' => 'stock_transfer', 'reference_id' => $transfer->id,
                        'notes' => $data['reference'] ?? 'Branch stock transfer',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }

            return $transfer;
        });

        $logger->log('inventory.stock.transferred', $transfer, [
            'from_branch_id' => $transfer->from_branch_id,
            'to_branch_id' => $transfer->to_branch_id,
            'line_count' => count($data['items']),
        ]);

        return back()->with('success', 'Stock transfer completed and ledger entries recorded.');
    }

    private function authorizeTransfers(Request $request, string $operation): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('inventory.transfers.' . $operation)
                || $user->can('inventory.transfers.manage')
                || $user->can('inventory.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
