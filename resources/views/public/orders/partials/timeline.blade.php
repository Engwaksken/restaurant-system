@php
    $statusSteps = [
        'pending' => 'Order placed',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'completed' => 'Completed',
    ];
    $stepKeys = array_keys($statusSteps);
    $currentStatus = $order->status === 'served' ? 'ready' : $order->status;
    $currentIndex = array_search($currentStatus, $stepKeys, true);
@endphp
<ol class="order-timeline list-unstyled mb-0">
    @foreach($statusSteps as $key => $label)
        @php($index = array_search($key, $stepKeys, true))
        <li class="timeline-step {{ $index <= $currentIndex ? 'done' : '' }} {{ $index === $currentIndex ? 'current' : '' }}">
            <span class="timeline-dot">
                @if($index < $currentIndex)
                    <i class="fas fa-check"></i>
                @elseif($index === $currentIndex)
                    <i class="fas fa-circle"></i>
                @endif
            </span>
            <div class="timeline-label">{{ $label }}</div>
        </li>
    @endforeach
</ol>
