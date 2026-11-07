<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'served'];
    private const FINISHED_STATUSES = ['completed', 'cancelled'];

    public function index(Request $request): View
    {
        return $this->renderOrders($request, null, 'All Orders', 'fas fa-receipt');
    }

    public function dineIn(Request $request): View
    {
        return $this->renderOrders($request, 'dine_in', 'Dine-In Orders', 'fas fa-utensils');
    }

    public function takeaway(Request $request): View
    {
        return $this->renderOrders($request, 'takeaway', 'Takeaway Orders', 'fas fa-bag-shopping');
    }

    public function delivery(Request $request): View
    {
        return $this->renderOrders($request, 'delivery', 'Delivery Orders', 'fas fa-motorcycle');
    }

    public function online(Request $request): View
    {
        return $this->renderOrders($request, 'online', 'Online Orders', 'fas fa-globe');
    }

    public function cancelled(Request $request): View
    {
        return $this->renderOrders($request, 'cancelled', 'Cancelled Orders', 'fas fa-ban');
    }

    /** POS: orders still in progress (not yet completed or cancelled). */
    public function current(Request $request): View
    {
        return $this->renderOrders($request, null, 'Current Orders', 'fas fa-clock', self::ACTIVE_STATUSES);
    }

    /** POS: finished orders, filterable by date range. */
    public function history(Request $request): View
    {
        $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        return $this->renderOrders($request, null, 'Order History', 'fas fa-clock-rotate-left', self::FINISHED_STATUSES, true);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOrder($order, 'orders.view');
        $order->load(['items', 'branch:id,name', 'customer:id,name,phone,loyalty_points', 'waiter:id,name',
            'payments.receiver:id,name', 'payments.refunds', 'refunds.refunder:id,name']);
        $user = $request->user();

        return view('orders.show', [
            'order' => $order,
            'paymentMethods' => config('restaurant.payment_methods', []),
            'statusOptions' => Order::STATUS_TRANSITIONS[$order->status] ?? [],
            'canPay' => $user->can('orders.payments.create') || $user->can('orders.manage') || $user->can('admin'),
            'canManage' => $user->can('orders.manage') || $user->can('orders.update') || $user->can('admin'),
            'canRefund' => $user->can('orders.refunds.create') || $user->can('admin'),
        ]);
    }

    /** Printable customer receipt laid out with the Receipt settings. */
    public function receipt(Order $order): View
    {
        return $this->printable($order, 'orders.receipt', 'receipt');
    }

    /** Printable invoice laid out with the Invoice settings. */
    public function invoice(Order $order): View
    {
        return $this->printable($order, 'orders.invoice', 'invoice');
    }

    private function printable(Order $order, string $view, string $group): View
    {
        $this->authorizeOrder($order, 'orders.view');
        $order->load(['items', 'branch.restaurant', 'customer:id,name,phone,email,address', 'waiter:id,name', 'payments' => fn ($query) => $query->where('status', 'successful')]);
        $restaurant = $order->branch?->restaurant;

        return view($view, [
            'order' => $order,
            'brand' => \App\Support\Brand::for($restaurant),
            'restaurant' => $restaurant,
            'settings' => app(\App\Services\Settings\RestaurantSettings::class)->group($restaurant?->id, $group),
        ]);
    }

    public function updateStatus(Request $request, Order $order, LoyaltyService $loyalty): RedirectResponse
    {
        $this->authorizeOrder($order, 'orders.update');
        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled'])],
        ]);
        $userId = $request->user()->id;

        DB::transaction(function () use ($order, $validated, $loyalty, $userId): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canTransitionTo($validated['status']), 422, 'Invalid order status transition.');
            $locked->status = $validated['status'];
            if ($validated['status'] === 'completed') {
                $locked->completed_at ??= now();
            }
            $locked->save();

            if ($validated['status'] === 'completed') {
                $loyalty->earnForOrder($locked, $userId);
            } elseif ($validated['status'] === 'cancelled') {
                $loyalty->returnRedeemed($locked, $userId);
            }
        });

        if ($validated['status'] === 'ready') {
            app(\App\Services\Notifications\SmsService::class)->orderReady($order->fresh());
        }

        return back()->with('success', 'Order status updated successfully.');
    }

    public function cancel(Request $request, Order $order, LoyaltyService $loyalty): RedirectResponse
    {
        $this->authorizeOrder($order, 'orders.cancel');
        $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $userId = $request->user()->id;

        DB::transaction(function () use ($order, $loyalty, $userId): void {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($locked->status, ['pending', 'confirmed', 'preparing', 'ready'], true), 422, 'This order cannot be cancelled.');
            $locked->status = 'cancelled';
            $locked->save();
            $loyalty->returnRedeemed($locked, $userId);
        });

        return back()->with('success', 'Order cancelled successfully.');
    }

    private function authorizeOrder(Order $order, string $ability): void
    {
        $user = auth()->user();
        // POS operators land on the order they just created, so they may view orders.
        $posViewer = $ability === 'orders.view' && $user && ($user->can('admin.pos.manage') || $user->can('admin.pos.checkout'));
        abort_unless($user && ($posViewer || $user->can($ability) || $user->can('orders.manage') || $user->can('admin')), 403);
        if ($user->restaurant_id !== null) {
            $branch = $order->branch;
            abort_unless($branch && (int) $branch->restaurant_id === (int) $user->restaurant_id, 404);
        }
        if ($user->branch_id !== null) {
            abort_unless((int) $order->branch_id === (int) $user->branch_id, 404);
        }
    }

    private function renderOrders(
        Request $request,
        ?string $type,
        string $pageTitle,
        string $pageIcon,
        ?array $statuses = null,
        bool $dateRange = false
    ): View {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id && ($user->can('orders.view') || $user->can('orders.manage') || $user->can('admin')), 403);
        $query = Order::query()->whereHas('branch', function ($branchQuery) use ($user): void {
            $branchQuery->where('restaurant_id', $user->restaurant_id);
            if ($user->branch_id) {
                $branchQuery->whereKey($user->branch_id);
            }
        });

        $relations = $this->safeRelations();
        if ($relations !== []) {
            $query->with($relations);
        }

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }
        if ($dateRange) {
            $this->applyDateRange($query, $request);
        }

        if ($type === 'cancelled') {
            if (Schema::hasColumn('orders', 'status')) {
                $query->where('status', 'cancelled');
            }
        } elseif ($type !== null) {
            $this->applyOrderTypeFilter($query, $type);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));

            $query->where(function (Builder $builder) use ($search): void {
                foreach (['order_number', 'reference', 'customer_name', 'customer_phone'] as $column) {
                    if (Schema::hasColumn('orders', $column)) {
                        $builder->orWhere($column, 'like', '%' . $search . '%');
                    }
                }

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        if ($request->filled('status') && Schema::hasColumn('orders', 'status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('payment_status') && Schema::hasColumn('orders', 'payment_status')) {
            $query->where('payment_status', $request->string('payment_status')->toString());
        }

        $query->latest('id');

        $orders = $query->paginate(20)->withQueryString();

        $statsBase = Order::query()->whereHas('branch', function ($branchQuery) use ($user): void {
            $branchQuery->where('restaurant_id', $user->restaurant_id);
            if ($user->branch_id) {
                $branchQuery->whereKey($user->branch_id);
            }
        });

        if ($type === 'cancelled') {
            if (Schema::hasColumn('orders', 'status')) {
                $statsBase->where('status', 'cancelled');
            }
        } elseif ($type !== null) {
            $this->applyOrderTypeFilter($statsBase, $type);
        }
        if ($statuses !== null) {
            $statsBase->whereIn('status', $statuses);
        }
        if ($dateRange) {
            $this->applyDateRange($statsBase, $request);
        }

        $statistics = [
            'total' => (clone $statsBase)->count(),
            'pending' => $this->countByStatus(clone $statsBase, ['pending', 'new']),
            'preparing' => $this->countByStatus(clone $statsBase, ['preparing', 'processing']),
            'completed' => $this->countByStatus(clone $statsBase, ['completed', 'served', 'delivered']),
        ];

        $statusOptions = [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'preparing' => 'Preparing',
            'ready' => 'Ready',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ];
        if ($statuses !== null) {
            $statusOptions = array_intersect_key($statusOptions, array_flip($statuses));
        }

        $paymentStatusOptions = [
            'unpaid' => 'Unpaid',
            'partial' => 'Partially Paid',
            'paid' => 'Paid',
        ];

        return view('orders.index', compact(
            'orders',
            'statistics',
            'pageTitle',
            'pageIcon',
            'type',
            'statusOptions',
            'paymentStatusOptions',
            'dateRange'
        ));
    }

    private function applyOrderTypeFilter(Builder $query, string $type): void
    {
        if (Schema::hasColumn('orders', 'order_type')) {
            $aliases = match ($type) {
                'dine_in' => ['dine_in', 'dine-in', 'dinein', 'dine in'],
                'takeaway' => ['takeaway', 'take_away', 'take-away', 'pickup'],
                'delivery' => ['delivery'],
                'online' => ['online', 'web'],
                default => [$type],
            };

            $query->whereIn('order_type', $aliases);
            return;
        }

        if (Schema::hasColumn('orders', 'type')) {
            $query->where('type', $type);
        }
    }

    private function applyDateRange(Builder $query, Request $request): void
    {
        $column = 'COALESCE(orders.ordered_at, orders.created_at)';
        if ($request->filled('date_from')) {
            $query->whereRaw($column . ' >= ?', [\Illuminate\Support\Carbon::parse($request->input('date_from'))->startOfDay()]);
        }
        if ($request->filled('date_to')) {
            $query->whereRaw($column . ' <= ?', [\Illuminate\Support\Carbon::parse($request->input('date_to'))->endOfDay()]);
        }
    }

    private function countByStatus(Builder $query, array $statuses): int
    {
        if (!Schema::hasColumn('orders', 'status')) {
            return 0;
        }

        return $query->whereIn('status', $statuses)->count();
    }

    private function safeRelations(): array
    {
        $relations = [];

        $order = new Order();

        foreach (['customer', 'table', 'items', 'payments'] as $relation) {
            if (method_exists($order, $relation)) {
                $relations[] = $relation;
            }
        }

        return $relations;
    }
}
