<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchasingController extends Controller
{
    public function suppliers(Request $request): View
    {
        $restaurantId = $this->authorizePurchasing($request, 'suppliers.view');
        $suppliers = Supplier::query()->where('restaurant_id', $restaurantId)->latest()->paginate(25);

        return view('admin.purchasing.suppliers', compact('suppliers'));
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'suppliers.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:100'],
        ]);
        Supplier::create([...$data, 'restaurant_id' => $restaurantId, 'is_active' => true]);

        return back()->with('success', 'Supplier added.');
    }

    public function updateSupplier(Request $request, int $supplier): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'suppliers.manage');
        $record = Supplier::query()->where('restaurant_id', $restaurantId)->whereKey($supplier)->firstOrFail();
        $data = $request->validateWithBag('editSupplier' . $record->id, [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:100'],
        ]);
        $record->update($data);

        return back()->with('success', 'Supplier updated.');
    }

    public function destroySupplier(Request $request, int $supplier): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'suppliers.manage');
        $record = Supplier::query()->where('restaurant_id', $restaurantId)->whereKey($supplier)->firstOrFail();
        if (PurchaseOrder::query()->where('supplier_id', $record->id)->whereIn('status', ['ordered', 'partially_received'])->exists()) {
            return back()->with('error', 'This supplier has open purchase orders. Receive or cancel them before deleting the supplier.');
        }
        $hasUnpaidPurchases = Purchase::query()->where('supplier_id', $record->id)
            ->whereRaw('purchases.total > (select coalesce(sum(supplier_payments.amount), 0) from supplier_payments where supplier_payments.purchase_id = purchases.id)')
            ->exists();
        if ($hasUnpaidPurchases) {
            return back()->with('error', 'This supplier has purchases with an unpaid balance. Settle them before deleting the supplier.');
        }
        $record->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    public function purchases(Request $request): View
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchases.view');
        $user = $request->user();
        $purchases = Purchase::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->with(['branch:id,name', 'supplier:id,name', 'items.inventoryItem:id,name,usage_unit', 'supplierPayments'])
            ->latest('received_at')->latest('id')->paginate(25)->withQueryString();
        $branches = Branch::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $inventoryItems = InventoryItem::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'usage_unit']);

        return view('admin.purchasing.purchases', compact('purchases', 'branches', 'suppliers', 'inventoryItems'));
    }

    public function purchaseOrders(Request $request): View
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchase-orders.view');
        $user = $request->user();
        $orders = PurchaseOrder::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->with(['branch:id,name', 'supplier:id,name', 'items.inventoryItem:id,name,usage_unit'])
            ->latest('ordered_at')->latest('id')->paginate(25)->withQueryString();
        $branches = Branch::query()->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $inventoryItems = InventoryItem::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'usage_unit']);

        return view('admin.purchasing.purchase-orders', compact('orders', 'branches', 'suppliers', 'inventoryItems'));
    }

    public function receivePurchaseOrderForm(Request $request, int $purchaseOrder): View
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchases.receive');
        $user = $request->user();
        $order = PurchaseOrder::query()
            ->whereKey($purchaseOrder)
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->with(['branch:id,name', 'supplier:id,name', 'items.inventoryItem:id,name,usage_unit'])
            ->firstOrFail();
        abort_if(in_array($order->status, ['received', 'cancelled'], true), 404);

        return view('admin.purchasing.receive-order', compact('order'));
    }

    public function cancelPurchaseOrder(Request $request, int $purchaseOrder, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchase-orders.cancel');
        $user = $request->user();
        $order = DB::transaction(function () use ($purchaseOrder, $restaurantId, $user): PurchaseOrder {
            $record = PurchaseOrder::query()->whereKey($purchaseOrder)
                ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                    ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
                ->lockForUpdate()->firstOrFail();
            abort_unless($record->status === 'ordered', 409, 'Only an unreceived purchase order can be cancelled.');
            abort_if($record->items()->where('received_quantity', '>', 0)->exists(), 409, 'Purchase orders with received goods cannot be cancelled.');
            $record->update(['status' => 'cancelled']);

            return $record;
        });

        $logger->log('purchasing.purchase_order.cancelled', $order, ['order_number' => $order->order_number]);

        return back()->with('success', 'Purchase order cancelled.');
    }

    public function storePurchaseOrder(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchase-orders.create');
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)
                ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id)))],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $order = DB::transaction(function () use ($data, $user): PurchaseOrder {
            $order = PurchaseOrder::create([
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'created_by' => $user->id,
                'order_number' => 'PO-' . strtoupper((string) \Illuminate\Support\Str::uuid()),
                'status' => 'ordered',
                'notes' => $data['notes'] ?? null,
                'ordered_at' => now(),
            ]);
            foreach ($data['items'] as $line) {
                $order->items()->create([
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => round((float) $line['quantity'], 3),
                    'received_quantity' => 0,
                    'unit_cost' => round((float) $line['unit_cost'], 2),
                ]);
            }

            return $order;
        });

        $logger->log('purchasing.purchase_order.created', $order, [
            'supplier_id' => $order->supplier_id,
            'branch_id' => $order->branch_id,
            'line_count' => count($data['items']),
        ]);

        return back()->with('success', 'Purchase order created.');
    }

    public function receive(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'purchases.receive');
        $user = $request->user();
        $data = $request->validate([
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)
                ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id)))],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'reference' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.inventory_item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)->where('is_active', true)->whereNull('deleted_at'))],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_cost' => ['required_without:purchase_order_id', 'nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);

        $purchase = DB::transaction(function () use ($data, $user, $restaurantId): Purchase {
            $order = null;
            if (!empty($data['purchase_order_id'])) {
                $order = PurchaseOrder::query()->whereKey($data['purchase_order_id'])
                    ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId))
                    ->lockForUpdate()->firstOrFail();
                if ($order->status === 'cancelled' || (int) $order->branch_id !== (int) $data['branch_id']
                    || (int) $order->supplier_id !== (int) ($data['supplier_id'] ?? 0)) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'purchase_order_id' => 'The purchase order must match this branch and supplier and must not be cancelled.',
                    ]);
                }
            }

            $total = 0.0;
            foreach ($data['items'] as $line) {
                if ($order) {
                    $orderLine = $order->items()->where('inventory_item_id', $line['inventory_item_id'])->lockForUpdate()->first();
                    $remaining = $orderLine ? round((float) $orderLine->quantity - (float) $orderLine->received_quantity, 3) : 0;
                    if (!$orderLine || $remaining <= 0 || round((float) $line['quantity'], 3) > $remaining) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'items' => 'Receipt quantities must match purchase-order lines and cannot exceed the remaining ordered quantity.',
                        ]);
                    }
                    $unitCost = (float) $orderLine->unit_cost;
                } else {
                    $orderLine = null;
                    $unitCost = (float) ($line['unit_cost'] ?? 0);
                }
                $total += round((float) $line['quantity'] * $unitCost, 2);
            }
            $purchase = Purchase::create([
                'purchase_order_id' => $order?->id,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'created_by' => $user->id,
                'reference' => $data['reference'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'total' => round($total, 2),
                'received_at' => now(),
            ]);

            foreach ($data['items'] as $line) {
                $quantity = round((float) $line['quantity'], 3);
                if ($order) {
                    $orderLine = $order->items()->where('inventory_item_id', $line['inventory_item_id'])->lockForUpdate()->firstOrFail();
                    $unitCost = (float) $orderLine->unit_cost;
                } else {
                    $orderLine = null;
                    $unitCost = round((float) ($line['unit_cost'] ?? 0), 2);
                }
                $lineTotal = round($quantity * $unitCost, 2);
                $purchase->items()->create([
                    'purchase_order_item_id' => $orderLine?->id,
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ]);

                $now = now();
                DB::table('inventory_stocks')->insertOrIgnore([
                    'branch_id' => $data['branch_id'],
                    'inventory_item_id' => $line['inventory_item_id'],
                    'quantity' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $stock = DB::table('inventory_stocks')
                    ->where('branch_id', $data['branch_id'])
                    ->where('inventory_item_id', $line['inventory_item_id'])
                    ->lockForUpdate()->first();
                DB::table('inventory_stocks')->where('id', $stock->id)->update([
                    'quantity' => round((float) $stock->quantity + $quantity, 3),
                    'updated_at' => $now,
                ]);
                DB::table('stock_movements')->insert([
                    'branch_id' => $data['branch_id'],
                    'inventory_item_id' => $line['inventory_item_id'],
                    'user_id' => $user->id,
                    'type' => 'purchase',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchase->id,
                    'notes' => $data['invoice_number'] ?? $data['reference'] ?? 'Stock receipt',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($orderLine) {
                    $newReceived = round((float) $orderLine->received_quantity + $quantity, 3);
                    $orderLine->update(['received_quantity' => $newReceived]);
                }
            }

            if ($order) {
                $order->load('items');
                $order->update([
                    'status' => $order->items->every(fn ($line) => (float) $line->received_quantity >= (float) $line->quantity)
                        ? 'received'
                        : 'partially_received',
                ]);
            }

            return $purchase;
        });

        $logger->log('purchasing.stock.received', $purchase, [
            'branch_id' => $purchase->branch_id,
            'supplier_id' => $purchase->supplier_id,
            'total' => $purchase->total,
        ]);

        return back()->with('success', 'Purchase receipt recorded and stock balances updated.');
    }

    public function recordSupplierPayment(Request $request, int $purchase, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizePurchasing($request, 'supplier-payments.create');
        $user = $request->user();
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'mobile_money', 'bank', 'card', 'other'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($purchase, $restaurantId, $user, $data): \App\Models\SupplierPayment {
            $record = Purchase::query()->whereKey($purchase)
                ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                    ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
                ->lockForUpdate()->firstOrFail();
            $paid = (float) $record->supplierPayments()->sum('amount');
            $balance = round((float) $record->total - $paid, 2);
            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0 || $amount > $balance) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Payment amount must be positive and cannot exceed the remaining purchase balance.',
                ]);
            }

            return \App\Models\SupplierPayment::create([
                'purchase_id' => $record->id,
                'branch_id' => $record->branch_id,
                'supplier_id' => $record->supplier_id,
                'created_by' => $user->id,
                'receipt_number' => 'SP-' . strtoupper((string) \Illuminate\Support\Str::uuid()),
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
            ]);
        });

        $logger->log('purchasing.supplier_payment.recorded', $payment, [
            'purchase_id' => $purchase,
            'amount' => $payment->amount,
        ]);

        return back()->with('success', 'Supplier payment recorded.');
    }

    private function authorizePurchasing(Request $request, string $ability): int
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('purchasing.' . $ability) || $user->can('purchasing.manage') || $user->can('admin'), 403);

        return (int) $user->restaurant_id;
    }
}
