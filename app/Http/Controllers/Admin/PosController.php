<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Services\Orders\OrderNumberGenerator;
use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\HeldOrder;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Inventory\RecipeInventoryService;
use App\Services\LoyaltyService;
use App\Services\Orders\OrderPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Point of sale. Prices, tax, delivery and loyalty value are all computed on
 * the server; held orders store only item choices and are re-priced at checkout.
 */
class PosController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request, LoyaltyService $loyalty): View
    {
        $user = $this->authorizePos($request, 'view');
        $restaurantId = (int) $user->restaurant_id;
        $categories = MenuCategory::query()
            ->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->with(['items' => fn ($query) => $query->where('restaurant_id', $restaurantId)->where('is_available', true)->orderBy('name')
                ->with(['variants' => fn ($v) => $v->where('is_available', true), 'addons' => fn ($a) => $a->where('is_available', true)])])
            ->orderBy('display_order')->orderBy('name')
            ->get();

        $resume = null;
        if ($request->filled('resume')) {
            $resume = $this->whereUserBranch(HeldOrder::query(), $user)->find((int) $request->query('resume'));
        }

        return view('pos.index', [
            'categories' => $categories,
            'branches' => $this->userBranches($user),
            'customers' => Customer::query()->where('restaurant_id', $restaurantId)->orderBy('name')->limit(500)->get(['id', 'name', 'phone', 'loyalty_points']),
            'heldCount' => $this->whereUserBranch(HeldOrder::query(), $user)->count(),
            'resume' => $resume,
            'canDiscount' => $user->can('admin.pos.discount') || $user->can('admin'),
            'loyalty' => [
                'enabled' => $loyalty->enabled(),
                'pointValue' => $loyalty->pointValue(),
                'minPoints' => (int) config('restaurant.loyalty.min_redeem_points', 0),
                'maxPercent' => (float) config('restaurant.loyalty.max_redeem_percent', 100),
            ],
            'taxRate' => max(0, min(100, (float) config('restaurant.tax_rate', 0))),
            'deliveryFee' => max(0, (float) config('restaurant.delivery_fee', 0)),
        ]);
    }

    public function checkout(
        Request $request,
        ActivityLogger $logger,
        RecipeInventoryService $inventory,
        OrderPricingService $pricing,
        LoyaltyService $loyalty,
    ): RedirectResponse {
        $user = $this->authorizePos($request, 'checkout');
        $restaurantId = (int) $user->restaurant_id;
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('restaurant_id', $restaurantId)->whereNull('deleted_at')],
            'order_type' => ['required', 'in:dine_in,takeaway,delivery,online'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'held_order_id' => ['nullable', 'integer'],
            ...OrderPricingService::lineRules(),
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('restaurant_id', $restaurantId)->where('is_available', true)],
        ]);
        $points = (int) ($data['loyalty_points'] ?? 0);
        if ($points > 0 && empty($data['customer_id'])) {
            return back()->withErrors(['loyalty_points' => 'Select the customer whose points are being redeemed.'])->withInput();
        }

        $order = DB::transaction(function () use ($data, $restaurantId, $user, $inventory, $pricing, $loyalty, $points): Order {
            $priced = $pricing->price($restaurantId, $data['items']);
            $subtotal = $priced['subtotal'];
            $discount = round((float) ($data['discount'] ?? 0), 2);
            abort_if($discount > 0 && !($user->can('admin.pos.discount') || $user->can('admin')), 403, 'You are not allowed to apply discounts.');
            abort_if($discount > $subtotal, 422, 'Discount cannot exceed the subtotal.');

            $customer = !empty($data['customer_id'])
                ? Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->first()
                : null;
            $loyaltyDiscount = $points > 0 ? $loyalty->quoteRedemption($customer, $points, $subtotal - $discount) : 0.0;

            $taxable = max(0, $subtotal - $discount - $loyaltyDiscount);
            $tax = round($taxable * max(0, min(100, (float) config('restaurant.tax_rate', 0))) / 100, 2);
            $deliveryFee = $data['order_type'] === 'delivery' ? max(0, (float) config('restaurant.delivery_fee', 0)) : 0.0;
            $total = round($taxable + $tax + $deliveryFee, 2);

            $order = Order::create([
                'branch_id' => $data['branch_id'],
                'customer_id' => $customer?->id,
                'waiter_id' => $user->id,
                'order_number' => app(OrderNumberGenerator::class)->next('POS'),
                'order_type' => $data['order_type'],
                'status' => 'confirmed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'loyalty_points_redeemed' => $points,
                'loyalty_discount' => $loyaltyDiscount,
                'tax' => $tax,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'paid_amount' => 0,
                'balance' => $total,
                'notes' => $data['notes'] ?? null,
                'ordered_at' => now(),
            ]);
            foreach ($priced['lines'] as $line) {
                $order->items()->create($line);
            }
            $inventory->consume($restaurantId, (int) $data['branch_id'], $data['items'], $user->id, (int) $order->id);
            if ($points > 0) {
                $loyalty->redeem($customer, $points, $order, $user->id);
            }
            if (!empty($data['held_order_id'])) {
                $this->whereUserBranch(HeldOrder::query(), $user)->whereKey($data['held_order_id'])->delete();
            }

            return $order;
        });
        $logger->log('order.created', $order, ['total' => $order->total, 'loyalty_points_redeemed' => $points]);

        return redirect()->route('admin.orders.show', $order)->with('success', 'Order ' . $order->order_number . ' created.');
    }

    public function hold(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePos($request, 'checkout');
        $restaurantId = (int) $user->restaurant_id;
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('restaurant_id', $restaurantId)],
            'order_type' => ['required', 'in:dine_in,takeaway,delivery,online'],
            'hold_label' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'held_order_id' => ['nullable', 'integer'],
            ...OrderPricingService::lineRules(),
            'items.*.menu_item_id' => ['required', 'integer', Rule::exists('menu_items', 'id')->where('restaurant_id', $restaurantId)],
        ]);
        $items = collect($data['items'])->map(fn (array $line) => [
            'menu_item_id' => (int) $line['menu_item_id'],
            'quantity' => (float) $line['quantity'],
            'variant_id' => isset($line['variant_id']) ? (int) $line['variant_id'] : null,
            'addon_ids' => array_values(array_map('intval', $line['addon_ids'] ?? [])),
            'notes' => $line['notes'] ?? null,
        ])->values()->all();

        $held = DB::transaction(function () use ($data, $user, $items): HeldOrder {
            if (!empty($data['held_order_id'])) {
                $this->whereUserBranch(HeldOrder::query(), $user)->whereKey($data['held_order_id'])->delete();
            }

            return HeldOrder::create([
                'branch_id' => $data['branch_id'],
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? null,
                'label' => trim((string) ($data['hold_label'] ?? '')) ?: 'Held ' . now()->format('H:i'),
                'order_type' => $data['order_type'],
                'items' => $items,
                'notes' => $data['notes'] ?? null,
            ]);
        });
        $logger->log('pos.order.held', $held, ['label' => $held->label, 'lines' => count($items)]);

        return redirect()->route('admin.pos.index')->with('success', 'Order "' . $held->label . '" put on hold.');
    }

    public function held(Request $request): View
    {
        $user = $this->authorizePos($request, 'view');
        $held = $this->whereUserBranch(HeldOrder::query(), $user)
            ->with(['branch:id,name', 'user:id,name', 'customer:id,name'])
            ->latest('id')
            ->paginate(25);

        $scope = $this->whereUserBranch(HeldOrder::query(), $user);
        $pageStats = [
            ['label' => 'Orders on hold', 'value' => number_format($held->total()), 'icon' => 'fas fa-pause', 'tone' => 'brand'],
            ['label' => 'Held today', 'value' => number_format((clone $scope)->where('created_at', '>=', now()->startOfDay())->count()), 'icon' => 'fas fa-calendar-day', 'tone' => 'info'],
            ['label' => 'Held by you', 'value' => number_format((clone $scope)->where('user_id', $user->id)->count()), 'icon' => 'fas fa-user', 'tone' => 'success'],
            ['label' => 'Older than a day', 'value' => number_format((clone $scope)->where('created_at', '<', now()->subDay())->count()), 'icon' => 'fas fa-clock-rotate-left', 'tone' => 'warning', 'hint' => 'Consider resuming or discarding'],
        ];

        return view('pos.held', ['held' => $held, 'pageStats' => $pageStats]);
    }

    public function discardHeld(Request $request, int $held, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePos($request, 'checkout');
        $record = $this->whereUserBranch(HeldOrder::query(), $user)->findOrFail($held);
        $record->delete();
        $logger->log('pos.order.discarded', null, ['held_order_id' => $held, 'label' => $record->label]);

        return back()->with('success', 'Held order "' . $record->label . '" discarded.');
    }

    private function authorizePos(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('admin.pos.' . $operation)
                || $user->can('admin.pos.manage')
                || $user->can('admin'),
            403
        );

        return $user;
    }
}
