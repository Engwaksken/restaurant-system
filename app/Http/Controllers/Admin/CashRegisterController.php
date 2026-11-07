<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ScopesToUserBranches;
use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Cashier till sessions: open with a counted float, record pay-ins/outs,
 * close with a counted total. Variances beyond the configured tolerance need
 * an explanation and a review by someone other than the cashier.
 */
class CashRegisterController extends Controller
{
    use ScopesToUserBranches;

    public function __construct(private readonly CashRegisterService $register)
    {
    }

    public function index(Request $request): View
    {
        $user = $this->authorizeRegister($request, 'use');
        $current = CashRegisterSession::query()
            ->where('opened_by', $user->id)->where('status', 'open')
            ->with(['branch:id,name', 'movements.user:id,name'])
            ->first();
        $oversees = $this->oversees($user);
        $seesAll = $oversees || $this->canReview($user);

        $sessions = $this->whereUserBranch(CashRegisterSession::query(), $user)
            ->when(!$seesAll, fn ($query) => $query->where('opened_by', $user->id))
            ->when($request->query('review') === '1', fn ($query) => $query->where('review_required', true)->whereNull('reviewed_at'))
            ->with(['branch:id,name', 'opener:id,name', 'closer:id,name', 'reviewer:id,name'])
            ->latest('opened_at')->latest('id')
            ->paginate(25)->withQueryString();

        $scope = $this->whereUserBranch(CashRegisterSession::query(), $user)
            ->when(!$seesAll, fn ($query) => $query->where('opened_by', $user->id));
        $currency = config('restaurant.currency');
        $pageStats = [
            ['label' => 'Open drawers', 'value' => number_format((clone $scope)->where('status', 'open')->count()), 'icon' => 'fas fa-cash-register', 'tone' => 'success', 'hint' => $current ? 'Including yours' : 'You have no open drawer'],
            ['label' => 'Closed today', 'value' => number_format((clone $scope)->where('status', 'closed')->where('closed_at', '>=', now()->startOfDay())->count()), 'icon' => 'fas fa-lock', 'tone' => 'info'],
            ['label' => 'Awaiting review', 'value' => number_format((clone $scope)->where('review_required', true)->whereNull('reviewed_at')->count()), 'icon' => 'fas fa-magnifying-glass-dollar', 'tone' => 'warning', 'hint' => 'Variance above tolerance'],
            ['label' => 'Net variance (30 days)', 'value' => $currency . ' ' . number_format((float) (clone $scope)->where('closed_at', '>=', now()->subDays(30))->sum('variance'), 2), 'icon' => 'fas fa-scale-balanced', 'tone' => 'neutral', 'hint' => 'Counted minus expected'],
        ];

        return view('admin.finance.cash-register', [
            'pageStats' => $pageStats,
            'current' => $current,
            'expected' => $current ? $this->register->expectedCash($current) : null,
            'cashTaken' => $current ? (float) $current->payments()->where('payment_method', 'cash')->sum('amount') : 0,
            'cashRefunded' => $current ? (float) $current->refunds()->where('method', 'cash')->sum('amount') : 0,
            'sessions' => $sessions,
            'branches' => $this->userBranches($user),
            'oversees' => $oversees,
            'canReview' => $this->canReview($user),
            'tolerance' => $this->register->tolerance(),
            'requiresSession' => $this->register->requiresSessionForCash(),
        ]);
    }

    public function open(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeRegister($request, 'use');
        $data = $request->validate([
            'branch_id' => ['required', 'integer', $this->userBranchRule($user)],
            'opening_float' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'opening_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = DB::transaction(function () use ($user, $data): CashRegisterSession {
            // Serialise per cashier: one open drawer at a time.
            User::query()->whereKey($user->id)->lockForUpdate()->first();
            if (CashRegisterSession::query()->where('opened_by', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['branch_id' => 'You already have an open cash register session. Close it first.']);
            }

            return CashRegisterSession::create([
                'branch_id' => $data['branch_id'],
                'opened_by' => $user->id,
                'reference' => 'CRS-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'status' => 'open',
                'opening_float' => round((float) $data['opening_float'], 2),
                'opening_notes' => $data['opening_notes'] ?? null,
                'opened_at' => now(),
            ]);
        });
        $logger->log('finance.cash_register.opened', $session, ['branch_id' => $session->branch_id, 'opening_float' => $session->opening_float]);

        return back()->with('success', 'Cash register session ' . $session->reference . ' opened.');
    }

    public function movement(Request $request, int $session, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeRegister($request, 'use');
        $data = $request->validate([
            'type' => ['required', 'in:pay_in,pay_out'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $movement = DB::transaction(function () use ($user, $session, $data): CashMovement {
            $record = $this->lockedOpenSession($user, $session);
            $amount = round((float) $data['amount'], 2);
            if ($data['type'] === 'pay_out') {
                $this->register->assertCashAvailable($record, $amount, 'amount');
            }

            return $record->movements()->create([
                'user_id' => $user->id, 'type' => $data['type'], 'amount' => $amount, 'reason' => $data['reason'],
            ]);
        });
        $logger->log('finance.cash_register.' . $data['type'], $movement, ['session_id' => $session, 'amount' => $movement->amount]);

        return back()->with('success', ($data['type'] === 'pay_in' ? 'Pay-in' : 'Pay-out') . ' recorded.');
    }

    public function close(Request $request, int $session, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeRegister($request, 'use');
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $record = DB::transaction(function () use ($user, $session, $data): CashRegisterSession {
            $record = $this->lockedOpenSession($user, $session);
            $expected = $this->register->expectedCash($record);
            $counted = round((float) $data['counted_cash'], 2);
            $variance = round($counted - $expected, 2);
            $outsideTolerance = abs($variance) > $this->register->tolerance() + 0.000001;
            $notes = trim((string) ($data['closing_notes'] ?? ''));
            if ($outsideTolerance && mb_strlen($notes) < 5) {
                throw ValidationException::withMessages(['closing_notes' => 'Explain the ' . number_format($variance, 2) . ' cash difference before closing.']);
            }

            $now = now();
            $record->update([
                'status' => 'closed', 'closed_by' => $user->id, 'closed_at' => $now,
                'expected_cash' => $expected, 'counted_cash' => $counted, 'variance' => $variance,
                'closing_notes' => $notes !== '' ? $notes : null,
                'review_required' => $outsideTolerance,
            ]);
            // Cash taken in this drawer is reconciled by the drawer count.
            DB::table('payments')
                ->where('cash_register_session_id', $record->id)
                ->where('reconciliation_status', 'unreconciled')
                ->update([
                    'reconciliation_status' => 'matched', 'statement_reference' => $record->reference,
                    'reconciled_by' => $user->id, 'reconciled_at' => $now, 'updated_at' => $now,
                ]);

            return $record;
        });
        $logger->log('finance.cash_register.closed', $record, [
            'expected' => $record->expected_cash, 'counted' => $record->counted_cash, 'variance' => $record->variance,
        ]);

        $message = 'Session ' . $record->reference . ' closed.';
        if ($record->review_required) {
            $message .= ' The ' . number_format((float) $record->variance, 2) . ' difference has been flagged for manager review.';
        }

        return back()->with('success', $message);
    }

    public function review(Request $request, int $session, ActivityLogger $logger): RedirectResponse
    {
        $user = $this->authorizeRegister($request, 'review');
        $data = $request->validate(['review_notes' => ['required', 'string', 'min:5', 'max:2000']]);
        $record = DB::transaction(function () use ($user, $session, $data): CashRegisterSession {
            $record = $this->whereUserBranch(CashRegisterSession::query(), $user)->lockForUpdate()->findOrFail($session);
            abort_unless($record->review_required && $record->reviewed_at === null, 422, 'This session does not need review.');
            abort_if((int) $record->opened_by === (int) $user->id || (int) $record->closed_by === (int) $user->id, 403, 'A cash variance must be reviewed by someone other than the cashier.');
            $record->update(['reviewed_by' => $user->id, 'reviewed_at' => now(), 'review_notes' => $data['review_notes']]);

            return $record;
        });
        $logger->log('finance.cash_register.reviewed', $record, ['variance' => $record->variance]);

        return back()->with('success', 'Variance on ' . $record->reference . ' reviewed.');
    }

    /** The cashier's own open session, or any open session for overseers. */
    private function lockedOpenSession(User $user, int $session): CashRegisterSession
    {
        $record = $this->whereUserBranch(CashRegisterSession::query(), $user)
            ->when(!$this->oversees($user), fn ($query) => $query->where('opened_by', $user->id))
            ->lockForUpdate()
            ->findOrFail($session);
        if ($record->status !== 'open') {
            throw ValidationException::withMessages(['session' => 'This cash register session is already closed.']);
        }

        return $record;
    }

    private function oversees(User $user): bool
    {
        return $user->can('finance.cash-register.manage') || $user->can('admin');
    }

    private function canReview(User $user): bool
    {
        return $user->can('finance.cash-register.review') || $user->can('admin');
    }

    private function authorizeRegister(Request $request, string $operation): User
    {
        $user = $request->user();
        abort_unless($user && $user->restaurant_id, 403, 'Your account is not assigned to a restaurant.');
        $allowed = match ($operation) {
            'review' => $this->canReview($user),
            default => $user->can('finance.cash-register.use') || $this->oversees($user) || $this->canReview($user),
        };
        abort_unless($allowed, 403);

        return $user;
    }
}
