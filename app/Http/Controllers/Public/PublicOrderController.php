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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response;

class PublicOrderController extends Controller
{
    public function store(Request $request, RecipeInventoryService $inventory): RedirectResponse
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

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'branch_id' => [
                Rule::requiredIf($singleBranchId === null),
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true)),
            ],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => [
                'required',
                'integer',
                Rule::exists('menu_items', 'id')->where(fn ($query) => $query
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_available', true)),
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = DB::transaction(function () use ($validated, $restaurant, $singleBranchId, $inventory): array {
            $itemsById = MenuItem::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('is_available', true)
                ->whereHas('category', fn ($query) => $query
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true))
                ->whereIn('id', collect($validated['items'])->pluck('menu_item_id')->unique())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            abort_if(
                $itemsById->count() !== collect($validated['items'])->pluck('menu_item_id')->unique()->count(),
                422,
                'One or more menu items are no longer available.'
            );

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

            $subtotal = 0.0;
            foreach ($validated['items'] as $line) {
                $subtotal += (float) $itemsById[$line['menu_item_id']]->price * (float) $line['quantity'];
            }
            $subtotal = round($subtotal, 2);
            $tax = round($subtotal * max(0, min(100, (float) config('restaurant.tax_rate', 0))) / 100, 2);
            $deliveryFee = max(0, (float) config('restaurant.delivery_fee', 0));
            $total = $subtotal + $tax + $deliveryFee;
            $trackingToken = bin2hex(random_bytes(32));

            $order = Order::create([
                'branch_id' => $branchId,
                'customer_id' => $customer->id,
                'order_number' => 'WEB-' . Str::upper((string) Str::uuid()),
                'order_type' => 'online',
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

            foreach ($validated['items'] as $line) {
                $item = $itemsById[$line['menu_item_id']];
                $quantity = (float) $line['quantity'];
                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'item_name' => $item->name,
                    'unit_price' => $item->price,
                    'quantity' => $quantity,
                    'total' => round((float) $item->price * $quantity, 2),
                ]);
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

        return redirect()->route('order.success', ['order' => $trackingToken]);
    }

    public function success(string $order): Response
    {
        $record = $this->findPublicOrder($order);

        return response()->view('public.orders.success', [
            'order' => $record,
            'trackingToken' => $order,
            'gateways' => $this->enabledGateways(),
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
            ->with(['items', 'branch'])
            ->firstOrFail();
    }

    private function enabledGateways(): array
    {
        return collect(config('restaurant.gateways', []))
            ->filter(fn (array $gateway, string $name): bool =>
                ($gateway['enabled'] ?? false)
                && ($name === 'flutterwave'
                    ? !empty($gateway['secret_key'])
                    : !empty($gateway['consumer_key']) && !empty($gateway['consumer_secret']) && !empty($gateway['notification_id']))
            )
            ->map(fn (array $gateway, string $name): string => ucfirst($name))
            ->all();
    }
}
