<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\HeldOrder;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class MenuOptionsLoyaltyTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_menu_options_are_managed_per_tenant(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $pizza = $this->menuItem($restaurant, 'Pizza', 20);
        $foreignItem = $this->menuItem($foreignRestaurant, 'Foreign pizza', 20);
        $foreignVariant = MenuItemVariant::create(['menu_item_id' => $foreignItem->id, 'name' => 'Large', 'price' => 30]);
        $user = $this->staff($restaurant, $branch);
        $this->actingAs($user)->get(route('admin.menu-options.index', 'variants'))->assertForbidden();
        $this->grant($user, 'menu.options.manage');

        $this->get(route('admin.menu-options.index', 'variants'))->assertOk()->assertSee('Pizza')->assertDontSee('Foreign pizza');
        $this->post(route('admin.menu-options.store', 'variants'), ['menu_item_id' => $pizza->id, 'name' => 'Large', 'price' => 28])->assertSessionHasNoErrors();
        $this->post(route('admin.menu-options.store', 'variants'), ['menu_item_id' => $pizza->id, 'name' => 'Large', 'price' => 29])->assertSessionHasErrors('name');
        $this->post(route('admin.menu-options.store', 'addons'), ['menu_item_id' => $pizza->id, 'name' => 'Extra cheese', 'price' => 3])->assertSessionHasNoErrors();
        $this->post(route('admin.menu-options.store', 'addons'), ['menu_item_id' => $foreignItem->id, 'name' => 'Sneaky', 'price' => 1])->assertNotFound();
        $this->put(route('admin.menu-options.update', ['variants', $foreignVariant->id]), ['name' => 'Hijack', 'price' => 1])->assertNotFound();
        $this->assertDatabaseHas('menu_item_variants', ['menu_item_id' => $pizza->id, 'name' => 'Large', 'price' => '28.00']);
        $this->assertDatabaseHas('menu_item_addons', ['menu_item_id' => $pizza->id, 'name' => 'Extra cheese']);
    }

    public function test_pos_prices_variants_and_addons_server_side_and_requires_a_variant(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $pizza = $this->menuItem($restaurant, 'Pizza', 20);
        $large = MenuItemVariant::create(['menu_item_id' => $pizza->id, 'name' => 'Large', 'price' => 30]);
        $soldOut = MenuItemVariant::create(['menu_item_id' => $pizza->id, 'name' => 'Family', 'price' => 45, 'is_available' => false]);
        $cheese = MenuItemAddon::create(['menu_item_id' => $pizza->id, 'name' => 'Extra cheese', 'price' => 4]);
        $otherItem = $this->menuItem($restaurant, 'Soda', 2);
        $foreignAddon = MenuItemAddon::create(['menu_item_id' => $otherItem->id, 'name' => 'Ice', 'price' => 1]);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'admin.pos.manage');
        $this->actingAs($user);
        $checkout = fn (array $line) => $this->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id, 'order_type' => 'takeaway', 'items' => [$line],
        ]);

        $checkout(['menu_item_id' => $pizza->id, 'quantity' => 1])->assertSessionHasErrors('items.0.variant_id');
        $checkout(['menu_item_id' => $pizza->id, 'quantity' => 1, 'variant_id' => $soldOut->id])->assertSessionHasErrors('items.0.variant_id');
        $checkout(['menu_item_id' => $pizza->id, 'quantity' => 1, 'variant_id' => $large->id, 'addon_ids' => [$foreignAddon->id]])->assertSessionHasErrors('items.0.addon_ids');
        $this->assertDatabaseCount('orders', 0);

        $checkout(['menu_item_id' => $pizza->id, 'quantity' => 2, 'variant_id' => $large->id, 'addon_ids' => [$cheese->id], 'notes' => 'Well done'])
            ->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame('68.00', $order->subtotal);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id, 'menu_item_variant_id' => $large->id, 'variant_name' => 'Large',
            'unit_price' => '34.00', 'total' => '68.00', 'notes' => 'Well done',
        ]);
        $this->assertSame([['id' => $cheese->id, 'name' => 'Extra cheese', 'price' => 4]], $order->items()->first()->addons);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Pizza (Large)')->assertSee('Extra cheese');
    }

    public function test_held_orders_are_saved_resumed_and_cleared_on_checkout(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant, $foreignBranch] = $this->restaurantWithBranch('B');
        $soup = $this->menuItem($restaurant, 'Soup', 5);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'admin.pos.manage');
        $foreignHeld = HeldOrder::create(['branch_id' => $foreignBranch->id, 'label' => 'Foreign table', 'order_type' => 'dine_in', 'items' => []]);
        $this->actingAs($user);

        $this->post(route('admin.pos.hold'), [
            'branch_id' => $branch->id, 'order_type' => 'dine_in', 'hold_label' => 'Table 4',
            'items' => [['menu_item_id' => $soup->id, 'quantity' => 2]],
        ])->assertRedirect(route('admin.pos.index'));
        $held = HeldOrder::query()->where('label', 'Table 4')->firstOrFail();
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('admin.pos.held'))->assertOk()->assertSee('Table 4')->assertDontSee('Foreign table');
        $this->get(route('admin.pos.index', ['resume' => $held->id]))->assertOk()->assertSee('Resumed: Table 4');
        $this->delete(route('admin.pos.held.destroy', $foreignHeld->id))->assertNotFound();

        $this->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id, 'order_type' => 'dine_in', 'held_order_id' => $held->id,
            'items' => [['menu_item_id' => $soup->id, 'quantity' => 2]],
        ])->assertRedirect();
        $this->assertDatabaseMissing('held_orders', ['id' => $held->id]);
        $this->assertDatabaseHas('held_orders', ['id' => $foreignHeld->id]);
        $this->assertDatabaseHas('orders', ['branch_id' => $branch->id, 'subtotal' => '10.00']);
    }

    public function test_public_checkout_prices_selected_options(): void
    {
        Mail::fake();
        config(['restaurant.public_code' => 'PUBLIC', 'restaurant.tax_rate' => 0, 'restaurant.delivery_fee' => 0]);
        $restaurant = Restaurant::create(['name' => 'Public', 'code' => 'PUBLIC', 'currency' => 'USD']);
        Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Only branch', 'code' => 'ONLY']);
        $burger = $this->menuItem($restaurant, 'Burger', 8);
        $double = MenuItemVariant::create(['menu_item_id' => $burger->id, 'name' => 'Double', 'price' => 12]);
        $bacon = MenuItemAddon::create(['menu_item_id' => $burger->id, 'name' => 'Bacon', 'price' => 2.5]);

        $this->get('/')->assertOk()->assertSee('Double')->assertSee('Bacon');
        $base = ['name' => 'Guest', 'phone' => '0700111222'];
        $this->post(route('order.store'), [...$base, 'items' => [['menu_item_id' => $burger->id, 'quantity' => 1]]])
            ->assertSessionHasErrors('items.0.variant_id');
        $this->post(route('order.store'), [...$base, 'items' => [['menu_item_id' => $burger->id, 'quantity' => 2, 'variant_id' => $double->id, 'addon_ids' => [$bacon->id]]]])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['order_type' => 'online', 'subtotal' => '29.00', 'total' => '29.00']);
        $this->assertDatabaseHas('order_items', ['variant_name' => 'Double', 'unit_price' => '14.50']);
    }

    public function test_loyalty_points_are_earned_redeemed_returned_and_clawed_back(): void
    {
        config(['restaurant.loyalty' => [
            'enabled' => true, 'earn_amount' => 10, 'point_value' => 0.5, 'min_redeem_points' => 10, 'max_redeem_percent' => 50,
        ]]);
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $meal = $this->menuItem($restaurant, 'Meal', 40);
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina', 'phone' => '0700000001', 'loyalty_points' => 50]);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'admin.pos.manage', 'orders.manage', 'orders.refunds.create', 'finance.cash-register.use');
        $this->actingAs($user);
        $sale = fn (array $extra = []) => $this->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id, 'order_type' => 'takeaway', 'items' => [['menu_item_id' => $meal->id, 'quantity' => 1]], ...$extra,
        ]);

        $sale(['loyalty_points' => 20])->assertSessionHasErrors('loyalty_points');
        $sale(['customer_id' => $customer->id, 'loyalty_points' => 5])->assertSessionHasErrors('loyalty_points');
        $sale(['customer_id' => $customer->id, 'loyalty_points' => 60])->assertSessionHasErrors('loyalty_points');

        // 40 points x 0.5 = 20 off a 40 subtotal: exactly the 50% cap.
        $sale(['customer_id' => $customer->id, 'loyalty_points' => 40])->assertRedirect();
        $redeemed = Order::query()->latest('id')->firstOrFail();
        $this->assertSame(['20.00', '20.00', 40], [$redeemed->loyalty_discount, $redeemed->total, $redeemed->loyalty_points_redeemed]);
        $this->assertSame(10, $customer->fresh()->loyalty_points);

        // Cancelling returns the redeemed points.
        $this->patch(route('admin.orders.cancel', $redeemed))->assertRedirect();
        $this->assertSame(50, $customer->fresh()->loyalty_points);

        // A fully paid order earns floor(40 / 10) = 4 points, once.
        $sale(['customer_id' => $customer->id])->assertRedirect();
        $earning = Order::query()->latest('id')->firstOrFail();
        $this->post(route('admin.cash-register.open'), ['branch_id' => $branch->id, 'opening_float' => 0]);
        $this->post(route('admin.orders.payments.store', $earning), ['payment_method' => 'cash', 'amount' => 40])->assertSessionHasNoErrors();
        $this->assertSame(54, $customer->fresh()->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', ['order_id' => $earning->id, 'type' => 'earn', 'points' => 4]);

        // Refunding half the order claws back half the earned points.
        $payment = Payment::query()->where('order_id', $earning->id)->firstOrFail();
        $this->post(route('admin.orders.refunds.store', $earning), ['payment_id' => $payment->id, 'amount' => 20, 'reason' => 'Half wrong'])->assertSessionHasNoErrors();
        $this->assertSame(52, $customer->fresh()->loyalty_points);
    }

    public function test_loyalty_adjustments_and_customer_erasure(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina Private', 'phone' => '0700000001', 'email' => 'amina@example.com', 'loyalty_points' => 5]);
        $order = Order::create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'order_number' => 'ORD-ERASE', 'order_type' => 'takeaway', 'status' => 'completed', 'total' => 10, 'balance' => 10]);
        Reservation::create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'reservation_number' => 'RSV-1', 'guest_name' => 'Amina Private', 'phone' => '0700000001', 'reserved_at' => now()->addDay()]);
        OrderFeedback::create(['branch_id' => $branch->id, 'order_id' => $order->id, 'customer_id' => $customer->id, 'rating' => 5, 'comment' => 'Call me on 0700000001']);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'customers.view', 'customers.loyalty.adjust');
        $this->actingAs($user);

        $this->post(route('admin.loyalty.adjust'), ['customer_id' => $customer->id, 'points' => -10, 'note' => 'Too many'])->assertSessionHasErrors('points');
        $this->post(route('admin.loyalty.adjust'), ['customer_id' => $customer->id, 'points' => 15, 'note' => 'Birthday bonus'])->assertSessionHasNoErrors();
        $this->assertSame(20, $customer->fresh()->loyalty_points);
        $this->get(route('admin.loyalty.index'))->assertOk()->assertSee('Birthday bonus');

        $this->delete(route('admin.customers.erase', $customer->id), ['confirm' => 'ERASE'])->assertForbidden();
        $this->grant($user, 'customers.erase');
        $this->delete(route('admin.customers.erase', $customer->id), ['confirm' => 'yes'])->assertSessionHasErrors('confirm');
        $this->delete(route('admin.customers.erase', $customer->id), ['confirm' => 'ERASE'])->assertSessionHasErrors('confirm');

        $order->update(['balance' => 0]);
        $this->delete(route('admin.customers.erase', $customer->id), ['confirm' => 'ERASE'])->assertRedirect(route('admin.customers.index'));
        $erased = Customer::withTrashed()->findOrFail($customer->id);
        $this->assertSame(['Erased customer #' . $customer->id, null, null, 0], [$erased->name, $erased->phone, $erased->email, $erased->loyalty_points]);
        $this->assertTrue($erased->trashed());
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'customer_id' => $customer->id]);
        $this->assertDatabaseHas('reservations', ['reservation_number' => 'RSV-1', 'guest_name' => 'Erased guest', 'phone' => 'erased']);
        $this->assertDatabaseHas('order_feedback', ['order_id' => $order->id, 'comment' => null, 'rating' => 5]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'customers.erased', 'properties' => json_encode(['name' => 'Amina Private'])]);
    }
}
