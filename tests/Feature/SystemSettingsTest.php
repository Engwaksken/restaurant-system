<?php

namespace Tests\Feature;

use App\Mail\PublicOrderConfirmation;
use App\Models\Customer;
use App\Models\ManualPaymentMethod;
use App\Models\Order;
use App\Services\Backups\RestaurantBackupService;
use App\Services\Settings\RestaurantSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;
use ZipArchive;

class SystemSettingsTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    private function owner(string $suffix = 'A'): array
    {
        [$restaurant, $branch] = $this->restaurantWithBranch($suffix);
        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.system.manage');

        return [$restaurant, $branch, $owner];
    }

    public function test_every_settings_page_renders_and_saves(): void
    {
        [, , $owner] = $this->owner();
        $this->actingAs($owner);

        foreach (['ai', 'sms', 'email', 'receipt', 'invoice', 'accessibility'] as $group) {
            $this->get(route('admin.settings.system', $group))->assertOk()->assertSee('Save settings');
        }
        $this->get(route('admin.settings.backups'))->assertOk()->assertSee('Create backup now');

        $this->put(route('admin.settings.system.update', 'receipt'), ['paper_width' => '58', 'show_logo' => '0', 'footer_text' => 'Come again!'])
            ->assertRedirect(route('admin.settings.system', 'receipt'));
        $this->get(route('admin.settings.system', 'receipt'))->assertSee('Come again!');
    }

    public function test_secrets_are_encrypted_masked_and_kept_when_blank(): void
    {
        [$restaurant, , $owner] = $this->owner();
        $this->actingAs($owner);

        // Turning SMS on without credentials is refused.
        $this->put(route('admin.settings.system.update', 'sms'), ['enabled' => '1', 'provider' => 'africastalking'])
            ->assertSessionHasErrors(['at_username', 'at_api_key']);

        $this->put(route('admin.settings.system.update', 'sms'), [
            'enabled' => '1', 'provider' => 'africastalking', 'at_username' => 'kemmy', 'at_api_key' => 'atsk_secret_9f3c',
        ])->assertRedirect();

        $raw = DB::table('restaurant_settings')->where('restaurant_id', $restaurant->id)->where('key', 'at_api_key')->value('value');
        $this->assertStringNotContainsString('atsk_secret', $raw);

        $this->get(route('admin.settings.system', 'sms'))->assertSee('9f3c')->assertDontSee('atsk_secret_9f3c');

        $this->put(route('admin.settings.system.update', 'sms'), ['enabled' => '1', 'provider' => 'africastalking', 'at_username' => 'kemmy', 'at_api_key' => ''])->assertRedirect();
        $this->assertSame('atsk_secret_9f3c', app(RestaurantSettings::class)->get($restaurant->id, 'sms', 'at_api_key'));
    }

    public function test_settings_require_permission_and_restaurant_wide_access(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('B');
        $plain = $this->staff($restaurant, null);
        $this->actingAs($plain)->get(route('admin.settings.system', 'ai'))->assertForbidden();
        $this->get(route('admin.settings.backups'))->assertForbidden();

        $branchStaff = $this->staff($restaurant, $branch);
        $this->grant($branchStaff, 'settings.system.manage');
        $this->actingAs($branchStaff)->put(route('admin.settings.system.update', 'email'), [])->assertForbidden();
    }

    public function test_order_sms_and_email_follow_the_settings(): void
    {
        [$restaurant, $branch, $owner] = $this->owner('S');
        config(['restaurant.public_code' => $restaurant->code]);
        $item = $this->menuItem($restaurant, 'Rolex', 6000);
        Http::fake(['api.africastalking.com/*' => Http::response(['SMSMessageData' => ['Recipients' => []]], 201)]);
        Mail::fake();

        $settings = app(RestaurantSettings::class);
        $settings->save($restaurant->id, 'sms', ['enabled' => true, 'notify_order_placed' => true, 'notify_order_ready' => true, 'provider' => 'africastalking', 'at_username' => 'kemmy', 'at_api_key' => 'key123', 'template_order_placed' => 'Hi {name}, order {order} received', 'template_order_ready' => 'Ready {order}']);
        $settings->save($restaurant->id, 'email', ['send_order_confirmations' => false]);

        $this->post(route('order.store'), [
            'name' => 'Amina Kato', 'phone' => '0772123456', 'email' => 'amina@example.com', 'branch_id' => $branch->id,
            'payment_option' => 'cash_on_delivery', 'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertRedirect();

        Mail::assertNotSent(PublicOrderConfirmation::class);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'api.africastalking.com/version1/messaging')
            && $request['to'] === '+256772123456'
            && str_starts_with($request['message'], 'Hi Amina, order WEB-')
            && $request->header('apiKey')[0] === 'key123');
    }

    public function test_website_chat_uses_gemini_with_only_public_menu_context(): void
    {
        [$restaurant] = $this->restaurantWithBranch('C');
        config(['restaurant.public_code' => $restaurant->code]);
        $this->menuItem($restaurant, 'Luwombo Chicken', 25000);
        app(RestaurantSettings::class)->save($restaurant->id, 'ai', ['enabled' => true, 'provider' => 'gemini', 'gemini_model' => 'gemini-2.5-flash', 'gemini_api_key' => 'g-key']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Try our Luwombo Chicken!']]]]]])]);

        $this->postJson(route('api.v1.chat.message'), ['message' => 'What do you recommend?'])
            ->assertOk()->assertJson(['reply' => 'Try our Luwombo Chicken!']);

        Http::assertSent(function (HttpRequest $request): bool {
            $system = data_get($request->data(), 'systemInstruction.parts.0.text', '');

            return $request->header('x-goog-api-key')[0] === 'g-key'
                && !str_contains($request->url(), 'g-key')
                && str_contains($system, 'Luwombo Chicken');
        });
    }

    public function test_chat_falls_back_to_menu_answers_when_ai_is_off(): void
    {
        [$restaurant] = $this->restaurantWithBranch('D');
        config(['restaurant.public_code' => $restaurant->code]);
        $this->menuItem($restaurant, 'Margherita Pizza', 30000);
        Http::fake();

        $this->postJson(route('api.v1.chat.message'), ['message' => 'Do you have pizza?'])
            ->assertOk()->assertJsonPath('items.0.name', 'Margherita Pizza');
        Http::assertNothingSent();
    }

    public function test_manual_payment_methods_are_offered_and_shown_with_instructions(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('M');
        config(['restaurant.public_code' => $restaurant->code]);
        $item = $this->menuItem($restaurant, 'Chips', 5000);
        $admin = $this->staff($restaurant, null);
        $this->grant($admin, 'settings.payment-gateways.manage');

        $this->actingAs($admin)->post(route('admin.settings.payment-methods.store'), [
            'name' => 'MTN MoMo Pay', 'icon' => 'fas fa-mobile-screen-button', 'instructions' => 'Dial *165*3# and use merchant code 778899.', 'is_active' => '1',
        ])->assertRedirect();
        $method = ManualPaymentMethod::query()->firstOrFail();
        auth()->logout();

        $this->get(route('home'))->assertSee('MTN MoMo Pay');
        $response = $this->post(route('order.store'), [
            'name' => 'Brian', 'phone' => '0700000001', 'branch_id' => $branch->id,
            'payment_option' => $method->optionKey(), 'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('merchant code 778899')->assertSee('MTN MoMo Pay');

        // Another restaurant's method id is not accepted.
        [$other] = $this->restaurantWithBranch('N');
        $foreign = ManualPaymentMethod::create(['restaurant_id' => $other->id, 'name' => 'X', 'instructions' => 'Y']);
        $this->post(route('order.store'), [
            'name' => 'Brian', 'phone' => '0700000001', 'branch_id' => $branch->id,
            'payment_option' => $foreign->optionKey(), 'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('payment_option');
    }

    public function test_iotec_mobile_money_payment_flow(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('I');
        config(['restaurant.public_code' => $restaurant->code, 'restaurant.currency' => 'UGX']);
        $item = $this->menuItem($restaurant, 'Platter', 28000);
        $admin = $this->staff($restaurant, null);
        $this->grant($admin, 'admin');
        $this->actingAs($admin)->put(route('admin.settings.payment-gateways.update', 'iotec'), [
            'is_enabled' => 1, 'mode' => 'live', 'client_id' => 'pay-client', 'client_secret' => 'pay-secret', 'wallet_id' => '11111111-2222-3333-4444-555555555555',
        ])->assertRedirect();
        auth()->logout();

        $this->get(route('home'))->assertSee('Mobile money (MTN / Airtel)');
        $placed = $this->post(route('order.store'), [
            'name' => 'Grace', 'phone' => '0772 123 456', 'branch_id' => $branch->id,
            'payment_option' => 'iotec', 'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
        ]);
        $placed->assertRedirect();
        $token = basename(parse_url($placed->headers->get('Location'), PHP_URL_PATH));
        $order = Order::query()->firstOrFail();

        $remote = ['status' => 'Pending', 'amount' => 28000];
        Http::fake([
            'id.iotec.io/connect/token' => Http::response(['access_token' => 'tok', 'expires_in' => 300]),
            'pay.iotec.io/api/collections/collect' => Http::response(['id' => 'abc', 'status' => 'Pending']),
            'pay.iotec.io/api/collections/external-id/*' => function (HttpRequest $request) use (&$remote) {
                return Http::response($remote + ['externalId' => basename(parse_url($request->url(), PHP_URL_PATH))]);
            },
        ]);

        $this->post(route('order.pay', $token), ['gateway' => 'iotec'])
            ->assertRedirect(route('order.track', $token))
            ->assertSessionHas('success');
        $payment = $order->payments()->firstOrFail();
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/api/collections/collect')
            && $request['payer'] === '256772123456'
            && $request['externalId'] === $payment->transaction_reference
            && $request['walletId'] === '11111111-2222-3333-4444-555555555555');

        $this->get(route('order.track', $token))->assertSee('I have approved the payment');

        // First check: still pending.
        $this->get(route('order.payment.return', [$token, $payment->id]))->assertSessionHas('error');
        $this->assertSame('pending', $payment->fresh()->status);

        // A success for the wrong amount is not accepted.
        $remote = ['status' => 'Success', 'amount' => 500];
        $this->get(route('order.payment.return', [$token, $payment->id]))->assertSessionHas('error');
        $this->assertSame('pending', $payment->fresh()->status);

        // Second check: approved on the phone.
        $remote = ['status' => 'Success', 'amount' => 28000];
        $this->get(route('order.payment.return', [$token, $payment->id]))->assertSessionHas('success');
        $this->assertSame('successful', $payment->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->balance);
    }

    public function test_backups_contain_only_this_restaurants_data(): void
    {
        Storage::fake('local');
        [$restaurant, $branch, $owner] = $this->owner('K');
        [$other, $otherBranch] = $this->restaurantWithBranch('L');
        Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Our Customer', 'phone' => '0701']);
        Customer::create(['restaurant_id' => $other->id, 'name' => 'Their Customer', 'phone' => '0702']);
        $ours = Order::create(['branch_id' => $branch->id, 'order_number' => 'ORD-OURS', 'order_type' => 'takeaway', 'status' => 'completed', 'subtotal' => 1, 'total' => 1, 'ordered_at' => now()]);
        $ours->items()->create(['item_name' => 'Our item', 'unit_price' => 1, 'quantity' => 1, 'total' => 1]);
        $theirs = Order::create(['branch_id' => $otherBranch->id, 'order_number' => 'ORD-THEIRS', 'order_type' => 'takeaway', 'status' => 'completed', 'subtotal' => 1, 'total' => 1, 'ordered_at' => now()]);
        $theirs->items()->create(['item_name' => 'Their item', 'unit_price' => 1, 'quantity' => 1, 'total' => 1]);
        app(RestaurantSettings::class)->save($restaurant->id, 'sms', ['provider' => 'twilio', 'twilio_token' => 'very-secret-token']);

        $this->actingAs($owner)->post(route('admin.settings.backups.store'))->assertRedirect()->assertSessionHas('success');
        $backup = app(RestaurantBackupService::class)->list($restaurant->id)->first();
        $this->assertNotNull($backup);

        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path(RestaurantBackupService::directory($restaurant->id) . '/' . $backup['name']));
        $all = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $all .= $zip->getFromIndex($i);
        }
        $zip->close();

        $this->assertStringContainsString('Our Customer', $all);
        $this->assertStringContainsString('ORD-OURS', $all);
        $this->assertStringContainsString('Our item', $all);
        $this->assertStringNotContainsString('Their Customer', $all);
        $this->assertStringNotContainsString('ORD-THEIRS', $all);
        $this->assertStringNotContainsString('Their item', $all);
        $this->assertStringNotContainsString('very-secret-token', $all);
        $this->assertStringNotContainsString('"password":', $all);

        $this->get(route('admin.settings.backups.download', $backup['name']))->assertOk();
        $this->get(route('admin.settings.backups.download', '..%2F..%2F.env'))->assertNotFound();

        // Another restaurant's admin cannot download it.
        $intruder = $this->staff($other, null);
        $this->grant($intruder, 'admin');
        $this->actingAs($intruder)->get(route('admin.settings.backups.download', $backup['name']))->assertNotFound();
    }

    public function test_receipt_and_invoice_use_settings(): void
    {
        [$restaurant, $branch, $owner] = $this->owner('R');
        $this->grant($owner, 'orders.view');
        $settings = app(RestaurantSettings::class);
        $settings->save($restaurant->id, 'receipt', ['paper_width' => '58', 'footer_text' => 'Asante sana!', 'show_logo' => true]);
        $settings->save($restaurant->id, 'invoice', ['prefix' => 'KEM', 'bank_details' => 'Stanbic 9030000000', 'due_days' => '7']);
        $order = Order::create(['branch_id' => $branch->id, 'order_number' => 'POS-2611-0007', 'order_type' => 'dine_in', 'status' => 'completed', 'subtotal' => 20, 'total' => 20, 'balance' => 20, 'ordered_at' => now()]);
        $order->items()->create(['item_name' => 'Tea', 'unit_price' => 20, 'quantity' => 1, 'total' => 20]);

        $this->actingAs($owner)->get(route('admin.orders.receipt', $order))->assertOk()->assertSee('Asante sana!')->assertSee('58mm', false);
        $this->get(route('admin.orders.invoice', $order))->assertOk()->assertSee('KEM-POS-2611-0007')->assertSee('Stanbic 9030000000');
    }
}
