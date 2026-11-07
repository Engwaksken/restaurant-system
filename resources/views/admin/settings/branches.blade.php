@extends('layouts.app')

@section('title', 'Branches')
@section('page-title', 'Branches')

@section('content')
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add branch</h2>
        <form method="POST" action="{{ route('admin.settings.branches.store') }}">
            @csrf
            @include('admin.settings._branch-fields', ['branch' => null])
            <button class="btn btn-primary" type="submit">Create branch</button>
        </form>
    </div></div>
    <div class="col-lg-8"><div class="panel p-4">
        <h2 class="h5">Your branches</h2>
        @foreach($branches as $branch)
            <div class="border-bottom py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <strong>{{ $branch->name }}</strong> <code>{{ $branch->code }}</code>
                        @if($branch->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif
                        <small class="text-muted d-block">{{ $branch->staff_count }} active staff</small>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.branches.status', $branch->id) }}" @if($branch->is_active) onsubmit="return confirm('Deactivate this branch? It will no longer accept orders.')" @endif>
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $branch->is_active ? 0 : 1 }}">
                        <button class="btn btn-sm {{ $branch->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $branch->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                </div>
                <details>
                    <summary class="small">Edit details</summary>
                    <form method="POST" action="{{ route('admin.settings.branches.update', $branch->id) }}" class="mt-2">
                        @csrf @method('PUT')
                        @include('admin.settings._branch-fields', ['branch' => $branch])
                        <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                    </form>
                </details>
            </div>
        @endforeach
    </div></div>
</div>
@endsection
