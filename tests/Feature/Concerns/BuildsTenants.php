<?php

namespace Tests\Feature\Concerns;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait BuildsTenants
{
    /** @return array{0: Restaurant, 1: Branch} */
    protected function restaurantWithBranch(string $suffix): array
    {
        $restaurant = Restaurant::create(['name' => 'Restaurant ' . $suffix, 'code' => 'REST-' . $suffix, 'currency' => 'USD']);
        $branch = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Branch ' . $suffix, 'code' => 'BR-' . $suffix]);

        return [$restaurant, $branch];
    }

    protected function staff(Restaurant $restaurant, ?Branch $branch, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch?->id])->save();

        return $user->fresh();
    }

    protected function grant(User $user, string ...$abilities): void
    {
        foreach ($abilities as $ability) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id, 'ability' => $ability, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    protected function menuItem(Restaurant $restaurant, string $name, float $price = 10, array $attributes = []): MenuItem
    {
        $category = MenuCategory::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'mains-' . $restaurant->id],
            ['name' => 'Mains', 'is_active' => true],
        );

        return MenuItem::create(array_merge([
            'restaurant_id' => $restaurant->id, 'menu_category_id' => $category->id,
            'name' => $name, 'price' => $price, 'is_available' => true,
        ], $attributes));
    }
}
