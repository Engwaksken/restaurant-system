<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Loyalty balances, the points ledger and audited manual adjustments. */
class LoyaltyController extends Controller
{
    public function index(Request $request, LoyaltyService $loyalty): View
    {
        $user = $this->authorizeLoyalty($request, 'view');
        $customers = Customer::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->where('loyalty_points', '>', 0)
            ->orderByDesc('loyalty_points')
            ->paginate(25, ['id', 'name', 'phone', 'loyalty_points'], 'customers_page');
        $ledger = LoyaltyTransaction::query()
            ->whereHas('customer', fn ($query) => $query->where('restaurant_id', $user->restaurant_id))
            ->with(['customer:id,name', 'order:id,order_number', 'user:id,name'])
            ->latest('id')
            ->paginate(25, ['*'], 'ledger_page');

        return view('admin.customers.loyalty', [
            'customers' => $customers,
            'ledger' => $ledger,
            'enabled' => $loyalty->enabled(),
            'settings' => config('restaurant.loyalty'),
            'outstanding' => (int) Customer::query()->where('restaurant_id', $user->restaurant_id)->sum('loyalty_points'),
            'canAdjust' => $this->canAdjust($user),
            'allCustomers' => $this->canAdjust($user)
                ? Customer::query()->where('restaurant_id', $user->restaurant_id)->orderBy('name')->limit(500)->get(['id', 'name', 'phone', 'loyalty_points'])
                : collect(),
        ]);
    }

    public function adjust(Request $request, LoyaltyService $loyalty, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeLoyalty($request, 'adjust');
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('restaurant_id', $user->restaurant_id)->whereNull('deleted_at')],
            'points' => ['required', 'integer', 'not_in:0', 'between:-1000000,1000000'],
            'note' => ['required', 'string', 'min:5', 'max:255'],
        ]);
        $entry = DB::transaction(function () use ($data, $loyalty, $user) {
            $customer = Customer::query()->whereKey($data['customer_id'])->lockForUpdate()->firstOrFail();

            return $loyalty->adjust($customer, (int) $data['points'], $data['note'], $user->id);
        });
        $logger->log('customers.loyalty.adjusted', $entry, ['customer_id' => $entry->customer_id, 'points' => $entry->points, 'note' => $entry->note]);

        return back()->with('success', 'Points adjusted. New balance: ' . $entry->balance_after . '.');
    }

    private function canAdjust(User $user): bool
    {
        return $user->can('customers.loyalty.adjust') || $user->can('admin');
    }

    private function authorizeLoyalty(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'adjust'
            ? $this->canAdjust($user)
            : $this->canAdjust($user) || $user->can('customers.loyalty.view') || $user->can('customers.view') || $user->can('customers.manage');
        abort_unless($allowed, 403);

        return $user;
    }
}
