<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationsViewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_records_the_acting_users_restaurant(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->actingAs($user);

        app(ActivityLogger::class)->log('test.recorded', $branch, ['note' => 'hello']);

        $this->assertDatabaseHas('activity_logs', [
            'restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'action' => 'test.recorded', 'subject_id' => $branch->id,
        ]);
    }

    public function test_activity_log_is_tenant_scoped_filterable_and_permission_gated(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant, $foreignBranch] = $this->restaurantWithBranch('B');
        $viewer = $this->staff($restaurant, $branch);
        $colleague = $this->staff($restaurant, $branch, 'Colleague Cook');
        $outsider = $this->staff($foreignRestaurant, $foreignBranch, 'Outsider');
        ActivityLog::create(['restaurant_id' => $restaurant->id, 'user_id' => $colleague->id, 'action' => 'kitchen.order.ready', 'properties' => ['from' => 'preparing']]);
        ActivityLog::create(['restaurant_id' => $restaurant->id, 'user_id' => null, 'action' => 'reservations.created', 'properties' => ['branch_id' => $branch->id]]);
        // Rows written before activity logs carried a restaurant are attributed through their user.
        ActivityLog::create(['restaurant_id' => null, 'user_id' => $colleague->id, 'action' => 'inventory.wastage.recorded']);
        ActivityLog::create(['restaurant_id' => null, 'user_id' => $outsider->id, 'action' => 'inventory.secret.foreign']);
        ActivityLog::create(['restaurant_id' => $foreignRestaurant->id, 'user_id' => $outsider->id, 'action' => 'tables.foreign.change']);

        $this->actingAs($viewer)->get(route('admin.settings.activity-logs'))->assertForbidden();
        $this->grant($viewer, 'settings.activity-logs.view');

        $this->get(route('admin.settings.activity-logs'))
            ->assertOk()
            ->assertSee('kitchen.order.ready')
            ->assertSee('reservations.created')
            ->assertSee('inventory.wastage.recorded')
            ->assertSee('Colleague Cook')
            ->assertSee('System / customer')
            ->assertDontSee('inventory.secret.foreign')
            ->assertDontSee('tables.foreign.change');

        $this->get(route('admin.settings.activity-logs', ['area' => 'kitchen']))
            ->assertOk()->assertSee('kitchen.order.ready')->assertDontSee('inventory.wastage.recorded');
        $this->get(route('admin.settings.activity-logs', ['user_id' => $outsider->id]))
            ->assertSessionHasErrors('user_id');
    }

    public function test_menu_availability_sets_explicit_state_is_scoped_and_blocks_pos_sales(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $item = $this->menuItem($restaurant, 'Fish fillet');
        $foreignItem = $this->menuItem($foreignRestaurant, 'Foreign fillet');
        $user = $this->staff($restaurant, $branch);

        $this->actingAs($user)->get(route('admin.menu-availability.index'))->assertForbidden();
        $this->grant($user, 'menu.availability.manage');
        $this->get(route('admin.menu-availability.index'))->assertOk()->assertSee('Fish fillet')->assertDontSee('Foreign fillet');

        $this->patch(route('admin.menu-availability.update', $item->id), ['is_available' => 0])->assertRedirect();
        $this->patch(route('admin.menu-availability.update', $item->id), ['is_available' => 0])->assertRedirect();
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'is_available' => false]);
        $this->assertSame(1, ActivityLog::query()->where('action', 'menu.availability.updated')->count());
        $this->get(route('admin.menu-availability.index', ['show' => 'unavailable']))->assertOk()->assertSee('1 item currently unavailable');

        $this->patch(route('admin.menu-availability.update', $foreignItem->id), ['is_available' => 0])->assertNotFound();
        $this->assertDatabaseHas('menu_items', ['id' => $foreignItem->id, 'is_available' => true]);

        $this->grant($user, 'admin.pos.manage');
        $this->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id, 'order_type' => 'takeaway',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.menu_item_id');
        $this->assertDatabaseCount('orders', 0);

        $this->patch(route('admin.menu-availability.update', $item->id), ['is_available' => 1])->assertRedirect();
        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'is_available' => true]);
    }

    public function test_pos_current_and_history_split_orders_by_status_and_date(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $this->order($branch, 'POS-ACTIVE', 'preparing', now());
        $this->order($branch, 'POS-DONE-TODAY', 'completed', now());
        $this->order($branch, 'POS-DONE-OLD', 'completed', now()->subDays(10));
        $this->order($branch, 'POS-VOID', 'cancelled', now());
        $this->order($foreignBranch, 'POS-FOREIGN', 'preparing', now());
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'orders.view');
        $this->actingAs($user);

        $this->get(route('admin.pos.current'))
            ->assertOk()->assertSee('POS-ACTIVE')
            ->assertDontSee('POS-DONE-TODAY')->assertDontSee('POS-VOID')->assertDontSee('POS-FOREIGN');

        $this->get(route('admin.pos.history'))
            ->assertOk()->assertSee('POS-DONE-TODAY')->assertSee('POS-DONE-OLD')->assertSee('POS-VOID')
            ->assertDontSee('POS-ACTIVE');

        $this->get(route('admin.pos.history', ['date_from' => now()->subDay()->toDateString()]))
            ->assertOk()->assertSee('POS-DONE-TODAY')->assertDontSee('POS-DONE-OLD');
        $this->get(route('admin.pos.history', ['date_from' => now()->toDateString(), 'date_to' => now()->subDays(2)->toDateString()]))
            ->assertSessionHasErrors('date_to');
    }

    private function order(Branch $branch, string $number, string $status, $at): Order
    {
        return Order::create([
            'branch_id' => $branch->id, 'order_number' => $number, 'order_type' => 'takeaway',
            'status' => $status, 'total' => 10, 'ordered_at' => $at,
        ]);
    }

    private function menuItem(Restaurant $restaurant, string $name): MenuItem
    {
        $category = MenuCategory::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'mains-' . $restaurant->id],
            ['name' => 'Mains', 'is_active' => true],
        );

        return MenuItem::create([
            'restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id,
            'name' => $name, 'price' => 12, 'is_available' => true,
        ]);
    }

    private function restaurantWithBranch(string $suffix): array
    {
        $restaurant = Restaurant::create(['name' => 'Restaurant ' . $suffix, 'code' => 'REST-' . $suffix, 'currency' => 'USD']);
        $branch = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Branch ' . $suffix, 'code' => 'BR-' . $suffix]);

        return [$restaurant, $branch];
    }

    private function staff(Restaurant $restaurant, Branch $branch, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();

        return $user;
    }

    private function grant(User $user, string $ability): void
    {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id, 'ability' => $ability, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
