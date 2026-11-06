<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_item_from_another_restaurant_cannot_be_updated(): void
    {
        Gate::define('admin.menu-items.manage', fn () => true);
        [$restaurant, $category] = $this->restaurantWithCategory('A');
        [, $foreignCategory] = $this->restaurantWithCategory('B');
        $item = MenuItem::create([
            'restaurant_id' => $foreignCategory->restaurant_id,
            'menu_category_id' => $foreignCategory->id,
            'name' => 'Foreign item', 'price' => 10,
        ]);
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();

        $this->actingAs($user)->put(route('admin.menu-items.update', $item), [
            'menu_category_id' => $category->id,
            'name' => 'Changed item', 'price' => 12,
        ])->assertNotFound();

        $this->assertDatabaseHas('menu_items', ['id' => $item->id, 'name' => 'Foreign item']);
    }

    public function test_pos_rejects_a_branch_from_another_restaurant(): void
    {
        Gate::define('admin.pos.manage', fn () => true);
        [$restaurant] = $this->restaurantWithCategory('A');
        [, , $foreignBranch] = $this->restaurantWithCategory('B');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();

        $this->actingAs($user)->post(route('admin.pos.checkout'), [
            'branch_id' => $foreignBranch->id,
            'order_type' => 'takeaway',
            'items' => [['menu_item_id' => 1, 'quantity' => 1]],
        ])->assertSessionHasErrors('branch_id');
    }

    public function test_pos_ignores_client_tax_and_rejects_ungranted_discount(): void
    {
        Gate::define('admin.pos.manage', fn () => true);
        [$restaurant, $category, $branch] = $this->restaurantWithCategory('A');
        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'POS meal', 'price' => 10, 'is_available' => true,
        ]);
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();

        $this->actingAs($user)->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'tax' => 1000,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();
        $order = Order::query()->where('branch_id', $branch->id)->firstOrFail();
        $this->assertSame('0.00', $order->tax);
        $this->assertSame('10.00', $order->total);

        $this->post(route('admin.pos.checkout'), [
            'branch_id' => $branch->id,
            'order_type' => 'dine_in',
            'discount' => 1,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertForbidden();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_from_another_restaurant_cannot_be_changed(): void
    {
        Gate::define('orders.manage', fn () => true);
        [$restaurant] = $this->restaurantWithCategory('A');
        [, , $foreignBranch] = $this->restaurantWithCategory('B');
        $order = Order::create([
            'branch_id' => $foreignBranch->id,
            'order_number' => 'ORDER-FOREIGN-001',
            'order_type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
        ]);
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();

        $this->actingAs($user)->patch(route('admin.orders.status.update', $order), [
            'status' => 'confirmed',
        ])->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

    public function test_menu_and_pos_routes_deny_users_without_explicit_permissions(): void
    {
        [$restaurant] = $this->restaurantWithCategory('A');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();

        $this->actingAs($user)->get(route('admin.pos.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.menu-items.index'))->assertForbidden();
    }

    public function test_generic_module_write_routes_are_disabled_even_for_admins(): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id, 'ability' => 'admin', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->post(route('admin.modules.store', ['module' => 'menu.categories']))
            ->assertNotFound();
    }

    public function test_user_permission_grant_is_recognized_by_authorization_checks(): void
    {
        $user = User::factory()->create();
        DB::table('user_permissions')->insert([
            'user_id' => $user->id,
            'ability' => 'admin.pos.view',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($user->can('admin.pos.view'));
        $this->assertFalse($user->can('admin.pos.checkout'));
    }

    public function test_order_status_must_follow_the_transition_map(): void
    {
        Gate::define('orders.manage', fn () => true);
        [$restaurant, , $branch] = $this->restaurantWithCategory('A');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();
        $order = Order::create([
            'branch_id' => $branch->id,
            'order_number' => 'ORDER-TRANSITION-001',
            'order_type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
        ]);

        $this->actingAs($user)->patch(route('admin.orders.status.update', $order), [
            'status' => 'completed',
        ])->assertUnprocessable();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])
            ->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_dashboard_metrics_only_include_the_users_tenant(): void
    {
        Gate::define('admin.dashboard.view', fn () => true);
        [$restaurant, , $branch] = $this->restaurantWithCategory('A');
        [, , $foreignBranch] = $this->restaurantWithCategory('B');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();
        $this->orderForBranch($branch, 'ORDER-DASHBOARD-LOCAL');
        $this->orderForBranch($foreignBranch, 'ORDER-DASHBOARD-FOREIGN');

        $this->actingAs($user)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats): bool => $stats['orders'] === 1);
    }

    public function test_sales_exports_require_permission_and_are_tenant_scoped(): void
    {
        [$restaurant, , $branch] = $this->restaurantWithCategory('A');
        [, , $foreignBranch] = $this->restaurantWithCategory('B');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();
        $localOrder = $this->orderForBranch($branch, 'ORDER-LOCAL-REPORT');
        $foreignOrder = $this->orderForBranch($foreignBranch, 'ORDER-FOREIGN-REPORT');

        $this->actingAs($user)->get(route('admin.reports.sales.csv'))->assertForbidden();

        DB::table('user_permissions')->insert([
            'user_id' => $user->id, 'ability' => 'reports.sales.export',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $response = $this->get(route('admin.reports.sales.csv'));
        $response->assertOk();
        $this->assertStringContainsString($localOrder->order_number, $response->streamedContent());
        $this->assertStringNotContainsString($foreignOrder->order_number, $response->streamedContent());
    }

    public function test_payment_cannot_be_recorded_against_a_foreign_restaurant_order(): void
    {
        [$restaurant] = $this->restaurantWithCategory('A');
        [, , $foreignBranch] = $this->restaurantWithCategory('B');
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();
        $order = $this->orderForBranch($foreignBranch, 'ORDER-FOREIGN-PAYMENT');
        Gate::define('orders.manage', fn () => true);

        $this->actingAs($user)->post(route('admin.orders.payments.store', $order), [
            'payment_method' => 'cash', 'amount' => 5,
        ])->assertNotFound();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_public_order_uses_unpredictable_token_and_scoped_menu_items(): void
    {
        config(['restaurant.public_code' => 'PUBLIC']);
        $restaurant = Restaurant::create(['name' => 'Public Restaurant', 'code' => 'PUBLIC', 'currency' => 'USD']);
        $branch = Branch::create([
            'restaurant_id' => $restaurant->id, 'name' => 'Public Branch', 'code' => 'PUB-BR', 'is_active' => true,
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id, 'name' => 'Meals', 'slug' => 'meals', 'is_active' => true,
        ]);
        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'name' => 'Soup',
            'price' => 12.5,
            'is_available' => true,
        ]);
        $otherRestaurant = Restaurant::create(['name' => 'Other Restaurant', 'code' => 'PRIVATE', 'currency' => 'USD']);
        $otherCategory = MenuCategory::create([
            'restaurant_id' => $otherRestaurant->id, 'name' => 'Private', 'slug' => 'private', 'is_active' => true,
        ]);
        $foreignItem = MenuItem::create([
            'restaurant_id' => $otherRestaurant->id,
            'menu_category_id' => $otherCategory->id,
            'name' => 'Private item', 'price' => 99, 'is_available' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Soup')->assertDontSee('Private item');
        $this->post(route('order.store'), [
            'name' => 'Another Customer',
            'phone' => '+15555550124',
            'branch_id' => $branch->id,
            'items' => [['menu_item_id' => $foreignItem->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.menu_item_id');

        $response = $this->post(route('order.store'), [
            'name' => 'Test Customer',
            'phone' => '+15555550123',
            'branch_id' => $branch->id,
            'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
        ]);

        $response->assertRedirect();
        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);
        preg_match('#/order/success/([a-f0-9]{64})$#', (string) $path, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $order = Order::query()->where('tracking_token_hash', hash('sha256', $matches[1]))->firstOrFail();
        $this->assertSame('25.00', $order->total);

        $successPage = $this->get(route('order.success', $matches[1]));
        $successPage
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertDontSee(hash('sha256', $matches[1]));
        $this->assertStringContainsString('no-store', $successPage->headers->get('Cache-Control'));
        $this->get(route('order.track', str_repeat('a', 64)))->assertNotFound();
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'menu_item_id' => $item->id]);
    }

    private function orderForBranch(Branch $branch, string $number): Order
    {
        return Order::create([
            'branch_id' => $branch->id,
            'order_number' => $number,
            'order_type' => 'takeaway',
            'status' => 'pending',
            'total' => 10,
            'balance' => 10,
            'ordered_at' => now(),
        ]);
    }

    private function restaurantWithCategory(string $suffix): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Restaurant ' . $suffix,
            'code' => 'REST-' . $suffix,
            'currency' => 'USD',
        ]);
        $branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Branch ' . $suffix,
            'code' => 'BR-' . $suffix,
        ]);
        $category = MenuCategory::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Category ' . $suffix,
            'slug' => 'category-' . strtolower($suffix),
            'is_active' => true,
        ]);

        return [$restaurant, $category, $branch];
    }
}
