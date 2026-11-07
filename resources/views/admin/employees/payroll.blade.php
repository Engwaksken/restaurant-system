@extends('layouts.app')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $statusClasses = ['draft' => 'warning', 'approved' => 'primary', 'paid' => 'success', 'void' => 'secondary'];
    $autoOpen = null;
    if ($errors->any()) {
        if (session()->hasOldInput('pay_type')) {
            $autoOpen = 'add-pay-rate';
        } elseif (session()->hasOldInput('month')) {
            $autoOpen = 'add-payroll-run';
        }
    }
@endphp
<div class="row g-4">
    <div class="col-lg-7"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0">Payroll runs</h2>
            @if($canManage)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-payroll-run">
                    <i class="fas fa-plus me-1"></i>
                    Prepare draft
                </button>
            @endif
        </div>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Run</th><th>Period</th><th>Status</th><th class="text-end">Gross</th><th class="text-end">Net</th></tr></thead>
            <tbody>
            @forelse($runs as $run)
                <tr>
                    <td><a href="{{ route('admin.payroll.show', $run->id) }}">{{ $run->reference }}</a><small class="d-block text-muted">{{ $run->branch?->name }}</small></td>
                    <td>{{ $run->period_start->format('F Y') }}</td>
                    <td><span class="badge bg-{{ $statusClasses[$run->status] ?? 'secondary' }}">{{ ucfirst($run->status) }}</span></td>
                    <td class="text-end">{{ number_format((float) $run->gross_total, 2) }}</td>
                    <td class="text-end">{{ number_format((float) $run->net_total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">No payroll runs yet.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $runs->links() }}
    </div></div>
    <div class="col-lg-5"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0">Pay rates</h2>
            @if($canManage)
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#add-pay-rate">
                    <i class="fas fa-plus me-1"></i>
                    Set a pay rate
                </button>
            @endif
        </div>
        <p class="small text-muted">
            Monthly staff receive their full salary per monthly run. Hourly staff are paid for clocked hours in the month.
            <br>
            @if($statutory === [])
                <strong>No statutory deductions are configured.</strong>
                Set them in <code>config/restaurant.php</code> (payroll.statutory_deductions) for your jurisdiction.
            @else
                Statutory deductions:
                {{ collect($statutory)->map(fn ($rule) => $rule['label'] . ' (' . (($rule['type'] ?? 'fixed') === 'percent' ? $rule['value'] . '%' : number_format((float) $rule['value'], 2)) . ')')->implode(', ') }}
            @endif
        </p>
        <ul class="list-group list-group-flush mb-3">
            @forelse($compensations as $compensation)
                <li class="list-group-item px-0 d-flex justify-content-between {{ $compensation->is_active && $compensation->user?->is_active ? '' : 'text-muted' }}">
                    <span>{{ $compensation->user?->name }}<small class="d-block text-muted">{{ $compensation->branch?->name }}{{ $compensation->is_active ? '' : ' · inactive' }}</small></span>
                    <span>{{ number_format((float) $compensation->rate, 2) }} / {{ $compensation->pay_type === 'hourly' ? 'hour' : 'month' }}</span>
                </li>
            @empty
                <li class="list-group-item px-0 text-muted">No pay rates set.</li>
            @endforelse
        </ul>
    </div></div>
</div>

{{-- Modals are outside the table. --}}
@if($canManage)
    <div class="modal fade" id="add-payroll-run" tabindex="-1" aria-labelledby="add-payroll-run-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.payroll.store') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-payroll-run-label"><i class="fas fa-file-circle-plus me-2 text-primary"></i>Prepare payroll draft</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="runBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select @error('branch_id') is-invalid @enderror" id="runBranch" name="branch_id" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="form-label" for="runMonth">Month <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('month') is-invalid @enderror" id="runMonth" type="month" name="month" value="{{ old('month', now()->subMonth()->format('Y-m')) }}" required>
                        @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <p class="small text-muted mt-3 mb-0">
                        The draft uses the pay rates and attendance for the month. It needs approval before it can be paid.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-circle-plus me-1"></i>
                        Prepare draft
                    </button>
                </div>

            </form>
        </div>
    </div>

    <div class="modal fade" id="add-pay-rate" tabindex="-1" aria-labelledby="add-pay-rate-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('admin.payroll.compensation') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-pay-rate-label"><i class="fas fa-money-bill-wave me-2 text-primary"></i>Set a pay rate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="rateUser">Staff member <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select @error('user_id') is-invalid @enderror" id="rateUser" name="user_id" required>
                            @foreach($staff as $member)
                                <option value="{{ $member->id }}" @selected((string) old('user_id') === (string) $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="rateBranch">Paying branch <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select @error('branch_id') is-invalid @enderror" id="rateBranch" name="branch_id" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-5">
                            <label class="form-label" for="ratePayType">Pay type <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('pay_type') is-invalid @enderror" id="ratePayType" name="pay_type" required>
                                <option value="monthly" @selected(old('pay_type', 'monthly') === 'monthly')>Monthly</option>
                                <option value="hourly" @selected(old('pay_type') === 'hourly')>Hourly</option>
                            </select>
                            @error('pay_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-7">
                            <label class="form-label" for="rateAmount">Rate <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('rate') is-invalid @enderror" id="rateAmount" type="number" name="rate" min="0.01" step="0.01" value="{{ old('rate') }}" required placeholder="e.g. 1500000">
                            @error('rate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input @error('is_active') is-invalid @enderror" type="checkbox" name="is_active" value="1" id="rateActive" @checked(session()->hasOldInput('pay_type') ? old('is_active') : true)>
                        <label class="form-check-label" for="rateActive">On payroll</label>
                        @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Save rate
                    </button>
                </div>

            </form>
        </div>
    </div>
@endif

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
