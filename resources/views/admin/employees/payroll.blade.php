@extends('layouts.app')

@section('title', 'Payroll')
@section('page-title', 'Payroll')

@section('content')
@php $statusClasses = ['draft' => 'warning', 'approved' => 'primary', 'paid' => 'success', 'void' => 'secondary']; @endphp
<div class="row g-4">
    <div class="col-lg-7"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0">Payroll runs</h2></div>
        @if($canManage)
            <form method="POST" action="{{ route('admin.payroll.store') }}" class="row g-2 align-items-end mb-3">
                @csrf
                <div class="col-sm-5"><label class="form-label" for="runBranch">Branch</label><select class="form-select" id="runBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select></div>
                <div class="col-sm-4"><label class="form-label" for="runMonth">Month</label><input class="form-control" id="runMonth" type="month" name="month" value="{{ old('month', now()->subMonth()->format('Y-m')) }}" required></div>
                <div class="col-sm-3"><button class="btn btn-primary w-100" type="submit">Prepare draft</button></div>
            </form>
        @endif
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
        <h2 class="h5">Pay rates</h2>
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
        @if($canManage)
            <form method="POST" action="{{ route('admin.payroll.compensation') }}">
                @csrf
                <h3 class="h6">Set a pay rate</h3>
                <select class="form-select mb-2" name="user_id" required aria-label="Staff member">@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
                <select class="form-select mb-2" name="branch_id" required aria-label="Paying branch">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                <div class="row g-2 mb-2">
                    <div class="col-5"><select class="form-select" name="pay_type" aria-label="Pay type"><option value="monthly">Monthly</option><option value="hourly">Hourly</option></select></div>
                    <div class="col-7"><input class="form-control" type="number" name="rate" min="0.01" step="0.01" required placeholder="Rate" aria-label="Rate"></div>
                </div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="rateActive" checked><label class="form-check-label" for="rateActive">On payroll</label></div>
                <button class="btn btn-outline-primary btn-sm" type="submit">Save rate</button>
            </form>
        @endif
    </div></div>
</div>
@endsection
