@php
    $currency = $brand->currency();
    $money = fn ($value) => number_format((float) $value, 2);
    $prefix = trim((string) ($settings['prefix'] ?? 'INV'));
    $number = ($prefix !== '' ? $prefix . '-' : '') . $order->order_number;
    $issued = $order->ordered_at ?? $order->created_at;
    $dueDays = (int) ($settings['due_days'] ?? 0);
    $paid = (float) $order->payments->sum('amount');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $number }} · {{ $brand->name() }}</title>
    @if($brand->faviconUrl())<link rel="icon" href="{{ $brand->faviconUrl() }}">@endif
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; font-family: "Inter", system-ui, sans-serif; color: #1f2937; font-size: 13px; }
        .sheet { max-width: 820px; margin: 24px auto; background: #fff; padding: 40px 44px; box-shadow: 0 6px 24px rgba(0,0,0,.08); border-top: 6px solid {{ $brand->primary() }}; }
        .head { display: flex; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
        .brand { display: flex; gap: 14px; align-items: center; }
        .brand img { max-height: 60px; max-width: 160px; }
        .brand-name { font-size: 20px; font-weight: 800; color: {{ $brand->accent() }}; }
        .muted { color: #6b7280; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 30px; letter-spacing: .08em; color: {{ $brand->primary() }}; }
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; padding: 16px; background: #f9fafb; border-radius: 10px; }
        .label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: {{ $brand->onAccent() }}; background: {{ $brand->accent() }}; padding: 10px; }
        td { padding: 10px; border-bottom: 1px solid #eef0f3; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 320px; margin-left: auto; margin-top: 16px; }
        .totals td { border: 0; padding: 6px 10px; }
        .grand td { font-size: 17px; font-weight: 800; border-top: 2px solid {{ $brand->accent() }}; padding-top: 10px; }
        .due td { color: {{ (float) $order->balance > 0 ? '#b91c1c' : '#047857' }}; font-weight: 700; }
        .notes { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 32px; }
        .pre { white-space: pre-line; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; background: {{ (float) $order->balance > 0 ? '#fee2e2' : '#dcfce7' }}; color: {{ (float) $order->balance > 0 ? '#991b1b' : '#166534' }}; }
        .toolbar { text-align: center; margin: 16px 0; }
        .toolbar button { padding: 9px 20px; border: 0; border-radius: 8px; background: {{ $brand->primary() }}; color: {{ $brand->onPrimary() }}; font-weight: 600; cursor: pointer; }
        footer { margin-top: 36px; padding-top: 14px; border-top: 1px solid #eef0f3; text-align: center; color: #9ca3af; font-size: 11px; }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; padding: 0; max-width: none; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print or save as PDF</button></div>
<div class="sheet">
    <div class="head">
        <div>
            <div class="brand">
                @if($brand->logoUrl())<img src="{{ $brand->logoUrl() }}" alt="">@endif
                <div>
                    <div class="brand-name">{{ $brand->name() }}</div>
                    @if($brand->tagline())<div class="muted">{{ $brand->tagline() }}</div>@endif
                </div>
            </div>
            <div class="muted" style="margin-top:10px">
                @if($brand->address()){{ $brand->address() }}<br>@endif
                {{ collect([$brand->phone(), $brand->email()])->filter()->implode(' · ') }}
                @if(filter_var($settings['show_tin'] ?? false, FILTER_VALIDATE_BOOL) && $restaurant?->tin)<br>TIN: {{ $restaurant->tin }}@endif
            </div>
        </div>
        <div class="title">
            <h1>INVOICE</h1>
            <div style="font-weight:700; margin-top:4px">{{ $number }}</div>
            <div style="margin-top:8px"><span class="badge">{{ (float) $order->balance > 0 ? 'Payment due' : 'Paid' }}</span></div>
        </div>
    </div>

    <div class="meta">
        <div>
            <div class="label">Billed to</div>
            <strong>{{ $order->customer?->name ?? 'Walk-in customer' }}</strong>
            @if($order->customer?->phone)<div class="muted">{{ $order->customer->phone }}</div>@endif
            @if($order->customer?->email)<div class="muted">{{ $order->customer->email }}</div>@endif
            @if($order->customer?->address)<div class="muted">{{ $order->customer->address }}</div>@endif
        </div>
        <div>
            <div class="label">Issue date</div>
            {{ $issued?->format('j F Y') }}
            <div class="label" style="margin-top:10px">Due</div>
            {{ $dueDays > 0 ? $issued?->copy()->addDays($dueDays)->format('j F Y') : 'On receipt' }}
        </div>
        <div>
            <div class="label">Order</div>
            {{ $order->order_number }}
            <div class="muted">{{ \Illuminate\Support\Str::headline((string) $order->order_type) }}{{ $order->branch ? ' · ' . $order->branch->name : '' }}</div>
        </div>
    </div>

    <table>
        <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Unit price</th><th class="num">Amount ({{ $currency }})</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td><strong>{{ $item->displayName() }}</strong>@if(!empty($item->addons))<div class="muted">+ {{ collect($item->addons)->pluck('name')->implode(', ') }}</div>@endif</td>
                <td class="num">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                <td class="num">{{ $money($item->unit_price) }}</td>
                <td class="num">{{ $money($item->total) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
        @if((float) $order->discount > 0)<tr><td>Discount</td><td class="num">-{{ $money($order->discount) }}</td></tr>@endif
        @if((float) $order->tax > 0)<tr><td>Tax</td><td class="num">{{ $money($order->tax) }}</td></tr>@endif
        @if((float) $order->service_charge > 0)<tr><td>Service charge</td><td class="num">{{ $money($order->service_charge) }}</td></tr>@endif
        @if((float) $order->delivery_fee > 0)<tr><td>Delivery</td><td class="num">{{ $money($order->delivery_fee) }}</td></tr>@endif
        <tr class="grand"><td>Total</td><td class="num">{{ $currency }} {{ $money($order->total) }}</td></tr>
        @if($paid > 0)<tr><td>Paid</td><td class="num">-{{ $money($paid) }}</td></tr>@endif
        <tr class="due"><td>Balance due</td><td class="num">{{ $currency }} {{ $money($order->balance) }}</td></tr>
    </table>

    @if(!empty($settings['bank_details']) || !empty($settings['payment_terms']))
        <div class="notes">
            @if(!empty($settings['bank_details']))
                <div><div class="label">How to pay</div><div class="pre">{{ $settings['bank_details'] }}</div><div class="muted" style="margin-top:6px">Reference: {{ $number }}</div></div>
            @endif
            @if(!empty($settings['payment_terms']))
                <div><div class="label">Payment terms</div><div class="pre">{{ $settings['payment_terms'] }}</div></div>
            @endif
        </div>
    @endif

    <footer>
        @if(!empty($settings['notes']))<div class="pre" style="color:#4b5563; margin-bottom:6px">{{ $settings['notes'] }}</div>@endif
        {{ $brand->name() }} · {{ $number }}
    </footer>
</div>
</body>
</html>
