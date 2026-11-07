@extends('layouts.app')

@section('title', 'Floors / Sections')
@section('page-title', 'Floors / Sections')

@section('content')
<div class="row g-4">
    @if($canManage)
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add floor or section</h2>
        @if($branches->isEmpty())<p class="text-muted">An active branch is required.</p>@else
        <form method="POST" action="{{ route('admin.floors.store') }}">
            @csrf
            <label class="form-label" for="floorBranch">Branch</label>
            <select class="form-select mb-2" id="floorBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
            <label class="form-label" for="floorName">Name</label>
            <input class="form-control mb-2" id="floorName" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="e.g. Terrace">
            <label class="form-label" for="floorOrder">Display order</label>
            <input class="form-control mb-3" id="floorOrder" type="number" name="display_order" min="0" value="{{ old('display_order', 0) }}">
            <button class="btn btn-primary" type="submit">Create section</button>
        </form>
        @endif
    </div></div>
    @endif
    <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}"><div class="panel p-4">
        <h2 class="h5">Sections</h2>
        @forelse($floors as $floor)
            <div class="border-bottom py-3">
                @if($canManage)
                <form method="POST" action="{{ route('admin.floors.update', $floor->id) }}" class="row g-2 align-items-end">
                    @csrf @method('PUT')
                    <div class="col-md-5"><label class="form-label" for="floor-{{ $floor->id }}">Name <small class="text-muted">({{ $floor->branch?->name }})</small></label><input class="form-control" id="floor-{{ $floor->id }}" name="name" value="{{ $floor->name }}" maxlength="255" required></div>
                    <div class="col-md-2"><label class="form-label" for="floor-order-{{ $floor->id }}">Order</label><input class="form-control" id="floor-order-{{ $floor->id }}" type="number" name="display_order" min="0" value="{{ $floor->display_order }}"></div>
                    <div class="col-md-2"><small class="text-muted">{{ $floor->tables_count }} tables</small></div>
                    <div class="col-md-3"><button class="btn btn-outline-primary btn-sm" type="submit">Save</button></div>
                </form>
                @if($floor->tables_count === 0)
                    <form method="POST" action="{{ route('admin.floors.destroy', $floor->id) }}" class="mt-2" onsubmit="return confirm('Delete this section?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete {{ $floor->name }}</button>
                    </form>
                @endif
                @else
                    <strong>{{ $floor->name }}</strong> <small class="text-muted">{{ $floor->branch?->name }} · {{ $floor->tables_count }} tables</small>
                @endif
            </div>
        @empty
            <p class="text-muted">No floors or sections configured.</p>
        @endforelse
    </div></div>
</div>
@endsection
