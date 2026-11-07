<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\CashRegisterService;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Refunds against a specific successful payment, paid back through the same
 * method. Food is not restocked. Cash refunds come out of the cashier's drawer.
 */
class RefundController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request): View
    {
        $user = $this->authorizeRefunds($request, 'view');
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);
        $query = $this->whereUserBranch(Refund::query(), $user)
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', Carbon::parse($to)->endOfDay()));

        $currency = config('restaurant.currency');
        $total = (float) (clone $query)->sum('amount');
        $count = (clone $query)->count();
        $pageStats = [
            ['label' => 'Total refunded', 'value' => $currency . ' ' . number_format($total, 2), 'icon' => 'fas fa-rotate-left', 'tone' => 'danger', 'hint' => ($filters['date_from'] ?? null) || ($filters['date_to'] ?? null) ? 'For the selected dates' : 'All time'],
            ['label' => 'Refunds', 'value' => number_format($count), 'icon' => 'fas fa-receipt', 'tone' => 'info'],
            ['label' => 'Average refund', 'value' => $currency . ' ' . number_format($count ? $total / $count : 0, 2), 'icon' => 'fas fa-divide', 'tone' => 'neutral'],
            ['label' => 'Refunded today', 'value' => $currency . ' ' . number_format((float) $this->whereUserBranch(Refund::query(), $user)->where('created_at', '>=', now()->startOfDay())->sum('amount'), 2), 'icon' => 'fas fa-calendar-day', 'tone' => 'warning'],
        ];

        return view('admin.finance.refunds', [
            'pageStats' => $pageStats,
            'refunds' => (clone $query)->with(['order:id,order_number', 'branch:id,name', 'refunder:id,name'])->latest('id')->paginate(25)->withQueryString(),
            'total' => $total,
            'filters' => $filters,
        ]);
    }

    public function store(
        Request $request,
        Order $order,
        ActivityLogger $logger,
        CashRegisterService $register,
        LoyaltyService $loyalty,
    ): RedirectResponse {
        $user = $this->authorizeRefunds($request, 'create');
        abort_unless($this->whereUserBranch(Order::query(), $user)->whereKey($order->id)->exists(), 404);
        $data = $request->validate([
            'payment_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $refund = DB::transaction(function () use ($order, $data, $user, $register, $loyalty): Refund {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $payment = Payment::query()->where('order_id', $lockedOrder->id)->whereKey($data['payment_id'])->lockForUpdate()->first();
            if (!$payment || !in_array($payment->status, ['successful', 'refunded'], true)) {
                throw ValidationException::withMessages(['payment_id' => 'Select a completed payment on this order.']);
            }
            $amount = round((float) $data['amount'], 2);
            $refundable = $payment->refundableAmount();
            if ($amount > $refundable + 0.000001) {
                throw ValidationException::withMessages(['amount' => 'At most ' . number_format($refundable, 2) . ' of this payment can be refunded.']);
            }
            if ($payment->payment_method !== 'cash' && $payment->payment_method !== 'manual' && blank($data['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'Enter the provider or bank reference for this refund.']);
            }
            $session = null;
            if ($payment->payment_method === 'cash') {
                $session = $register->sessionForCash($user, (int) $lockedOrder->branch_id, 'amount');
                if ($session) {
                    $register->assertCashAvailable($session, $amount, 'amount');
                }
            }

            $refund = Refund::create([
                'branch_id' => $lockedOrder->branch_id,
                'order_id' => $lockedOrder->id,
                'payment_id' => $payment->id,
                'cash_register_session_id' => $session?->id,
                'refunded_by' => $user->id,
                'refund_number' => 'RFD-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'method' => $payment->payment_method,
                'amount' => $amount,
                'reason' => $data['reason'],
                'reference' => $data['reference'] ?? null,
            ]);
            if ($refundable - $amount <= 0.000001) {
                $payment->update(['status' => 'refunded']);
            }
            $lockedOrder->update(['refunded_amount' => round((float) $lockedOrder->refunded_amount + $amount, 2)]);
            $loyalty->reverseForRefund($lockedOrder, $amount, $user->id);

            return $refund;
        });

        $logger->log('orders.refund.created', $refund, [
            'order' => $order->order_number, 'amount' => $refund->amount, 'method' => $refund->method,
        ]);

        return back()->with('success', 'Refund ' . $refund->refund_number . ' of ' . number_format((float) $refund->amount, 2) . ' recorded.');
    }

    private function authorizeRefunds(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $user->can('orders.refunds.create') || $user->can('admin')
            || ($operation === 'view' && $user->can('orders.refunds.view'));
        abort_unless($allowed, 403);

        return $user;
    }
}
