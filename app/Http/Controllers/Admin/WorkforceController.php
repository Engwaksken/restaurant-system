<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Workforce\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Attendance (time clock) and shift planning.
 *
 * Every active staff member can clock themselves in and out. Managers record
 * or correct entries with a mandatory note; entries inside an approved or paid
 * payroll period are locked.
 */
class WorkforceController extends Controller
{
    use ScopesToUserBranches;

    private const MAX_SHIFT_HOURS = 16;

    public function __construct(private readonly PayrollService $payroll)
    {
    }

    public function attendance(Request $request): View
    {
        $user = $this->requireRestaurant($request);
        $manages = $this->managesAttendance($user);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'user_id' => ['nullable', 'integer'],
        ]);
        $from = Carbon::parse($filters['date_from'] ?? now()->startOfWeek()->toDateString())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        $records = $this->whereUserBranch(Attendance::query(), $user)
            ->when(!$manages, fn ($query) => $query->where('user_id', $user->id))
            ->when($manages && ($filters['user_id'] ?? null), fn ($query) => $query->where('user_id', $filters['user_id']))
            ->whereBetween('clock_in_at', [$from, $to])
            ->with(['user:id,name', 'branch:id,name', 'editor:id,name'])
            ->latest('clock_in_at')
            ->paginate(50)->withQueryString();

        $scope = $this->whereUserBranch(Attendance::query(), $user)->when(!$manages, fn ($query) => $query->where('user_id', $user->id));
        $periodHours = round((clone $scope)->whereBetween('clock_in_at', [$from, $to])->get(['clock_in_at', 'clock_out_at'])->sum(fn (Attendance $a) => $a->hours()), 1);
        $pageStats = [
            ['label' => $manages ? 'On shift now' : 'Your status', 'value' => $manages ? number_format((clone $scope)->whereNull('clock_out_at')->count()) : (Attendance::query()->where('user_id', $user->id)->whereNull('clock_out_at')->exists() ? 'Clocked in' : 'Clocked out'), 'icon' => 'fas fa-user-clock', 'tone' => 'success'],
            ['label' => 'Clock-ins today', 'value' => number_format((clone $scope)->where('clock_in_at', '>=', now()->startOfDay())->count()), 'icon' => 'fas fa-right-to-bracket', 'tone' => 'info'],
            ['label' => 'Hours in period', 'value' => number_format($periodHours, 1) . ' h', 'icon' => 'fas fa-hourglass-half', 'tone' => 'brand', 'hint' => $from->format('j M') . ' – ' . $to->format('j M')],
            ['label' => 'Records in period', 'value' => number_format($records->total()), 'icon' => 'fas fa-list-check', 'tone' => 'neutral'],
        ];

        return view('admin.employees.attendance', [
            'pageStats' => $pageStats,
            'records' => $records,
            'open' => Attendance::query()->where('user_id', $user->id)->whereNull('clock_out_at')->with('branch:id,name')->first(),
            'manages' => $manages,
            'staff' => $manages ? $this->scopedStaff($user)->orderBy('name')->get(['id', 'name']) : collect(),
            'branches' => $this->userBranches($user),
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'totalHours' => round($records->getCollection()->sum(fn (Attendance $a) => $a->hours()), 2),
        ]);
    }

    public function clock(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->requireRestaurant($request);
        $data = $request->validate([
            'action' => ['required', 'in:in,out'],
            'branch_id' => [Rule::requiredIf(fn () => $request->input('action') === 'in' && !$user->branch_id), 'nullable', 'integer', $this->userBranchRule($user)],
        ]);

        $record = DB::transaction(function () use ($user, $data): Attendance {
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            $open = Attendance::query()->where('user_id', $user->id)->whereNull('clock_out_at')->lockForUpdate()->first();
            if ($data['action'] === 'in') {
                if ($open) {
                    throw ValidationException::withMessages(['action' => 'You are already clocked in since ' . $open->clock_in_at->format('H:i') . '.']);
                }

                return Attendance::create([
                    'branch_id' => $user->branch_id ?? $data['branch_id'],
                    'user_id' => $user->id, 'clock_in_at' => now(), 'recorded_by' => $user->id,
                ]);
            }
            if (!$open) {
                throw ValidationException::withMessages(['action' => 'You are not clocked in.']);
            }
            $open->update(['clock_out_at' => now()]);

            return $open;
        });
        $logger->log('employees.attendance.clock_' . $data['action'], $record);

        return back()->with('success', $data['action'] === 'in' ? 'Clocked in.' : 'Clocked out after ' . $record->hours() . ' hours.');
    }

    public function storeAttendance(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->requireManager($request, 'attendance');
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::in($this->scopedStaff($user)->pluck('id')->all())],
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'clock_in_at' => ['required', 'date', 'before_or_equal:now'],
            'clock_out_at' => ['required', 'date', 'after:clock_in_at', 'before_or_equal:now'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $in = Carbon::parse($data['clock_in_at']);
        $this->assertEditable((int) $data['user_id'], $in);
        $this->assertNoOverlap((int) $data['user_id'], $in, Carbon::parse($data['clock_out_at']));

        $record = Attendance::create([...$data, 'recorded_by' => $user->id, 'edited_by' => $user->id]);
        $logger->log('employees.attendance.recorded', $record, ['user_id' => $record->user_id, 'note' => $record->note]);

        return back()->with('success', 'Attendance recorded.');
    }

    public function updateAttendance(Request $request, int $attendance, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->requireManager($request, 'attendance');
        $record = $this->whereUserBranch(Attendance::query(), $user)->findOrFail($attendance);
        $data = $request->validate([
            'clock_in_at' => ['required', 'date', 'before_or_equal:now'],
            'clock_out_at' => ['nullable', 'date', 'after:clock_in_at', 'before_or_equal:now'],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $in = Carbon::parse($data['clock_in_at']);
        $this->assertEditable($record->user_id, $record->clock_in_at);
        $this->assertEditable($record->user_id, $in);
        $this->assertNoOverlap($record->user_id, $in, isset($data['clock_out_at']) ? Carbon::parse($data['clock_out_at']) : now(), $record->id);

        $before = ['in' => $record->clock_in_at?->toDateTimeString(), 'out' => $record->clock_out_at?->toDateTimeString()];
        $record->update([...$data, 'edited_by' => $user->id]);
        $logger->log('employees.attendance.corrected', $record, ['before' => $before, 'note' => $data['note']]);

        return back()->with('success', 'Attendance corrected.');
    }

    public function shifts(Request $request): View
    {
        $user = $this->requireRestaurant($request);
        $manages = $this->managesShifts($user);
        $week = Carbon::parse($request->validate(['week' => ['nullable', 'date_format:Y-m-d']])['week'] ?? now()->toDateString())->startOfWeek();

        $shifts = $this->whereUserBranch(Shift::query(), $user)
            ->when(!$manages, fn ($query) => $query->where('user_id', $user->id))
            ->whereBetween('starts_at', [$week, $week->copy()->endOfWeek()])
            ->with(['user:id,name', 'branch:id,name'])
            ->orderBy('starts_at')
            ->get();

        $hours = round($shifts->sum(fn ($shift) => $shift->starts_at->diffInMinutes($shift->ends_at) / 60), 1);
        $pageStats = [
            ['label' => 'Shifts this week', 'value' => number_format($shifts->count()), 'icon' => 'fas fa-calendar-days', 'tone' => 'brand', 'hint' => $week->format('j M') . ' – ' . $week->copy()->endOfWeek()->format('j M')],
            ['label' => 'Scheduled hours', 'value' => number_format($hours, 1) . ' h', 'icon' => 'fas fa-clock', 'tone' => 'info'],
            ['label' => 'Staff rostered', 'value' => number_format($shifts->pluck('user_id')->unique()->count()), 'icon' => 'fas fa-users', 'tone' => 'success'],
            ['label' => 'Shifts today', 'value' => number_format($shifts->filter(fn ($shift) => $shift->starts_at->isToday())->count()), 'icon' => 'fas fa-calendar-day', 'tone' => 'warning'],
        ];

        return view('admin.employees.shifts', [
            'pageStats' => $pageStats,
            'shifts' => $shifts,
            'days' => collect(range(0, 6))->map(fn (int $offset) => $week->copy()->addDays($offset)),
            'week' => $week,
            'manages' => $manages,
            'staff' => $manages ? $this->scopedStaff($user)->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect(),
            'branches' => $this->userBranches($user),
        ]);
    }

    public function storeShift(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->requireManager($request, 'shifts');
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::in($this->scopedStaff($user)->where('is_active', true)->pluck('id')->all())],
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'role_label' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $start = Carbon::parse($data['starts_at']);
        $end = Carbon::parse($data['ends_at']);
        if ($start->diffInMinutes($end) > self::MAX_SHIFT_HOURS * 60) {
            throw ValidationException::withMessages(['ends_at' => 'A shift cannot be longer than ' . self::MAX_SHIFT_HOURS . ' hours.']);
        }

        $shift = DB::transaction(function () use ($data, $user, $start, $end): Shift {
            User::query()->whereKey($data['user_id'])->lockForUpdate()->first();
            $clash = Shift::query()->where('user_id', $data['user_id'])
                ->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
            if ($clash) {
                throw ValidationException::withMessages(['starts_at' => 'This overlaps another shift for the same person.']);
            }

            return Shift::create([...$data, 'created_by' => $user->id]);
        });
        $logger->log('employees.shift.created', $shift, ['user_id' => $shift->user_id]);

        return redirect()->route('admin.shifts.index', ['week' => $start->copy()->startOfWeek()->toDateString()])->with('success', 'Shift added.');
    }

    public function destroyShift(Request $request, int $shift, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->requireManager($request, 'shifts');
        $record = $this->whereUserBranch(Shift::query(), $user)->findOrFail($shift);
        if ($record->starts_at->isPast()) {
            throw ValidationException::withMessages(['shift' => 'Shifts that have started cannot be removed; correct attendance instead.']);
        }
        $record->delete();
        $logger->log('employees.shift.deleted', null, ['shift_id' => $shift, 'user_id' => $record->user_id]);

        return back()->with('success', 'Shift removed.');
    }

    private function assertEditable(int $userId, Carbon $at): void
    {
        if ($this->payroll->attendanceLocked($userId, $at)) {
            throw ValidationException::withMessages(['clock_in_at' => 'This period is already in an approved or paid payroll and cannot be changed.']);
        }
    }

    private function assertNoOverlap(int $userId, Carbon $in, Carbon $out, ?int $ignoreId = null): void
    {
        $overlap = Attendance::query()->where('user_id', $userId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('clock_in_at', '<', $out)
            ->where(fn ($query) => $query->whereNull('clock_out_at')->orWhere('clock_out_at', '>', $in))
            ->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['clock_in_at' => 'This overlaps another attendance record for the same person.']);
        }
    }

    private function scopedStaff(User $user)
    {
        return User::query()->where('restaurant_id', $user->restaurant_id)
            ->when($user->branch_id, fn ($query) => $query->where('branch_id', $user->branch_id));
    }

    private function managesAttendance(User $user): bool
    {
        return $user->can('employees.attendance.manage') || $user->can('admin');
    }

    private function managesShifts(User $user): bool
    {
        return $user->can('employees.shifts.manage') || $user->can('admin');
    }

    private function requireRestaurant(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');

        return $user;
    }

    private function requireManager(Request $request, string $area): User
    {
        $user = $this->requireRestaurant($request);
        abort_unless($area === 'shifts' ? $this->managesShifts($user) : $this->managesAttendance($user), 403);

        return $user;
    }
}
