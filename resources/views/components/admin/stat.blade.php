@props([
    'label',
    'value',
    'icon' => 'fas fa-chart-simple',
    'tone' => 'brand',
    'hint' => null,
    'href' => null,
])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['stat-card', 'tone-' . $tone]) }}>
    <span class="stat-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
    <span class="stat-body">
        <span class="stat-label">{{ $label }}</span>
        <span class="stat-value">{{ $value }}</span>
        @if($hint)
            <span class="stat-hint">{{ $hint }}</span>
        @endif
    </span>
</{{ $tag }}>
