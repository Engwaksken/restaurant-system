<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Services\Orders\OrderNumberGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class OrderNumbersAndGatewaysTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_order_numbers_are_short_sequential_and_reset_monthly(): void
    {
        $generator = app(OrderNumberGenerator::class);
        $november = Carbon::create(2026, 11, 7);

        $this->assertSame('WEB-2611-0001', $generator->next('WEB', $november));
        $this->assertSame('WEB-2611-0002', $generator->next('WEB', $november));
        $this->assertSame('POS-2611-0001', $generator->next('POS', $november), 'Each prefix has its own counter.');
        $this->assertSame('WEB-2612-0001', $generator->next('WEB', Carbon::create(2026, 12, 1)), 'Counter restarts each month.');

        DB::table('order_number_sequences')->where('sequence_key', 'WEB-2611')->update(['last_value' => 9999]);
        $this->assertSame('WEB-2611-10000', $generator->next('WEB', $november), 'Grows past four digits instead of colliding.');
    }

    public function test_web_checkout_uses_the_short_format(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('W');
        config(['restaurant.public_code' => $restaurant->code]);
        $item = $this->menuItem($restaurant, 'Rolex', 6000);

        $this->post(route('order.store'), [
            'name' => 'Amina', 'phone' => '0700000000', 'branch_id' => $branch->id,
            'payment_option' => 'cash_on_delivery',
            'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        $number = DB::table('orders')->value('order_number');
        $this->assertMatchesRegularExpression('/^WEB-' . now()->format('ym') . '-0001$/', $number);
    }

    public function test_admin_saves_gateway_settings_and_checkout_offers_them(): void
    {
        [$restaurant] = $this->restaurantWithBranch('G');
        config([
            'restaurant.public_code' => $restaurant->code,
            'restaurant.gateways.flutterwave' => ['enabled' => false, 'secret_key' => null, 'base_url' => 'https://api.flutterwave.com/v3'],
        ]);
        $this->menuItem($restaurant, 'Pizza', 20);
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.payment-gateways.manage');

        $this->actingAs($owner)->get(route('admin.settings.payment-gateways'))
            ->assertOk()->assertSee('Flutterwave')->assertSee('Pesapal');

        // Cannot switch on without credentials.
        $this->put(route('admin.settings.payment-gateways.update', 'flutterwave'), ['is_enabled' => 1, 'mode' => 'test'])
            ->assertSessionHasErrorsIn('flutterwave', ['secret_key']);

        // Test key in live mode is refused.
        $this->put(route('admin.settings.payment-gateways.update', 'flutterwave'), ['is_enabled' => 1, 'mode' => 'live', 'secret_key' => 'FLWSECK_TEST-abc123'])
            ->assertSessionHasErrorsIn('flutterwave', ['secret_key']);

        $this->put(route('admin.settings.payment-gateways.update', 'flutterwave'), ['is_enabled' => 1, 'mode' => 'test', 'secret_key' => 'FLWSECK_TEST-secret9876'])
            ->assertRedirect();

        $setting = PaymentGatewaySetting::query()->where('restaurant_id', $restaurant->id)->where('gateway', 'flutterwave')->firstOrFail();
        $this->assertSame('FLWSECK_TEST-secret9876', $setting->credentials['secret_key']);
        $raw = DB::table('restaurant_payment_gateways')->where('id', $setting->id)->value('credentials');
        $this->assertStringNotContainsString('secret9876', $raw, 'Credentials are encrypted at rest.');

        // The secret is masked on the page and kept when the field is left blank.
        $this->get(route('admin.settings.payment-gateways'))->assertOk()->assertSee('9876')->assertDontSee('FLWSECK_TEST-secret9876');
        $this->put(route('admin.settings.payment-gateways.update', 'flutterwave'), ['is_enabled' => 1, 'mode' => 'test', 'secret_key' => ''])->assertRedirect();
        $this->assertSame('FLWSECK_TEST-secret9876', $setting->fresh()->credentials['secret_key']);

        // Customers now see the option at checkout.
        auth()->logout();
        $this->get(route('home'))->assertOk()->assertSee('Pay online with Flutterwave');
    }

    public function test_gateway_settings_are_protected(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('P');
        $noPermission = $this->staff($restaurant, null);
        $this->actingAs($noPermission)->get(route('admin.settings.payment-gateways'))->assertForbidden();

        $branchStaff = $this->staff($restaurant, $branch);
        $this->grant($branchStaff, 'settings.payment-gateways.manage');
        $this->actingAs($branchStaff)->put(route('admin.settings.payment-gateways.update', 'pesapal'), ['mode' => 'test'])->assertForbidden();

        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'admin');
        $this->actingAs($owner)->put(route('admin.settings.payment-gateways.update', 'paypal'), ['mode' => 'test'])->assertNotFound();
        $this->assertSame(0, PaymentGatewaySetting::query()->count());
    }
}
