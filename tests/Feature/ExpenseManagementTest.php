<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_update_changes_an_expense(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'finance.expenses.manage');
        $category = ExpenseCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Utilities']);
        $expense = $this->expenseFor($branch, $user, 'Gas refill', 100);

        $this->actingAs($user)->put(route('admin.expenses.update', $expense), [
            'branch_id' => $branch->id,
            'expense_category_id' => $category->id,
            'reference' => 'RCPT-2',
            'description' => 'Corrected gas refill',
            'amount' => 75.25,
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ])->assertRedirect()->assertSessionHas('success', 'Expense updated.');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id, 'description' => 'Corrected gas refill', 'amount' => '75.25',
            'expense_category_id' => $category->id, 'payment_method' => 'cash', 'reference' => 'RCPT-2',
        ]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'finance.expense.updated', 'subject_id' => $expense->id]);
        $this->actingAs($user)->get(route('admin.expenses.index'))->assertOk()->assertSee('Corrected gas refill');
    }

    public function test_soft_deleted_expense_is_hidden_from_the_list_and_totals(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'finance.expenses.manage');
        $expense = $this->expenseFor($branch, $user, 'Generator servicing', 300);

        $this->actingAs($user)->get(route('admin.expenses.index'))->assertOk()->assertSee('Generator servicing');

        $this->delete(route('admin.expenses.destroy', $expense))
            ->assertRedirect()
            ->assertSessionHas('success', 'Expense deleted.');

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
        $this->assertDatabaseHas('activity_logs', ['action' => 'finance.expense.deleted']);
        $this->get(route('admin.expenses.index'))->assertOk()
            ->assertDontSee('Generator servicing')
            ->assertSee('No expenses recorded yet.');
    }

    public function test_expenses_from_another_restaurant_or_branch_cannot_be_updated_or_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $owner = $this->staff($restaurant, $branch);
        $this->grant($owner, 'finance.expenses.manage');
        $expense = $this->expenseFor($branch, $owner, 'Local rent', 500);

        [$foreignRestaurant] = $this->restaurantWithBranch('C');
        $foreignUser = $this->staff($foreignRestaurant, null);
        $this->grant($foreignUser, 'finance.expenses.manage');
        $foreignExpense = $this->expenseFor($foreignBranch, $foreignUser, 'Foreign rent', 500);

        $payload = [
            'branch_id' => $branch->id,
            'description' => 'Hijacked',
            'amount' => 1,
            'expense_date' => now()->toDateString(),
        ];
        $this->actingAs($foreignUser)->put(route('admin.expenses.update', $expense), $payload)->assertNotFound();
        $this->actingAs($foreignUser)->delete(route('admin.expenses.destroy', $expense))->assertNotFound();
        $this->actingAs($owner)->put(route('admin.expenses.update', $foreignExpense), $payload)->assertNotFound();
        $this->actingAs($owner)->delete(route('admin.expenses.destroy', $foreignExpense))->assertNotFound();

        $otherBranchStaff = $this->staff($restaurant, $this->branchFor($restaurant, 'Second'));
        $this->grant($otherBranchStaff, 'finance.expenses.manage');
        $this->actingAs($otherBranchStaff)->put(route('admin.expenses.update', $expense), $payload)->assertNotFound();
        $this->actingAs($otherBranchStaff)->delete(route('admin.expenses.destroy', $expense))->assertNotFound();

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'description' => 'Local rent', 'deleted_at' => null]);
        $this->assertDatabaseHas('expenses', ['id' => $foreignExpense->id, 'description' => 'Foreign rent', 'deleted_at' => null]);
    }

    public function test_category_with_expenses_cannot_be_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'finance.expenses.manage');
        $category = ExpenseCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Repairs']);
        $expense = $this->expenseFor($branch, $user, 'Fridge repair', 80);
        $expense->update(['expense_category_id' => $category->id]);

        $this->actingAs($user)->delete(route('admin.expense-categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('error', 'This category is used by 1 expense(s) and cannot be deleted. Rename it instead.');
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);

        $expense->delete();
        $this->actingAs($user)->delete(route('admin.expense-categories.destroy', $category))
            ->assertSessionHas('error', 'This category is used by 1 expense(s) and cannot be deleted. Rename it instead.');
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_unused_category_can_be_deleted(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'finance.expenses.manage');
        $category = ExpenseCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Unused']);

        $this->actingAs($user)->delete(route('admin.expense-categories.destroy', $category))
            ->assertRedirect()
            ->assertSessionHas('success', 'Expense category deleted.');
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_category_cannot_be_renamed_to_an_existing_name(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'finance.expenses.manage');
        ExpenseCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Utilities']);
        $rent = ExpenseCategory::create(['restaurant_id' => $restaurant->id, 'name' => 'Rent']);

        $this->actingAs($user)->put(route('admin.expense-categories.update', $rent), ['name' => 'Utilities'])
            ->assertSessionHasErrors('name', null, 'expense-category-' . $rent->id);
        $this->assertDatabaseHas('expense_categories', ['id' => $rent->id, 'name' => 'Rent']);

        $this->put(route('admin.expense-categories.update', $rent), ['name' => 'Rent'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Expense category updated.');
        $this->put(route('admin.expense-categories.update', $rent), ['name' => 'Housing'])
            ->assertRedirect();
        $this->assertDatabaseHas('expense_categories', ['id' => $rent->id, 'name' => 'Housing']);
    }

    private function expenseFor(Branch $branch, User $user, string $description, float $amount): Expense
    {
        return Expense::create([
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'description' => $description,
            'amount' => $amount,
            'payment_method' => 'bank',
            'expense_date' => now()->toDateString(),
        ]);
    }

    private function branchFor(Restaurant $restaurant, string $suffix): Branch
    {
        return Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Branch ' . $suffix, 'code' => 'BR-' . $suffix]);
    }
}
