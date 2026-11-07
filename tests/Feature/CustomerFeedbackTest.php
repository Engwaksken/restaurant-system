<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CustomerFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_directory_is_tenant_scoped_and_requires_manage_to_edit(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina Own', 'phone' => '0700000001']);
        $foreign = Customer::create(['restaurant_id' => $foreignRestaurant->id, 'name' => 'Foreign Person', 'phone' => '0700000009']);
        $user = $this->staff($restaurant, $branch);

        $this->actingAs($user)->get(route('admin.customers.index'))->assertForbidden();
        $this->grant($user, 'customers.view');
        $this->get(route('admin.customers.index'))->assertOk()->assertSee('Amina Own')->assertDontSee('Foreign Person')->assertDontSee('Create customer');
        $this->get(route('admin.customers.index', ['search' => '0001']))->assertOk()->assertSee('Amina Own');
        $this->get(route('admin.customers.show', $foreign->id))->assertNotFound();
        $this->post(route('admin.customers.store'), ['name' => 'X', 'phone' => '0700000002'])->assertForbidden();

        $this->grant($user, 'customers.manage');
        $this->post(route('admin.customers.store'), ['name' => 'Dup', 'phone' => '0700000001'])->assertSessionHasErrors('phone');
        // The same phone may exist in another restaurant.
        $this->post(route('admin.customers.store'), ['name' => 'Brian', 'phone' => '0700000009', 'email' => 'brian@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['restaurant_id' => $restaurant->id, 'name' => 'Brian', 'phone' => '0700000009']);

        $this->put(route('admin.customers.update', $customer->id), ['name' => 'Amina Updated', 'phone' => '0700000001', 'notes' => 'Nut allergy'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Amina Updated', 'notes' => 'Nut allergy']);
        $this->put(route('admin.customers.update', $foreign->id), ['name' => 'Hijack', 'phone' => '0700000099'])->assertNotFound();
        $this->assertDatabaseHas('customers', ['id' => $foreign->id, 'name' => 'Foreign Person']);
    }

    public function test_customer_profile_only_counts_orders_in_visible_branches(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $sibling = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Sibling', 'code' => 'BR-A2']);
        $customer = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina', 'phone' => '0700000001']);
        $this->order($branch, 'ORD-VISIBLE', 'completed', 30, $customer);
        $this->order($branch, 'ORD-VOID', 'cancelled', 1000, $customer);
        $this->order($sibling, 'ORD-SIBLING', 'completed', 500, $customer);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'customers.view');

        $this->actingAs($user)->get(route('admin.customers.show', $customer->id))
            ->assertOk()
            ->assertSee('ORD-VISIBLE')
            ->assertSee('ORD-VOID')
            ->assertDontSee('ORD-SIBLING')
            ->assertSee('30.00')
            ->assertDontSee('1,030.00')
            ->assertDontSee('530.00');
    }

    public function test_customer_can_rate_a_completed_order_once_with_its_tracking_token(): void
    {
        // This test exercises several submissions; the endpoint's rate limit is not under test.
        $this->withoutMiddleware(ThrottleRequests::class);
        [, $branch] = $this->restaurantWithBranch('A');
        $customer = Customer::create(['restaurant_id' => $branch->restaurant_id, 'name' => 'Amina', 'phone' => '0700000001']);
        [$pending, $pendingToken] = $this->trackedOrder($branch, 'WEB-PENDING', 'pending', $customer);
        [$done, $token] = $this->trackedOrder($branch, 'WEB-DONE', 'completed', $customer);

        $this->get(route('order.track', $pendingToken))->assertOk()->assertDontSee('How was your order?');
        $this->post(route('order.feedback', $pendingToken), ['rating' => 5])->assertSessionHas('error');
        $this->assertDatabaseCount('order_feedback', 0);

        $this->get(route('order.track', $token))->assertOk()->assertSee('How was your order?');
        $this->post(route('order.feedback', $token), ['rating' => 9])->assertSessionHasErrors('rating');
        $this->post(route('order.feedback', str_repeat('a', 64)), ['rating' => 5])->assertNotFound();

        $this->post(route('order.feedback', $token), ['rating' => 4, 'comment' => '  Great chips  '])
            ->assertRedirect(route('order.track', $token))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('order_feedback', [
            'order_id' => $done->id, 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'rating' => 4, 'comment' => 'Great chips',
        ]);

        $this->post(route('order.feedback', $token), ['rating' => 1])->assertSessionHas('error');
        $this->assertDatabaseCount('order_feedback', 1);
        $this->get(route('order.track', $token))->assertSee('you rated this order 4/5')->assertDontSee('How was your order?');
    }

    public function test_feedback_report_is_branch_scoped_and_summarised(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $sibling = Branch::create(['restaurant_id' => $restaurant->id, 'name' => 'Sibling', 'code' => 'BR-A2']);
        [, $foreignBranch] = $this->restaurantWithBranch('B');
        $this->feedback($branch, 'ORD-F1', 5, 'Lovely service');
        $this->feedback($branch, 'ORD-F2', 2, 'Cold food');
        $this->feedback($sibling, 'ORD-F3', 1, 'Sibling complaint');
        $this->feedback($foreignBranch, 'ORD-F4', 1, 'Foreign complaint');
        $user = $this->staff($restaurant, $branch);

        $this->actingAs($user)->get(route('admin.feedback.index'))->assertForbidden();
        $this->grant($user, 'customers.feedback.view');
        $this->get(route('admin.feedback.index'))
            ->assertOk()
            ->assertSee('Lovely service')->assertSee('Cold food')
            ->assertDontSee('Sibling complaint')->assertDontSee('Foreign complaint')
            ->assertSee('3.5 / 5');
        $this->get(route('admin.feedback.index', ['rating' => 2]))
            ->assertOk()->assertSee('Cold food')->assertDontSee('Lovely service');
    }

    public function test_reservations_link_to_the_restaurant_customer_by_phone(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant] = $this->restaurantWithBranch('B');
        $existing = Customer::create(['restaurant_id' => $restaurant->id, 'name' => 'Amina', 'phone' => '0700000001']);
        Customer::create(['restaurant_id' => $foreignRestaurant->id, 'name' => 'Other', 'phone' => '0700000002']);
        $user = $this->staff($restaurant, $branch);
        $this->grant($user, 'reservations.manage');
        $at = now()->addDay()->setTime(19, 0)->format('Y-m-d H:i');
        $this->actingAs($user);

        $this->post(route('admin.reservations.store'), [
            'branch_id' => $branch->id, 'guest_name' => 'Amina K', 'phone' => '0700000001', 'guest_count' => 2, 'reserved_at' => $at,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['guest_name' => 'Amina K', 'customer_id' => $existing->id]);

        $this->post(route('admin.reservations.store'), [
            'branch_id' => $branch->id, 'guest_name' => 'New Guest', 'phone' => '0700000002', 'guest_count' => 2, 'reserved_at' => $at,
        ])->assertSessionHasNoErrors();
        $created = Customer::query()->where('restaurant_id', $restaurant->id)->where('phone', '0700000002')->firstOrFail();
        $this->assertSame('New Guest', $created->name);
        $reservation = Reservation::query()->where('guest_name', 'New Guest')->firstOrFail();
        $this->assertSame($created->id, $reservation->customer_id);

        $this->put(route('admin.reservations.update', $reservation->id), [
            'guest_name' => 'New Guest', 'phone' => '0700000001', 'guest_count' => 2, 'reserved_at' => $at,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'customer_id' => $existing->id]);
    }

    private function feedback(Branch $branch, string $number, int $rating, string $comment): void
    {
        $order = $this->order($branch, $number, 'completed', 10);
        OrderFeedback::create(['branch_id' => $branch->id, 'order_id' => $order->id, 'rating' => $rating, 'comment' => $comment]);
    }

    private function trackedOrder(Branch $branch, string $number, string $status, Customer $customer): array
    {
        $token = bin2hex(random_bytes(32));
        $order = $this->order($branch, $number, $status, 20, $customer);
        $order->forceFill(['tracking_token_hash' => hash('sha256', $token)])->save();

        return [$order, $token];
    }

    private function order(Branch $branch, string $number, string $status, float $total, ?Customer $customer = null): Order
    {
        return Order::create([
            'branch_id' => $branch->id, 'customer_id' => $customer?->id, 'order_number' => $number,
            'order_type' => 'online', 'status' => $status, 'total' => $total, 'balance' => 0, 'ordered_at' => now(),
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
