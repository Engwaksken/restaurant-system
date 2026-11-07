@extends('layouts.app')

@section('title', 'Menu ' . \Illuminate\Support\Str::plural($label))
@section('page-title', 'Menu ' . \Illuminate\Support\Str::plural($label))

@section('content')
<div class="panel p-4">
    <p class="text-muted small">
        @if($type === 'variants')
            Variants are sizes or portions. A variant's price replaces the item price, and customers must pick one when an item has any available variants.
        @else
            Add-ons are optional extras. Their price is added to the item (or variant) price.
        @endif
        Stock is deducted using the item's recipe regardless of the options chosen.
    </p>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm {{ $type === 'variants' ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ route('admin.menu-options.index', 'variants') }}">Variants</a>
        <a class="btn btn-sm {{ $type === 'addons' ? 'btn-dark' : 'btn-outline-dark' }}" href="{{ route('admin.menu-options.index', 'addons') }}">Add-ons</a>
        <form method="GET" class="ms-auto d-flex gap-2"><input class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Find menu item" aria-label="Find menu item"><button class="btn btn-sm btn-outline-secondary" type="submit">Search</button></form>
    </div>
    @forelse($items as $item)
        <div class="border-bottom py-3">
            <div class="d-flex justify-content-between"><strong>{{ $item->name }}</strong><small class="text-muted">{{ $item->category?->name }} · base {{ number_format((float) $item->price, 2) }}</small></div>
            @foreach($item->{$type} as $option)
                <form method="POST" action="{{ route('admin.menu-options.update', [$type, $option->id]) }}" class="row g-2 align-items-center mt-1">
                    @csrf @method('PUT')
                    <div class="col-md-4"><input class="form-control form-control-sm" name="name" value="{{ $option->name }}" maxlength="100" required aria-label="Name"></div>
                    <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="price" value="{{ $option->price }}" min="0" step="0.01" required aria-label="Price"></div>
                    <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="display_order" value="{{ $option->display_order }}" min="0" aria-label="Display order"></div>
                    <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_available" value="1" id="opt-{{ $type }}-{{ $option->id }}" @checked($option->is_available)><label class="form-check-label small" for="opt-{{ $type }}-{{ $option->id }}">Available</label></div></div>
                    <div class="col-md-2 d-flex gap-1">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                        <button class="btn btn-sm btn-outline-danger" type="submit" form="del-{{ $type }}-{{ $option->id }}">Remove</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.menu-options.destroy', [$type, $option->id]) }}" id="del-{{ $type }}-{{ $option->id }}" data-confirm="Remove {{ $option->name }}?" onsubmit="return confirm(this.dataset.confirm)">@csrf @method('DELETE')</form>
            @endforeach
            <form method="POST" action="{{ route('admin.menu-options.store', $type) }}" class="row g-2 align-items-center mt-2">
                @csrf
                <input type="hidden" name="menu_item_id" value="{{ $item->id }}">
                <div class="col-md-4"><input class="form-control form-control-sm" name="name" maxlength="100" required placeholder="New {{ strtolower($label) }}, e.g. {{ $type === 'variants' ? 'Large' : 'Extra cheese' }}" aria-label="New {{ strtolower($label) }} name for {{ $item->name }}"></div>
                <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="price" min="0" step="0.01" required placeholder="{{ $type === 'variants' ? 'Price' : '+ Price' }}" aria-label="Price"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-outline-success" type="submit">Add</button></div>
            </form>
        </div>
    @empty
        <p class="text-muted">No menu items found.</p>
    @endforelse
    {{ $items->links() }}
</div>
@endsection
