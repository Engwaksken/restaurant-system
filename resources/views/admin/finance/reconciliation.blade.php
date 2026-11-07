@extends('layouts.app')

@section('title', 'Reconciliation')
@section('page-title', 'Payment Reconciliation')

@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); @endphp
<div class="panel p-4 mb-4">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label" for="recFrom">From</label><input class="form-control" type="date" id="recFrom" name="date_from" value="{{ $from->toDateString() }}"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="recTo">To</label><input class="form-control" type="date" id="recTo" name="date_to" value="{{ $to->toDateString() }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="recMethod">Method</label>
            <select class="form-select" id="recMethod" name="method"><option value="">All non-cash</option>@foreach($methods as $value => $label)<option value="{{ $value }}" @selected(($filters['method'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-6 col-md-3"><label class="form-label" for="recStatus">Show</label>
            <select class="form-select" id="recStatus" name="status">@foreach(['unreconciled' => 'Unreconciled', 'disputed' => 'Disputed', 'matched' => 'Matched'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Show</button></div>
    </form>
    <p class="small text-muted mt-2 mb-0">Cash is reconciled by cash register counts and is not listed here.</p>
</div>

<div class="panel p-4 mb-4">
    <h2 class="h5">Summary · {{ $from->format('j M') }} – {{ $to->format('j M Y') }}</h2>
    <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th>Method</th><th class="text-end">Payments</th><th class="text-end">Total</th><th class="text-end">Matched</th><th class="text-end">Unreconciled</th><th class="text-end">Disputed</th></tr></thead>
        <tbody>
        @forelse($summary as $row)
            <tr><td>{{ $methods[$row->payment_method] ?? $row->payment_method }}</td><td class="text-end">{{ $row->payments }}</td><td class="text-end">{{ $money($row->total) }}</td>
                <td class="text-end text-success">{{ $money($row->matched) }}</td><td class="text-end {{ (float) $row->unreconciled > 0 ? 'text-warning' : '' }}">{{ $money($row->unreconciled) }}</td><td class="text-end {{ (float) $row->disputed > 0 ? 'text-danger' : '' }}">{{ $money($row->disputed) }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-muted">No non-cash payments in this period.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="panel p-4">
    <h2 class="h5">{{ ucfirst($status) }} payments</h2>
    <form method="POST" action="{{ route('admin.reconciliation.match') }}" id="matchForm">
        @csrf
    </form>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr>@if($canManage && $status !== 'matched')<th><span class="visually-hidden">Select</span></th>@endif<th>Paid</th><th>Receipt</th><th>Method / reference</th><th class="text-end">Amount</th><th>Order</th><th>Reconciliation</th></tr></thead>
        <tbody>
        @forelse($payments as $payment)
            <tr>
                @if($canManage && $status !== 'matched')<td><input class="form-check-input" type="checkbox" name="payment_ids[]" value="{{ $payment->id }}" form="matchForm" aria-label="Select {{ $payment->receipt_number }}"></td>@endif
                <td class="text-nowrap">{{ $payment->paid_at?->format('Y-m-d H:i') }}</td>
                <td class="small">{{ $payment->receipt_number }}</td>
                <td>{{ $methods[$payment->payment_method] ?? $payment->payment_method }}<small class="d-block text-muted">{{ $payment->transaction_reference ?? 'No reference' }}</small></td>
                <td class="text-end">{{ $money($payment->amount) }}</td>
                <td>@if($payment->order)<a href="{{ route('admin.orders.show', $payment->order) }}">{{ $payment->order->order_number }}</a><small class="d-block text-muted">{{ $payment->order->branch?->name }}</small>@endif</td>
                <td class="small">
                    @if($payment->statement_reference)<span class="d-block">Statement: {{ $payment->statement_reference }}</span>@endif
                    @if($payment->reconciliation_note)<span class="d-block">{{ $payment->reconciliation_note }}</span>@endif
                    @if($payment->reconciler)<span class="d-block text-muted">{{ $payment->reconciler->name }}, {{ $payment->reconciled_at?->format('Y-m-d') }}</span>@endif
                    @if($canManage)
                        <details>
                            <summary>{{ $payment->reconciliation_status === 'unreconciled' ? 'Dispute' : 'Reopen / dispute' }}</summary>
                            <form method="POST" action="{{ route('admin.reconciliation.dispute', $payment->id) }}" class="mt-1">
                                @csrf @method('PATCH')
                                <select class="form-select form-select-sm mb-1" name="status" aria-label="New status">
                                    <option value="disputed">Flag as disputed</option>
                                    @if($payment->reconciliation_status !== 'unreconciled')<option value="unreconciled">Reopen (unreconciled)</option>@endif
                                </select>
                                <input class="form-control form-control-sm mb-1" name="note" minlength="5" maxlength="1000" required placeholder="What is wrong?" aria-label="Note">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Save</button>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted">Nothing to show.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $payments->links() }}
    @if($canManage && $status !== 'matched' && $payments->isNotEmpty())
        <div class="row g-2 align-items-end border-top pt-3">
            <div class="col-md-5"><label class="form-label" for="statementRef">Statement reference for selected payments</label><input class="form-control" id="statementRef" name="statement_reference" maxlength="255" required form="matchForm" placeholder="e.g. MTN statement 2026-11-07"></div>
            <div class="col-md-5"><label class="form-label" for="matchNote">Note (optional)</label><input class="form-control" id="matchNote" name="note" maxlength="1000" form="matchForm"></div>
            <div class="col-md-2"><button class="btn btn-success w-100" type="submit" form="matchForm">Mark matched</button></div>
        </div>
    @endif
</div>
@endsection
