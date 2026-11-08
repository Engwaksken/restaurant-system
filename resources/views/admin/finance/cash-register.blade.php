@extends('layouts.app')

@section('title', 'Cash Register')
@section('page-title', 'Cash Register')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $currency = config('restaurant.currency');
    $money = fn ($v) => $currency . ' ' . number_format((float) $v, 2);
    $canReviewSession = fn ($session) => $canReview && $session->review_required && ! $session->reviewed_at && $session->opened_by !== auth()->id() && $session->closed_by !== auth()->id();
    $reviewable = collect($sessions->items())->filter($canReviewSession);
    $activeReviewId = $errors->has('review_notes') && $reviewable->count() === 1 ? $reviewable->first()->id : null;
    $autoOpen = null;
    if ($errors->any()) {
        if (session()->hasOldInput('opening_float')) {
            $autoOpen = 'add-drawer';
        } elseif (session()->hasOldInput('counted_cash')) {
            $autoOpen = 'close-drawer';
        } elseif (session()->hasOldInput('reason')) {
            $autoOpen = 'add-cash-movement';
        } elseif ($activeReviewId) {
            $autoOpen = 'review-session-' . $activeReviewId;
        }
    }
@endphp
<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="panel p-4 h-100">
        @if($current)
            <h2 class="h5">Your open drawer <small class="text-muted">{{ $current->reference }}</small></h2>
            <p class="small text-muted">{{ $current->branch?->name }} · opened {{ $current->opened_at->format('Y-m-d H:i') }}</p>
            <dl class="row mb-3">
                <dt class="col-7">Opening float</dt><dd class="col-5 text-end">{{ $money($current->opening_float) }}</dd>
                <dt class="col-7">Cash payments</dt><dd class="col-5 text-end">{{ $money($cashTaken) }}</dd>
                    <dt class="col-7">Cash refunds</dt><dd class="col-5 text-end">- {{ $money($cashRefunded) }}</dd>
                <dt class="col-7">Pay-ins</dt><dd class="col-5 text-end">{{ $money($current->movements->where('type', 'pay_in')->sum('amount')) }}</dd>
                    <dt class="col-7">Pay-outs</dt><dd class="col-5 text-end">- {{ $money($current->movements->where('type', 'pay_out')->sum('amount')) }}</dd>
                <dt class="col-7 border-top pt-2">Expected in drawer</dt><dd class="col-5 text-end border-top pt-2 fw-bold">{{ $money($expected) }}</dd>
            </dl>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#add-cash-movement">
                    <i class="fas fa-plus me-1"></i>Record a pay-in or pay-out
                </button>
                <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#close-drawer">
                    <i class="fas fa-lock me-1"></i>Close drawer
                </button>
            </div>
            @if($current->movements->isNotEmpty())
                    <ul class="list-unstyled small mb-0">@foreach($current->movements as $movement)<li>{{ $movement->created_at->format('H:i') }} · {{ $movement->type === 'pay_in' ? '+' : '-' }}{{ $money($movement->amount) }} · {{ $movement->reason }}</li>@endforeach</ul>
            @endif
        @else
            <h2 class="h5">Open a drawer</h2>
            @if($requiresSession)<p class="small text-muted">An open drawer is required before you can take cash payments or give cash refunds.</p>@endif
            @if($branches->isEmpty())<p class="text-muted">An active branch is required.</p>@else
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-drawer">
                <i class="fas fa-plus me-1"></i>Open drawer
            </button>
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
                    <td>{{ $session->reference }}<small class="d-block text-muted">{{ $session->branch?->name }} · {{ $session->opened_at->format('Y-m-d H:i') }}@if($session->closed_at) - {{ $session->closed_at->format('H:i') }}@endif</small></td>
                    <td>{{ $session->opener?->name }}@if($session->status === 'open') <span class="badge bg-success">Open</span>@endif</td>
                    <td class="text-end">{{ $session->expected_cash !== null ? number_format((float) $session->expected_cash, 2) : '-' }}</td>
                    <td class="text-end">{{ $session->counted_cash !== null ? number_format((float) $session->counted_cash, 2) : '-' }}</td>
                    <td class="text-end {{ (float) $session->variance < 0 ? 'text-danger' : ((float) $session->variance > 0 ? 'text-warning' : '') }}">{{ $session->variance !== null ? number_format((float) $session->variance, 2) : '-' }}</td>
                    <td class="small">
                        @if($session->closing_notes)<span class="d-block">{{ $session->closing_notes }}</span>@endif
                        @if($session->reviewed_at)
                            <span class="text-success">Reviewed by {{ $session->reviewer?->name }}</span>@if($session->review_notes)<span class="d-block text-muted">{{ $session->review_notes }}</span>@endif
                        @elseif($session->review_required)
                            <span class="badge bg-warning text-dark">Needs review</span>
                            @if($canReviewSession($session))
                                <button type="button" class="btn btn-sm btn-outline-success mt-1" data-bs-toggle="modal" data-bs-target="#review-session-{{ $session->id }}">
                                    <i class="fas fa-clipboard-check me-1"></i>Mark reviewed
                                </button>
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

{{-- Modals are outside the table. --}}
@if($current)
    <div class="modal fade" id="add-cash-movement" tabindex="-1" aria-labelledby="add-cash-movement-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.cash-register.movement', $current->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-cash-movement-label"><i class="fas fa-right-left me-2 text-primary"></i>Record a pay-in or pay-out</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-5">
                            <label class="form-label" for="movementType">Type <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('type') is-invalid @enderror" id="movementType" name="type" required>
                                <option value="pay_out" @selected(old('type', 'pay_out') === 'pay_out')>Pay-out</option>
                                <option value="pay_in" @selected(old('type') === 'pay_in')>Pay-in</option>
                            </select>
                            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-7">
                            <label class="form-label" for="movementAmount">Amount <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('amount') is-invalid @enderror" id="movementAmount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required placeholder="e.g. 25000">
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="movementReason">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('reason') is-invalid @enderror" id="movementReason" name="reason" minlength="3" maxlength="255" value="{{ old('reason') }}" required placeholder="e.g. Change float top-up">
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Record
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="modal fade" id="close-drawer" tabindex="-1" aria-labelledby="close-drawer-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.cash-register.close', $current->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="close-drawer-label"><i class="fas fa-lock me-2 text-danger"></i>Close drawer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="countedCash">Counted cash <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('counted_cash') is-invalid @enderror" id="countedCash" type="number" name="counted_cash" min="0" step="0.01" value="{{ old('counted_cash') }}" required placeholder="e.g. 450000">
                        @error('counted_cash')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="form-label" for="closingNotes">Notes {{ $tolerance > 0 ? '(required if the difference exceeds ' . $money($tolerance) . ')' : '(required if the count differs)' }}</label>
                        <textarea class="form-control @error('closing_notes') is-invalid @enderror" id="closingNotes" name="closing_notes" maxlength="2000" rows="2" placeholder="Explain any difference from the expected amount">{{ old('closing_notes') }}</textarea>
                        @error('closing_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-lock me-1"></i>
                        Count and close
                    </button>
                </div>

            </form>
        </div>
    </div>
@elseif($branches->isNotEmpty())
    <div class="modal fade" id="add-drawer" tabindex="-1" aria-labelledby="add-drawer-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.cash-register.open') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-drawer-label"><i class="fas fa-cash-register me-2 text-primary"></i>Open a drawer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="registerBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select @error('branch_id') is-invalid @enderror" id="registerBranch" name="branch_id" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="openingFloat">Counted opening float <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('opening_float') is-invalid @enderror" id="openingFloat" type="number" name="opening_float" min="0" step="0.01" value="{{ old('opening_float') }}" required placeholder="e.g. 200000">
                        @error('opening_float')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="form-label" for="openingNotes">Notes (optional)</label>
                        <textarea class="form-control @error('opening_notes') is-invalid @enderror" id="openingNotes" name="opening_notes" maxlength="1000" rows="2" placeholder="Anything the next person should know">{{ old('opening_notes') }}</textarea>
                        @error('opening_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-door-open me-1"></i>
                        Open drawer
                    </button>
                </div>

            </form>
        </div>
    </div>
@endif

@foreach($reviewable as $session)
    @php $isActiveReview = $activeReviewId === $session->id; @endphp
    <div class="modal fade" id="review-session-{{ $session->id }}" tabindex="-1" aria-labelledby="review-session-label-{{ $session->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.cash-register.review', $session->id) }}">
                @csrf

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="review-session-label-{{ $session->id }}"><i class="fas fa-clipboard-check me-2 text-success"></i>Review drawer</h5>
                        <small class="text-muted">{{ $session->reference }}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <label class="form-label" for="reviewNotes{{ $session->id }}">Review outcome <span class="text-danger" aria-hidden="true">*</span></label>
                    <input class="form-control{{ $isActiveReview && $errors->has('review_notes') ? ' is-invalid' : '' }}" id="reviewNotes{{ $session->id }}" name="review_notes" minlength="5" maxlength="2000" required placeholder="e.g. Count checked against till report" value="{{ $isActiveReview ? old('review_notes') : '' }}">
                    @if($isActiveReview)
                        @error('review_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-circle-check me-1"></i>
                        Mark reviewed
                    </button>
                </div>

            </form>
        </div>
    </div>
@endforeach

@if($autoOpen)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById(@json($autoOpen));

        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
@endsection
