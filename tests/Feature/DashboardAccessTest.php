<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_a_restaurant_sees_the_reason_on_the_dashboard(): void
    {
        $user = User::factory()->create(['restaurant_id' => null]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('not assigned to a restaurant');
    }

    public function test_user_without_dashboard_permission_sees_the_reason_on_the_dashboard(): void
    {
        $restaurant = Restaurant::create([
            'name' => 'Test Restaurant',
            'code' => 'TEST',
            'currency' => 'UGX',
            'timezone' => 'Africa/Kampala',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('does not have permission to view the dashboard');
    }
}
