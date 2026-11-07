<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StaffCompensation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Workforce\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Monthly per-branch payroll: draft -> approved -> paid (or void).
 * The approver must differ from the preparer (configurable). Paying a run
 * records a Payroll expense for the gross amount.
 */
class PayrollController extends Controller
{
    use ScopesToUserBranches;

    public function __construct(private readonly PayrollService $payroll)
    {
    }

    public function index(Request $request): View
    {
        $user = $this->authorizePayroll($request, 'view');
        $runs = $this->whereUserBranch(PayrollRun::query(), $user)
            ->with(['branch:id,name', 'preparer:id,name', 'approver:id,name'])
            ->latest('period_start')->latest('id')
            ->paginate(24);
        $compensations = $this->whereUserBranch(StaffCompensation::query(), $user)
            ->with(['user:id,name,is_active', 'branch:id,name'])
            ->get()->sortBy('user.name');
        $staff = User::query()->where('restaurant_id', $user->restaurant_id)->where('is_active', true)
            ->when($user->branch_id, fn ($query) => $query->where('branch_id', $user->branch_id))
            ->orderBy('name')->get(['id', 'name']);

        $scope = $this->whereUserBranch(PayrollRun::query(), $user);
        $currency = config('restaurant.currency');
        $pageStats = [
            ['label' => 'Paid this year', 'value' => $currency . ' ' . number_format((float) (clone $scope)->where('status', 'paid')->where('period_start', '>=', now()->startOfYear()->toDateString())->sum('net_total'), 2), 'icon' => 'fas fa-money-check-dollar', 'tone' => 'success', 'hint' => 'Net pay, ' . now()->year],
            ['label' => 'Awaiting approval', 'value' => number_format((clone $scope)->where('status', 'draft')->count()), 'icon' => 'fas fa-file-pen', 'tone' => 'warning'],
            ['label' => 'Approved, unpaid', 'value' => number_format((clone $scope)->where('status', 'approved')->count()), 'icon' => 'fas fa-stamp', 'tone' => 'info'],
            ['label' => 'Staff on payroll', 'value' => number_format($compensations->count()), 'icon' => 'fas fa-users', 'tone' => 'brand'],
        ];

        return view('admin.employees.payroll', [
            'pageStats' => $pageStats,
            'runs' => $runs,
            'compensations' => $compensations,
            'staff' => $staff,
            'branches' => $this->userBranches($user),
            'canManage' => $this->can($user, 'manage'),
            'statutory' => (array) config('restaurant.payroll.statutory_deductions', []),
        ]);
    }

    public function saveCompensation(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'manage');
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('restaurant_id', $user->restaurant_id)
                ->where(fn ($query) => $user->branch_id ? $query->where('branch_id', $user->branch_id) : $query)],
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'pay_type' => ['required', 'in:monthly,hourly'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $compensation = StaffCompensation::query()->updateOrCreate(
            ['user_id' => $data['user_id']],
            [
                'branch_id' => $data['branch_id'], 'pay_type' => $data['pay_type'], 'rate' => round((float) $data['rate'], 2),
                'is_active' => $request->boolean('is_active', true), 'updated_by' => $user->id,
            ],
        );
        $logger->log('employees.payroll.rate_saved', $compensation, [
            'user_id' => $compensation->user_id, 'pay_type' => $compensation->pay_type, 'rate' => $compensation->rate,
        ]);

        return back()->with('success', 'Pay rate saved.');
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'manage');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'month' => ['required', 'date_format:Y-m'],
        ]);
        $start = Carbon::createFromFormat('Y-m-d', $data['month'] . '-01')->startOfMonth();

        $run = DB::transaction(function () use ($data, $start, $user): PayrollRun {
            $exists = PayrollRun::query()->where('branch_id', $data['branch_id'])
                ->whereDate('period_start', $start)->where('status', '!=', 'void')->lockForUpdate()->exists();
            if ($exists) {
                throw ValidationException::withMessages(['month' => 'A payroll run already exists for this branch and month.']);
            }
            $run = PayrollRun::create([
                'branch_id' => $data['branch_id'],
                'reference' => 'PAY-' . $start->format('Ym') . '-' . Str::upper(Str::random(5)),
                'period_start' => $start->toDateString(),
                'period_end' => $start->copy()->endOfMonth()->toDateString(),
                'status' => 'draft',
                'prepared_by' => $user->id,
            ]);
            $this->payroll->generate($run, $user->id);

            return $run;
        });
        $logger->log('employees.payroll.drafted', $run, ['branch_id' => $run->branch_id, 'period' => $data['month']]);

        return redirect()->route('admin.payroll.show', $run->id)->with('success', 'Draft payroll ' . $run->reference . ' prepared.');
    }

    public function show(Request $request, int $run): View
    {
        $user = $this->authorizePayroll($request, 'view');
        $record = $this->whereUserBranch(PayrollRun::query(), $user)
            ->with(['branch:id,name', 'lines' => fn ($q) => $q->orderBy('employee_name'), 'lines.adjustments', 'preparer:id,name', 'approver:id,name', 'payer:id,name'])
            ->findOrFail($run);

        return view('admin.employees.payroll-run', [
            'run' => $record,
            'canManage' => $this->can($user, 'manage'),
            'canApprove' => $this->can($user, 'approve') && (!config('restaurant.payroll.separate_approver', true) || (int) $record->prepared_by !== (int) $user->id),
            'canPay' => $this->can($user, 'pay'),
        ]);
    }

    public function payslip(Request $request, int $run, int $line): View
    {
        $user = $this->authorizePayroll($request, 'view');
        $record = $this->whereUserBranch(PayrollRun::query(), $user)->with('branch.restaurant')->findOrFail($run);
        $payslip = $record->lines()->with('adjustments')->findOrFail($line);

        return view('admin.employees.payslip', ['run' => $record, 'line' => $payslip]);
    }

    public function recalculate(Request $request, int $run, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'manage');
        $record = DB::transaction(function () use ($user, $run): PayrollRun {
            $record = $this->lockedRun($user, $run, ['draft']);
            $this->payroll->generate($record, $user->id);

            return $record;
        });
        $logger->log('employees.payroll.recalculated', $record);

        return back()->with('success', 'Payroll recalculated from current pay rates and attendance.');
    }

    public function addAdjustment(Request $request, int $run, int $line, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'manage');
        $data = $request->validate([
            'type' => ['required', 'in:allowance,deduction'],
            'label' => ['required', 'string', 'min:2', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
        ]);
        $adjustment = DB::transaction(function () use ($user, $run, $line, $data): PayrollAdjustment {
            $record = $this->lockedRun($user, $run, ['draft']);
            $payrollLine = $record->lines()->findOrFail($line);
            if ($data['type'] === 'deduction' && (float) $payrollLine->deductions + (float) $data['amount'] > (float) $payrollLine->gross_pay + 0.000001) {
                throw ValidationException::withMessages(['amount' => 'Deductions cannot exceed gross pay.']);
            }
            $adjustment = $payrollLine->adjustments()->create([...$data, 'amount' => round((float) $data['amount'], 2), 'created_by' => $user->id]);
            $this->payroll->refreshTotals($record);

            return $adjustment;
        });
        $logger->log('employees.payroll.adjusted', $adjustment, ['run_id' => $run, 'type' => $adjustment->type, 'amount' => $adjustment->amount]);

        return back()->with('success', ucfirst($data['type']) . ' added.');
    }

    public function removeAdjustment(Request $request, int $run, int $adjustment, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'manage');
        DB::transaction(function () use ($user, $run, $adjustment): void {
            $record = $this->lockedRun($user, $run, ['draft']);
            $item = PayrollAdjustment::query()->whereKey($adjustment)
                ->whereHas('line', fn ($lines) => $lines->where('payroll_run_id', $record->id))->firstOrFail();
            abort_if($item->is_statutory, 422, 'Statutory deductions come from configuration and cannot be removed here.');
            $item->delete();
            $this->payroll->refreshTotals($record);
        });
        $logger->log('employees.payroll.adjustment_removed', null, ['run_id' => $run, 'adjustment_id' => $adjustment]);

        return back()->with('success', 'Adjustment removed.');
    }

    public function approve(Request $request, int $run, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'approve');
        $record = DB::transaction(function () use ($user, $run): PayrollRun {
            $record = $this->lockedRun($user, $run, ['draft']);
            if (config('restaurant.payroll.separate_approver', true) && (int) $record->prepared_by === (int) $user->id) {
                abort(403, 'Payroll must be approved by someone other than the person who prepared it.');
            }
            abort_if($record->lines()->doesntExist(), 422, 'There are no staff on this payroll run.');
            $record->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);

            return $record;
        });
        $logger->log('employees.payroll.approved', $record, ['net_total' => $record->net_total]);

        return back()->with('success', 'Payroll ' . $record->reference . ' approved.');
    }

    public function pay(Request $request, int $run, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'pay');
        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
            'payment_method' => ['required', 'string', Rule::in(array_keys(config('restaurant.payment_methods', [])))],
        ]);
        $record = DB::transaction(function () use ($user, $run, $data): PayrollRun {
            $record = $this->lockedRun($user, $run, ['approved']);
            $category = ExpenseCategory::query()->firstOrCreate([
                'restaurant_id' => $user->restaurant_id,
                'name' => (string) config('restaurant.payroll.expense_category', 'Payroll'),
            ]);
            $expense = Expense::create([
                'branch_id' => $record->branch_id,
                'expense_category_id' => $category->id,
                'created_by' => $user->id,
                'reference' => $record->reference,
                'description' => 'Payroll ' . $record->period_start->format('F Y') . ' (net paid ' . number_format((float) $record->net_total, 2)
                    . ', deductions withheld ' . number_format((float) $record->deductions_total, 2) . ')',
                'amount' => $record->gross_total,
                'payment_method' => $data['payment_method'],
                'expense_date' => now()->toDateString(),
            ]);
            $record->update([
                'status' => 'paid', 'paid_by' => $user->id, 'paid_at' => now(),
                'payment_reference' => $data['payment_reference'], 'expense_id' => $expense->id,
            ]);

            return $record;
        });
        $logger->log('employees.payroll.paid', $record, ['net_total' => $record->net_total, 'reference' => $data['payment_reference']]);

        return back()->with('success', 'Payroll ' . $record->reference . ' marked paid and recorded as an expense.');
    }

    public function void(Request $request, int $run, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizePayroll($request, 'approve');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $record = DB::transaction(function () use ($user, $run): PayrollRun {
            $record = $this->lockedRun($user, $run, ['draft', 'approved']);
            $record->update(['status' => 'void']);

            return $record;
        });
        $logger->log('employees.payroll.voided', $record, ['reason' => $data['reason']]);

        return back()->with('success', 'Payroll ' . $record->reference . ' voided.');
    }

    private function lockedRun(User $user, int $run, array $statuses): PayrollRun
    {
        $record = $this->whereUserBranch(PayrollRun::query(), $user)->lockForUpdate()->findOrFail($run);
        abort_unless(in_array($record->status, $statuses, true), 422, 'This payroll run is ' . $record->status . '.');

        return $record;
    }

    private function can(User $user, string $operation): bool
    {
        return $user->can('employees.payroll.' . $operation) || $user->can('admin');
    }

    private function authorizePayroll(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = $operation === 'view'
            ? collect(['view', 'manage', 'approve', 'pay'])->contains(fn (string $op) => $this->can($user, $op))
            : $this->can($user, $operation);
        abort_unless($allowed, 403);

        return $user;
    }
}
