<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $restaurantId = $this->authorizeExpenses('view');
        $user = $request->user();
        $expenses = Expense::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->with(['branch:id,name', 'category:id,name'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();
        $branches = Branch::query()
            ->where('restaurant_id', $restaurantId)->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->whereKey($user->branch_id))
            ->orderBy('name')->get(['id', 'name']);
        $categories = ExpenseCategory::query()->where('restaurant_id', $restaurantId)->orderBy('name')->get(['id', 'name']);
        $total = (float) Expense::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)))
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        return view('admin.expenses.index', compact('expenses', 'branches', 'categories', 'total'));
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')->where('restaurant_id', $restaurantId)],
        ]);
        ExpenseCategory::create(['restaurant_id' => $restaurantId, 'name' => $data['name']]);

        return back()->with('success', 'Expense category created.');
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('create');
        $user = $request->user();
        $data = $request->validate([
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where(fn ($query) => $query
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->when($user->branch_id, fn ($branches) => $branches->where('id', $user->branch_id)))],
            'expense_category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('restaurant_id', $restaurantId)],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'payment_method' => ['nullable', Rule::in(['cash', 'mobile_money', 'bank', 'card', 'other'])],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
        ]);

        DB::transaction(fn () => Expense::create([...$data, 'created_by' => $user->id]));

        return back()->with('success', 'Expense recorded.');
    }

    private function authorizeExpenses(string $operation): int
    {
        $user = auth()->user();
        abort_unless($user && $user->restaurant_id, 403);
        abort_unless(
            $user->can('finance.expenses.' . $operation)
                || $user->can('finance.expenses.manage')
                || $user->can('admin'),
            403
        );

        return (int) $user->restaurant_id;
    }
}
