<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip · {{ $line->employee_name }} · {{ $run->period_start->format('F Y') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    @if($brand->faviconUrl())<link rel="icon" href="{{ $brand->faviconUrl() }}">@endif
    <style>
        body { font-family: "Inter", system-ui, sans-serif; }
        .slip-header { border-bottom: 4px solid {{ $brand->primary() }}; }
        .slip-logo { height: 48px; width: auto; border-radius: 8px; }
        .table thead th, .table .fw-bold td { color: {{ $brand->accent() }}; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="bg-white">
@php $money = fn ($v) => config('restaurant.currency') . ' ' . number_format((float) $v, 2); @endphp
<main class="container py-4" style="max-width: 720px">
    <div class="d-flex justify-content-between align-items-start mb-4 pb-3 slip-header">
        <div class="d-flex align-items-center gap-3">
            @if($brand->logoUrl())<img src="{{ $brand->logoUrl() }}" alt="" class="slip-logo">@endif
            <div>
            <h1 class="h4 mb-0">{{ $run->branch?->restaurant?->name ?? $brand->name() }}</h1>
            <span class="text-muted">{{ $run->branch?->name }}</span>
            </div>
        </div>
        <div class="text-end">
            <h2 class="h5 mb-0">Payslip</h2>
            <span class="text-muted">{{ $run->period_start->format('j M') }} – {{ $run->period_end->format('j M Y') }}</span>
            <small class="d-block text-muted">{{ $run->reference }} · {{ ucfirst($run->status) }}</small>
        </div>
    </div>
    <p><strong>Employee:</strong> {{ $line->employee_name }}</p>
    <table class="table">
        <tbody>
            <tr><td>{{ $line->pay_type === 'hourly' ? 'Hours worked: ' . number_format((float) $line->hours, 2) . ' × ' . $money($line->rate) : 'Monthly salary' }}</td><td class="text-end">{{ $money($line->base_pay) }}</td></tr>
            @foreach($line->adjustments->where('type', 'allowance') as $adjustment)<tr><td>{{ $adjustment->label }}</td><td class="text-end">{{ $money($adjustment->amount) }}</td></tr>@endforeach
            <tr class="fw-bold"><td>Gross pay</td><td class="text-end">{{ $money($line->gross_pay) }}</td></tr>
            @foreach($line->adjustments->where('type', 'deduction') as $adjustment)<tr><td>{{ $adjustment->label }}</td><td class="text-end">− {{ $money($adjustment->amount) }}</td></tr>@endforeach
            <tr class="fw-bold fs-5"><td>Net pay</td><td class="text-end">{{ $money($line->net_pay) }}</td></tr>
        </tbody>
    </table>
    @if($run->status !== 'paid')<p class="text-warning">This payslip is not final until the payroll is paid.</p>@endif
    <button class="btn btn-outline-secondary no-print" onclick="window.print()">Print</button>
    <a class="btn btn-link no-print" href="{{ route('admin.payroll.show', $run->id) }}">Back to payroll</a>
</main>
</body>
</html>
