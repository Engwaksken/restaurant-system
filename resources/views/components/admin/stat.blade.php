@props([
    'label',
    'value',
    'icon' => 'fas fa-chart-simple',
    'tone' => 'brand',
    'hint' => null,
    'trend' => null,
    'href' => null,
])
@php
    $tag = $href ? 'a' : 'div';
    $trendIcon = match ($trend) {
        'up' => 'fas fa-arrow-trend-up',
        'down' => 'fas fa-arrow-trend-down',
        default => null,
    };
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['stat-card', 'tone-' . $tone]) }}>
    <div class="stat-head">
        <span class="stat-label">{{ $label }}</span>
        <span class="stat-icon" aria-hidden="true"><i class="{{ $icon }}"></i></span>
    </div>
    <div class="stat-value">{{ $value }}</div>
    @if($hint || $href)
        <div class="stat-foot">
            @if($hint)
                <span class="stat-hint {{ $trend ? 'trend-' . $trend : '' }}">
                    @if($trendIcon)<i class="{{ $trendIcon }}"></i>@endif{{ $hint }}
                </span>
            @endif
            @if($href)
                <span class="stat-link" aria-hidden="true"><i class="fas fa-arrow-right"></i></span>
            @endif
        </div>
    @endif
</{{ $tag }}>
