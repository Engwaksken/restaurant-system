@extends('layouts.app')

@section('title', $run->reference)
@section('page-title', 'Payroll ' . $run->reference)

@section('content')
@php $money = fn ($v) => number_format((float) $v, 2); $draft = $run->status === 'draft'; @endphp
<p><a href="{{ route('admin.payroll.index') }}">← Payroll</a></p>
<div class="panel p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">{{ $run->branch?->name }} · {{ $run->period_start->format('F Y') }}</h2>
            <span class="badge bg-{{ ['draft' => 'warning', 'approved' => 'primary', 'paid' => 'success', 'void' => 'secondary'][$run->status] }}">{{ ucfirst($run->status) }}</span>
            <small class="text-muted ms-2">Prepared by {{ $run->preparer?->name }}@if($run->approver) · approved by {{ $run->approver->name }} {{ $run->approved_at?->format('Y-m-d') }}@endif @if($run->payer) · paid by {{ $run->payer->name }} ({{ $run->payment_reference }})@endif</small>
        </div>
        <div class="text-end">
            <div>Gross <strong>{{ $money($run->gross_total) }}</strong> · Deductions <strong>{{ $money($run->deductions_total) }}</strong></div>
            <div class="fs-5">Net payable <strong>{{ config('restaurant.currency') }} {{ $money($run->net_total) }}</strong></div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        @if($draft && $canManage)
            <form method="POST" action="{{ route('admin.payroll.recalculate', $run->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary" type="submit">Recalculate from rates &amp; attendance</button></form>
        @endif
        @if($draft && $canApprove)
            <form method="POST" action="{{ route('admin.payroll.approve', $run->id) }}" onsubmit="return confirm('Approve this payroll? Attendance in this period will be locked.')">@csrf<button class="btn btn-sm btn-primary" type="submit">Approve</button></form>
        @elseif($draft)
            <small class="text-muted align-self-center">Awaiting approval by a second authorised person.</small>
        @endif
        @if($run->status === 'approved' && $canPay)
            <form method="POST" action="{{ route('admin.payroll.pay', $run->id) }}" class="d-flex gap-2" onsubmit="return confirm('Mark this payroll as paid?')">
                @csrf
                <select class="form-select form-select-sm" name="payment_method" aria-label="Payment method">@foreach(config('restaurant.payment_methods') as $value => $label)<option value="{{ $value }}" @selected($value === 'bank')>{{ $label }}</option>@endforeach</select>
                <input class="form-control form-control-sm" name="payment_reference" maxlength="255" required placeholder="Bank batch / reference" aria-label="Payment reference">
                <button class="btn btn-sm btn-success" type="submit">Mark paid</button>
            </form>
        @endif
        @if(in_array($run->status, ['draft', 'approved'], true) && $canApprove)
            <details><summary class="small text-danger">Void</summary>
                <form method="POST" action="{{ route('admin.payroll.void', $run->id) }}" class="d-flex gap-2 mt-1">@csrf
                    <input class="form-control form-control-sm" name="reason" minlength="5" maxlength="1000" required placeholder="Reason" aria-label="Void reason">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Void run</button></form>
            </details>
        @endif
    </div>
</div>
<div class="panel p-4">
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Employee</th><th>Basis</th><th class="text-end">Base</th><th class="text-end">Allowances</th><th class="text-end">Gross</th><th class="text-end">Deductions</th><th class="text-end">Net</th><th></th></tr></thead>
        <tbody>
        @forelse($run->lines as $line)
            <tr>
                <td><strong>{{ $line->employee_name }}</strong>
                    @foreach($line->adjustments as $adjustment)
                        <small class="d-block {{ $adjustment->type === 'deduction' ? 'text-danger' : 'text-success' }}">{{ $adjustment->type === 'deduction' ? '−' : '+' }} {{ $adjustment->label }}: {{ $money($adjustment->amount) }}
                            @if($draft && $canManage && !$adjustment->is_statutory)
                                <form method="POST" action="{{ route('admin.payroll.adjustments.destroy', [$run->id, $adjustment->id]) }}" class="d-inline">@csrf @method('DELETE')<button class="btn btn-link btn-sm p-0" type="submit" aria-label="Remove {{ $adjustment->label }}">remove</button></form>
                            @endif
                        </small>
                    @endforeach
                </td>
                <td class="small">{{ $line->pay_type === 'hourly' ? number_format((float) $line->hours, 2) . ' h × ' . $money($line->rate) : 'Monthly salary' }}</td>
                <td class="text-end">{{ $money($line->base_pay) }}</td>
                <td class="text-end">{{ $money($line->allowances) }}</td>
                <td class="text-end">{{ $money($line->gross_pay) }}</td>
                <td class="text-end">{{ $money($line->deductions) }}</td>
                <td class="text-end fw-bold">{{ $money($line->net_pay) }}</td>
                <td class="text-nowrap">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payroll.payslip', [$run->id, $line->id]) }}">Payslip</a>
                    @if($draft && $canManage)
                        <details class="mt-1"><summary class="small">Adjust</summary>
                            <form method="POST" action="{{ route('admin.payroll.adjustments.store', [$run->id, $line->id]) }}" class="mt-1">@csrf
                                <select class="form-select form-select-sm mb-1" name="type" aria-label="Type"><option value="allowance">Allowance</option><option value="deduction">Deduction</option></select>
                                <input class="form-control form-control-sm mb-1" name="label" maxlength="100" required placeholder="e.g. Overtime, Advance" aria-label="Label">
                                <input class="form-control form-control-sm mb-1" type="number" name="amount" min="0.01" step="0.01" required placeholder="Amount" aria-label="Amount">
                                <button class="btn btn-sm btn-outline-primary" type="submit">Add</button></form>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted">No staff with an active pay rate in this branch.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
