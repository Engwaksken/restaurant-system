@extends('layouts.app')

@section('title', $run->reference)
@section('page-title', 'Payroll ' . $run->reference)

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 2);
    $draft = $run->status === 'draft';
    $adjustLines = $draft && $canManage ? collect($run->lines) : collect();
    $activeAdjustLineId = session()->hasOldInput('label') && $errors->any() && $adjustLines->count() === 1 ? $adjustLines->first()->id : null;
    $autoOpen = null;
    if ($errors->any()) {
        if (session()->hasOldInput('payment_reference')) {
            $autoOpen = 'pay-payroll';
        } elseif ($activeAdjustLineId) {
            $autoOpen = 'add-adjustment-' . $activeAdjustLineId;
        }
    }
@endphp
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
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#approve-payroll"><i class="fas fa-circle-check me-1"></i>Approve</button>
        @elseif($draft)
            <small class="text-muted align-self-center">Awaiting approval by a second authorised person.</small>
        @endif
        @if($run->status === 'approved' && $canPay)
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#pay-payroll"><i class="fas fa-money-bill-transfer me-1"></i>Mark paid</button>
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
                    <small class="d-block {{ $adjustment->type === 'deduction' ? 'text-danger' : 'text-success' }}">{{ $adjustment->type === 'deduction' ? '-' : '+' }} {{ $adjustment->label }}: {{ $money($adjustment->amount) }}
                            @if($draft && $canManage && !$adjustment->is_statutory)
                                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1 ms-1" data-bs-toggle="modal" data-bs-target="#delete-adjustment-{{ $adjustment->id }}" title="Remove {{ $adjustment->label }}" aria-label="Remove {{ $adjustment->label }}"><i class="fas fa-trash"></i></button>
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
                        <button type="button" class="btn btn-sm btn-outline-primary mt-1" data-bs-toggle="modal" data-bs-target="#add-adjustment-{{ $line->id }}">
                            <i class="fas fa-plus me-1"></i>Adjust
                        </button>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-muted">No staff with an active pay rate in this branch.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

{{-- Modals are outside the table. --}}
@if($draft && $canApprove)
    <div class="modal fade" id="approve-payroll" tabindex="-1" aria-labelledby="approve-payroll-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.payroll.approve', $run->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="approve-payroll-label"><i class="fas fa-circle-check me-2 text-primary"></i>Approve payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-0">Approve this payroll? Attendance in this period will be locked.</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-circle-check me-1"></i>
                        Approve
                    </button>
                </div>

            </form>
        </div>
    </div>
@endif

@if($run->status === 'approved' && $canPay)
    <div class="modal fade" id="pay-payroll" tabindex="-1" aria-labelledby="pay-payroll-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.payroll.pay', $run->id) }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="pay-payroll-label"><i class="fas fa-money-bill-transfer me-2 text-success"></i>Mark payroll as paid</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted">Mark this payroll as paid? Enter the bank batch or reference used for the payment.</p>

                    <div class="mb-3">
                        <label class="form-label" for="payMethod">Payment method <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="payMethod" name="payment_method" required>
                            @foreach(config('restaurant.payment_methods') as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'bank') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="payReference">Payment reference <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('payment_reference') is-invalid @enderror" id="payReference" name="payment_reference" maxlength="255" value="{{ old('payment_reference') }}" required placeholder="e.g. Bank batch 2026-11">
                        @error('payment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-money-bill-transfer me-1"></i>
                        Mark paid
                    </button>
                </div>

            </form>
        </div>
    </div>
@endif

@foreach($run->lines as $line)
    @if($draft && $canManage)
        @php $isActiveAdjust = $activeAdjustLineId === $line->id; @endphp
        <div class="modal fade" id="add-adjustment-{{ $line->id }}" tabindex="-1" aria-labelledby="add-adjustment-label-{{ $line->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.payroll.adjustments.store', [$run->id, $line->id]) }}">
                    @csrf

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="add-adjustment-label-{{ $line->id }}"><i class="fas fa-plus-circle me-2 text-primary"></i>Add adjustment</h5>
                            <small class="text-muted">{{ $line->employee_name }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="adjustType{{ $line->id }}">Type <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select{{ $isActiveAdjust && $errors->has('type') ? ' is-invalid' : '' }}" id="adjustType{{ $line->id }}" name="type" required>
                                <option value="allowance" @selected(($isActiveAdjust ? old('type', 'allowance') : 'allowance') === 'allowance')>Allowance</option>
                                <option value="deduction" @selected(($isActiveAdjust ? old('type') : null) === 'deduction')>Deduction</option>
                            </select>
                            @if($isActiveAdjust)
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="adjustLabel{{ $line->id }}">Label <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control{{ $isActiveAdjust && $errors->has('label') ? ' is-invalid' : '' }}" id="adjustLabel{{ $line->id }}" name="label" maxlength="100" required placeholder="e.g. Overtime, Advance" value="{{ $isActiveAdjust ? old('label') : '' }}">
                            @if($isActiveAdjust)
                                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>

                        <div>
                            <label class="form-label" for="adjustAmount{{ $line->id }}">Amount <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control{{ $isActiveAdjust && $errors->has('amount') ? ' is-invalid' : '' }}" id="adjustAmount{{ $line->id }}" type="number" name="amount" min="0.01" step="0.01" required placeholder="e.g. 50000" value="{{ $isActiveAdjust ? old('amount') : '' }}">
                            @if($isActiveAdjust)
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Add adjustment
                        </button>
                    </div>

                </form>
            </div>
        </div>
    @endif
@endforeach

@foreach($run->lines as $line)
    @foreach($line->adjustments as $adjustment)
        @if($draft && $canManage && !$adjustment->is_statutory)
            <div class="modal fade" id="delete-adjustment-{{ $adjustment->id }}" tabindex="-1" aria-labelledby="delete-adjustment-label-{{ $adjustment->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('admin.payroll.adjustments.destroy', [$run->id, $adjustment->id]) }}">
                        @csrf
                        @method('DELETE')

                        <div class="modal-header">
                            <h5 class="modal-title" id="delete-adjustment-label-{{ $adjustment->id }}"><i class="fas fa-trash me-2 text-danger"></i>Remove adjustment</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <p class="mb-0">
                                Remove <strong>{{ $adjustment->label }}</strong> ({{ $money($adjustment->amount) }}) from {{ $line->employee_name }}'s pay in this payroll run?
                            </p>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="fas fa-xmark me-1"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i>
                                Remove
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        @endif
    @endforeach
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
