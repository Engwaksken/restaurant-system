{{--
    Branded, email-client-safe layout (tables + inline styles).
    Expects $brand (App\Support\Brand). Sections: title, preheader, content.
--}}
@php
    $primary = $brand->primary();
    $accent = $brand->accent();
    $logo = $brand->absoluteLogoUrl();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>@yield('title', $brand->name())</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#18181b;-webkit-font-smoothing:antialiased;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;">
    <tr>
        <td align="center" style="padding:32px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
                {{-- Header --}}
                <tr>
                    <td style="background:{{ $accent }};border-radius:16px 16px 0 0;padding:24px 32px;">
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                @if($logo)
                                    <td style="padding-right:12px;"><img src="{{ $logo }}" alt="{{ $brand->name() }}" height="44" style="display:block;height:44px;width:auto;border-radius:8px;background:#ffffff;"></td>
                                @else
                                    <td style="padding-right:12px;">
                                        <div style="width:44px;height:44px;line-height:44px;text-align:center;border-radius:10px;background:{{ $primary }};color:{{ $brand->onPrimary() }};font-weight:800;font-size:16px;">{{ $brand->initials() }}</div>
                                    </td>
                                @endif
                                <td>
                                    <div style="color:{{ $brand->onAccent() }};font-size:18px;font-weight:700;">{{ $brand->name() }}</div>
                                    @if($brand->tagline())
                                        <div style="color:{{ $brand->onAccent() }};opacity:.7;font-size:13px;">{{ $brand->tagline() }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr><td style="height:4px;background:{{ $primary }};font-size:0;line-height:0;">&nbsp;</td></tr>

                {{-- Body --}}
                <tr>
                    <td style="background:#ffffff;padding:32px;border-radius:0 0 16px 16px;font-size:15px;line-height:1.6;">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 16px;text-align:center;color:#71717a;font-size:12px;line-height:1.6;">
                        @if($brand->emailFooter())
                            <p style="margin:0 0 10px;color:#52525b;">{!! nl2br(e($brand->emailFooter())) !!}</p>
                        @endif
                        <p style="margin:0;">
                            <strong style="color:#3f3f46;">{{ $brand->name() }}</strong>
                            @if($brand->address())<br>{{ $brand->address() }}@endif
                            @if($brand->phone() || $brand->email())
                                <br>{{ collect([$brand->phone(), $brand->email()])->filter()->implode(' · ') }}
                            @endif
                        </p>
                        @if($brand->socialLinks())
                            <p style="margin:10px 0 0;">
                                @foreach($brand->socialLinks() as $link)
                                    <a href="{{ $link['url'] }}" style="color:{{ $primary }};text-decoration:none;margin:0 6px;">{{ $link['label'] }}</a>
                                @endforeach
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
