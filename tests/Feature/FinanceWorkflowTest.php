<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class FinanceWorkflowTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_cash_payments_require_an_open_drawer_and_closing_computes_variance(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $cashier = $this->staff($restaurant, $branch);
        $this->grant($cashier, 'orders.payments.create', 'finance.cash-register.use');
        $order = $this->order($branch, 'ORD-CASH', 100);
        $this->actingAs($cashier);

        $this->post(route('admin.orders.payments.store', $order), ['payment_method' => 'cash', 'amount' => 60])
            ->assertSessionHasErrors('payment_method');
        $this->assertDatabaseCount('payments', 0);

        $this->post(route('admin.cash-register.open'), ['branch_id' => $branch->id, 'opening_float' => 50])->assertSessionHasNoErrors();
        $this->post(route('admin.cash-register.open'), ['branch_id' => $branch->id, 'opening_float' => 10])->assertSessionHasErrors('branch_id');
        $session = CashRegisterSession::query()->firstOrFail();

        $this->post(route('admin.orders.payments.store', $order), ['payment_method' => 'cash', 'amount' => 60])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'cash_register_session_id' => $session->id, 'amount' => '60.00']);
        $this->post(route('admin.orders.payments.store', $order), ['payment_method' => 'card', 'amount' => 40])
            ->assertSessionHasErrors('transaction_reference');
        $this->post(route('admin.orders.payments.store', $order), ['payment_method' => 'card', 'amount' => 40, 'transaction_reference' => 'CARD-1'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'completed', 'balance' => '0.00']);

        $this->post(route('admin.cash-register.movement', $session->id), ['type' => 'pay_out', 'amount' => 500, 'reason' => 'Gas refill'])
            ->assertSessionHasErrors('amount');
        $this->post(route('admin.cash-register.movement', $session->id), ['type' => 'pay_out', 'amount' => 20, 'reason' => 'Gas refill'])
            ->assertSessionHasNoErrors();

        // Expected: 50 float + 60 cash - 20 pay-out = 90. Counting 85 needs an explanation.
        $this->post(route('admin.cash-register.close', $session->id), ['counted_cash' => 85])->assertSessionHasErrors('closing_notes');
        $this->post(route('admin.cash-register.close', $session->id), ['counted_cash' => 85, 'closing_notes' => 'Gave wrong change'])
            ->assertSessionHasNoErrors();
        $session->refresh();
        $this->assertSame(['closed', '90.00', '85.00', '-5.00', true], [
            $session->status, $session->expected_cash, $session->counted_cash, $session->variance, $session->review_required,
        ]);
        $this->assertDatabaseHas('payments', ['payment_method' => 'cash', 'reconciliation_status' => 'matched', 'statement_reference' => $session->reference]);
        $this->assertDatabaseHas('payments', ['payment_method' => 'card', 'reconciliation_status' => 'unreconciled']);

        // The cashier cannot review their own variance; a separate reviewer can.
        $this->grant($cashier, 'finance.cash-register.review');
        $this->post(route('admin.cash-register.review', $session->id), ['review_notes' => 'Looks fine'])->assertForbidden();
        $manager = $this->staff($restaurant, $branch);
        $this->grant($manager, 'finance.cash-register.review');
        $this->actingAs($manager)->get(route('admin.cash-register.index', ['review' => 1]))->assertOk()->assertSee($session->reference);
        $this->post(route('admin.cash-register.review', $session->id), ['review_notes' => 'Counted again, confirmed short'])->assertSessionHasNoErrors();
        $this->assertNotNull($session->fresh()->reviewed_at);
    }

    public function test_refunds_are_bounded_by_payment_and_cash_refunds_leave_the_drawer(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $cashier = $this->staff($restaurant, $branch);
        $this->grant($cashier, 'orders.payments.create', 'finance.cash-register.use', 'orders.view');
        $order = $this->order($branch, 'ORD-REFUND', 100);
        $foreignOrder = $this->order($foreignBranch, 'ORD-FOREIGN', 100);
        $this->actingAs($cashier);
        $this->post(route('admin.cash-register.open'), ['branch_id' => $branch->id, 'opening_float' => 0]);
        $this->post(route('admin.orders.payments.store', $order), ['payment_method' => 'cash', 'amount' => 100]);
        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $this->post(route('admin.orders.refunds.store', $order), ['payment_id' => $payment->id, 'amount' => 30, 'reason' => 'Cold soup'])
            ->assertForbidden();
        $this->grant($cashier, 'orders.refunds.create');
        $this->post(route('admin.orders.refunds.store', $foreignOrder), ['payment_id' => $payment->id, 'amount' => 30, 'reason' => 'Cold soup'])
            ->assertNotFound();
        $this->post(route('admin.orders.refunds.store', $order), ['payment_id' => $payment->id, 'amount' => 130, 'reason' => 'Cold soup'])
            ->assertSessionHasErrors('amount');
        $this->post(route('admin.orders.refunds.store', $order), ['payment_id' => $payment->id, 'amount' => 30, 'reason' => 'Cold soup'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $order), ['payment_id' => $payment->id, 'amount' => 70, 'reason' => 'Whole order wrong'])
            ->assertSessionHasNoErrors();
        $this->post(route('admin.orders.refunds.store', $order), ['payment_id' => $payment->id, 'amount' => 1, 'reason' => 'One more time'])
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'refunded_amount' => '100.00']);
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded']);
        $session = CashRegisterSession::query()->firstOrFail();
        $this->post(route('admin.cash-register.close', $session->id), ['counted_cash' => 0])->assertSessionHasNoErrors();
        $this->assertSame('0.00', $session->fresh()->expected_cash);

        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Whole order wrong')->assertSee('100.00');
        $this->get(route('admin.pos.returns'))->assertOk()->assertSee('Cold soup');
    }

    public function test_reconciliation_matches_only_scoped_non_cash_payments(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $mobile = $this->payment($this->order($branch, 'ORD-M', 50), 'mtn_mobile_money', 'MM-123');
        $card = $this->payment($this->order($branch, 'ORD-C', 70), 'card', 'CARD-9');
        $cash = $this->payment($this->order($branch, 'ORD-$', 20), 'cash', null);
        $foreign = $this->payment($this->order($foreignBranch, 'ORD-F', 90), 'card', 'CARD-FOREIGN');
        $user = $this->staff($restaurant, $branch);
        $this->actingAs($user)->get(route('admin.reconciliation.index'))->assertForbidden();
        $this->grant($user, 'finance.reconciliation.manage');

        $this->get(route('admin.reconciliation.index'))->assertOk()
            ->assertSee('MM-123')->assertSee('CARD-9')->assertDontSee('CARD-FOREIGN')->assertDontSee($cash->receipt_number);

        $this->post(route('admin.reconciliation.match'), ['payment_ids' => [$mobile->id, $foreign->id], 'statement_reference' => 'STMT-1'])
            ->assertStatus(422);
        $this->post(route('admin.reconciliation.match'), ['payment_ids' => [$cash->id], 'statement_reference' => 'STMT-1'])
            ->assertStatus(422);
        $this->post(route('admin.reconciliation.match'), ['payment_ids' => [$mobile->id], 'statement_reference' => 'MTN-NOV-07'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', ['id' => $mobile->id, 'reconciliation_status' => 'matched', 'statement_reference' => 'MTN-NOV-07', 'reconciled_by' => $user->id]);

        $this->patch(route('admin.reconciliation.dispute', $card->id), ['status' => 'disputed', 'note' => 'Not on bank statement'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payments', ['id' => $card->id, 'reconciliation_status' => 'disputed']);
        $this->patch(route('admin.reconciliation.dispute', $foreign->id), ['status' => 'disputed', 'note' => 'Not mine at all'])->assertNotFound();
        $this->assertDatabaseHas('payments', ['id' => $foreign->id, 'reconciliation_status' => 'unreconciled']);
    }

    private function order(Branch $branch, string $number, float $total): Order
    {
        return Order::create([
            'branch_id' => $branch->id, 'order_number' => $number, 'order_type' => 'takeaway', 'status' => 'ready',
            'subtotal' => $total, 'total' => $total, 'balance' => $total, 'ordered_at' => now(),
        ]);
    }

    private function payment(Order $order, string $method, ?string $reference): Payment
    {
        return Payment::create([
            'order_id' => $order->id, 'receipt_number' => 'RCT-' . $order->order_number, 'payment_method' => $method,
            'amount' => $order->total, 'transaction_reference' => $reference, 'status' => 'successful', 'paid_at' => now(),
        ]);
    }
}
