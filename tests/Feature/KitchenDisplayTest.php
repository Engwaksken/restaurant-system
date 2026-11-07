<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KitchenDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_requires_permission_and_shows_only_the_users_branch(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $otherBranch = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Other branch', 'code' => 'BR-A2']);
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $this->order($branch, 'KDS-OWN', 'pending', 'Grilled tilapia');
        $this->order($otherBranch, 'KDS-SIBLING', 'pending', 'Sibling dish');
        $this->order($foreignBranch, 'KDS-FOREIGN', 'preparing', 'Foreign dish');

        $user = $this->staff($restaurant, $branch);
        $this->actingAs($user)->get(route('admin.kitchen.index'))->assertForbidden();

        $this->grant($user, 'kitchen.view');
        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('KDS-OWN')
            ->assertSee('Grilled tilapia')
            ->assertDontSee('KDS-SIBLING')
            ->assertDontSee('KDS-FOREIGN')
            ->assertDontSee('Start preparing');

        $this->get(route('admin.kitchen.pending'))->assertOk()->assertSee('KDS-OWN');
        $this->get(route('admin.kitchen.preparing'))->assertOk()->assertDontSee('KDS-FOREIGN');
    }

    public function test_view_only_staff_cannot_advance_orders(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $order = $this->order($branch, 'KDS-VIEW', 'pending');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'kitchen.view');

        $this->actingAs($user)
            ->post(route('admin.kitchen.advance', $order), ['from' => 'pending', 'to' => 'preparing'])
            ->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_kitchen_advances_orders_and_syncs_item_status_with_audit_log(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $order = $this->order($branch, 'KDS-FLOW', 'confirmed');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'kitchen.manage');

        $this->actingAs($user)
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Start preparing');

        $this->post(route('admin.kitchen.advance', $order), ['from' => 'confirmed', 'to' => 'preparing'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'preparing']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'kitchen_status' => 'preparing']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'kitchen_status' => 'cancelled']);

        $this->post(route('admin.kitchen.advance', $order), ['from' => 'preparing', 'to' => 'ready'])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready']);
        $this->assertDatabaseMissing('order_items', ['order_id' => $order->id, 'kitchen_status' => 'preparing']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'kitchen_status' => 'ready']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'kitchen_status' => 'cancelled']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id, 'action' => 'kitchen.order.ready', 'subject_id' => $order->id,
        ]);
    }

    public function test_stale_screen_does_not_reapply_a_transition(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $order = $this->order($branch, 'KDS-STALE', 'ready');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'kitchen.manage');

        $this->actingAs($user)
            ->post(route('admin.kitchen.advance', $order), ['from' => 'preparing', 'to' => 'ready'])
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'ready']);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_kitchen_cannot_skip_steps_complete_orders_or_touch_other_tenants(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $order = $this->order($branch, 'KDS-RULES', 'pending');
        $readyOrder = $this->order($branch, 'KDS-READY', 'ready');
        $foreignOrder = $this->order($foreignBranch, 'KDS-OTHER', 'pending');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'kitchen.manage');
        $this->actingAs($user);

        $this->post(route('admin.kitchen.advance', $order), ['from' => 'pending', 'to' => 'ready'])
            ->assertUnprocessable();
        $this->post(route('admin.kitchen.advance', $readyOrder), ['from' => 'ready', 'to' => 'completed'])
            ->assertSessionHasErrors(['from', 'to']);
        $this->post(route('admin.kitchen.advance', $foreignOrder), ['from' => 'pending', 'to' => 'preparing'])
            ->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseHas('orders', ['id' => $readyOrder->id, 'status' => 'ready']);
        $this->assertDatabaseHas('orders', ['id' => $foreignOrder->id, 'status' => 'pending']);
    }

    public function test_completed_queue_lists_todays_completed_orders(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $this->order($branch, 'KDS-DONE-TODAY', 'completed', 'Chips', ['completed_at' => now()]);
        $this->order($branch, 'KDS-DONE-OLD', 'completed', 'Chips', ['completed_at' => now()->subDays(2)]);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'kitchen.view');

        $this->actingAs($user)->get(route('admin.kitchen.completed'))
            ->assertOk()
            ->assertSee('KDS-DONE-TODAY')
            ->assertDontSee('KDS-DONE-OLD')
            ->assertDontSee('Mark ready');
    }

    private function restaurantWithBranch(string $suffix): array
    {
        $restaurant = Restaurant::create(['name' => 'Restaurant ' . $suffix, 'code' => 'REST-' . $suffix, 'currency' => 'USD']);
        $branch = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Branch ' . $suffix, 'code' => 'BR-' . $suffix]);

        return [$restaurant, $branch];
    }

    private function staff(Restaurant $restaurant, Branch $branch): User
    {
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();

        return $user;
    }

    private function grant(User $user, string $ability): void
    {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id, 'ability' => $ability, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function order(Branch $branch, string $number, string $status, string $dish = 'House special', array $extra = []): Order
    {
        $order = Order::create(array_merge([
            'branch_id' => $branch->id,
            'order_number' => $number,
            'order_type' => 'dine_in',
            'status' => $status,
            'total' => 20,
            'ordered_at' => now()->subMinutes(5),
        ], $extra));
        $order->items()->create([
            'item_name' => $dish, 'unit_price' => 10, 'quantity' => 2, 'total' => 20, 'kitchen_status' => 'pending',
        ]);
        $order->items()->create([
            'item_name' => 'Voided side', 'unit_price' => 5, 'quantity' => 1, 'total' => 5, 'kitchen_status' => 'cancelled',
        ]);

        return $order;
    }
}
