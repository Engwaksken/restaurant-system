<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Restaurant;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class InventoryAndSupplierManagementTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_inventory_item_can_be_updated(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage');
        $item = $this->inventoryItem($restaurant, 'Flour', 'FLR-1');

        $this->actingAs($user)->put(route('admin.inventory.products.update', $item), $this->itemPayload([
            'name' => 'Wheat flour', 'sku' => 'FLR-1',
        ]))->assertRedirect();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'name' => 'Wheat flour', 'sku' => 'FLR-1']);
    }

    public function test_inventory_item_sku_must_stay_unique_against_other_items(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage');
        $this->inventoryItem($restaurant, 'Rice', 'RICE-1');
        $item = $this->inventoryItem($restaurant, 'Flour', 'FLR-1');

        $this->actingAs($user)->put(route('admin.inventory.products.update', $item), $this->itemPayload([
            'sku' => 'RICE-1',
        ]))->assertSessionHasErrors('sku', null, 'editItem' . $item->id);
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'sku' => 'FLR-1']);
    }

    public function test_unit_fields_are_locked_once_an_item_has_stock_history(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage');
        $item = $this->inventoryItem($restaurant, 'Sugar', 'SUG-1');
        DB::table('stock_movements')->insert([
            'branch_id' => $branch->id, 'inventory_item_id' => $item->id, 'type' => 'opening',
            'quantity' => 5, 'unit_cost' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->put(route('admin.inventory.products.update', $item), $this->itemPayload([
            'name' => 'Brown sugar', 'purchase_unit' => 'crate', 'usage_unit' => 'g', 'conversion_factor' => 1000,
        ]))->assertRedirect();

        $fresh = $item->fresh();
        $this->assertSame('Brown sugar', $fresh->name);
        $this->assertSame('bag', $fresh->purchase_unit);
        $this->assertSame('kg', $fresh->usage_unit);
        $this->assertEquals(1.0, (float) $fresh->conversion_factor);
    }

    public function test_foreign_inventory_item_returns_not_found_on_update_and_delete(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $user = $this->staff($restaurant, null);
        $this->grant($user, 'inventory.manage');
        $foreignItem = $this->inventoryItem($foreignRestaurant, 'Foreign flour', 'FOR-1');

        $this->actingAs($user)->put(route('admin.inventory.products.update', $foreignItem), $this->itemPayload([
            'name' => 'Hijacked',
        ]))->assertNotFound();
        $this->actingAs($user)->delete(route('admin.inventory.products.destroy', $foreignItem))->assertNotFound();

        $this->assertDatabaseHas('inventory_items', ['id' => $foreignItem->id, 'name' => 'Foreign flour', 'deleted_at' => null]);
    }

    public function test_inventory_item_with_stock_on_hand_cannot_be_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage');
        $item = $this->inventoryItem($restaurant, 'Oil', 'OIL-1');
        DB::table('inventory_stocks')->insert([
            'branch_id' => $branch->id, 'inventory_item_id' => $item->id, 'quantity' => 3,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('admin.inventory.products.destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id, 'deleted_at' => null]);
    }

    public function test_inventory_item_without_stock_is_soft_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage');
        $item = $this->inventoryItem($restaurant, 'Old packaging', 'PKG-1');

        $this->actingAs($user)->delete(route('admin.inventory.products.destroy', $item))->assertRedirect();

        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);
    }

    public function test_supplier_can_be_updated(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'purchasing.suppliers.manage');
        $supplier = Supplier::create(['restaurant_id' => $restaurant->id, 'name' => 'Old supplier', 'is_active' => true]);

        $this->actingAs($user)->put(route('admin.suppliers.update', $supplier), [
            'name' => 'New supplier',
            'contact_person' => 'Sarah Nakato',
            'phone' => '+256700000000',
            'email' => 'orders@example.test',
            'address' => 'Plot 12, Industrial Area',
            'tin' => '1000234567',
        ])->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id, 'name' => 'New supplier', 'email' => 'orders@example.test', 'tin' => '1000234567',
        ]);
    }

    public function test_supplier_with_open_purchase_order_cannot_be_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'purchasing.suppliers.manage');
        $supplier = Supplier::create(['restaurant_id' => $restaurant->id, 'name' => 'Open order supplier', 'is_active' => true]);
        PurchaseOrder::create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
            'order_number' => 'PO-OPEN-1', 'status' => 'ordered', 'ordered_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('admin.suppliers.destroy', $supplier))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);
    }

    public function test_supplier_with_unpaid_purchase_is_deleted_only_after_it_is_settled(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'purchasing.suppliers.manage');
        $supplier = Supplier::create(['restaurant_id' => $restaurant->id, 'name' => 'Credit supplier', 'is_active' => true]);
        $purchase = Purchase::create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id,
            'total' => 50, 'received_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('admin.suppliers.destroy', $supplier))->assertSessionHas('error');
        $this->assertDatabaseHas('suppliers', ['id' => $supplier->id, 'deleted_at' => null]);

        SupplierPayment::create([
            'purchase_id' => $purchase->id, 'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'created_by' => $user->id, 'receipt_number' => 'SP-TEST-1', 'payment_method' => 'cash',
            'amount' => 50, 'paid_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('admin.suppliers.destroy', $supplier))->assertRedirect();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_inventory_and_supplier_pages_render_edit_and_delete_controls(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'inventory.manage', 'purchasing.suppliers.view', 'purchasing.suppliers.manage');
        $item = $this->inventoryItem($restaurant, 'Pepper', 'PEP-1');
        $this->inventoryItem($restaurant, 'Rice', 'RICE-1');
        $supplier = Supplier::create(['restaurant_id' => $restaurant->id, 'name' => 'Spice supplier', 'is_active' => true]);

        $this->actingAs($user)->get(route('admin.inventory.products'))->assertOk()
            ->assertSee('edit-item-' . $item->id)
            ->assertSee('delete-item-' . $item->id)
            ->assertSee(route('admin.inventory.products.update', $item), false);
        $this->get(route('admin.suppliers.index'))->assertOk()
            ->assertSee('edit-supplier-' . $supplier->id)
            ->assertSee('delete-supplier-' . $supplier->id)
            ->assertSee(route('admin.suppliers.update', $supplier), false);

        $this->put(route('admin.inventory.products.update', $item), $this->itemPayload(['sku' => 'RICE-1']));
        $this->get(route('admin.inventory.products'))->assertOk()->assertSee('has already been taken');
    }

    private function inventoryItem(Restaurant $restaurant, string $name, ?string $sku = null): InventoryItem
    {
        return InventoryItem::create([
            'restaurant_id' => $restaurant->id, 'name' => $name, 'sku' => $sku,
            'purchase_unit' => 'bag', 'usage_unit' => 'kg', 'conversion_factor' => 1,
            'unit_cost' => 2, 'is_active' => true,
        ]);
    }

    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Flour',
            'sku' => null,
            'inventory_category_id' => null,
            'purchase_unit' => 'bag',
            'usage_unit' => 'kg',
            'conversion_factor' => 1,
            'unit_cost' => 2,
        ], $overrides);
    }
}
