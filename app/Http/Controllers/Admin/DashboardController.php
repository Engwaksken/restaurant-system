<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless($user->can('admin.dashboard.view') || $user->can('admin'), 403);

        $branchScope = fn ($query) => $query->whereHas('branch', function ($branches) use ($user): void {
            $branches->where('restaurant_id', $user->restaurant_id);
            if ($user->branch_id) {
                $branches->whereKey($user->branch_id);
            }
        });
        $today = now()->toDateString();
        $stats = [
            'sales' => $branchScope(Order::query())->whereDate('ordered_at', $today)->whereNotIn('status', ['cancelled'])->sum('total'),
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

        return view('dashboard.index', compact('stats'));
    }
}
