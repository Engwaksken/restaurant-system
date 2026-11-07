<?php

declare(strict_types=1);

namespace App\Services\Workforce;

use App\Models\Attendance;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\StaffCompensation;
use Illuminate\Support\Carbon;

/**
 * Payroll calculation for a monthly, per-branch run.
 *
 * gross = base pay (monthly salary, or hourly rate x clocked hours) + allowances
 * net   = gross - deductions (configured statutory + manual), never below zero
 */
class PayrollService
{
    /** (Re)build draft lines from current pay rates and attendance; manual adjustments are kept. */
    public function generate(PayrollRun $run, ?int $userId): void
    {
        abort_unless($run->status === 'draft', 422, 'Only draft payroll runs can be recalculated.');

        $compensations = StaffCompensation::query()
            ->where('branch_id', $run->branch_id)
            ->where('is_active', true)
            ->whereHas('user', fn ($users) => $users->where('is_active', true))
            ->with('user:id,name')
            ->get();
        $keep = $compensations->pluck('user_id')->all();
        // Staff no longer on this branch's payroll drop out of the draft.
        $run->lines()->whereNotIn('user_id', $keep)->delete();

        foreach ($compensations as $compensation) {
            $hours = $compensation->pay_type === 'hourly'
                ? $this->clockedHours($compensation->user_id, $run->period_start, $run->period_end)
                : 0.0;
            $base = $compensation->pay_type === 'hourly'
                ? round($hours * (float) $compensation->rate, 2)
                : round((float) $compensation->rate, 2);

            PayrollLine::query()->updateOrCreate(
                ['payroll_run_id' => $run->id, 'user_id' => $compensation->user_id],
                [
                    'employee_name' => $compensation->user->name,
                    'pay_type' => $compensation->pay_type,
                    'rate' => $compensation->rate,
                    'hours' => $hours,
                    'base_pay' => $base,
                ],
            );
        }

        $this->refreshTotals($run, $userId);
    }

    public function refreshLine(PayrollLine $line): void
    {
        $adjustments = $line->adjustments()->get();
        $allowances = round((float) $adjustments->where('type', 'allowance')->sum('amount'), 2);
        $deductions = round((float) $adjustments->where('type', 'deduction')->sum('amount'), 2);
        $gross = round((float) $line->base_pay + $allowances, 2);
        $line->update([
            'allowances' => $allowances,
            'gross_pay' => $gross,
            'deductions' => $deductions,
            'net_pay' => max(0, round($gross - $deductions, 2)),
        ]);
    }

    /** Recompute every line (statutory deductions follow current gross pay) and the run totals. */
    public function refreshTotals(PayrollRun $run, ?int $userId = null): void
    {
        $lines = $run->lines()->get();
        foreach ($lines as $line) {
            $this->applyStatutory($line, $userId);
        }
        $lines = $run->lines()->get();
        $run->update([
            'gross_total' => round((float) $lines->sum('gross_pay'), 2),
            'deductions_total' => round((float) $lines->sum('deductions'), 2),
            'net_total' => round((float) $lines->sum('net_pay'), 2),
        ]);
    }

    /** Attendance inside an approved or paid payroll period is locked. */
    public function attendanceLocked(int $userId, Carbon $at): bool
    {
        return PayrollRun::query()
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('period_start', '<=', $at)
            ->whereDate('period_end', '>=', $at)
            ->whereHas('lines', fn ($lines) => $lines->where('user_id', $userId))
            ->exists();
    }

    private function clockedHours(int $userId, Carbon $start, Carbon $end): float
    {
        return round(Attendance::query()
            ->where('user_id', $userId)
            ->whereNotNull('clock_out_at')
            ->where('clock_in_at', '>=', $start->copy()->startOfDay())
            ->where('clock_in_at', '<=', $end->copy()->endOfDay())
            ->get()
            ->sum(fn (Attendance $attendance) => $attendance->hours()), 2);
    }

    /** Replace configured statutory deductions, computed on gross pay. */
    private function applyStatutory(PayrollLine $line, ?int $userId): void
    {
        $line->adjustments()->where('is_statutory', true)->delete();
        $this->refreshLine($line);
        $gross = (float) $line->gross_pay;

        foreach ((array) config('restaurant.payroll.statutory_deductions', []) as $rule) {
            $value = (float) ($rule['value'] ?? 0);
            $amount = ($rule['type'] ?? 'fixed') === 'percent'
                ? round($gross * $value / 100, 2)
                : round($value, 2);
            if ($amount > 0 && !empty($rule['label'])) {
                $line->adjustments()->create([
                    'type' => 'deduction', 'label' => (string) $rule['label'], 'amount' => $amount,
                    'is_statutory' => true, 'created_by' => $userId,
                ]);
            }
        }
        $this->refreshLine($line);
    }
}
