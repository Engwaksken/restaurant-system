@extends('layouts.app')

@section('title', 'Expenses')
@section('page-title', 'Expenses')

@section('content')
<div class="row g-4">
    <div class="col-xl-4">
        <div class="panel p-4 mb-4">
            <h2 class="h5">Record expense</h2>
            @if($branches->isEmpty())<p class="text-muted">No active branch is assigned to your account.</p>@else
                <form method="POST" action="{{ route('admin.expenses.store') }}">
                    @csrf
                    <label class="form-label" for="expenseBranch">Branch</label>
                    <select class="form-select mb-2" id="expenseBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                    <label class="form-label" for="expenseCategory">Category</label>
                    <select class="form-select mb-2" id="expenseCategory" name="expense_category_id"><option value="">Uncategorised</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                    <label class="form-label" for="expenseDescription">Description</label>
                    <textarea class="form-control mb-2" id="expenseDescription" name="description" maxlength="1000" required></textarea>
                    <label class="form-label" for="expenseAmount">Amount ({{ config('restaurant.currency') }})</label>
                    <input class="form-control mb-2" type="number" id="expenseAmount" name="amount" step="0.01" min="0.01" max="100000000" required>
                    <label class="form-label" for="expenseDate">Date</label>
                    <input class="form-control mb-2" type="date" id="expenseDate" name="expense_date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                    <label class="form-label" for="expenseReference">Reference (optional)</label>
                    <input class="form-control mb-2" id="expenseReference" name="reference" maxlength="255">
                    <label class="form-label" for="expensePayment">Payment method</label>
                    <select class="form-select mb-3" id="expensePayment" name="payment_method"><option value="">Not specified</option><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option><option value="card">Card</option><option value="other">Other</option></select>
                    <button class="btn btn-primary" type="submit">Save expense</button>
                </form>
            @endif
        </div>
        <div class="panel p-4">
            <h2 class="h5">Add category</h2>
            <form method="POST" action="{{ route('admin.expense-categories.store') }}">
                @csrf
                <label class="form-label" for="expenseCategoryName">Category name</label>
                <input class="form-control mb-3" id="expenseCategoryName" name="name" maxlength="255" required>
                <button class="btn btn-outline-primary" type="submit">Add category</button>
            </form>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="panel p-4 mb-4"><span class="text-muted">Expenses this month</span><div class="h3 mb-0">{{ config('restaurant.currency') }} {{ number_format($total, 2) }}</div></div>
        <div class="panel p-4">
            <h2 class="h5">Expense history</h2>
            <div class="table-responsive"><table class="table table-striped">
                <thead><tr><th>Date</th><th>Branch</th><th>Category</th><th>Description</th><th>Method</th><th>Amount</th></tr></thead>
                <tbody>@forelse($expenses as $expense)<tr><td>{{ $expense->expense_date->format('Y-m-d') }}</td><td>{{ $expense->branch?->name }}</td><td>{{ $expense->category?->name ?? '—' }}</td><td>{{ $expense->description }} @if($expense->reference)<small class="text-muted d-block">Ref: {{ $expense->reference }}</small>@endif</td><td>{{ $expense->payment_method ? ucfirst(str_replace('_', ' ', $expense->payment_method)) : '—' }}</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $expense->amount, 2) }}</td></tr>@empty<tr><td colspan="6" class="text-muted">No expenses recorded yet.</td></tr>@endforelse</tbody>
            </table></div>
            {{ $expenses->links() }}
        </div>
    </div>
</div>
@endsection
