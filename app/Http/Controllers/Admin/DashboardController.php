<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): View|Response
    {
        $user = auth()->user();

        if (!$user || !$user->restaurant_id) {
            return response()->view('admin.access-denied', [
                'message' => 'Your account is not assigned to a restaurant yet. Ask an administrator to assign you.',
            ], 403);
        }

        if (!($user->can('admin.dashboard.view') || $user->can('admin'))) {
            return response()->view('admin.access-denied', [
                'message' => 'Your account does not have permission to view the dashboard. Ask an administrator to grant it.',
            ], 403);
        }

        $branchScope = fn ($query) => $query->whereHas('branch', function ($branches) use ($user): void {
            $branches->where('restaurant_id', $user->restaurant_id);
            if ($user->branch_id) {
                $branches->whereKey($user->branch_id);
            }
        });
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $salesOn = fn (string $date): float => (float) $branchScope(Order::query())
            ->whereDate('ordered_at', $date)->where('status', '!=', 'cancelled')->sum('total');

        $stats = [
            'sales' => $salesOn($today),
            'orders' => $branchScope(Order::query())->whereDate('ordered_at', $today)->count(),
            // Net takings: money received today less refunds paid out today.
            'payments' => round(
                (float) Payment::query()->whereHas('order', $branchScope)->whereDate('paid_at', $today)->whereIn('status', ['successful', 'refunded'])->sum('amount')
                - (float) $branchScope(Refund::query())->whereDate('created_at', $today)->sum('amount'),
                2
            ),
            'pending' => $branchScope(Order::query())->whereIn('status', ['pending', 'confirmed', 'preparing'])->count(),
            'menu_items' => MenuItem::query()->where('restaurant_id', $user->restaurant_id)->where('is_available', true)->count(),
        ];

        $yesterdaySales = $salesOn($yesterday);
        $insights = [
            'sales_change' => $yesterdaySales > 0 ? round((($stats['sales'] - $yesterdaySales) / $yesterdaySales) * 100, 1) : null,
            'average_order' => $stats['orders'] > 0 ? $stats['sales'] / max(1, $branchScope(Order::query())->whereDate('ordered_at', $today)->where('status', '!=', 'cancelled')->count()) : 0,
            'reservations_today' => $branchScope(Reservation::query())->whereDate('reserved_at', $today)->whereNotIn('status', ['cancelled', 'no_show'])->count(),
            'low_stock' => $this->lowStockCount($user),
        ];

        // Seven-day sales trend (grouped in PHP so it works on every database driver).
        $from = now()->subDays(6)->startOfDay();
        $daily = $branchScope(Order::query())
            ->where('ordered_at', '>=', $from)
            ->where('status', '!=', 'cancelled')
            ->get(['ordered_at', 'total'])
            ->groupBy(fn (Order $order) => $order->ordered_at?->toDateString())
            ->map(fn ($orders) => (float) $orders->sum('total'));
        $trend = collect(range(6, 0))->map(function (int $daysAgo) use ($daily): array {
            $date = now()->subDays($daysAgo);

            return ['label' => $date->format('D'), 'date' => $date->format('j M'), 'total' => $daily->get($date->toDateString(), 0.0)];
        });

        $recentOrders = $branchScope(Order::query())
            ->with('customer:id,name')
            ->latest('ordered_at')->latest('id')
            ->limit(6)
            ->get(['id', 'customer_id', 'order_number', 'order_type', 'status', 'total', 'ordered_at']);

        $topItems = OrderItem::query()
            ->whereHas('order', fn ($orders) => $branchScope($orders)->where('ordered_at', '>=', now()->subDays(30))->where('status', '!=', 'cancelled'))
            ->select('item_name', DB::raw('SUM(quantity) as quantity'), DB::raw('SUM(total) as revenue'))
            ->groupBy('item_name')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get();

        $orderMix = $branchScope(Order::query())
            ->whereDate('ordered_at', '>=', now()->subDays(30)->toDateString())
            ->where('status', '!=', 'cancelled')
            ->select('order_type', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('order_type')
            ->pluck('aggregate', 'order_type');

        return view('dashboard.index', compact('stats', 'insights', 'trend', 'recentOrders', 'topItems', 'orderMix'));
    }

    private function lowStockCount($user): int
    {
        return DB::table('inventory_stocks')
            ->join('branches', 'branches.id', '=', 'inventory_stocks.branch_id')
            ->join('inventory_items', 'inventory_items.id', '=', 'inventory_stocks.inventory_item_id')
            ->where('branches.restaurant_id', $user->restaurant_id)
            ->where('inventory_items.restaurant_id', $user->restaurant_id)
            ->whereNull('inventory_items.deleted_at')
            ->where('inventory_items.is_active', true)
            ->whereRaw('inventory_stocks.quantity <= CASE WHEN inventory_items.minimum_stock >= inventory_items.reorder_level THEN inventory_items.minimum_stock ELSE inventory_items.reorder_level END')
            ->when($user->branch_id, fn ($query) => $query->where('branches.id', $user->branch_id))
            ->count();
    }
}
