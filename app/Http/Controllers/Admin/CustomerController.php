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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Restaurant customer directory. Customers belong to the restaurant; order
 * history and spend are limited to the branches the user may see.
 *
 * There is intentionally no delete: erasure/anonymisation needs an approved
 * data-retention policy.
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

        return view('admin.customers.index', [
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
