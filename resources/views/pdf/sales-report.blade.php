<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 28px 30px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1f2937; }
    .header { border-bottom: 3px solid {{ $brand->primary() }}; padding-bottom: 10px; margin-bottom: 14px; }
    .header td { vertical-align: middle; }
    .brand-name { font-size: 17px; font-weight: bold; color: {{ $brand->accent() }}; }
    .brand-meta { color: #6b7280; font-size: 9px; }
    .title { font-size: 13px; font-weight: bold; color: {{ $brand->primary() }}; text-align: right; }
    .logo { height: 42px; }
    table.data { width: 100%; border-collapse: collapse; }
    table.data th { background: {{ $brand->accent() }}; color: {{ $brand->onAccent() }}; font-weight: bold; padding: 6px; text-align: left; font-size: 8.5px; text-transform: uppercase; }
    table.data td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; }
    table.data tr:nth-child(even) td { background: #f9fafb; }
    table.data tfoot td { font-weight: bold; border-top: 2px solid {{ $brand->accent() }}; background: #f3f4f6; }
    .num { text-align: right; }
    .footer { margin-top: 16px; color: #9ca3af; font-size: 8px; text-align: center; }
</style>
</head>
<body>
@php
    $sum = fn (string $column) => $orders->sum(fn ($o) => (float) $o->{$column});
@endphp
<table class="header" width="100%">
    <tr>
        <td width="60%">
            <table><tr>
                @if($brand->logoPath())
                    <td style="padding-right:10px"><img class="logo" src="{{ $brand->logoPath() }}" alt=""></td>
                @endif
                <td>
                    <div class="brand-name">{{ $brand->name() }}</div>
                    <div class="brand-meta">{{ collect([$brand->address(), $brand->phone(), $brand->email()])->filter()->implode(' · ') }}</div>
                </td>
            </tr></table>
        </td>
        <td width="40%">
            <div class="title">Sales Report</div>
            <div class="brand-meta" style="text-align:right">Generated {{ now()->format('d M Y H:i') }} · {{ $orders->count() }} orders</div>
        </td>
    </tr>
</table>

<table class="data">
    <thead>
    <tr><th>Order</th><th>Customer</th><th>Type</th><th class="num">Subtotal</th><th class="num">Discount</th><th class="num">Tax</th><th class="num">Total</th><th class="num">Paid</th><th class="num">Balance</th><th>Status</th><th>Date</th></tr>
    </thead>
    <tbody>
    @foreach($orders as $o)
        <tr><td>{{ $o->order_number }}</td><td>{{ $o->customer?->name ?? 'Walk-in' }}</td><td>{{ \Illuminate\Support\Str::headline($o->order_type) }}</td><td class="num">{{ number_format((float)$o->subtotal) }}</td><td class="num">{{ number_format((float)$o->discount) }}</td><td class="num">{{ number_format((float)$o->tax) }}</td><td class="num">{{ number_format((float)$o->total) }}</td><td class="num">{{ number_format((float)$o->paid_amount) }}</td><td class="num">{{ number_format((float)$o->balance) }}</td><td>{{ ucfirst($o->status) }}</td><td>{{ $o->ordered_at?->format('d/m/Y H:i') }}</td></tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr><td colspan="3">Totals ({{ $brand->currency() }})</td><td class="num">{{ number_format($sum('subtotal')) }}</td><td class="num">{{ number_format($sum('discount')) }}</td><td class="num">{{ number_format($sum('tax')) }}</td><td class="num">{{ number_format($sum('total')) }}</td><td class="num">{{ number_format($sum('paid_amount')) }}</td><td class="num">{{ number_format($sum('balance')) }}</td><td colspan="2"></td></tr>
    </tfoot>
</table>
<div class="footer">{{ $brand->name() }} · Confidential management report</div>
</body>
</html>
