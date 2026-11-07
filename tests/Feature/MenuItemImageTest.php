<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class MenuItemImageTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_uploaded_menu_image_is_saved_and_its_public_url_is_used_on_the_homepage(): void
    {
        Storage::fake('public');
        config(['restaurant.public_code' => 'REST-IMG']);
        [$restaurant, $branch] = $this->restaurantWithBranch('IMG');
        $category = MenuCategory::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'mains-img'],
            ['name' => 'Mains', 'is_active' => true],
        );
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'admin.menu-items.manage');

        $this->actingAs($user)->post(route('admin.menu-items.store'), [
            'name' => 'Grilled fish',
            'menu_category_id' => $category->id,
            'price' => 18,
            'cost_price' => 8,
            'is_available' => 1,
            'image' => UploadedFile::fake()->image('fish.jpg', 640, 480),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item = MenuItem::query()->where('name', 'Grilled fish')->firstOrFail();
        $this->assertNotEmpty($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
        $this->assertStringContainsString('/storage/menu-items/', $item->image_url);

        $this->get('/')->assertOk()->assertSee($item->image_url, false);
    }

    public function test_replacing_a_menu_image_stores_the_new_file_before_removing_the_old_one(): void
    {
        Storage::fake('public');
        [$restaurant, $branch] = $this->restaurantWithBranch('IMGEDIT');
        $item = $this->menuItem($restaurant, 'Soup', 10, ['image_path' => 'menu-items/old.jpg']);
        Storage::disk('public')->put($item->image_path, 'old-image');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'admin.menu-items.manage');

        $this->actingAs($user)->put(route('admin.menu-items.update', $item), [
            'name' => $item->name,
            'menu_category_id' => $item->menu_category_id,
            'price' => $item->price,
            'cost_price' => $item->cost_price,
            'is_available' => 1,
            'image' => UploadedFile::fake()->image('new-soup.png', 320, 240),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertNotSame('menu-items/old.jpg', $item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
        Storage::disk('public')->assertMissing('menu-items/old.jpg');
    }
}
