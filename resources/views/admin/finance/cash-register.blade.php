@extends('layouts.app')

@section('title', 'Cash Register')
@section('page-title', 'Cash Register')

@section('content')
@php $currency = config('restaurant.currency'); $money = fn ($v) => $currency . ' ' . number_format((float) $v, 2); @endphp
<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="panel p-4 h-100">
        @if($current)
            <h2 class="h5">Your open drawer <small class="text-muted">{{ $current->reference }}</small></h2>
            <p class="small text-muted">{{ $current->branch?->name }} · opened {{ $current->opened_at->format('Y-m-d H:i') }}</p>
            <dl class="row mb-3">
                <dt class="col-7">Opening float</dt><dd class="col-5 text-end">{{ $money($current->opening_float) }}</dd>
                <dt class="col-7">Cash payments</dt><dd class="col-5 text-end">{{ $money($cashTaken) }}</dd>
                <dt class="col-7">Cash refunds</dt><dd class="col-5 text-end">− {{ $money($cashRefunded) }}</dd>
                <dt class="col-7">Pay-ins</dt><dd class="col-5 text-end">{{ $money($current->movements->where('type', 'pay_in')->sum('amount')) }}</dd>
                <dt class="col-7">Pay-outs</dt><dd class="col-5 text-end">− {{ $money($current->movements->where('type', 'pay_out')->sum('amount')) }}</dd>
                <dt class="col-7 border-top pt-2">Expected in drawer</dt><dd class="col-5 text-end border-top pt-2 fw-bold">{{ $money($expected) }}</dd>
            </dl>
            <details class="mb-3">
                <summary>Record a pay-in or pay-out</summary>
                <form method="POST" action="{{ route('admin.cash-register.movement', $current->id) }}" class="mt-2">
                    @csrf
                    <div class="row g-2">
                        <div class="col-5"><select class="form-select" name="type" aria-label="Type"><option value="pay_out">Pay-out</option><option value="pay_in">Pay-in</option></select></div>
                        <div class="col-7"><input class="form-control" type="number" name="amount" min="0.01" step="0.01" required placeholder="Amount" aria-label="Amount"></div>
                        <div class="col-12"><input class="form-control" name="reason" minlength="3" maxlength="255" required placeholder="Reason, e.g. change float top-up" aria-label="Reason"></div>
                        <div class="col-12"><button class="btn btn-outline-primary btn-sm" type="submit">Record</button></div>
                    </div>
                </form>
                @if($current->movements->isNotEmpty())
                    <ul class="list-unstyled small mt-2 mb-0">@foreach($current->movements as $movement)<li>{{ $movement->created_at->format('H:i') }} · {{ $movement->type === 'pay_in' ? '+' : '−' }}{{ $money($movement->amount) }} · {{ $movement->reason }}</li>@endforeach</ul>
                @endif
            </details>
            <form method="POST" action="{{ route('admin.cash-register.close', $current->id) }}">
                @csrf
                <h3 class="h6">Close drawer</h3>
                <label class="form-label" for="countedCash">Counted cash</label>
                <input class="form-control mb-2" id="countedCash" type="number" name="counted_cash" min="0" step="0.01" required>
                <label class="form-label" for="closingNotes">Notes {{ $tolerance > 0 ? '(required if the difference exceeds ' . $money($tolerance) . ')' : '(required if the count differs)' }}</label>
                <textarea class="form-control mb-3" id="closingNotes" name="closing_notes" maxlength="2000" rows="2"></textarea>
                <button class="btn btn-danger" type="submit">Count and close</button>
            </form>
        @else
            <h2 class="h5">Open a drawer</h2>
            @if($requiresSession)<p class="small text-muted">An open drawer is required before you can take cash payments or give cash refunds.</p>@endif
            @if($branches->isEmpty())<p class="text-muted">An active branch is required.</p>@else
            <form method="POST" action="{{ route('admin.cash-register.open') }}">
                @csrf
                <label class="form-label" for="registerBranch">Branch</label>
                <select class="form-select mb-2" id="registerBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <label class="form-label" for="openingFloat">Counted opening float</label>
                <input class="form-control mb-2" id="openingFloat" type="number" name="opening_float" min="0" step="0.01" required value="{{ old('opening_float') }}">
                <label class="form-label" for="openingNotes">Notes (optional)</label>
                <textarea class="form-control mb-3" id="openingNotes" name="opening_notes" maxlength="1000" rows="2"></textarea>
                <button class="btn btn-primary" type="submit">Open drawer</button>
            </form>
            @endif
        @endif
    </div></div>
    <div class="col-lg-7"><div class="panel p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">{{ $oversees || $canReview ? 'Sessions' : 'Your sessions' }}</h2>
            @if($canReview)
                @if(request('review') === '1')<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.cash-register.index') }}">All sessions</a>
                @else<a class="btn btn-sm btn-outline-warning" href="{{ route('admin.cash-register.index', ['review' => 1]) }}">Awaiting review</a>@endif
            @endif
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr><th>Session</th><th>Cashier</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Variance</th><th>Review</th></tr></thead>
            <tbody>
            @forelse($sessions as $session)
                <tr>
                    <td>{{ $session->reference }}<small class="d-block text-muted">{{ $session->branch?->name }} · {{ $session->opened_at->format('Y-m-d H:i') }}@if($session->closed_at) – {{ $session->closed_at->format('H:i') }}@endif</small></td>
                    <td>{{ $session->opener?->name }}@if($session->status === 'open') <span class="badge bg-success">Open</span>@endif</td>
                    <td class="text-end">{{ $session->expected_cash !== null ? number_format((float) $session->expected_cash, 2) : '—' }}</td>
                    <td class="text-end">{{ $session->counted_cash !== null ? number_format((float) $session->counted_cash, 2) : '—' }}</td>
                    <td class="text-end {{ (float) $session->variance < 0 ? 'text-danger' : ((float) $session->variance > 0 ? 'text-warning' : '') }}">{{ $session->variance !== null ? number_format((float) $session->variance, 2) : '—' }}</td>
                    <td class="small">
                        @if($session->closing_notes)<span class="d-block">{{ $session->closing_notes }}</span>@endif
                        @if($session->reviewed_at)
                            <span class="text-success">Reviewed by {{ $session->reviewer?->name }}</span>@if($session->review_notes)<span class="d-block text-muted">{{ $session->review_notes }}</span>@endif
                        @elseif($session->review_required)
                            <span class="badge bg-warning text-dark">Needs review</span>
                            @if($canReview && $session->opened_by !== auth()->id() && $session->closed_by !== auth()->id())
                                <form method="POST" action="{{ route('admin.cash-register.review', $session->id) }}" class="mt-1">
                                    @csrf
                                    <input class="form-control form-control-sm mb-1" name="review_notes" minlength="5" maxlength="2000" required placeholder="Review outcome" aria-label="Review notes for {{ $session->reference }}">
                                    <button class="btn btn-sm btn-outline-success" type="submit">Mark reviewed</button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No sessions yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $sessions->links() }}
    </div></div>
</div>
@endsection
