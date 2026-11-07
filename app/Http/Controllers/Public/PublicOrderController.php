<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

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

        $paymentGateways = array_keys($this->enabledGateways());
        $paymentOption = $request->input('payment_option');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => [$paymentOption === 'cash_on_delivery' ? 'nullable' : 'required', 'email', 'max:255'],
            'payment_option' => ['required', Rule::in(['cash_on_delivery', ...$paymentGateways])],
            'address' => ['nullable', 'string', 'max:1000'],
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

        $order = DB::transaction(function () use ($validated, $restaurant, $singleBranchId, $inventory, $pricing): array {
            $priced = $pricing->price((int) $restaurant->id, $validated['items'], fn ($query) => $query
                ->whereHas('category', fn ($categories) => $categories
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true)));

            $branchId = $singleBranchId ?? (int) $validated['branch_id'];
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
            $tax = round($subtotal * max(0, min(100, (float) config('restaurant.tax_rate', 0))) / 100, 2);
            $deliveryFee = max(0, (float) config('restaurant.delivery_fee', 0));
            $total = $subtotal + $tax + $deliveryFee;
            $trackingToken = bin2hex(random_bytes(32));

            $order = Order::create([
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'order_number' => 'WEB-' . Str::upper((string) Str::uuid()),
                'order_type' => 'online',
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
        if ($email) {
            try {
                Mail::to($email)->send(new PublicOrderConfirmation($orderRecord, $trackingToken));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $redirect = redirect()->route('order.success', ['order' => $trackingToken]);
        if ($validated['payment_option'] !== 'cash_on_delivery') {
            $redirect->with('start_payment_gateway', $validated['payment_option']);
        }

        return $redirect;
    }

    public function success(Request $request, string $order): Response
    {
        $record = $this->findPublicOrder($order);
        $gateways = $this->enabledGateways();
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
        ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function track(string $order): Response
    {
        return response()->view('public.orders.track', [
            'order' => $this->findPublicOrder($order),
            'trackingToken' => $order,
            'gateways' => $this->enabledGateways(),
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

    private function enabledGateways(): array
    {
        return collect(config('restaurant.gateways', []))
            ->filter(fn (array $gateway, string $name): bool =>
                ($gateway['enabled'] ?? false)
                && !empty($gateway['base_url'])
                && ($name === 'flutterwave'
                    ? !empty($gateway['secret_key'])
                    : !empty($gateway['consumer_key']) && !empty($gateway['consumer_secret']) && !empty($gateway['notification_id']))
            )
            ->map(fn (array $gateway, string $name): string => ucfirst($name))
            ->all();
    }
}
