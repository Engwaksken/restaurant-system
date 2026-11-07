<?php

namespace Tests\Feature;

use App\Mail\PublicOrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kemmy Grill',
            'tagline' => 'Flame-grilled goodness',
            'primary_color' => '#16A34A',
            'accent_color' => '#14532d',
            'phone' => '+256 700 111 222',
            'email' => 'hello@kemmy.test',
            'instagram_url' => 'https://instagram.com/kemmy',
            'whatsapp_number' => '+256 700 111 222',
            'email_footer' => 'Thanks for eating with us!',
        ], $overrides);
    }

    public function test_admin_updates_branding_with_logo_and_favicon(): void
    {
        Storage::fake('public');
        [$restaurant] = $this->restaurantWithBranch('A');
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.branding.manage', 'admin.dashboard.view');

        $this->actingAs($owner)->get(route('admin.settings.restaurant'))
            ->assertOk()
            ->assertSee('Brand your restaurant');

        $this->put(route('admin.settings.restaurant.update'), $this->validPayload([
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'favicon' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ]))->assertRedirect(route('admin.settings.restaurant'));

        $restaurant->refresh();
        $this->assertSame('Kemmy Grill', $restaurant->name);
        $this->assertSame('#16a34a', $restaurant->primary_color);
        Storage::disk('public')->assertExists($restaurant->logo_path);
        Storage::disk('public')->assertExists($restaurant->favicon_path);

        // The admin layout carries the new name, favicon and brand colour.
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Kemmy Grill')
            ->assertSee('rel="icon"', false)
            ->assertSee('--brand:#16a34a', false)
            ->assertSee('stat-card', false);

        // Removing the logo deletes the stored file.
        $oldLogo = $restaurant->logo_path;
        $this->put(route('admin.settings.restaurant.update'), $this->validPayload(['remove_logo' => '1']))->assertRedirect();
        $this->assertNull($restaurant->fresh()->logo_path);
        Storage::disk('public')->assertMissing($oldLogo);
    }

    public function test_branding_is_restricted_to_permitted_restaurant_wide_staff(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $noPermission = $this->staff($restaurant, null);
        $this->actingAs($noPermission)->get(route('admin.settings.restaurant'))->assertForbidden();
        $this->put(route('admin.settings.restaurant.update'), $this->validPayload())->assertForbidden();

        $branchManager = $this->staff($restaurant, $branch);
        $this->grant($branchManager, 'settings.branding.manage');
        $this->actingAs($branchManager)->put(route('admin.settings.restaurant.update'), $this->validPayload())->assertForbidden();

        $this->assertSame('Restaurant A', $restaurant->fresh()->name);
    }

    public function test_branding_rejects_unsafe_values(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'admin');

        $this->actingAs($owner)->put(route('admin.settings.restaurant.update'), $this->validPayload([
            'primary_color' => 'red;}body{display:none',
            'facebook_url' => 'javascript:alert(1)',
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors(['primary_color', 'facebook_url', 'logo']);

        $this->assertNull($restaurant->fresh()->primary_color);
    }

    public function test_public_site_and_order_email_use_the_brand(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('PUB');
        config(['restaurant.public_code' => $restaurant->code]);
        $restaurant->update([
            'name' => 'Mama Rosa',
            'tagline' => 'Taste of home',
            'primary_color' => '#dc2626',
            'accent_color' => '#18181b',
            'about' => 'Family kitchen since 1998.',
            'email_footer' => 'See you again soon!',
        ]);
        $this->menuItem($restaurant, 'Lasagne', 25);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Mama Rosa')
            ->assertSee('Taste of home')
            ->assertSee('Family kitchen since 1998.')
            ->assertSee('--brand:#dc2626', false)
            ->assertSee('Lasagne');

        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina Kato', 'phone' => '0700']);
        $order = Order::create([
            'branch_id' => $branch->id, 'customer_id' => $customer->id, 'order_number' => 'ORD-BRAND',
            'order_type' => 'online', 'status' => 'pending', 'subtotal' => 25, 'total' => 25, 'balance' => 25, 'ordered_at' => now(),
        ]);
        $order->items()->create(['item_name' => 'Lasagne', 'unit_price' => 25, 'quantity' => 1, 'total' => 25]);

        $mail = new PublicOrderConfirmation($order, str_repeat('a', 64));
        $mail->assertSeeInHtml('Mama Rosa');
        $mail->assertSeeInHtml('#dc2626');
        $mail->assertSeeInHtml('See you again soon!');
        $mail->assertSeeInHtml('Thanks, Amina!');
        $mail->assertSeeInHtml('Lasagne');
        $this->assertSame('Order ORD-BRAND received - Mama Rosa', $mail->build()->subject);
    }

    public function test_brand_defaults_and_contrast(): void
    {
        $brand = Brand::for(null);
        $this->assertSame(Brand::DEFAULT_PRIMARY, $brand->primary());
        $this->assertNull($brand->logoUrl());

        [$restaurant] = $this->restaurantWithBranch('C');
        $restaurant->update(['primary_color' => '#facc15', 'accent_color' => '#111111', 'facebook_url' => 'javascript:alert(1)']);
        $brand = Brand::for($restaurant->fresh());
        $this->assertSame('#111827', $brand->onPrimary(), 'Dark text on a light yellow brand colour.');
        $this->assertSame('#ffffff', $brand->onAccent());
        $this->assertArrayNotHasKey('facebook', $brand->socialLinks(), 'Non-http links are never rendered.');
        $this->assertSame('RC', $brand->initials());
    }

    public function test_long_lists_are_paginated_with_summary(): void
    {
        [$restaurant] = $this->restaurantWithBranch('P');
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'admin');
        foreach (range(1, 30) as $i) {
            Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Customer ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'phone' => '07' . $i]);
        }

        $this->actingAs($owner)->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('pager-bar', false)
            ->assertSeeInOrder(['Showing', '1', '25', 'of', '30'])
            ->assertSee('New this month');
    }
}
