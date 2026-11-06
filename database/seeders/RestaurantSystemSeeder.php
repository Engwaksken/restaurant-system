<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RestaurantSystemSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => env('RESTAURANT_NAME', 'My Restaurant'),
                'currency' => env('RESTAURANT_CURRENCY', 'UGX'),
                'timezone' => env('RESTAURANT_TIMEZONE', 'Africa/Kampala'),
            ]
        );

        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN-BRANCH'],
            ['restaurant_id' => $restaurant->id, 'name' => 'Main Branch', 'is_active' => true]
        );

        foreach (['Main Dishes', 'Drinks', 'Snacks', 'Desserts'] as $index => $name) {
            MenuCategory::firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'slug' => str($name)->slug()],
                ['name' => $name, 'display_order' => $index + 1, 'is_active' => true]
            );
        }

        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');
        if ($email === '' || $password === '') {
            return;
        }
        if (strlen($password) < 16) {
            throw new \InvalidArgumentException('ADMIN_PASSWORD must contain at least 16 characters.');
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make($password),
                'restaurant_id' => $restaurant->id,
                'branch_id' => $branch->id,
            ]
        );

        if ($user->restaurant_id === null) {
            $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();
        }

        if (filter_var(env('ADMIN_GRANT_SUPER_ADMIN', false), FILTER_VALIDATE_BOOL)) {
            DB::table('user_permissions')->updateOrInsert(
                ['user_id' => $user->id, 'ability' => 'admin'],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
