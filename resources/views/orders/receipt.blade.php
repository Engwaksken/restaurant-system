@php
    $width = $settings['paper_width'] ?? '80';
    $on = fn (string $key) => filter_var($settings[$key] ?? false, FILTER_VALIDATE_BOOL);
    $currency = $brand->currency();
    $money = fn ($value) => number_format((float) $value, 2);
    $paid = $order->payments->sum('amount');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $order->order_number }} · {{ $brand->name() }}</title>
    @if($brand->faviconUrl())<link rel="icon" href="{{ $brand->faviconUrl() }}">@endif
    <style>
        @page { size: {{ $width === 'a4' ? 'A4' : $width . 'mm auto' }}; margin: {{ $width === 'a4' ? '16mm' : '3mm' }}; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; font-family: "Courier New", ui-monospace, monospace; color: #111; font-size: {{ $width === '58' ? '11px' : '12.5px' }}; }
        .receipt { width: {{ $width === 'a4' ? '420px' : ($width === '58' ? '58mm' : '80mm') }}; margin: 24px auto; padding: 14px 12px; background: #fff; box-shadow: 0 4px 18px rgba(0,0,0,.08); }
        .center { text-align: center; }
        .logo { max-height: 56px; max-width: 70%; margin-bottom: 6px; }
        .name { font-size: 1.3em; font-weight: bold; }
        .muted { color: #444; }
        hr { border: 0; border-top: 1px dashed #888; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .total td { font-weight: bold; font-size: 1.15em; padding-top: 4px; }
        .pre { white-space: pre-line; }
        .toolbar { text-align: center; margin: 16px 0; font-family: system-ui, sans-serif; }
        .toolbar button { padding: 8px 18px; border: 0; border-radius: 8px; background: {{ $brand->primary() }}; color: {{ $brand->onPrimary() }}; font-weight: 600; cursor: pointer; }
        @media print { body { background: #fff; } .receipt { margin: 0; box-shadow: none; width: auto; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Print receipt</button></div>
<div class="receipt">
    <div class="center">
        @if($on('show_logo') && $brand->logoUrl())<img class="logo" src="{{ $brand->logoUrl() }}" alt=""><br>@endif
        <div class="name">{{ $brand->name() }}</div>
        @if($on('show_address'))
            @if($brand->address())<div class="muted">{{ $brand->address() }}</div>@endif
            @if($brand->phone())<div class="muted">Tel: {{ $brand->phone() }}</div>@endif
        @endif
        @if($on('show_tin') && $restaurant?->tin)<div class="muted">TIN: {{ $restaurant->tin }}</div>@endif
        @if(!empty($settings['header_text']))<div class="pre" style="margin-top:6px">{{ $settings['header_text'] }}</div>@endif
    </div>
    <hr>
    <table>
        <tr><td>Receipt</td><td class="num">{{ $order->order_number }}</td></tr>
        <tr><td>Date</td><td class="num">{{ ($order->ordered_at ?? $order->created_at)?->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Type</td><td class="num">{{ \Illuminate\Support\Str::headline((string) $order->order_type) }}</td></tr>
        @if($order->branch)<tr><td>Branch</td><td class="num">{{ $order->branch->name }}</td></tr>@endif
        @if($on('show_cashier') && $order->waiter)<tr><td>Served by</td><td class="num">{{ $order->waiter->name }}</td></tr>@endif
        @if($order->customer)<tr><td>Customer</td><td class="num">{{ $order->customer->name }}</td></tr>@endif
    </table>
    <hr>
    <table>
        @foreach($order->items as $item)
            <tr><td colspan="2">{{ $item->displayName() }}</td></tr>
            @if(!empty($item->addons))<tr><td colspan="2" class="muted">&nbsp;+ {{ collect($item->addons)->pluck('name')->implode(', ') }}</td></tr>@endif
            <tr><td class="muted">&nbsp;{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }} x {{ $money($item->unit_price) }}</td><td class="num">{{ $money($item->total) }}</td></tr>
        @endforeach
    </table>
    <hr>
    <table>
        <tr><td>Subtotal</td><td class="num">{{ $money($order->subtotal) }}</td></tr>
        @if((float) $order->discount > 0)<tr><td>Discount</td><td class="num">-{{ $money($order->discount) }}</td></tr>@endif
        @if((float) $order->tax > 0)<tr><td>Tax</td><td class="num">{{ $money($order->tax) }}</td></tr>@endif
        @if((float) $order->service_charge > 0)<tr><td>Service</td><td class="num">{{ $money($order->service_charge) }}</td></tr>@endif
        @if((float) $order->delivery_fee > 0)<tr><td>Delivery</td><td class="num">{{ $money($order->delivery_fee) }}</td></tr>@endif
        <tr class="total"><td>TOTAL {{ $currency }}</td><td class="num">{{ $money($order->total) }}</td></tr>
        @foreach($order->payments as $payment)
            <tr><td class="muted">Paid ({{ \Illuminate\Support\Str::headline((string) $payment->payment_method) }})</td><td class="num">{{ $money($payment->amount) }}</td></tr>
        @endforeach
        @if((float) $order->balance > 0)<tr><td><strong>Balance due</strong></td><td class="num"><strong>{{ $money($order->balance) }}</strong></td></tr>@endif
    </table>
    @if(!empty($settings['footer_text']))
        <hr>
        <div class="center pre">{{ $settings['footer_text'] }}</div>
    @endif
</div>
</body>
</html>
