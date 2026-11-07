<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Restaurant customer directory. Customers belong to the restaurant; order
 * history and spend are limited to the branches the user may see.
 *
 * There is no plain delete. Erasure anonymises personal data while keeping
 * order/payment records for accounting (see erase()).
 */
class CustomerController extends Controller
{
    use ScopesToUserBranches;

    public function index(Request $request): View
    {
        $user = $this->authorizeCustomers($request, 'view');
        $search = trim((string) $request->query('search', ''));

        $customers = Customer::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('phone', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')))
            ->withCount(['orders as orders_count' => fn (Builder $orders) => $this->visibleOrders($orders, $user)])
            ->withSum(['orders as total_spent' => fn (Builder $orders) => $this->visibleOrders($orders, $user)], 'total')
            ->withMax(['orders as last_order_at' => fn (Builder $orders) => $this->visibleOrders($orders, $user)], 'ordered_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $directory = Customer::query()->where('restaurant_id', $user->restaurant_id);
        $pageStats = [
            ['label' => 'Customers', 'value' => number_format((clone $directory)->count()), 'icon' => 'fas fa-users', 'tone' => 'brand', 'hint' => 'In your customer directory'],
            ['label' => 'New this month', 'value' => number_format((clone $directory)->where('created_at', '>=', now()->startOfMonth())->count()), 'icon' => 'fas fa-user-plus', 'tone' => 'success', 'hint' => 'Since ' . now()->startOfMonth()->format('j M')],
            ['label' => 'Reachable by email', 'value' => number_format((clone $directory)->whereNotNull('email')->where('email', '!=', '')->count()), 'icon' => 'fas fa-envelope', 'tone' => 'info', 'hint' => 'Receive receipts and updates'],
            ['label' => 'Loyalty points held', 'value' => number_format((int) (clone $directory)->sum('loyalty_points')), 'icon' => 'fas fa-star', 'tone' => 'warning', 'hint' => 'Across all customers'],
        ];

        return view('admin.customers.index', [
            'pageStats' => $pageStats,
            'customers' => $customers,
            'search' => $search,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function show(Request $request, int $customer): View
    {
        $user = $this->authorizeCustomers($request, 'view');
        $record = Customer::query()->where('restaurant_id', $user->restaurant_id)->findOrFail($customer);

        $orders = $this->visibleOrders($record->orders()->getQuery(), $user, includeCancelled: true)
            ->with('branch:id,name')
            ->latest('id')
            ->paginate(15, ['*'], 'orders_page')
            ->withQueryString();
        $reservations = $this->whereUserBranch($record->reservations()->getQuery(), $user)
            ->with(['branch:id,name', 'table:id,name'])
            ->latest('reserved_at')
            ->limit(20)
            ->get();
        $feedback = $this->whereUserBranch($record->feedback()->getQuery(), $user)
            ->with('order:id,order_number')
            ->latest('id')
            ->limit(20)
            ->get();
        $summary = $this->visibleOrders($record->orders()->getQuery(), $user)
            ->selectRaw('COUNT(*) as orders_count, COALESCE(SUM(total), 0) as total_spent, COALESCE(SUM(balance), 0) as outstanding')
            ->first();

        return view('admin.customers.show', [
            'customer' => $record,
            'orders' => $orders,
            'reservations' => $reservations,
            'feedback' => $feedback,
            'summary' => $summary,
            'canManage' => $this->canManage($request),
            'canErase' => $user->can('customers.erase') || $user->can('admin'),
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeCustomers($request, 'manage');
        $data = $request->validate($this->rules($user));
        $customer = Customer::create([...$data, 'restaurant_id' => $user->restaurant_id]);
        $logger->log('customers.created', $customer);

        return redirect()->route('admin.customers.show', $customer->id)->with('success', 'Customer created.');
    }

    public function update(Request $request, int $customer, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeCustomers($request, 'manage');
        $record = Customer::query()->where('restaurant_id', $user->restaurant_id)->findOrFail($customer);
        $data = $request->validate($this->rules($user, $record->id));
        $record->update($data);
        $logger->log('customers.updated', $record, ['fields' => array_keys($record->getChanges())]);

        return back()->with('success', 'Customer updated.');
    }

    /**
     * Erase a customer's personal data. Orders and payments are kept as
     * accounting records but no longer identify the person. Refused while
     * the customer still owes money.
     */
    public function erase(Request $request, int $customer, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeCustomers($request, 'view');
        abort_unless($user->can('customers.erase') || $user->can('admin'), 403);
        $request->validate(['confirm' => ['required', 'in:ERASE']], ['confirm.in' => 'Type ERASE to confirm.']);

        DB::transaction(function () use ($user, $customer): void {
            $record = Customer::query()->where('restaurant_id', $user->restaurant_id)->lockForUpdate()->findOrFail($customer);
            $owing = $record->orders()->where('status', '!=', 'cancelled')->where('balance', '>', 0)->exists();
            if ($owing) {
                throw ValidationException::withMessages(['confirm' => 'This customer has unpaid orders. Settle or cancel them before erasing.']);
            }
            $record->forceFill([
                'name' => 'Erased customer #' . $record->id,
                'phone' => null, 'email' => null, 'address' => null, 'date_of_birth' => null, 'notes' => null,
                'loyalty_points' => 0,
            ])->save();
            $record->reservations()->update(['guest_name' => 'Erased guest', 'phone' => 'erased', 'special_requests' => null, 'updated_at' => now()]);
            $record->feedback()->update(['comment' => null, 'updated_at' => now()]);
            $record->delete();
        });
        // Log the identifier only: the point of erasure is not to keep the data.
        $logger->log('customers.erased', null, ['customer_id' => $customer]);

        return redirect()->route('admin.customers.index')->with('success', 'Customer data erased.');
    }

    private function rules(User $user, ?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Phone is the restaurant's customer key (public orders and reservations match on it).
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\s-]{6,40}$/',
                Rule::unique('customers', 'phone')
                    ->where('restaurant_id', $user->restaurant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId)],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Orders in branches the user may see; cancelled orders excluded from spend unless asked. */
    private function visibleOrders(Builder $orders, User $user, bool $includeCancelled = false): Builder
    {
        return $orders
            ->whereIn('orders.branch_id', Branch::query()
                ->where('restaurant_id', $user->restaurant_id)
                ->when($user->branch_id, fn (Builder $query) => $query->whereKey($user->branch_id))
                ->select('id'))
            ->when(!$includeCancelled, fn (Builder $query) => $query->where('orders.status', '!=', 'cancelled'));
    }

    private function authorizeCustomers(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'view'
            ? $user->can('customers.view') || $this->canManage($request)
            : $this->canManage($request);
        abort_unless($allowed, 403);

        return $user;
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return (bool) $user && ($user->can('customers.manage') || $user->can('admin'));
    }
}
