{{--
    Responsive row of stat cards.
    :items = [['label' => .., 'value' => .., 'icon' => .., 'tone' => .., 'hint' => .., 'href' => ..], ...]
--}}
@props(['items' => []])
@php($items = array_values(array_filter($items)))
@if(count($items))
    @php($columns = match (true) { count($items) >= 4 => 'col-6 col-xl-3', count($items) === 3 => 'col-12 col-sm-6 col-xl-4', default => 'col-12 col-sm-6' })
    <div {{ $attributes->class(['row g-3 stat-grid']) }}>
        @foreach($items as $item)
            <div class="{{ count($items) > 4 ? 'col-6 col-lg-4 col-xxl' : $columns }}">
                <x-admin.stat
                    :label="$item['label']"
                    :value="$item['value']"
                    :icon="$item['icon'] ?? 'fas fa-chart-simple'"
                    :tone="$item['tone'] ?? 'brand'"
                    :hint="$item['hint'] ?? null"
                    :trend="$item['trend'] ?? null"
                    :href="$item['href'] ?? null" />
            </div>
        @endforeach
    </div>
@endif
