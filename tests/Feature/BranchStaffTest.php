<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class BranchStaffTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_branches_are_managed_by_restaurant_wide_staff_and_one_stays_active(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant, $foreignBranch] = $this->restaurantWithBranch('B');
        $branchManager = $this->staff($restaurant, $branch);
        $this->grant($branchManager, 'settings.branches.manage');
        $this->actingAs($branchManager)->get(route('admin.settings.branches'))->assertForbidden();

        $owner = $this->staff($restaurant, null);
        $this->grant($owner, 'settings.branches.manage');
        $this->actingAs($owner)->get(route('admin.settings.branches'))->assertOk()->assertSee('Branch A')->assertDontSee('Branch B');

        $this->post(route('admin.settings.branches.store'), ['name' => 'Kololo', 'code' => 'BR-B'])->assertSessionHasErrors('code');
        $this->post(route('admin.settings.branches.store'), ['name' => 'Kololo', 'code' => 'KOL-1', 'phone' => '0700'])->assertSessionHasNoErrors();
        $second = Branch::query()->where('code', 'KOL-1')->firstOrFail();
        $this->assertSame($restaurant->id, $second->restaurant_id);

        $this->put(route('admin.settings.branches.update', $foreignBranch->id), ['name' => 'Taken', 'code' => 'BR-B'])->assertNotFound();

        $this->patch(route('admin.settings.branches.status', $second->id), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('admin.settings.branches.status', $branch->id), ['is_active' => 0])->assertSessionHasErrors('branch');
        $this->assertTrue($branch->fresh()->is_active);
        $this->assertFalse($second->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'settings.branch.deactivated', 'restaurant_id' => $restaurant->id]);
    }

    public function test_staff_accounts_are_created_scoped_and_deactivation_blocks_sign_in(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant, $foreignBranch] = $this->restaurantWithBranch('B');
        $sibling = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Sibling', 'code' => 'SIB']);
        $manager = $this->staff($restaurant, $branch);
        $siblingCook = $this->staff($restaurant, $sibling, ['name' => 'Sibling Cook']);
        $foreigner = $this->staff($foreignRestaurant, $foreignBranch, ['name' => 'Foreign Waiter']);
        $this->actingAs($manager)->get(route('admin.staff.index'))->assertForbidden();
        $this->grant($manager, 'employees.staff.manage');

        $this->get(route('admin.staff.index'))->assertOk()->assertDontSee('Sibling Cook')->assertDontSee('Foreign Waiter');

        $payload = ['name' => 'New Waiter', 'email' => 'waiter@example.com', 'password' => 'correct-horse-42', 'password_confirmation' => 'correct-horse-42'];
        $this->post(route('admin.staff.store'), [...$payload, 'branch_id' => $sibling->id])->assertSessionHasErrors('branch_id');
        $this->post(route('admin.staff.store'), [...$payload, 'password' => 'short', 'password_confirmation' => 'short', 'branch_id' => $branch->id])
            ->assertSessionHasErrors('password');
        $this->post(route('admin.staff.store'), [...$payload, 'branch_id' => $branch->id])->assertSessionHasNoErrors();

        $waiter = User::query()->where('email', 'waiter@example.com')->firstOrFail();
        $this->assertSame([$restaurant->id, $branch->id, true], [$waiter->restaurant_id, $waiter->branch_id, $waiter->is_active]);
        $this->assertTrue(Hash::check('correct-horse-42', $waiter->password));
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $waiter->id]);

        $this->put(route('admin.staff.update', $siblingCook->id), ['name' => 'X', 'email' => 'x@example.com', 'branch_id' => $branch->id])->assertNotFound();
        $this->patch(route('admin.staff.status', $foreigner->id), ['is_active' => 0])->assertNotFound();
        $this->patch(route('admin.staff.status', $manager->id), ['is_active' => 0])->assertStatus(422);

        $this->patch(route('admin.staff.status', $waiter->id), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($waiter->fresh()->is_active);
        $this->assertNotNull($waiter->fresh()->deactivated_at);

        auth()->logout();
        $this->post(route('admin.login.submit'), ['email' => 'waiter@example.com', 'password' => 'correct-horse-42'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // A session that was already signed in is ended on the next request.
        $this->actingAs($waiter->fresh())->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_wildcard_administrators_cannot_be_changed_from_the_staff_screen(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $manager = $this->staff($restaurant, null);
        $this->grant($manager, 'employees.staff.manage');
        $owner = $this->staff($restaurant, null, ['name' => 'Owner']);
        $this->grant($owner, 'admin');

        $this->actingAs($manager)->patch(route('admin.staff.status', $owner->id), ['is_active' => 0])->assertStatus(422);
        $this->put(route('admin.staff.password', $owner->id), ['password' => 'another-pass-99', 'password_confirmation' => 'another-pass-99'])
            ->assertStatus(422);
        $this->assertTrue($owner->fresh()->is_active);
    }
}
