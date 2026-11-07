<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PermissionManagementController;
use App\Support\StaffRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class StaffRolesTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_every_role_uses_only_known_permissions(): void
    {
        foreach (StaffRoles::all() as $key => $role) {
            $unknown = array_diff($role['abilities'], array_keys(PermissionManagementController::ABILITIES));
            $this->assertSame([], array_values($unknown), "Role {$key} references unknown permissions.");
        }
    }

    public function test_roles_page_lists_roles_and_staff_assignments(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        $manager = $this->staff($restaurant, null, ['name' => 'Owner']);
        $this->grant($manager, 'settings.roles-permissions.manage');
        $cashier = $this->staff($restaurant, null, ['name' => 'Cathy Cashier']);
        $this->grant($cashier, ...StaffRoles::abilities('cashier'));

        $this->actingAs($manager)->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('Manager')
            ->assertSee('Cashier')
            ->assertSee('Kitchen staff')
            ->assertSee('Cathy Cashier')
            ->assertSee('Who has which role');

        $this->get(route('admin.settings.roles-permissions'))
            ->assertOk()
            ->assertSee('Start from a role');
    }

    public function test_assigning_a_role_replaces_permissions(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        $manager = $this->staff($restaurant, null);
        $this->grant($manager, 'settings.roles-permissions.manage');
        $member = $this->staff($restaurant, null);
        $this->grant($member, 'inventory.manage');

        $this->actingAs($manager)->post(route('admin.roles.assign', 'waiter'), ['user_id' => $member->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $granted = DB::table('user_permissions')->where('user_id', $member->id)->pluck('ability')->all();
        $this->assertSame('waiter', StaffRoles::match($granted));
        $this->assertNotContains('inventory.manage', $granted);
    }

    public function test_role_assignment_is_tenant_scoped_and_protected(): void
    {
        [$restaurant] = $this->restaurantWithBranch('A');
        [$other] = $this->restaurantWithBranch('B');
        $manager = $this->staff($restaurant, null);
        $this->grant($manager, 'settings.roles-permissions.manage');
        $foreign = $this->staff($other, null);
        $wildcard = $this->staff($restaurant, null);
        $this->grant($wildcard, 'admin');
        $plain = $this->staff($restaurant, null);

        $this->actingAs($manager)->post(route('admin.roles.assign', 'manager'), ['user_id' => $foreign->id])
            ->assertSessionHasErrors('user_id');
        $this->post(route('admin.roles.assign', 'manager'), ['user_id' => $wildcard->id])->assertStatus(422);
        $this->post(route('admin.roles.assign', 'not-a-role'), ['user_id' => $plain->id])->assertNotFound();
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $foreign->id)->count());

        $this->actingAs($plain)->get(route('admin.roles.index'))->assertForbidden();
        $this->post(route('admin.roles.assign', 'manager'), ['user_id' => $plain->id])->assertForbidden();
    }
}
