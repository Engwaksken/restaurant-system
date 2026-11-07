@extends('mail.layouts.branded')

@section('title', 'Order ' . $order->order_number . ' received')
@section('preheader', 'Thanks for your order! We have received order ' . $order->order_number . ' and will start preparing it shortly.')

@section('content')
@php
    $currency = $brand->currency();
    $firstName = $order->customer?->name ? \Illuminate\Support\Str::of($order->customer->name)->explode(' ')->first() : null;
@endphp
<h1 style="margin:0 0 8px;font-size:22px;line-height:1.3;color:#18181b;">Thanks{{ $firstName ? ', ' . $firstName : '' }}! We've got your order.</h1>
<p style="margin:0 0 24px;color:#52525b;">Order <strong style="color:#18181b;">{{ $order->order_number }}</strong> was received {{ $order->ordered_at?->format('j M Y, g:i a') }}. We'll let the kitchen know right away.</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7;border-radius:12px;border-collapse:separate;">
    <tr>
        <td style="padding:16px 18px;border-bottom:1px solid #e4e4e7;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#71717a;">Order summary</td>
    </tr>
    @foreach($order->items as $item)
        <tr>
            <td style="padding:12px 18px;border-bottom:1px solid #f4f4f5;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:14px;color:#18181b;">
                            <strong>{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}×</strong> {{ $item->displayName() }}
                            @if(!empty($item->addons))
                                <div style="font-size:12px;color:#71717a;">+ {{ collect($item->addons)->pluck('name')->implode(', ') }}</div>
                            @endif
                        </td>
                        <td align="right" style="font-size:14px;color:#18181b;white-space:nowrap;">{{ $currency }} {{ number_format((float) $item->total, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    @endforeach
    <tr>
        <td style="padding:14px 18px;background:#fafafa;border-radius:0 0 12px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#52525b;">
                <tr><td>Subtotal</td><td align="right">{{ $currency }} {{ number_format((float) $order->subtotal, 2) }}</td></tr>
                @if((float) $order->tax > 0)<tr><td>Tax</td><td align="right">{{ $currency }} {{ number_format((float) $order->tax, 2) }}</td></tr>@endif
                @if((float) $order->delivery_fee > 0)<tr><td>Delivery</td><td align="right">{{ $currency }} {{ number_format((float) $order->delivery_fee, 2) }}</td></tr>@endif
                @if((float) $order->discount > 0)<tr><td>Discount</td><td align="right">-{{ $currency }} {{ number_format((float) $order->discount, 2) }}</td></tr>@endif
                <tr>
                    <td style="padding-top:8px;font-size:16px;font-weight:700;color:#18181b;">Total</td>
                    <td align="right" style="padding-top:8px;font-size:16px;font-weight:700;color:#18181b;">{{ $currency }} {{ number_format((float) $order->total, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 8px;">
    <tr>
        <td style="border-radius:999px;background:{{ $brand->primary() }};">
            <a href="{{ route('order.track', $trackingToken) }}" style="display:inline-block;padding:14px 28px;color:{{ $brand->onPrimary() }};font-weight:700;font-size:15px;text-decoration:none;border-radius:999px;">Track your order</a>
        </td>
    </tr>
</table>
<p style="margin:16px 0 0;font-size:12px;color:#a1a1aa;text-align:center;">Keep this link private. Anyone with it can see the status of this order.</p>
@endsection
