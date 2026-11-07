<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Services\Orders\TaxService;
use App\Services\Settings\RestaurantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class TaxesAiProvidersAndBarcodeTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_taxes_page_saves_and_applies_to_online_orders(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('T');
        config(['restaurant.public_code' => $restaurant->code, 'restaurant.tax_rate' => 0, 'restaurant.delivery_fee' => 0]);
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.system.manage');
        $item = $this->menuItem($restaurant, 'Burger', 10000);

        $this->actingAs($owner)->get(route('admin.settings.system', 'taxes'))->assertOk()->assertSee('Tax rate (%)');
        $this->put(route('admin.settings.system.update', 'taxes'), ['enabled' => '1', 'tax_name' => 'VAT', 'rate' => '18', 'mode' => 'exclusive', 'tin' => '1000123456'])->assertRedirect();
        $this->assertSame('1000123456', $restaurant->fresh()->tin);
        auth()->logout();

        $this->post(route('order.store'), [
            'name' => 'Amina', 'phone' => '0700000000', 'branch_id' => $branch->id, 'payment_option' => 'cash_on_delivery',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();
        $order = Order::query()->firstOrFail();
        $this->assertSame(1800.0, (float) $order->tax);
        $this->assertSame(11800.0, (float) $order->total);
    }

    public function test_inclusive_tax_and_env_fallback(): void
    {
        [$restaurant] = $this->restaurantWithBranch('U');
        $taxes = app(TaxService::class);

        // Nothing saved yet: the .env rate is added on top.
        config(['restaurant.tax_rate' => 10]);
        $this->assertSame(['tax' => 100.0, 'total' => 1100.0], $taxes->apply($restaurant->id, 1000));

        app(RestaurantSettings::class)->save($restaurant->id, 'taxes', ['enabled' => true, 'tax_name' => 'VAT', 'rate' => '18', 'mode' => 'inclusive']);
        $this->assertSame(['tax' => 180.0, 'total' => 1180.0], $taxes->apply($restaurant->id, 1180));

        app(RestaurantSettings::class)->save($restaurant->id, 'taxes', ['enabled' => false, 'rate' => '18', 'mode' => 'exclusive']);
        $this->assertSame(['tax' => 0.0, 'total' => 500.0], $taxes->apply($restaurant->id, 500), 'Saved and switched off overrides .env.');
    }

    public function test_ai_provider_presets_fill_model_and_use_the_right_api(): void
    {
        [$restaurant] = $this->restaurantWithBranch('V');
        config(['restaurant.public_code' => $restaurant->code]);
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.system.manage');
        $this->menuItem($restaurant, 'Rolex', 6000);
        Http::fake([
            'api.groq.com/openai/v1/models' => Http::response(['data' => [['id' => 'llama-3.3-70b-versatile'], ['id' => 'mixtral-8x7b']]]),
            'api.groq.com/openai/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Our Rolex is a local favourite!']]]]),
        ]);

        $this->actingAs($owner)->get(route('admin.settings.system', 'ai'))->assertOk()
            ->assertSee('Groq')->assertSee('api.groq.com', false)->assertSee('https://api.anthropic.com', false);

        // Admin only picks the provider and pastes a key; the model is filled in.
        $this->put(route('admin.settings.system.update', 'ai'), ['enabled' => '1', 'provider' => 'groq', 'model' => '', 'key_groq' => 'gsk_test'])->assertRedirect();
        $settings = app(RestaurantSettings::class)->group($restaurant->id, 'ai');
        $this->assertSame('llama-3.3-70b-versatile', $settings['model']);
        $this->assertSame(['llama-3.3-70b-versatile', 'mixtral-8x7b'], json_decode($settings['models_groq'], true), 'Model list loads when a key is saved.');

        auth()->logout();
        $this->postJson(route('api.v1.chat.message'), ['message' => 'What is good?'])
            ->assertOk()->assertJson(['reply' => 'Our Rolex is a local favourite!']);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
            && $request->header('Authorization')[0] === 'Bearer gsk_test'
            && $request['model'] === 'llama-3.3-70b-versatile'
            && str_contains($request['messages'][0]['content'], 'Rolex'));
    }

    public function test_ai_requires_the_selected_providers_key(): void
    {
        [$restaurant] = $this->restaurantWithBranch('W');
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.system.manage');

        $this->actingAs($owner)->put(route('admin.settings.system.update', 'ai'), ['enabled' => '1', 'provider' => 'openai'])
            ->assertSessionHasErrors('key_openai');
        $this->put(route('admin.settings.system.update', 'ai'), ['enabled' => '1', 'provider' => 'not-a-provider'])
            ->assertSessionHasErrors('provider');
    }

    public function test_inventory_tabs_barcode_save_search_and_lookup(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('X');
        [$other] = $this->restaurantWithBranch('Y');
        $user = $this->staff($restaurant, null);
        $this->grant($user, 'inventory.manage', 'inventory.view');
        $this->actingAs($user);

        $this->post(route('admin.inventory.products.store'), [
            'name' => 'Coca-Cola 500ml', 'barcode' => '5449000000996', 'purchase_unit' => 'crate', 'usage_unit' => 'bottle',
            'conversion_factor' => 24, 'unit_cost' => 1500,
        ])->assertRedirect();
        $item = InventoryItem::query()->where('barcode', '5449000000996')->firstOrFail();
        DB::table('inventory_stocks')->insert(['branch_id' => $branch->id, 'inventory_item_id' => $item->id, 'quantity' => 36, 'created_at' => now(), 'updated_at' => now()]);

        // Barcodes are unique within a restaurant, but another restaurant may reuse one.
        $this->post(route('admin.inventory.products.store'), ['name' => 'Duplicate', 'barcode' => '5449000000996', 'purchase_unit' => 'unit', 'usage_unit' => 'unit', 'conversion_factor' => 1, 'unit_cost' => 1])
            ->assertSessionHasErrors('barcode');
        InventoryItem::create(['restaurant_id' => $other->id, 'name' => 'Their Coke', 'barcode' => '5449000000996']);

        $this->get(route('admin.inventory.products'))->assertOk()
            ->assertSee('Inventory items')->assertSee('Stock balances')->assertSee('Scan barcode')->assertSee('5449000000996');
        $this->get(route('admin.inventory.products', ['tab' => 'stock', 'search' => '5449000000996']))->assertOk()->assertSee('Coca-Cola 500ml');

        $this->getJson(route('admin.inventory.lookup', ['code' => '5449000000996']))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('item.name', 'Coca-Cola 500ml')
            ->assertJsonPath('item.stock.0.quantity', 36);
        $this->getJson(route('admin.inventory.lookup', ['code' => '0000000000000']))->assertOk()->assertJsonPath('found', false);
    }
}
