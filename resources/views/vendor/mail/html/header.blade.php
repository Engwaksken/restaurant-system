@props(['url'])
@php($brand = app(\App\Support\BrandResolver::class)->current())
<tr>
<td class="header" style="border-bottom: 4px solid {{ $brand->primary() }};">
<a href="{{ $url }}" style="display: inline-block; color: {{ $brand->accent() }}; font-weight: 800;">
@if($brand->absoluteLogoUrl())
<img src="{{ $brand->absoluteLogoUrl() }}" class="logo" alt="{{ $brand->name() }}" style="height: 56px; width: auto; max-height: 56px;"><br>
@endif
{!! $slot !!}
</a>
</td>
</tr>
