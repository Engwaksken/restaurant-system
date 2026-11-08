<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\PayrollRun;
use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Concerns\BuildsTenants;
use Tests\TestCase;

class WorkforcePayrollTest extends TestCase
{
    use BuildsTenants;
    use RefreshDatabase;

    public function test_staff_clock_in_and_out_once_at_a_time(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $cook = $this->staff($restaurant, $branch);
        $this->actingAs($cook);

        $this->post(route('admin.attendance.clock'), ['action' => 'out'])->assertSessionHasErrors('action');
        $this->post(route('admin.attendance.clock'), ['action' => 'in'])->assertSessionHasNoErrors();
        $this->post(route('admin.attendance.clock'), ['action' => 'in'])->assertSessionHasErrors('action');
        $this->travel(3)->hours();
        $this->post(route('admin.attendance.clock'), ['action' => 'out'])->assertSessionHasNoErrors();

        $record = Attendance::query()->firstOrFail();
        $this->assertSame([$branch->id, 3.0], [$record->branch_id, $record->hours()]);
        $this->get(route('admin.attendance.index'))->assertOk()->assertSee('3.00')->assertDontSee('Record missed attendance');
    }

    public function test_shifts_reject_overlaps_and_other_branches(): void
    {
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        [$foreignRestaurant, $foreignBranch] = $this->restaurantWithBranch('B');
        $manager = $this->staff($restaurant, $branch);
        $cook = $this->staff($restaurant, $branch, ['name' => 'Cook Joy']);
        $outsider = $this->staff($foreignRestaurant, $foreignBranch);
        $this->grant($manager, 'employees.shifts.manage');
        $this->actingAs($manager);
        $day = now()->addDays(2)->setTime(8, 0);
        $shift = fn ($user, $start, $end, $branchId = null) => [
            'user_id' => $user->id, 'branch_id' => $branchId ?? $branch->id,
            'starts_at' => $start->format('Y-m-d H:i'), 'ends_at' => $end->format('Y-m-d H:i'),
        ];

        $this->post(route('admin.shifts.store'), $shift($cook, $day, $day->copy()->addHours(8)))->assertSessionHasNoErrors();
        $this->post(route('admin.shifts.store'), $shift($cook, $day->copy()->addHours(6), $day->copy()->addHours(10)))->assertSessionHasErrors('starts_at');
        $this->post(route('admin.shifts.store'), $shift($cook, $day->copy()->addHours(9), $day->copy()->addHours(27)))->assertSessionHasErrors('ends_at');
        $this->post(route('admin.shifts.store'), $shift($outsider, $day, $day->copy()->addHours(4)))->assertSessionHasErrors('user_id');
        $this->post(route('admin.shifts.store'), $shift($cook, $day->copy()->addDay(), $day->copy()->addDay()->addHours(4), $foreignBranch->id))->assertSessionHasErrors('branch_id');

        $this->get(route('admin.shifts.index', ['week' => $day->toDateString()]))->assertOk()->assertSee('Cook Joy');
        $this->assertSame(1, Shift::query()->count());

        // Staff without the permission see only their own rota.
        $this->actingAs($cook)->get(route('admin.shifts.index', ['week' => $day->toDateString()]))->assertOk()->assertDontSee('Add shift');
        $this->post(route('admin.shifts.store'), $shift($cook, $day->copy()->addDays(3), $day->copy()->addDays(3)->addHours(2)))->assertForbidden();
    }

    public function test_payroll_runs_calculate_pay_require_a_second_approver_and_lock_attendance(): void
    {
        config(['restaurant.payroll.statutory_deductions' => [['label' => 'Pension', 'type' => 'percent', 'value' => 10]]]);
        [$restaurant, $branch] = $this->restaurantWithBranch('A');
        $preparer = $this->staff($restaurant, null, ['name' => 'Preparer']);
        $approver = $this->staff($restaurant, null, ['name' => 'Approver']);
        $chef = $this->staff($restaurant, $branch, ['name' => 'Chef Salaried']);
        $waiter = $this->staff($restaurant, $branch, ['name' => 'Waiter Hourly']);
        $this->grant($preparer, 'employees.payroll.manage', 'employees.payroll.approve', 'employees.attendance.manage');
        $this->grant($approver, 'employees.payroll.approve', 'employees.payroll.pay', 'employees.attendance.manage');
        $month = now()->subMonth()->startOfMonth();
        $shiftDay = $month->copy()->addDays(3)->setTime(9, 0);
        Attendance::create(['branch_id' => $branch->id, 'user_id' => $waiter->id, 'clock_in_at' => $shiftDay, 'clock_out_at' => $shiftDay->copy()->addHours(5)]);
        Attendance::create(['branch_id' => $branch->id, 'user_id' => $waiter->id, 'clock_in_at' => $shiftDay->copy()->addDay(), 'clock_out_at' => $shiftDay->copy()->addDay()->addHours(3)]);
        // A shift crossing the month boundary contributes only its in-period hours.
        $monthEndShift = $month->copy()->endOfMonth()->setTime(22, 0);
        Attendance::create(['branch_id' => $branch->id, 'user_id' => $waiter->id, 'clock_in_at' => $monthEndShift, 'clock_out_at' => $monthEndShift->copy()->addHours(4)]);
        // Hours outside the month are not paid in this run.
        Attendance::create(['branch_id' => $branch->id, 'user_id' => $waiter->id, 'clock_in_at' => $month->copy()->addMonth()->addDay()->setTime(9, 0), 'clock_out_at' => $month->copy()->addMonth()->addDay()->setTime(17, 0)]);

        $this->actingAs($preparer);
        $this->post(route('admin.payroll.compensation'), ['user_id' => $chef->id, 'branch_id' => $branch->id, 'pay_type' => 'monthly', 'rate' => 1000, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.compensation'), ['user_id' => $waiter->id, 'branch_id' => $branch->id, 'pay_type' => 'hourly', 'rate' => 20, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.store'), ['branch_id' => $branch->id, 'month' => $month->format('Y-m')])->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.store'), ['branch_id' => $branch->id, 'month' => $month->format('Y-m')])->assertSessionHasErrors('month');
        $run = PayrollRun::query()->firstOrFail();

        $waiterLine = $run->lines()->where('user_id', $waiter->id)->firstOrFail();
        $chefLine = $run->lines()->where('user_id', $chef->id)->firstOrFail();
        // Waiter: 8h + 2 in-period hours = 10h x 20. Chef gets their full monthly salary.
        $this->assertSame(['10.00', '200.00', '20.00', '180.00'], [$waiterLine->hours, $waiterLine->gross_pay, $waiterLine->deductions, $waiterLine->net_pay]);
        $this->assertSame('900.00', $chefLine->net_pay);

        $this->post(route('admin.payroll.adjustments.store', [$run->id, $waiterLine->id]), ['type' => 'allowance', 'label' => 'Overtime', 'amount' => 40])->assertSessionHasNoErrors();
        $this->post(route('admin.payroll.adjustments.store', [$run->id, $chefLine->id]), ['type' => 'deduction', 'label' => 'Advance', 'amount' => 5000])->assertSessionHasErrors('amount');
        $run->refresh();
        $this->assertSame(['1240.00', '1116.00'], [$run->gross_total, $run->net_total]);
        $this->assertSame('24.00', $waiterLine->fresh()->deductions);

        $this->post(route('admin.payroll.approve', $run->id))->assertForbidden();
        $this->actingAs($approver)->post(route('admin.payroll.approve', $run->id))->assertSessionHasNoErrors();
        $this->assertSame('approved', $run->fresh()->status);

        $attendance = Attendance::query()->orderBy('id')->firstOrFail();
        $this->put(route('admin.attendance.update', $attendance->id), [
            'clock_in_at' => $shiftDay->format('Y-m-d H:i'), 'clock_out_at' => $shiftDay->copy()->addHours(9)->format('Y-m-d H:i'), 'note' => 'Forgot to clock out',
        ])->assertSessionHasErrors('clock_in_at');

        $this->post(route('admin.payroll.pay', $run->id), ['payment_reference' => 'BANK-BATCH-7', 'payment_method' => 'bank'])->assertSessionHasNoErrors();
        $run->refresh();
        $this->assertSame('paid', $run->status);
        $this->assertDatabaseHas('expenses', ['id' => $run->expense_id, 'branch_id' => $branch->id, 'amount' => '1240.00', 'reference' => $run->reference]);
        $this->assertDatabaseHas('expense_categories', ['restaurant_id' => $restaurant->id, 'name' => 'Payroll']);
        $this->get(route('admin.payroll.payslip', [$run->id, $waiterLine->id]))->assertOk()->assertSee('Overtime')->assertSee('Pension')->assertSee('216.00');
    }
}
