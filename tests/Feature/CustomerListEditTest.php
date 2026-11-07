<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerListEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_list_offers_edit_modal_only_for_the_users_restaurant(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Local Guest', 'phone' => '+15555550201']);
        $foreign = Customer::create(['restaurant_id' => $foreignRestaurant->id, 'name' => 'Foreign Guest', 'phone' => '+15555550202']);
        $user = $this->managerFor($restaurant);

        $this->actingAs($user)->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('id="edit-customer-list-' . $customer->id . '"', false)
            ->assertDontSee('edit-customer-list-' . $foreign->id);
    }

    public function test_update_route_saves_changes_to_the_users_customer(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Old Name', 'phone' => '+15555550203']);
        $user = $this->managerFor($restaurant);

        $this->actingAs($user)->put(route('admin.customers.update', $customer->id), [
            'name' => 'New Name',
            'phone' => '+15555550203',
            'email' => 'new@example.test',
            'address' => 'Plot 12',
            '_edit_modal' => 'edit-customer-list-' . $customer->id,
        ])->assertRedirect()->assertSessionHas('success', 'Customer updated.');

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id, 'name' => 'New Name', 'email' => 'new@example.test', 'address' => 'Plot 12',
        ]);
    }

    public function test_failed_update_reopens_the_same_row_modal_on_the_list(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Guest', 'phone' => '+15555550204']);
        $user = $this->managerFor($restaurant);

        $this->followingRedirects()->actingAs($user)
            ->from(route('admin.customers.index'))
            ->put(route('admin.customers.update', $customer->id), [
            'name' => '',
            'phone' => '+15555550204',
            '_edit_modal' => 'edit-customer-list-' . $customer->id,
        ])->assertOk()
            ->assertSee('edit-customer-list-' . $customer->id)
            ->assertSee('getElementById(', false);
    }

    private function managerFor(Restaurant $restaurant): User
    {
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id])->save();
        foreach (['customers.view', 'customers.manage'] as $ability) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'ability' => $ability, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $user;
    }

    private function restaurantWithBranch(string $suffix): array
    {
        $restaurant = Restaurant::create([
            'name' => 'Restaurant ' . $suffix,
            'code' => 'CLE-' . $suffix,
            'currency' => 'USD',
        ]);
        $branch = Branch::create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Branch ' . $suffix,
            'code' => 'CLE-BR-' . $suffix,
        ]);

        return [$restaurant, $branch];
    }
}
