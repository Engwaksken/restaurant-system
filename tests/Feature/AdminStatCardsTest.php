<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

/** Every admin list page opens with a row of statistics cards. */
class AdminStatCardsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_list_pages_show_statistics_cards(): void
    {
        [$restaurant] = $this->restaurantWithBranch('S');
        $admin = $this->staff($restaurant, null);
        $this->grant($admin, 'admin');
        $this->menuItem($restaurant, 'Pizza', 20);
        $this->actingAs($admin);

        $routes = [
            'admin.dashboard', 'admin.customers.index', 'admin.feedback.index', 'admin.loyalty.index',
            'admin.staff.index', 'admin.attendance.index', 'admin.shifts.index', 'admin.payroll.index',
            'admin.expenses.index', 'admin.cash-register.index', 'admin.reconciliation.index', 'admin.pos.returns',
            'admin.inventory.products', 'admin.inventory.stock', 'admin.inventory.low-stock', 'admin.inventory.adjustments',
            'admin.inventory.transfers', 'admin.inventory.wastage', 'admin.kitchen.index', 'admin.kitchen.pending',
            'admin.kitchen.completed', 'admin.menu-items.index', 'admin.menu-categories.index', 'admin.menu-availability.index',
            'admin.recipes.index', 'admin.settings.roles-permissions', 'admin.suppliers.index', 'admin.purchases.index',
            'admin.purchase-orders.index', 'admin.floors.index', 'admin.tables.index', 'admin.reservations.index',
            'admin.settings.branches', 'admin.settings.activity-logs', 'admin.chatbot.index', 'admin.pos.held',
            'admin.orders.index',
        ];

        $missing = [];
        foreach ($routes as $name) {
            $response = $this->get(route($name));
            $html = (string) $response->getContent();
            if ($response->status() !== 200 || !(str_contains($html, 'stat-card') || str_contains($html, 'menu-stat-card'))) {
                $missing[] = $name . ' (' . $response->status() . ')';
            }
        }
        foreach (['variants', 'addons'] as $type) {
            $html = (string) $this->get(route('admin.menu-options.index', $type))->assertOk()->getContent();
            if (!str_contains($html, 'stat-card')) {
                $missing[] = 'menu-options.' . $type;
            }
        }

        $this->assertSame([], $missing);
    }
}
