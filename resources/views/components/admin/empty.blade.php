@props(['icon' => 'fas fa-inbox', 'title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon"><i class="{{ $icon }}"></i></span>
    <h6>{{ $title }}</h6>
    @if($message)
        <p class="mb-0 small">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>
