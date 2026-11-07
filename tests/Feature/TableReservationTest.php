<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Floor;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TableReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_floors_and_tables_are_branch_scoped_and_require_manage_permission(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $foreignFloor = Floor::create(['branch_id' => $foreignBranch->id, 'name' => 'Foreign terrace']);
        RestaurantTable::create(['branch_id' => $foreignBranch->id, 'name' => 'Foreign T1', 'code' => 'F-T1', 'capacity' => 4]);
        $user = $this->staff($restaurant, $branch);

        $this->actingAs($user)->get(route('admin.tables.index'))->assertForbidden();
        $this->grant($user, 'tables.view');
        $this->get(route('admin.tables.index'))->assertOk()->assertDontSee('Foreign T1')->assertDontSee('Create table');
        $this->get(route('admin.floors.index'))->assertOk()->assertDontSee('Foreign terrace');
        $this->post(route('admin.floors.store'), ['branch_id' => $branch->id, 'name' => 'Hall'])->assertForbidden();

        $this->grant($user, 'tables.manage');
        $this->post(route('admin.floors.store'), ['branch_id' => $foreignBranch->id, 'name' => 'Sneaky'])
            ->assertSessionHasErrors('branch_id');
        $this->post(route('admin.floors.store'), ['branch_id' => $branch->id, 'name' => 'Hall'])->assertRedirect();
        $floor = Floor::query()->where('name', 'Hall')->firstOrFail();

        $this->post(route('admin.tables.store'), [
            'branch_id' => $branch->id, 'floor_id' => $foreignFloor->id, 'name' => 'T1', 'capacity' => 4,
        ])->assertSessionHasErrors('floor_id');
        $this->post(route('admin.tables.store'), [
            'branch_id' => $branch->id, 'floor_id' => $floor->id, 'name' => 'T1', 'capacity' => 4,
        ])->assertRedirect();
        $this->post(route('admin.tables.store'), [
            'branch_id' => $branch->id, 'name' => 'T1', 'capacity' => 2,
        ])->assertSessionHasErrors('name');
        $this->assertDatabaseHas('restaurant_tables', [
            'branch_id' => $branch->id, 'floor_id' => $floor->id, 'name' => 'T1', 'status' => 'available',
        ]);

        $this->put(route('admin.floors.update', $foreignFloor->id), ['name' => 'Taken over'])->assertNotFound();
        $this->delete(route('admin.floors.destroy', $floor->id))->assertSessionHasErrors('floor');
        $this->assertDatabaseHas('floors', ['id' => $floor->id]);

        // Validation errors are rendered by the shared layout.
        $this->from(route('admin.floors.index'))
            ->followingRedirects()
            ->delete(route('admin.floors.destroy', $floor->id))
            ->assertSee('Move this section');
    }

    public function test_table_status_can_only_be_set_to_manual_statuses_on_owned_tables(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $table = $this->table($branch, 'T1');
        $foreignTable = $this->table($foreignBranch, 'F1');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'tables.manage');
        $this->actingAs($user);

        $this->patch(route('admin.tables.status', $table->id), ['status' => 'cleaning'])->assertRedirect();
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'status' => 'cleaning']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'tables.status.updated', 'subject_id' => $table->id]);

        $this->patch(route('admin.tables.status', $table->id), ['status' => 'reserved'])->assertSessionHasErrors('status');
        $this->patch(route('admin.tables.status', $foreignTable->id), ['status' => 'inactive'])->assertNotFound();
        $this->assertDatabaseHas('restaurant_tables', ['id' => $foreignTable->id, 'status' => 'available']);
    }

    public function test_reservations_reject_double_booking_capacity_foreign_tables_and_past_times(): void
    {
        config(['restaurant.reservation_duration_minutes' => 120]);
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $table = $this->table($branch, 'T1', 4);
        $foreignTable = $this->table($foreignBranch, 'F1');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'reservations.manage');
        $this->actingAs($user);
        $at = now()->addDay()->setTime(19, 0);

        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, $at))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['restaurant_table_id' => $table->id, 'status' => 'pending', 'guest_name' => 'Amina']);

        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, $at->copy()->addMinutes(90)))
            ->assertSessionHasErrors('restaurant_table_id');
        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, $at->copy()->addHours(3), ['guest_count' => 6]))
            ->assertSessionHasErrors('guest_count');
        $this->post(route('admin.reservations.store'), $this->booking($branch, $foreignTable, $at->copy()->addHours(3)))
            ->assertSessionHasErrors('restaurant_table_id');
        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, now()->subHour()))
            ->assertSessionHasErrors('reserved_at');
        $this->post(route('admin.reservations.store'), $this->booking($foreignBranch, null, $at))
            ->assertSessionHasErrors('branch_id');

        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, $at->copy()->addHours(2)))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('reservations', 2);

        // A cancelled booking frees the slot.
        $first = Reservation::query()->orderBy('id')->firstOrFail();
        $this->patch(route('admin.reservations.status', $first->id), ['status' => 'cancelled'])->assertRedirect();
        $this->post(route('admin.reservations.store'), $this->booking($branch, $table, $at->copy()->subMinutes(30)))
            ->assertSessionHasNoErrors();
    }

    public function test_seating_and_completing_a_reservation_drives_table_status(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $table = $this->table($branch, 'T1', 4);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'reservations.manage');
        $this->actingAs($user);
        $unassigned = $this->reservation($branch, null, now()->addHour());
        $reservation = $this->reservation($branch, $table, now()->addMinutes(10));

        $this->patch(route('admin.reservations.status', $unassigned->id), ['status' => 'seated'])
            ->assertSessionHasErrors('status');
        $this->patch(route('admin.reservations.status', $reservation->id), ['status' => 'completed'])
            ->assertUnprocessable();

        $table->update(['status' => 'cleaning']);
        $this->patch(route('admin.reservations.status', $reservation->id), ['status' => 'seated'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);

        $table->update(['status' => 'available']);
        $this->patch(route('admin.reservations.status', $reservation->id), ['status' => 'seated'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'status' => 'occupied']);

        $this->put(route('admin.reservations.update', $reservation->id), [
            'guest_name' => 'Changed', 'phone' => '+256700000000', 'guest_count' => 2,
            'reserved_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('reservation');

        $this->patch(route('admin.reservations.status', $reservation->id), ['status' => 'completed'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'completed']);
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'status' => 'cleaning']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'reservations.status.completed', 'subject_id' => $reservation->id]);
    }

    public function test_reservations_are_tenant_scoped_and_editable_without_self_conflict(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $table = $this->table($branch, 'T1', 4);
        $at = now()->addDay()->setTime(12, 0);
        $reservation = $this->reservation($branch, $table, $at);
        $foreign = $this->reservation($foreignBranch, null, $at, 'Foreign guest');
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'reservations.view');
        $this->actingAs($user);

        $this->get(route('admin.reservations.index', ['date' => $at->toDateString()]))
            ->assertOk()->assertSee($reservation->guest_name)->assertDontSee('Foreign guest')->assertDontSee('Create reservation');
        $this->patch(route('admin.reservations.status', $reservation->id), ['status' => 'cancelled'])->assertForbidden();

        $this->grant($user, 'reservations.manage');
        $this->patch(route('admin.reservations.status', $foreign->id), ['status' => 'cancelled'])->assertNotFound();
        $this->put(route('admin.reservations.update', $reservation->id), [
            'guest_name' => 'Amina K', 'phone' => '+256700000000', 'guest_count' => 3,
            'reserved_at' => $at->copy()->addMinutes(30)->format('Y-m-d H:i'),
            'restaurant_table_id' => $table->id,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'guest_name' => 'Amina K', 'guest_count' => 3]);
        $this->assertDatabaseHas('reservations', ['id' => $foreign->id, 'status' => 'confirmed']);
    }

    private function booking(Branch $branch, ?RestaurantTable $table, $at, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $branch->id,
            'restaurant_table_id' => $table?->id,
            'guest_name' => 'Amina',
            'phone' => '+256 700 000000',
            'guest_count' => 2,
            'reserved_at' => $at->format('Y-m-d H:i'),
        ], $overrides);
    }

    private function reservation(Branch $branch, ?RestaurantTable $table, $at, string $guest = 'Booked guest'): Reservation
    {
        return Reservation::create([
            'branch_id' => $branch->id,
            'restaurant_table_id' => $table?->id,
            'reservation_number' => 'RSV-' . uniqid(),
            'guest_name' => $guest,
            'phone' => '+256700000000',
            'guest_count' => 2,
            'reserved_at' => $at,
            'status' => 'confirmed',
        ]);
    }

    private function table(Branch $branch, string $name, int $capacity = 4): RestaurantTable
    {
        return RestaurantTable::create([
            'branch_id' => $branch->id, 'name' => $name, 'code' => $branch->code . '-' . $name, 'capacity' => $capacity,
        ]);
    }

    private function restaurantWithBranch(string $suffix): array
    {
        $restaurant = Restaurant::create(['name' => 'Restaurant ' . $suffix, 'code' => 'REST-' . $suffix, 'currency' => 'USD']);
        $branch = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Branch ' . $suffix, 'code' => 'BR-' . $suffix]);

        return [$restaurant, $branch];
    }

    private function staff(Restaurant $restaurant, Branch $branch): User
    {
        $user = User::factory()->create();
        $user->forceFill(['restaurant_id' => $restaurant->id, 'branch_id' => $branch->id])->save();

        return $user;
    }

    private function grant(User $user, string $ability): void
    {
        DB::table('user_permissions')->insert([
            'user_id' => $user->id, 'ability' => $ability, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
