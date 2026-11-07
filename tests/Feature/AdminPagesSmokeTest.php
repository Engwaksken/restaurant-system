<?php

namespace Tests\Feature;

use App\Models\CashRegisterSession;
use App\Models\Customer;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Order;
use App\Models\PayrollRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

/** Every admin page renders for a full administrator (catches template/runtime errors). */
class AdminPagesSmokeTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_every_admin_page_renders(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $admin = $this->staff($restaurant, null, ['name' => 'Owner']);
        $this->grant($admin, 'admin');
        $item = $this->menuItem($restaurant, 'Pizza', 20);
        MenuItemVariant::create(['menu_item_id' => $item->id, 'name' => 'Large', 'price' => 30]);
        MenuItemAddon::create(['menu_item_id' => $item->id, 'name' => 'Cheese', 'price' => 2]);
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina', 'phone' => '0700', 'loyalty_points' => 5]);
        $order = Order::create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'order_number' => 'ORD-SMOKE', 'order_type' => 'dine_in', 'status' => 'confirmed', 'subtotal' => 20, 'total' => 20, 'balance' => 20, 'ordered_at' => now()]);
        $order->items()->create(['menu_item_id' => $item->id, 'item_name' => 'Pizza', 'variant_name' => 'Large', 'addons' => [['id' => 1, 'name' => 'Cheese', 'price' => 2]], 'unit_price' => 20, 'quantity' => 1, 'total' => 20]);
        CashRegisterSession::create(['branch_id' => $branch->id, 'opened_by' => $admin->id, 'reference' => 'CRS-SMOKE', 'opening_float' => 10, 'opened_at' => now()]);
        $this->actingAs($admin);
        $this->post(route('admin.payroll.compensation'), ['user_id' => $admin->id, 'branch_id' => $branch->id, 'pay_type' => 'monthly', 'rate' => 100]);
        $this->post(route('admin.payroll.store'), ['branch_id' => $branch->id, 'month' => now()->format('Y-m')]);
        $run = PayrollRun::query()->firstOrFail();

        $failures = [];
        $pages = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => in_array('GET', $route->methods(), true)
                && str_starts_with($route->uri(), 'admin/')
                && !str_contains($route->uri(), '{')
                && !str_ends_with($route->uri(), '.pdf')
                && $route->getName() !== 'login')
            ->map(fn (Route $route) => '/' . $route->uri())
            ->merge([
                route('admin.orders.show', $order, false),
                route('admin.customers.show', $customer->id, false),
                route('admin.payroll.show', $run->id, false),
                route('admin.payroll.payslip', [$run->id, $run->lines()->firstOrFail()->id], false),
                route('admin.menu-options.index', 'variants', false),
                route('admin.menu-options.index', 'addons', false),
            ]);

        foreach ($pages as $uri) {
            $response = $this->get($uri);
            $status = $response->baseResponse->getStatusCode();
            if ($status >= 500) {
                $failures[] = $uri . ' => ' . $status . ': ' . substr((string) $response->exception?->getMessage(), 0, 300);
            }
        }

        $this->assertSame([], $failures);
    }
}
