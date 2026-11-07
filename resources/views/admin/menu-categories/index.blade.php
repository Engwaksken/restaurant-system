@extends('layouts.app')

@section('title', 'Menu categories')
@section('page-title', 'Menu Categories')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $addFailed = $errors->hasAny(['name', 'description', 'display_order', 'is_active']) && ! old('_method');
@endphp
<div class="container-fluid px-0">

    <div class="card border-0 shadow-sm">

        <div class="card-header bg-white border-bottom py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-0">
                        <i class="fas fa-layer-group me-2 text-primary"></i>
                        Your categories
                    </h2>
                    <small class="text-muted">
                        {{ number_format($categories->total()) }} configured
                    </small>
                </div>

                <button type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#add-category">
                    <i class="fas fa-plus me-1"></i>
                    Add Category
                </button>
            </div>
        </div>

        <div class="card-body p-0">

            @forelse($categories as $category)

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 px-3 py-3 border-bottom">

                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="fw-semibold">{{ $category->name }}</span>

                            @if($category->is_active)
                                <span class="badge text-bg-success">
                                    <i class="fas fa-circle-check me-1"></i>
                                    Active
                                </span>
                            @else
                                <span class="badge text-bg-secondary">
                                    <i class="fas fa-circle-pause me-1"></i>
                                    Inactive
                                </span>
                            @endif
                        </div>

                        @if($category->description)
                            <small class="text-muted d-block">
                                {{ $category->description }}
                            </small>
                        @endif

                        <small class="text-muted">
                            Order {{ $category->display_order }} &middot; {{ $category->items_count }} items
                        </small>
                    </div>

                    <div class="d-inline-flex gap-1 flex-shrink-0">

                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#edit-category-{{ $category->id }}"
                                title="Edit">
                            <i class="fas fa-pen-to-square"></i>
                        </button>

                        @if($category->items_count === 0)
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#delete-category-{{ $category->id }}"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        @endif

                    </div>

                </div>

            @empty

                <div class="text-center py-5">
                    <div class="text-muted">
                        <i class="fas fa-layer-group fa-2x mb-3 d-block"></i>
                        <strong class="d-block">No menu categories configured</strong>
                        <small>Add your first category to start organising the menu.</small>
                    </div>
                </div>

            @endforelse

        </div>

        {{ $categories->links() }}

    </div>
</div>


{{-- =========================================================
     ADD CATEGORY MODAL
     OUTSIDE THE LIST
========================================================= --}}
<div class="modal fade"
     id="add-category"
     tabindex="-1"
     aria-labelledby="add-category-label"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.menu-categories.store') }}">

            @csrf

            <div class="modal-header">

                <h5 class="modal-title"
                    id="add-category-label">
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Add Category
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label" for="categoryName">
                        Name <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="categoryName"
                           class="form-control @error('name') is-invalid @enderror"
                           name="name"
                           value="{{ $addFailed ? old('name') : '' }}"
                           placeholder="e.g. Starters"
                           required
                           maxlength="255">
                    @if($addFailed)
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    @endif
                </div>

                <div class="mb-3">
                    <label class="form-label" for="categoryDescription">
                        Description
                    </label>
                    <textarea id="categoryDescription"
                              class="form-control @error('description') is-invalid @enderror"
                              name="description"
                              rows="2"
                              maxlength="5000"
                              placeholder="Short note shown to staff">{{ $addFailed ? old('description') : '' }}</textarea>
                    @if($addFailed)
                        @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    @endif
                </div>

                <div class="row g-3 align-items-end">
                    <div class="col-sm-6">
                        <label class="form-label" for="categoryOrder">
                            Display order
                        </label>
                        <input type="number"
                               id="categoryOrder"
                               class="form-control @error('display_order') is-invalid @enderror"
                               name="display_order"
                               min="0"
                               value="{{ $addFailed ? old('display_order', 0) : 0 }}">
                        @if($addFailed)
                            @error('display_order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="col-sm-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="is_active"
                                   value="1"
                                   id="categoryActive"
                                   @checked($addFailed ? old('is_active') : true)>
                            <label class="form-check-label fw-semibold"
                                   for="categoryActive">
                                Active on storefront
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer">

                <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>

                <button type="submit"
                        class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Create Category
                </button>

            </div>

        </form>

    </div>
</div>


{{-- =========================================================
     EDIT / DELETE MODALS
     IMPORTANT: rendered outside the list.
========================================================= --}}
@foreach($categories as $category)

    {{-- EDIT CATEGORY --}}
    <div class="modal fade"
         id="edit-category-{{ $category->id }}"
         tabindex="-1"
         aria-labelledby="edit-category-label-{{ $category->id }}"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content"
                  method="POST"
                  action="{{ route('admin.menu-categories.update', $category->id) }}">

                @csrf
                @method('PUT')

                <div class="modal-header">

                    <div>
                        <h5 class="modal-title"
                            id="edit-category-label-{{ $category->id }}">
                            <i class="fas fa-pen-to-square me-2 text-primary"></i>
                            Edit Category
                        </h5>

                        <small class="text-muted">
                            {{ $category->name }}
                        </small>
                    </div>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Close"></button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label" for="edit-category-name-{{ $category->id }}">
                            Name <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="edit-category-name-{{ $category->id }}"
                               class="form-control"
                               name="name"
                               value="{{ $category->name }}"
                               required
                               maxlength="255">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="edit-category-description-{{ $category->id }}">
                            Description
                        </label>
                        <textarea id="edit-category-description-{{ $category->id }}"
                                  class="form-control"
                                  name="description"
                                  rows="2"
                                  maxlength="5000">{{ $category->description }}</textarea>
                    </div>

                    <div class="row g-3 align-items-end">
                        <div class="col-sm-6">
                            <label class="form-label" for="edit-category-order-{{ $category->id }}">
                                Display order
                            </label>
                            <input type="number"
                                   id="edit-category-order-{{ $category->id }}"
                                   class="form-control"
                                   name="display_order"
                                   min="0"
                                   value="{{ $category->display_order }}">
                        </div>

                        <div class="col-sm-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="is_active"
                                       value="1"
                                       id="active-{{ $category->id }}"
                                       @checked($category->is_active)>
                                <label class="form-check-label fw-semibold"
                                       for="active-{{ $category->id }}">
                                    Active on storefront
                                </label>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Update Category
                    </button>

                </div>

            </form>

        </div>
    </div>

    @if($category->items_count === 0)

        {{-- DELETE CATEGORY --}}
        <div class="modal fade"
             id="delete-category-{{ $category->id }}"
             tabindex="-1"
             aria-labelledby="delete-category-label-{{ $category->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.menu-categories.destroy', $category->id) }}">

                    @csrf
                    @method('DELETE')

                    <div class="modal-header border-0 pb-0">

                        <h5 class="modal-title"
                            id="delete-category-label-{{ $category->id }}">
                            <i class="fas fa-trash me-2 text-danger"></i>
                            Delete Category
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                    </div>

                    <div class="modal-body text-center py-4">

                        <h5 class="mb-2">
                            Delete {{ $category->name }}?
                        </h5>

                        <p class="text-muted mb-0">
                            This category has no items and will be removed from the menu.
                        </p>

                    </div>

                    <div class="modal-footer border-0 justify-content-center pt-0 pb-4">

                        <button type="button"
                                class="btn btn-light px-4"
                                data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit"
                                class="btn btn-danger px-4">
                            <i class="fas fa-trash me-1"></i>
                            Delete
                        </button>

                    </div>

                </form>

            </div>
        </div>

    @endif

@endforeach

@endsection

@if($addFailed)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('add-category');

        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
