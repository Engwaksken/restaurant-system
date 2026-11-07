@extends('layouts.app')

@section('title', 'Menu Availability')
@section('page-title', 'Menu Availability')

@section('content')
<div class="panel p-4">
    <p class="text-muted">
        Unavailable items cannot be ordered at the POS or online.
        @if($unavailableCount > 0)
            <strong class="text-danger">{{ $unavailableCount }} {{ \Illuminate\Support\Str::plural('item', $unavailableCount) }} currently unavailable.</strong>
        @endif
    </p>
    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-12 col-md-4"><label class="form-label" for="availSearch">Search</label><input class="form-control" id="availSearch" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="100"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="availCategory">Category</label>
            <select class="form-select" id="availCategory" name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === $category->id)>{{ $category->name }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3"><label class="form-label" for="availShow">Show</label>
            <select class="form-select" id="availShow" name="show">
                <option value="all" @selected($show === 'all')>All items</option>
                <option value="available" @selected($show === 'available')>Available</option>
                <option value="unavailable" @selected($show === 'unavailable')>Unavailable</option>
            </select>
        </div>
        <div class="col-12 col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Filter</button></div>
    </form>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Item</th><th>Category</th><th class="text-end">Price</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($items as $item)
            <tr class="{{ $item->is_available ? '' : 'table-danger' }}">
                <td><strong>{{ $item->name }}</strong></td>
                <td>{{ $item->category?->name ?? '—' }}</td>
                <td class="text-end">{{ config('restaurant.currency') }} {{ number_format((float) $item->price, 2) }}</td>
                <td>@if($item->is_available)<span class="badge bg-success">Available</span>@else<span class="badge bg-danger">Unavailable</span>@endif</td>
                <td class="text-end">
                    <form method="POST" action="{{ route('admin.menu-availability.update', $item->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_available" value="{{ $item->is_available ? 0 : 1 }}">
                        <button class="btn btn-sm {{ $item->is_available ? 'btn-outline-danger' : 'btn-success' }}" type="submit">
                            {{ $item->is_available ? 'Mark unavailable' : 'Make available' }}
                        </button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">No menu items match.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $items->links() }}
</div>
@endsection
