@extends('layouts.app')

@section('title', 'Menu categories')
@section('page-title', 'Menu Categories')

@section('content')
<div class="row g-4">
    <div class="col-lg-4">
        <div class="panel p-4">
            <h2 class="h5">Add category</h2>
            <form method="POST" action="{{ route('admin.menu-categories.store') }}">
                @csrf
                <label class="form-label" for="categoryName">Name</label>
                <input id="categoryName" class="form-control mb-3" name="name" required maxlength="255">
                <label class="form-label" for="categoryDescription">Description</label>
                <textarea id="categoryDescription" class="form-control mb-3" name="description" maxlength="5000"></textarea>
                <label class="form-label" for="categoryOrder">Display order</label>
                <input id="categoryOrder" class="form-control mb-3" type="number" name="display_order" min="0" value="0">
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="categoryActive" checked><label class="form-check-label" for="categoryActive">Active on storefront</label></div>
                <button class="btn btn-primary" type="submit">Create category</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="panel p-4">
            <h2 class="h5">Your categories</h2>
            @forelse($categories as $category)
                <form class="border-bottom py-3" method="POST" action="{{ route('admin.menu-categories.update', $category->id) }}">
                    @csrf @method('PUT')
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ $category->name }}" required maxlength="255"></div>
                        <div class="col-md-3"><label class="form-label">Description</label><input class="form-control" name="description" value="{{ $category->description }}" maxlength="5000"></div>
                        <div class="col-md-2"><label class="form-label">Order</label><input class="form-control" type="number" name="display_order" min="0" value="{{ $category->display_order }}"></div>
                        <div class="col-md-2"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active-{{ $category->id }}" @checked($category->is_active)><label class="form-check-label" for="active-{{ $category->id }}">Active</label></div><small class="text-muted">{{ $category->items_count }} items</small></div>
                        <div class="col-md-2"><button class="btn btn-outline-primary btn-sm" type="submit">Save</button></div>
                    </div>
                </form>
                @if($category->items_count === 0)
                    <form method="POST" action="{{ route('admin.menu-categories.destroy', $category->id) }}" class="mt-2" onsubmit="return confirm('Delete this category?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit">Delete {{ $category->name }}</button>
                    </form>
                @endif
            @empty
                <p class="text-muted">No menu categories configured.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
