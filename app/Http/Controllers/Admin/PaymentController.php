<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\Finance\CashRegisterService;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Staff-recorded order payments. Cash goes into the cashier's open drawer;
 * a fully paid order is completed and earns loyalty points.
 */
class PaymentController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        ActivityLogger $logger,
        CashRegisterService $register,
        LoyaltyService $loyalty,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id && ($user->can('orders.payments.create') || $user->can('orders.manage') || $user->can('admin')), 403);
        abort_unless($order->branch()->where('restaurant_id', $user->restaurant_id)->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))->exists(), 404);
        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys(config('restaurant.payment_methods', [])))],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_reference' => [Rule::requiredIf(fn () => !in_array($request->input('payment_method'), ['cash', 'manual'], true)), 'nullable', 'string', 'max:255'],
        ]);

        $payment = DB::transaction(function () use ($data, $order, $user, $register, $loyalty): Payment {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($lockedOrder->status === 'cancelled', 422, 'Payments cannot be recorded against a cancelled order.');
            abort_if(
                Payment::query()->where('order_id', $lockedOrder->id)->where('status', 'pending')->exists(),
                409,
                'An online payment is in progress for this order.'
            );
            if ((float) $data['amount'] > (float) $lockedOrder->balance) {
                throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the outstanding balance.']);
            }
            $session = $data['payment_method'] === 'cash'
                ? $register->sessionForCash($user, (int) $lockedOrder->branch_id, 'payment_method')
                : null;

            $payment = Payment::create([
                'order_id' => $lockedOrder->id,
                'customer_id' => $lockedOrder->customer_id,
                'received_by' => $user->id,
                'cash_register_session_id' => $session?->id,
                'receipt_number' => 'RCT-' . Str::upper((string) Str::uuid()),
                'payment_method' => $data['payment_method'],
                'amount' => round((float) $data['amount'], 2),
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'status' => 'successful',
                'paid_at' => now(),
            ]);

            $paid = (float) $lockedOrder->payments()->whereIn('status', ['successful', 'refunded'])->sum('amount');
            $fullyPaid = $paid + 0.000001 >= (float) $lockedOrder->total;
            $lockedOrder->update([
                'paid_amount' => $paid,
                'balance' => max(0, round((float) $lockedOrder->total - $paid, 2)),
                'status' => $fullyPaid ? 'completed' : $lockedOrder->status,
                'completed_at' => $fullyPaid ? ($lockedOrder->completed_at ?? now()) : $lockedOrder->completed_at,
            ]);
            if ($fullyPaid) {
                $loyalty->earnForOrder($lockedOrder, $user->id);
            }

            return $payment;
        });

        $logger->log('payment.received', $payment, ['order' => $order->order_number, 'method' => $payment->payment_method, 'amount' => $payment->amount]);

        return back()->with('success', 'Payment recorded successfully.');
    }
}
