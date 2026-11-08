<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Services\Orders\OrderNumberGenerator;
use App\Services\Orders\TaxService;
use App\Services\Payments\GatewaySettings;
use App\Services\Notifications\SmsService;
use App\Services\Settings\MailSettings;
use App\Models\ManualPaymentMethod;
use App\Http\Controllers\Controller;
use App\Mail\PublicOrderConfirmation;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\Inventory\RecipeInventoryService;
use App\Services\Orders\OrderPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class PublicOrderController extends Controller
{
    public function store(Request $request, RecipeInventoryService $inventory, OrderPricingService $pricing): RedirectResponse
    {
        $restaurant = Restaurant::query()
            ->where('code', config('restaurant.public_code'))
            ->where('is_active', true)
            ->firstOrFail();

        $activeBranches = Branch::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('is_active', true);
        $singleBranchId = (clone $activeBranches)->count() === 1
            ? (clone $activeBranches)->value('id')
            : null;

        $paymentGateways = array_keys($this->enabledGateways($restaurant));
        $manualOptions = $this->manualMethods($restaurant->id)->map->optionKey()->all();
        $paymentOption = $request->input('payment_option');
        // Card gateways need an email for the receipt; mobile money and offline methods do not.
        $needsEmail = in_array($paymentOption, ['flutterwave', 'pesapal'], true);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => [$needsEmail ? 'required' : 'nullable', 'email', 'max:255'],
            'payment_option' => ['required', Rule::in(['cash_on_delivery', ...$paymentGateways, ...$manualOptions])],
            'address' => ['nullable', 'string', 'max:1000'],
            'table_token' => ['nullable', 'string', 'max:100'],
            'branch_id' => [
                Rule::requiredIf($singleBranchId === null),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true)),
            ],
            ...OrderPricingService::lineRules(),
            'items.*.menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_available', true)),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $qrTable = !empty($validated['table_token'])
            ? \App\Models\RestaurantTable::query()->where('qr_token', $validated['table_token'])
                ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurant->id)->where('is_active', true))->firstOrFail()
            : null;

        $order = DB::transaction(function () use ($validated, $restaurant, $singleBranchId, $inventory, $pricing, $qrTable): array {
            $priced = $pricing->price((int) $restaurant->id, $validated['items'], fn ($query) => $query
                ->whereHas('category', fn ($categories) => $categories
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true)));

            $branchId = $qrTable?->branch_id ?? $singleBranchId ?? (int) $validated['branch_id'];
            $customer = Customer::query()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'phone' => $validated['phone']],
                [
                    'name' => $validated['name'],
                    'email' => $validated['email'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]
            );
            if (!$customer->wasRecentlyCreated && !$customer->email && !empty($validated['email'])) {
                $customer->forceFill(['email' => $validated['email']])->save();
            }

            $subtotal = $priced['subtotal'];
            $taxed = app(TaxService::class)->apply((int) $restaurant->id, (float) $subtotal);
            $tax = $taxed['tax'];
            $deliveryFee = max(0, (float) config('restaurant.delivery_fee', 0));
            $total = $taxed['total'] + $deliveryFee;
            $trackingToken = bin2hex(random_bytes(32));

            $order = Order::create([
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'order_number' => app(OrderNumberGenerator::class)->next('WEB'),
                'restaurant_table_id' => $qrTable?->id,
                'order_type' => $qrTable ? 'dine_in' : 'online',
                'payment_preference' => $validated['payment_option'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => $tax,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'paid_amount' => 0,
                'balance' => $total,
                'notes' => $validated['notes'] ?? null,
                'ordered_at' => now(),
            ]);
            $order->forceFill(['tracking_token_hash' => hash('sha256', $trackingToken)])->save();

            foreach ($priced['lines'] as $line) {
                $order->items()->create($line);
            }

            $inventory->consume((int) $restaurant->id, $branchId, $validated['items'], null, (int) $order->id);

            return [$order, $trackingToken];
        });

        [$orderRecord, $trackingToken] = $order;

        $email = $validated['email'] ?? $orderRecord->customer?->email;
        $mailSettings = app(MailSettings::class);
        if ($email && $mailSettings->sendsOrderConfirmations($restaurant)) {
            try {
                $mailSettings->apply($restaurant);
                Mail::to($email)->send(new PublicOrderConfirmation($orderRecord, $trackingToken));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
        app(SmsService::class)->orderPlaced($orderRecord, route('order.track', $trackingToken));

        $redirect = redirect()->route('order.success', ['order' => $trackingToken]);
        if (in_array($validated['payment_option'], $paymentGateways, true)) {
            $redirect->with('start_payment_gateway', $validated['payment_option']);
        }

        return $redirect;
    }

    public function success(Request $request, string $order): Response
    {
        $record = $this->findPublicOrder($order);
        $gateways = $this->enabledGateways($record->branch?->restaurant);
        $startPaymentGateway = $request->session()->pull('start_payment_gateway');
        if (!is_string($startPaymentGateway)
            || $startPaymentGateway !== $record->payment_preference
            || !array_key_exists($startPaymentGateway, $gateways)) {
            $startPaymentGateway = null;
        }

        return response()->view('public.orders.success', [
            'order' => $record,
            'trackingToken' => $order,
            'gateways' => $gateways,
            'startPaymentGateway' => $startPaymentGateway,
            ...$this->paymentContext($record, $gateways),
        ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function track(string $order): Response
    {
        $record = $this->findPublicOrder($order);
        $gateways = $this->enabledGateways($record->branch?->restaurant);

        return response()->view('public.orders.track', [
            'order' => $record,
            'trackingToken' => $order,
            'gateways' => $gateways,
            ...$this->paymentContext($record, $gateways),
        ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    private function findPublicOrder(string $token): Order
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/', $token) === 1, 404);

        return Order::query()
            ->where('tracking_token_hash', hash('sha256', $token))
            ->with(['items', 'branch.restaurant', 'feedback'])
            ->firstOrFail();
    }

    /** Readable payment choice, offline instructions and any payment awaiting approval. */
    private function paymentContext(Order $order, array $gateways): array
    {
        $preference = (string) $order->payment_preference;
        $manualMethod = str_starts_with($preference, 'manual_')
            ? ManualPaymentMethod::query()->where('restaurant_id', $order->branch?->restaurant_id)->find((int) substr($preference, 7))
            : null;
        $label = match (true) {
            $preference === '' => null,
            $preference === 'cash_on_delivery' => 'Pay on delivery or pickup',
            $manualMethod !== null => $manualMethod->name,
            default => GatewaySettings::GATEWAYS[$preference]['label'] ?? ($gateways[$preference] ?? ucfirst($preference)),
        };

        return [
            'paymentLabel' => $label ?? 'Not chosen',
            'manualMethod' => $manualMethod,
            'pendingPayment' => $order->payments()->where('status', 'pending')->latest('id')->first(),
        ];
    }

    private function manualMethods(int $restaurantId)
    {
        return ManualPaymentMethod::query()->where('restaurant_id', $restaurantId)->where('is_active', true)->orderBy('display_order')->orderBy('name')->get();
    }

    /** Online gateways ready for this restaurant (admin settings, else .env). */
    private function enabledGateways(?\App\Models\Restaurant $restaurant): array
    {
        $settings = app(GatewaySettings::class);
        $settings->apply($restaurant);

        return $settings->enabled();
    }
}
