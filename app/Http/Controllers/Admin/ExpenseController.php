<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
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
        $expenses = $this->scopedExpenses($request, $restaurantId)
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
        $total = (float) $this->scopedExpenses($request, $restaurantId)
            ->whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');
        $canManage = $user->can('finance.expenses.manage') || $user->can('admin');
        $lastMonth = (float) $this->scopedExpenses($request, $restaurantId)
            ->whereBetween('expense_date', [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->sum('amount');
        $today = (float) $this->scopedExpenses($request, $restaurantId)->whereDate('expense_date', now()->toDateString())->sum('amount');
        $currency = config('restaurant.currency');
        $pageStats = [
            ['label' => 'This month', 'value' => $currency . ' ' . number_format($total, 2), 'icon' => 'fas fa-wallet', 'tone' => 'brand', 'hint' => now()->format('F Y')],
            ['label' => 'Last month', 'value' => $currency . ' ' . number_format($lastMonth, 2), 'icon' => 'fas fa-calendar-minus', 'tone' => 'neutral', 'hint' => $lastMonth > 0 ? ('This month is ' . number_format(abs(($total - $lastMonth) / $lastMonth * 100), 1) . '% ' . ($total >= $lastMonth ? 'higher' : 'lower')) : 'Nothing recorded last month', 'trend' => $lastMonth > 0 ? ($total >= $lastMonth ? 'up' : 'down') : null],
            ['label' => 'Today', 'value' => $currency . ' ' . number_format($today, 2), 'icon' => 'fas fa-calendar-day', 'tone' => 'info'],
            ['label' => 'Records', 'value' => number_format($expenses->total()), 'icon' => 'fas fa-list', 'tone' => 'violet', 'hint' => $categories->count() . ' categories'],
        ];

        return view('admin.expenses.index', compact('expenses', 'branches', 'categories', 'total', 'canManage', 'pageStats'));
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

    public function updateCategory(Request $request, ExpenseCategory $category, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('manage');
        $record = ExpenseCategory::query()->where('restaurant_id', $restaurantId)->whereKey($category->id)->firstOrFail();
        $data = $request->validateWithBag('expense-category-' . $record->id, [
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')
                ->where('restaurant_id', $restaurantId)
                ->ignore($record->id)],
        ]);
        $record->update(['name' => $data['name']]);
        $logger->log('finance.expense_category.updated', $record, ['fields' => array_keys($record->getChanges())]);

        return back()->with('success', 'Expense category updated.');
    }

    public function destroyCategory(Request $request, ExpenseCategory $category, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('manage');
        $record = ExpenseCategory::query()->where('restaurant_id', $restaurantId)->whereKey($category->id)->firstOrFail();
        $used = Expense::withTrashed()->where('expense_category_id', $record->id)->count();
        if ($used > 0) {
            return back()->with('error', "This category is used by {$used} expense(s) and cannot be deleted. Rename it instead.");
        }
        $name = $record->name;
        $record->delete();
        $logger->log('finance.expense_category.deleted', null, ['category_id' => $category->id, 'name' => $name]);

        return back()->with('success', 'Expense category deleted.');
    }

    public function store(Request $request): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('create');
        $user = $request->user();
        $data = $request->validate($this->expenseRules($request, $restaurantId));

        DB::transaction(fn () => Expense::create([...$data, 'created_by' => $user->id]));

        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('manage');
        $record = $this->scopedExpenses($request, $restaurantId)->whereKey($expense->id)->firstOrFail();
        $data = $request->validateWithBag('expense-' . $record->id, $this->expenseRules($request, $restaurantId));
        $record->update($data);
        $logger->log('finance.expense.updated', $record, ['fields' => array_keys($record->getChanges())]);

        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense, ActivityLogger $logger): RedirectResponse
    {
        $restaurantId = $this->authorizeExpenses('manage');
        $record = $this->scopedExpenses($request, $restaurantId)->whereKey($expense->id)->firstOrFail();
        $record->delete();
        $logger->log('finance.expense.deleted', $record, ['branch_id' => $record->branch_id, 'amount' => $record->amount]);

        return back()->with('success', 'Expense deleted.');
    }

    private function scopedExpenses(Request $request, int $restaurantId): Builder
    {
        $user = $request->user();

        return Expense::query()
            ->whereHas('branch', fn ($query) => $query->where('restaurant_id', $restaurantId)
                ->when($user->branch_id, fn ($branches) => $branches->whereKey($user->branch_id)));
    }

    private function expenseRules(Request $request, int $restaurantId): array
    {
        $user = $request->user();

        return [
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
        ];
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
