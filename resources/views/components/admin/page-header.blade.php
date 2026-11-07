@props(['title', 'subtitle' => null])
<div {{ $attributes->class(['page-head']) }}>
    <div>
        <h2>{{ $title }}</h2>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if(trim($slot) !== '')
        <div class="page-head-actions">{{ $slot }}</div>
    @endif
</div>
