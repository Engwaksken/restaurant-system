<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Points ledger. Every change locks the customer row and writes a ledger entry
 * holding the resulting balance. Call inside a database transaction.
 *
 * - Earn: once per order when it is completed, on net spend (after discounts,
 *   before tax and delivery).
 * - Redeem: at the POS against a known customer, within the configured minimum
 *   and maximum share of the order.
 * - Reverse: a cancelled order returns redeemed points; a refund claws back
 *   earned points in proportion to the amount refunded.
 */
class LoyaltyService
{
    public function enabled(): bool
    {
        return (bool) config('restaurant.loyalty.enabled', false);
    }

    public function pointValue(): float
    {
        return max(0, (float) config('restaurant.loyalty.point_value', 0));
    }

    /** Validate a redemption and return its currency value. */
    public function quoteRedemption(Customer $customer, int $points, float $subtotalAfterDiscount, string $field = 'loyalty_points'): float
    {
        if (!$this->enabled()) {
            throw ValidationException::withMessages([$field => 'The loyalty programme is not enabled.']);
        }
        $minimum = max(1, (int) config('restaurant.loyalty.min_redeem_points', 1));
        if ($points < $minimum) {
            throw ValidationException::withMessages([$field => 'At least ' . $minimum . ' points must be redeemed at a time.']);
        }
        if ($points > (int) $customer->loyalty_points) {
            throw ValidationException::withMessages([$field => 'The customer only has ' . (int) $customer->loyalty_points . ' points.']);
        }
        $value = round($points * $this->pointValue(), 2);
        $cap = round($subtotalAfterDiscount * max(0, min(100, (float) config('restaurant.loyalty.max_redeem_percent', 100))) / 100, 2);
        if ($value > $cap + 0.000001) {
            throw ValidationException::withMessages([$field => 'Points can pay for at most ' . number_format($cap, 2) . ' of this order.']);
        }

        return $value;
    }

    public function redeem(Customer $customer, int $points, Order $order, ?int $userId): void
    {
        $this->apply($customer, -$points, 'redeem', $order->id, $userId, 'Redeemed on ' . $order->order_number);
    }

    public function earnForOrder(Order $order, ?int $userId): void
    {
        if (!$this->enabled() || !$order->customer_id || $order->status !== 'completed') {
            return;
        }
        if (LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'earn')->exists()) {
            return;
        }
        $earnAmount = (float) config('restaurant.loyalty.earn_amount', 0);
        $netSpend = (float) $order->subtotal - (float) $order->discount - (float) $order->loyalty_discount - (float) $order->refunded_amount;
        $points = $earnAmount > 0 ? (int) floor(max(0, $netSpend) / $earnAmount) : 0;
        if ($points <= 0) {
            return;
        }
        $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
        if ($customer) {
            $this->apply($customer, $points, 'earn', $order->id, $userId, 'Earned on ' . $order->order_number);
        }
    }

    /** Return points redeemed on an order that is being cancelled. */
    public function returnRedeemed(Order $order, ?int $userId): void
    {
        if (!$order->customer_id || (int) $order->loyalty_points_redeemed <= 0) {
            return;
        }
        $already = (int) LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'reverse')->where('points', '>', 0)->sum('points');
        $points = (int) $order->loyalty_points_redeemed - $already;
        $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
        if ($points > 0 && $customer) {
            $this->apply($customer, $points, 'reverse', $order->id, $userId, 'Returned: ' . $order->order_number . ' cancelled');
        }
    }

    /** Claw back earned points in proportion to a refund (never below zero). */
    public function reverseForRefund(Order $order, float $refundAmount, ?int $userId): void
    {
        if (!$order->customer_id || (float) $order->total <= 0) {
            return;
        }
        $earned = (int) LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'earn')->sum('points');
        if ($earned <= 0) {
            return;
        }
        $clawedBack = -(int) LoyaltyTransaction::query()->where('order_id', $order->id)->where('type', 'reverse')->where('points', '<', 0)->sum('points');
        $target = (int) floor($earned * min(1, ((float) $order->refunded_amount) / (float) $order->total));
        $points = min($target - $clawedBack, $earned - $clawedBack);
        $customer = Customer::query()->whereKey($order->customer_id)->lockForUpdate()->first();
        if ($points > 0 && $customer) {
            $points = min($points, (int) $customer->loyalty_points);
            if ($points > 0) {
                $this->apply($customer, -$points, 'reverse', $order->id, $userId, 'Refund on ' . $order->order_number);
            }
        }
    }

    public function adjust(Customer $customer, int $points, string $note, ?int $userId): LoyaltyTransaction
    {
        if ($points < 0 && -$points > (int) $customer->loyalty_points) {
            throw ValidationException::withMessages(['points' => 'The balance cannot go below zero.']);
        }

        return $this->apply($customer, $points, 'adjust', null, $userId, $note);
    }

    private function apply(Customer $customer, int $points, string $type, ?int $orderId, ?int $userId, string $note): LoyaltyTransaction
    {
        $balance = (int) $customer->loyalty_points + $points;
        abort_if($balance < 0, 422, 'Loyalty balance cannot go below zero.');
        $customer->forceFill(['loyalty_points' => $balance])->save();

        return LoyaltyTransaction::create([
            'customer_id' => $customer->id, 'order_id' => $orderId, 'user_id' => $userId,
            'type' => $type, 'points' => $points, 'balance_after' => $balance, 'note' => $note,
        ]);
    }
}
