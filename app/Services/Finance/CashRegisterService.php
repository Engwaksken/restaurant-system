<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\CashRegisterSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash drawer accounting.
 *
 * expected cash = opening float + cash payments - cash refunds + pay-ins - pay-outs
 */
class CashRegisterService
{
    public function requiresSessionForCash(): bool
    {
        return (bool) config('restaurant.cash.require_open_session', true);
    }

    /** The user's open session in a branch, locked. Call inside a transaction. */
    public function lockedOpenSession(User $user, int $branchId): ?CashRegisterSession
    {
        return CashRegisterSession::query()
            ->where('opened_by', $user->id)
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Resolve the drawer that cash taken or paid out by this user belongs to.
     * Returns null when sessions are not required and none is open.
     */
    public function sessionForCash(User $user, int $branchId, string $field): ?CashRegisterSession
    {
        $session = $this->lockedOpenSession($user, $branchId);
        if (!$session && $this->requiresSessionForCash()) {
            throw ValidationException::withMessages([$field => 'Open a cash register session in this branch before handling cash.']);
        }

        return $session;
    }

    public function expectedCash(CashRegisterSession $session): float
    {
        $payments = (float) DB::table('payments')
            ->where('cash_register_session_id', $session->id)
            ->where('payment_method', 'cash')
            ->whereIn('status', ['successful', 'refunded'])
            ->sum('amount');
        $refunds = (float) DB::table('refunds')
            ->where('cash_register_session_id', $session->id)
            ->where('method', 'cash')
            ->sum('amount');
        $payIns = (float) DB::table('cash_movements')->where('cash_register_session_id', $session->id)->where('type', 'pay_in')->sum('amount');
        $payOuts = (float) DB::table('cash_movements')->where('cash_register_session_id', $session->id)->where('type', 'pay_out')->sum('amount');

        return round((float) $session->opening_float + $payments - $refunds + $payIns - $payOuts, 2);
    }

    /** Cash that may leave the drawer now; guards pay-outs and cash refunds. */
    public function assertCashAvailable(CashRegisterSession $session, float $amount, string $field): void
    {
        if ($amount > $this->expectedCash($session) + 0.000001) {
            throw ValidationException::withMessages([$field => 'The drawer does not hold enough cash for this amount.']);
        }
    }

    public function tolerance(): float
    {
        return max(0, (float) config('restaurant.cash.variance_tolerance', 0));
    }
}
