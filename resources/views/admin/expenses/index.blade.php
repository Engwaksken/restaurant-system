@extends('layouts.app')

@section('title', 'Expenses')
@section('page-title', 'Expenses')
@section('own-validation-errors', '1')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="panel p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="text-muted">Expenses this month</span>
                <div class="h3 mb-0">{{ config('restaurant.currency') }} {{ number_format($total, 2) }}</div>
                @if($branches->isEmpty())<p class="text-muted small mt-2 mb-0">No active branch is assigned to your account.</p>@endif
            </div>
            <div class="d-flex flex-wrap gap-2">
                @if($branches->isNotEmpty())
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-expense">
                        <i class="fas fa-plus me-1"></i> Record expense
                    </button>
                @endif
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#add-expense-category">
                    <i class="fas fa-plus me-1"></i> Add category
                </button>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="panel p-4">
            <h2 class="h5">Expense history</h2>
            <div class="table-responsive"><table class="table table-striped">
                <thead><tr><th>Date</th><th>Branch</th><th>Category</th><th>Description</th><th>Method</th><th>Amount</th>@if($canManage)<th class="text-end">Actions</th>@endif</tr></thead>
                <tbody>@forelse($expenses as $expense)<tr><td>{{ $expense->expense_date->format('Y-m-d') }}</td><td>{{ $expense->branch?->name }}</td><td>{{ $expense->category?->name ?? '—' }}</td><td>{{ $expense->description }} @if($expense->reference)<small class="text-muted d-block">Ref: {{ $expense->reference }}</small>@endif</td><td>{{ $expense->payment_method ? ucfirst(str_replace('_', ' ', $expense->payment_method)) : '—' }}</td><td>{{ config('restaurant.currency') }} {{ number_format((float) $expense->amount, 2) }}</td>@if($canManage)<td class="text-end text-nowrap">@if($branches->isNotEmpty())<button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-expense-{{ $expense->id }}" title="Edit" aria-label="Edit expense"><i class="fas fa-pen"></i></button>@endif <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-expense-{{ $expense->id }}" title="Delete" aria-label="Delete expense"><i class="fas fa-trash"></i></button></td>@endif</tr>@empty<tr><td colspan="{{ $canManage ? 7 : 6 }}" class="text-muted">No expenses recorded yet.</td></tr>@endforelse</tbody>
            </table></div>
            {{ $expenses->links() }}
        </div>
    </div>
    <div class="col-12">
        <div class="panel p-4">
            <h2 class="h5">Expense categories</h2>
            <ul class="list-group list-group-flush">
                @forelse($categories as $category)
                    <li class="list-group-item px-0 d-flex justify-content-between align-items-center gap-2">
                        <span>{{ $category->name }}</span>
                        @if($canManage)
                            <span class="d-inline-flex gap-1 flex-shrink-0">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-expense-category-{{ $category->id }}" title="Edit" aria-label="Edit category">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-expense-category-{{ $category->id }}" title="Delete" aria-label="Delete category">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </span>
                        @endif
                    </li>
                @empty
                    <li class="list-group-item px-0 text-muted">No expense categories yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>

{{-- =========================================================
     ADD EXPENSE MODAL
     OUTSIDE THE TABLE
========================================================= --}}
@if($branches->isNotEmpty())
    <div class="modal fade"
         id="add-expense"
         tabindex="-1"
         aria-labelledby="add-expenseLabel"
         aria-hidden="true">

        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.expenses.store') }}">

                @csrf

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title"
                            id="add-expenseLabel">
                            <i class="fas fa-receipt me-2 text-primary"></i>
                            Record expense
                        </h5>
                        <small class="text-muted">
                            Log a business cost against a branch.
                        </small>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label" for="expenseBranch">
                                Branch <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <select class="form-select @error('branch_id') is-invalid @enderror"
                                    id="expenseBranch"
                                    name="branch_id"
                                    required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}"
                                        @selected((string) old('branch_id') === (string) $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="expenseCategory">Category</label>
                            <select class="form-select @error('expense_category_id') is-invalid @enderror"
                                    id="expenseCategory"
                                    name="expense_category_id">
                                <option value="">Uncategorised</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}"
                                        @selected((string) old('expense_category_id') === (string) $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('expense_category_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="expenseDescription">
                                Description <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="expenseDescription"
                                      name="description"
                                      rows="2"
                                      maxlength="1000"
                                      placeholder="e.g. Cooking gas refill"
                                      required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="expenseAmount">
                                Amount ({{ config('restaurant.currency') }}) <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input class="form-control @error('amount') is-invalid @enderror"
                                   type="number"
                                   id="expenseAmount"
                                   name="amount"
                                   step="0.01"
                                   min="0.01"
                                   max="100000000"
                                   value="{{ old('amount') }}"
                                   placeholder="e.g. 25000"
                                   required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="expenseDate">
                                Date <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <input class="form-control @error('expense_date') is-invalid @enderror"
                                   type="date"
                                   id="expenseDate"
                                   name="expense_date"
                                   max="{{ now()->toDateString() }}"
                                   value="{{ old('expense_date', now()->toDateString()) }}"
                                   required>
                            @error('expense_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="expensePayment">Payment method</label>
                            <select class="form-select @error('payment_method') is-invalid @enderror"
                                    id="expensePayment"
                                    name="payment_method">
                                <option value="">Not specified</option>
                                <option value="cash" @selected(old('payment_method') === 'cash')>Cash</option>
                                <option value="mobile_money" @selected(old('payment_method') === 'mobile_money')>Mobile money</option>
                                <option value="bank" @selected(old('payment_method') === 'bank')>Bank</option>
                                <option value="card" @selected(old('payment_method') === 'card')>Card</option>
                                <option value="other" @selected(old('payment_method') === 'other')>Other</option>
                            </select>
                            @error('payment_method')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="expenseReference">Reference (optional)</label>
                            <input class="form-control @error('reference') is-invalid @enderror"
                                   id="expenseReference"
                                   name="reference"
                                   value="{{ old('reference') }}"
                                   maxlength="255"
                                   placeholder="e.g. Receipt 1043">
                            @error('reference')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Save expense
                    </button>
                </div>

            </form>

        </div>
    </div>
@endif

{{-- =========================================================
     ADD EXPENSE CATEGORY MODAL
========================================================= --}}
<div class="modal fade"
     id="add-expense-category"
     tabindex="-1"
     aria-labelledby="add-expense-categoryLabel"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.expense-categories.store') }}">

            @csrf

            <div class="modal-header">
                <h5 class="modal-title"
                    id="add-expense-categoryLabel">
                    <i class="fas fa-tag me-2 text-primary"></i>
                    Add expense category
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <label class="form-label" for="expenseCategoryName">
                    Category name <span class="text-danger" aria-hidden="true">*</span>
                </label>
                <input class="form-control @error('name') is-invalid @enderror"
                       id="expenseCategoryName"
                       name="name"
                       value="{{ old('name') }}"
                       maxlength="255"
                       placeholder="e.g. Utilities"
                       required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Add category
                </button>
            </div>

        </form>

    </div>
</div>

@if($canManage)

    {{-- =========================================================
         EDIT / DELETE EXPENSE MODALS
         OUTSIDE THE TABLE
    ========================================================= --}}
    @foreach($expenses as $expense)
        @php
            $expenseBag = 'expense-' . $expense->id;
            $values = $errors->hasBag($expenseBag) ? [
                'branch_id' => old('branch_id'),
                'expense_category_id' => old('expense_category_id'),
                'description' => old('description'),
                'amount' => old('amount'),
                'expense_date' => old('expense_date'),
                'payment_method' => old('payment_method'),
                'reference' => old('reference'),
            ] : [
                'branch_id' => $expense->branch_id,
                'expense_category_id' => $expense->expense_category_id,
                'description' => $expense->description,
                'amount' => $expense->amount,
                'expense_date' => $expense->expense_date->toDateString(),
                'payment_method' => $expense->payment_method,
                'reference' => $expense->reference,
            ];
        @endphp

        @if($branches->isNotEmpty())
            <div class="modal fade"
                 id="edit-expense-{{ $expense->id }}"
                 tabindex="-1"
                 aria-labelledby="edit-expense-label-{{ $expense->id }}"
                 aria-hidden="true">

                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

                    <form class="modal-content"
                          method="POST"
                          action="{{ route('admin.expenses.update', $expense->id) }}">

                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title"
                                    id="edit-expense-label-{{ $expense->id }}">
                                    <i class="fas fa-pen me-2 text-primary"></i>
                                    Edit expense
                                </h5>
                                <small class="text-muted">
                                    Update the details of this expense.
                                </small>
                            </div>

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>

                        <div class="modal-body">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label" for="edit-expenseBranch-{{ $expense->id }}">
                                        Branch <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <select class="form-select @error('branch_id', $expenseBag) is-invalid @enderror"
                                            id="edit-expenseBranch-{{ $expense->id }}"
                                            name="branch_id"
                                            required>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}"
                                                @selected((string) $values['branch_id'] === (string) $branch->id)>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="edit-expenseCategory-{{ $expense->id }}">Category</label>
                                    <select class="form-select @error('expense_category_id', $expenseBag) is-invalid @enderror"
                                            id="edit-expenseCategory-{{ $expense->id }}"
                                            name="expense_category_id">
                                        <option value="">Uncategorised</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}"
                                                @selected((string) $values['expense_category_id'] === (string) $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('expense_category_id', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="edit-expenseDescription-{{ $expense->id }}">
                                        Description <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <textarea class="form-control @error('description', $expenseBag) is-invalid @enderror"
                                              id="edit-expenseDescription-{{ $expense->id }}"
                                              name="description"
                                              rows="2"
                                              maxlength="1000"
                                              placeholder="e.g. Cooking gas refill"
                                              required>{{ $values['description'] }}</textarea>
                                    @error('description', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="edit-expenseAmount-{{ $expense->id }}">
                                        Amount ({{ config('restaurant.currency') }}) <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <input class="form-control @error('amount', $expenseBag) is-invalid @enderror"
                                           type="number"
                                           id="edit-expenseAmount-{{ $expense->id }}"
                                           name="amount"
                                           step="0.01"
                                           min="0.01"
                                           max="100000000"
                                           value="{{ $values['amount'] }}"
                                           placeholder="e.g. 25000"
                                           required>
                                    @error('amount', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="edit-expenseDate-{{ $expense->id }}">
                                        Date <span class="text-danger" aria-hidden="true">*</span>
                                    </label>
                                    <input class="form-control @error('expense_date', $expenseBag) is-invalid @enderror"
                                           type="date"
                                           id="edit-expenseDate-{{ $expense->id }}"
                                           name="expense_date"
                                           max="{{ now()->toDateString() }}"
                                           value="{{ $values['expense_date'] }}"
                                           required>
                                    @error('expense_date', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label" for="edit-expensePayment-{{ $expense->id }}">Payment method</label>
                                    <select class="form-select @error('payment_method', $expenseBag) is-invalid @enderror"
                                            id="edit-expensePayment-{{ $expense->id }}"
                                            name="payment_method">
                                        <option value="">Not specified</option>
                                        <option value="cash" @selected($values['payment_method'] === 'cash')>Cash</option>
                                        <option value="mobile_money" @selected($values['payment_method'] === 'mobile_money')>Mobile money</option>
                                        <option value="bank" @selected($values['payment_method'] === 'bank')>Bank</option>
                                        <option value="card" @selected($values['payment_method'] === 'card')>Card</option>
                                        <option value="other" @selected($values['payment_method'] === 'other')>Other</option>
                                    </select>
                                    @error('payment_method', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="edit-expenseReference-{{ $expense->id }}">Reference (optional)</label>
                                    <input class="form-control @error('reference', $expenseBag) is-invalid @enderror"
                                           id="edit-expenseReference-{{ $expense->id }}"
                                           name="reference"
                                           value="{{ $values['reference'] }}"
                                           maxlength="255"
                                           placeholder="e.g. Receipt 1043">
                                    @error('reference', $expenseBag)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button"
                                    class="btn btn-light"
                                    data-bs-dismiss="modal">
                                <i class="fas fa-xmark me-1"></i>
                                Cancel
                            </button>

                            <button type="submit"
                                    class="btn btn-primary">
                                <i class="fas fa-floppy-disk me-1"></i>
                                Save changes
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        @endif

        {{-- DELETE EXPENSE --}}
        <div class="modal fade"
             id="delete-expense-{{ $expense->id }}"
             tabindex="-1"
             aria-labelledby="delete-expense-label-{{ $expense->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.expenses.destroy', $expense->id) }}">

                    @csrf
                    @method('DELETE')

                    <div class="modal-header border-0 pb-0">

                        <h5 class="modal-title"
                            id="delete-expense-label-{{ $expense->id }}">
                            <i class="fas fa-trash me-2 text-danger"></i>
                            Delete expense
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                    </div>

                    <div class="modal-body text-center py-4">

                        <h5 class="mb-2">
                            Delete {{ config('restaurant.currency') }} {{ number_format((float) $expense->amount, 2) }}?
                        </h5>

                        <p class="text-muted mb-0">
                            {{ $expense->description }} on {{ $expense->expense_date->format('Y-m-d') }} will be hidden from the expense history. The record is kept for accounting.
                        </p>

                    </div>

                    <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                        <button type="button"
                                class="btn btn-light px-4"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-danger px-4">
                            <i class="fas fa-trash me-1"></i>
                            Delete
                        </button>

                    </div>

                </form>

            </div>
        </div>
    @endforeach

    {{-- =========================================================
         EDIT / DELETE EXPENSE CATEGORY MODALS
    ========================================================= --}}
    @foreach($categories as $category)
        @php($categoryBag = 'expense-category-' . $category->id)

        <div class="modal fade"
             id="edit-expense-category-{{ $category->id }}"
             tabindex="-1"
             aria-labelledby="edit-expense-category-label-{{ $category->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.expense-categories.update', $category->id) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title"
                                id="edit-expense-category-label-{{ $category->id }}">
                                <i class="fas fa-pen me-2 text-primary"></i>
                                Edit expense category
                            </h5>
                            <small class="text-muted">
                                {{ $category->name }}
                            </small>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <label class="form-label" for="edit-expense-category-name-{{ $category->id }}">
                            Category name <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input class="form-control @error('name', $categoryBag) is-invalid @enderror"
                               id="edit-expense-category-name-{{ $category->id }}"
                               name="name"
                               value="{{ $errors->hasBag($categoryBag) ? old('name') : $category->name }}"
                               maxlength="255"
                               placeholder="e.g. Utilities"
                               required>
                        @error('name', $categoryBag)
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="modal-footer">
                        <button type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save changes
                        </button>
                    </div>

                </form>

            </div>
        </div>

        {{-- DELETE EXPENSE CATEGORY --}}
        <div class="modal fade"
             id="delete-expense-category-{{ $category->id }}"
             tabindex="-1"
             aria-labelledby="delete-expense-category-label-{{ $category->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.expense-categories.destroy', $category->id) }}">

                    @csrf
                    @method('DELETE')

                    <div class="modal-header border-0 pb-0">

                        <h5 class="modal-title"
                            id="delete-expense-category-label-{{ $category->id }}">
                            <i class="fas fa-trash me-2 text-danger"></i>
                            Delete expense category
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                    </div>

                    <div class="modal-body text-center py-4">

                        <h5 class="mb-2">
                            Delete {{ $category->name }}?
                        </h5>

                        <p class="text-muted mb-0">
                            Categories used by any expense cannot be deleted. Rename them instead.
                        </p>

                    </div>

                    <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                        <button type="button"
                                class="btn btn-light px-4"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-danger px-4">
                            <i class="fas fa-trash me-1"></i>
                            Delete
                        </button>

                    </div>

                </form>

            </div>
        </div>
    @endforeach

@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.bootstrap) {
        return;
    }
    @if($branches->isNotEmpty() && $errors->hasAny(['branch_id', 'expense_category_id', 'description', 'amount', 'expense_date', 'payment_method', 'reference']))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('add-expense')).show();
    @endif
    @if($errors->has('name'))
        bootstrap.Modal.getOrCreateInstance(document.getElementById('add-expense-category')).show();
    @endif
    @if($canManage)
        @foreach($expenses as $expense)
            @if($branches->isNotEmpty() && $errors->hasBag('expense-' . $expense->id))
                bootstrap.Modal.getOrCreateInstance(document.getElementById('edit-expense-{{ $expense->id }}')).show();
            @endif
        @endforeach
        @foreach($categories as $category)
            @if($errors->hasBag('expense-category-' . $category->id))
                bootstrap.Modal.getOrCreateInstance(document.getElementById('edit-expense-category-{{ $category->id }}')).show();
            @endif
        @endforeach
    @endif
});
</script>
@endpush
@endsection
