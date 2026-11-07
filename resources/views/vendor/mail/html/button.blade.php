@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php($brand = app(\App\Support\BrandResolver::class)->current())
@php($style = $color === 'primary' ? 'background-color: ' . $brand->primary() . '; border-color: ' . $brand->primary() . '; border-top: 8px solid ' . $brand->primary() . '; border-bottom: 8px solid ' . $brand->primary() . '; border-left: 18px solid ' . $brand->primary() . '; border-right: 18px solid ' . $brand->primary() . '; color: ' . $brand->onPrimary() . ';' : '')
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td>
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="{{ $style }}">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
