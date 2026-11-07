<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Match non-cash payments against provider/bank statements. Cash is reconciled
 * by cash-register counts, so it is excluded here.
 */
class ReconciliationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $this->authorizeReconciliation($request, 'view');
        $methods = $this->nonCashMethods();
        $filters = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'method' => ['nullable', Rule::in(array_keys($methods))],
            'status' => ['nullable', Rule::in(['unreconciled', 'matched', 'disputed'])],
        ]);
        $from = Carbon::parse($filters['date_from'] ?? now()->subDays(6)->toDateString())->startOfDay();
        $to = Carbon::parse($filters['date_to'] ?? now()->toDateString())->endOfDay();

        $base = $this->scopedPayments($user)
            ->whereIn('payment_method', array_keys($methods))
            ->whereIn('status', ['successful', 'refunded'])
            ->whereBetween('paid_at', [$from, $to]);

        $summary = (clone $base)
            ->selectRaw('payment_method, COUNT(*) as payments, SUM(amount) as total,
                SUM(CASE WHEN reconciliation_status = ? THEN amount ELSE 0 END) as matched,
                SUM(CASE WHEN reconciliation_status = ? THEN amount ELSE 0 END) as unreconciled,
                SUM(CASE WHEN reconciliation_status = ? THEN amount ELSE 0 END) as disputed', ['matched', 'unreconciled', 'disputed'])
            ->groupBy('payment_method')
            ->get();

        $payments = (clone $base)
            ->when($filters['method'] ?? null, fn (Builder $q, $method) => $q->where('payment_method', $method))
            ->where('reconciliation_status', $filters['status'] ?? 'unreconciled')
            ->with(['order:id,order_number,branch_id', 'order.branch:id,name', 'reconciler:id,name'])
            ->orderBy('paid_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.finance.reconciliation', [
            'payments' => $payments,
            'summary' => $summary,
            'methods' => $methods,
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'status' => $filters['status'] ?? 'unreconciled',
            'canManage' => $this->canManage($user),
        ]);
    }

    public function match(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeReconciliation($request, 'manage');
        $data = $request->validate([
            'payment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'payment_ids.*' => ['integer', 'distinct'],
            'statement_reference' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $count = DB::transaction(function () use ($user, $data): int {
            $payments = $this->scopedPayments($user)
                ->whereIn('id', $data['payment_ids'])
                ->whereIn('payment_method', array_keys($this->nonCashMethods()))
                ->whereIn('reconciliation_status', ['unreconciled', 'disputed'])
                ->lockForUpdate()
                ->get();
            abort_if($payments->count() !== count($data['payment_ids']), 422, 'Some selected payments are not available to reconcile.');
            foreach ($payments as $payment) {
                $payment->update([
                    'reconciliation_status' => 'matched',
                    'statement_reference' => $data['statement_reference'],
                    'reconciliation_note' => $data['note'] ?? null,
                    'reconciled_by' => $user->id,
                    'reconciled_at' => now(),
                ]);
            }

            return $payments->count();
        });
        $logger->log('finance.reconciliation.matched', null, ['payment_ids' => $data['payment_ids'], 'statement_reference' => $data['statement_reference']]);

        return back()->with('success', $count . ' ' . str('payment')->plural($count) . ' matched to ' . $data['statement_reference'] . '.');
    }

    public function dispute(Request $request, int $payment, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeReconciliation($request, 'manage');
        $data = $request->validate([
            'status' => ['required', Rule::in(['disputed', 'unreconciled'])],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $record = DB::transaction(function () use ($user, $payment, $data): Payment {
            $record = $this->scopedPayments($user)
                ->whereIn('payment_method', array_keys($this->nonCashMethods()))
                ->lockForUpdate()->findOrFail($payment);
            $record->update([
                'reconciliation_status' => $data['status'],
                'statement_reference' => $data['status'] === 'unreconciled' ? null : $record->statement_reference,
                'reconciliation_note' => $data['note'],
                'reconciled_by' => $user->id,
                'reconciled_at' => now(),
            ]);

            return $record;
        });
        $logger->log('finance.reconciliation.' . $data['status'], $record, ['note' => $data['note']]);

        return back()->with('success', 'Payment ' . $record->receipt_number . ($data['status'] === 'disputed' ? ' flagged as disputed.' : ' reopened.'));
    }

    private function scopedPayments(User $user): Builder
    {
        return Payment::query()->whereHas('order', fn (Builder $orders) => $orders->whereIn('branch_id', Branch::query()
            ->where('restaurant_id', $user->restaurant_id)
            ->when($user->branch_id, fn (Builder $branches) => $branches->whereKey($user->branch_id))
            ->select('id')));
    }

    /** @return array<string, string> */
    private function nonCashMethods(): array
    {
        return collect(config('restaurant.payment_methods', []))
            ->except('cash')
            ->merge(['flutterwave' => 'Flutterwave', 'pesapal' => 'Pesapal'])
            ->all();
    }

    private function canManage(User $user): bool
    {
        return $user->can('finance.reconciliation.manage') || $user->can('admin');
    }

    private function authorizeReconciliation(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        abort_unless($this->canManage($user) || ($operation === 'view' && $user->can('finance.reconciliation.view')), 403);

        return $user;
    }
}
