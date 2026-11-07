<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kitchen display: tenant/branch-scoped preparation queue.
 *
 * Kitchen staff may only start preparation and mark orders ready. Completion,
 * cancellation and payment remain front-of-house order workflows.
 */
class KitchenController extends Controller
{
    /** Board columns => order statuses shown in that column. */
    private const QUEUES = [
        'pending' => ['pending', 'confirmed'],
        'preparing' => ['preparing'],
        'ready' => ['ready'],
    ];

    /** Kitchen-permitted transitions (a subset of Order::STATUS_TRANSITIONS). */
    private const KITCHEN_TRANSITIONS = [
        'pending' => 'preparing',
        'confirmed' => 'preparing',
        'preparing' => 'ready',
    ];

    /** Order item kitchen statuses that move with the order. */
    private const ITEM_SYNC = [
        'preparing' => ['from' => ['pending'], 'to' => 'preparing'],
        'ready' => ['from' => ['pending', 'preparing'], 'to' => 'ready'],
    ];

    private const BOARD_LIMIT = 100;

    public function index(Request $request): View
    {
        $this->authorizeKitchen($request, 'view');

        $columns = [];
        foreach (self::QUEUES as $queue => $statuses) {
            $columns[$queue] = $this->scopedOrders($request)
                ->whereIn('status', $statuses)
                ->with('items')
                ->orderByRaw('COALESCE(ordered_at, created_at) asc')
                ->orderBy('id')
                ->limit(self::BOARD_LIMIT)
                ->get();
        }

        return view('admin.kitchen.board', [
            'pageStats' => $this->kitchenStats($request),
            'columns' => $columns,
            'canManage' => $this->canManage($request),
            'transitions' => self::KITCHEN_TRANSITIONS,
        ]);
    }

    public function pending(Request $request): View
    {
        return $this->queue($request, 'pending', 'Pending', 'fas fa-hourglass-start');
    }

    public function preparing(Request $request): View
    {
        return $this->queue($request, 'preparing', 'Preparing', 'fas fa-fire');
    }

    public function ready(Request $request): View
    {
        return $this->queue($request, 'ready', 'Ready', 'fas fa-bell-concierge');
    }

    public function completed(Request $request): View
    {
        $this->authorizeKitchen($request, 'view');

        $orders = $this->scopedOrders($request)
            ->whereIn('status', ['served', 'completed'])
            ->where(fn (Builder $query) => $query
                ->where('completed_at', '>=', now()->startOfDay())
                ->orWhere(fn (Builder $fallback) => $fallback
                    ->whereNull('completed_at')
                    ->where('updated_at', '>=', now()->startOfDay())))
            ->with('items')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.kitchen.queue', [
            'pageStats' => $this->kitchenStats($request),
            'orders' => $orders,
            'queue' => 'completed',
            'title' => 'Completed Today',
            'icon' => 'fas fa-circle-check',
            'canManage' => false,
            'transitions' => self::KITCHEN_TRANSITIONS,
        ]);
    }

    public function advance(Request $request, Order $order, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeKitchen($request, 'manage');
        $data = $request->validate([
            'from' => ['required', 'string', Rule::in(array_keys(self::KITCHEN_TRANSITIONS))],
            'to' => ['required', 'string', Rule::in(array_unique(array_values(self::KITCHEN_TRANSITIONS)))],
        ]);
        abort_unless(self::KITCHEN_TRANSITIONS[$data['from']] === $data['to'], 422, 'Invalid kitchen status transition.');

        $user = $request->user();
        $result = DB::transaction(function () use ($order, $data, $user): ?Order {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->whereHas('branch', fn (Builder $branches) => $branches
                    ->where('restaurant_id', $user->restaurant_id)
                    ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id)))
                ->lockForUpdate()
                ->first();
            abort_unless($locked, 404);

            // Another screen already moved this order: do not re-apply.
            if ($locked->status !== $data['from']) {
                return null;
            }
            abort_unless($locked->canTransitionTo($data['to']), 422, 'Invalid order status transition.');

            $locked->status = $data['to'];
            $locked->save();

            $sync = self::ITEM_SYNC[$data['to']];
            $locked->items()
                ->whereIn('kitchen_status', $sync['from'])
                ->update(['kitchen_status' => $sync['to'], 'updated_at' => now()]);

            return $locked;
        });

        if ($result === null) {
            return back()->with('error', 'Order ' . $order->order_number . ' was already updated on another screen.');
        }

        $logger->log('kitchen.order.' . $data['to'], $result, [
            'branch_id' => $result->branch_id,
            'from' => $data['from'],
            'to' => $data['to'],
        ]);

        $label = $data['to'] === 'ready' ? 'is ready' : 'is being prepared';

        return back()->with('success', 'Order ' . $result->order_number . ' ' . $label . '.');
    }

    private function queue(Request $request, string $queue, string $title, string $icon): View
    {
        $this->authorizeKitchen($request, 'view');

        $orders = $this->scopedOrders($request)
            ->whereIn('status', self::QUEUES[$queue])
            ->with('items')
            ->orderByRaw('COALESCE(ordered_at, created_at) asc')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.kitchen.queue', [
            'pageStats' => $this->kitchenStats($request),
            'orders' => $orders,
            'queue' => $queue,
            'title' => $title,
            'icon' => $icon,
            'canManage' => $this->canManage($request),
            'transitions' => self::KITCHEN_TRANSITIONS,
        ]);
    }

    private function scopedOrders(Request $request): Builder
    {
        $user = $request->user();

        return Order::query()->whereHas('branch', fn (Builder $branches) => $branches
            ->where('restaurant_id', $user->restaurant_id)
            ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id)));
    }

    private function authorizeKitchen(Request $request, string $operation): void
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'view'
            ? $user->can('kitchen.view') || $this->canManage($request)
            : $this->canManage($request);
        abort_unless($allowed, 403);
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->can('kitchen.manage') || $user->can('orders.manage') || $user->can('admin'));
    }

    /** Live queue counts shown above every kitchen screen. */
    private function kitchenStats(Request $request): array
    {
        $late = (int) config('restaurant.kitchen_late_minutes', 20);
        $count = fn (array $statuses) => $this->scopedOrders($request)->whereIn('status', $statuses)->count();
        $lateCount = $this->scopedOrders($request)
            ->whereIn('status', array_merge(self::QUEUES['pending'], self::QUEUES['preparing']))
            ->whereRaw('COALESCE(ordered_at, created_at) <= ?', [now()->subMinutes($late)])
            ->count();
        $done = $this->scopedOrders($request)->whereIn('status', ['served', 'completed'])
            ->where(fn (Builder $query) => $query->where('completed_at', '>=', now()->startOfDay())
                ->orWhere(fn (Builder $fallback) => $fallback->whereNull('completed_at')->where('updated_at', '>=', now()->startOfDay())))
            ->count();

        return [
            ['label' => 'New', 'value' => number_format($count(self::QUEUES['pending'])), 'icon' => 'fas fa-hourglass-start', 'tone' => 'warning', 'href' => route('admin.kitchen.pending')],
            ['label' => 'Preparing', 'value' => number_format($count(self::QUEUES['preparing'])), 'icon' => 'fas fa-fire', 'tone' => 'brand', 'href' => route('admin.kitchen.preparing'), 'hint' => $lateCount > 0 ? $lateCount . ' running late (>' . $late . ' min)' : 'Nothing running late'],
            ['label' => 'Ready to serve', 'value' => number_format($count(self::QUEUES['ready'])), 'icon' => 'fas fa-bell-concierge', 'tone' => 'info', 'href' => route('admin.kitchen.ready')],
            ['label' => 'Completed today', 'value' => number_format($done), 'icon' => 'fas fa-circle-check', 'tone' => 'success', 'href' => route('admin.kitchen.completed')],
        ];
    }
}
