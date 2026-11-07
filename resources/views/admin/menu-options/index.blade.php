@extends('layouts.app')

@section('title', 'Menu ' . \Illuminate\Support\Str::plural($label))
@section('page-title', 'Menu ' . \Illuminate\Support\Str::plural($label))

@section('content')
@php
    $optionSlug = $type === 'variants' ? 'variant' : 'addon';
    $addFailed = $errors->hasAny(['name', 'price', 'menu_item_id']) && ! old('_method');
@endphp
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
        <button type="button"
                class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#add-{{ $optionSlug }}">
            <i class="fas fa-plus me-1"></i>
            Add {{ $label }}
        </button>
        <form method="GET" class="ms-auto d-flex gap-2"><input class="form-control form-control-sm" name="search" value="{{ request('search') }}" placeholder="Find menu item" aria-label="Find menu item"><button class="btn btn-sm btn-outline-secondary" type="submit">Search</button></form>
    </div>
    @forelse($items as $item)
        <div class="border-bottom py-3">
            <div class="d-flex justify-content-between"><strong>{{ $item->name }}</strong><small class="text-muted">{{ $item->category?->name }} · base {{ number_format((float) $item->price, 2) }}</small></div>
            @forelse($item->{$type} as $option)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="fw-semibold">{{ $option->name }}</span>
                        <span class="badge text-bg-light border">{{ number_format((float) $option->price, 2) }}</span>
                        @if($option->is_available)
                            <span class="badge text-bg-success"><i class="fas fa-circle-check me-1"></i>Available</span>
                        @else
                            <span class="badge text-bg-secondary"><i class="fas fa-circle-pause me-1"></i>Unavailable</span>
                        @endif
                    </div>
                    <div class="d-inline-flex gap-1">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#edit-{{ $optionSlug }}-{{ $option->id }}"
                                title="Edit">
                            <i class="fas fa-pen-to-square"></i>
                        </button>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger"
                                data-bs-toggle="modal"
                                data-bs-target="#delete-{{ $optionSlug }}-{{ $option->id }}"
                                title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <p class="text-muted small mt-2 mb-0">No {{ strtolower(\Illuminate\Support\Str::plural($label)) }} yet.</p>
            @endforelse
        </div>
    @empty
        <p class="text-muted">No menu items found.</p>
    @endforelse
    {{ $items->links() }}
</div>


{{-- =========================================================
     ADD MODAL
     OUTSIDE THE LIST
========================================================= --}}
<div class="modal fade"
     id="add-{{ $optionSlug }}"
     tabindex="-1"
     aria-labelledby="add-{{ $optionSlug }}-label"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <form class="modal-content"
              method="POST"
              action="{{ route('admin.menu-options.store', $type) }}">

            @csrf

            <div class="modal-header">

                <h5 class="modal-title"
                    id="add-{{ $optionSlug }}-label">
                    <i class="fas fa-plus-circle me-2 text-primary"></i>
                    Add {{ $label }}
                </h5>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label" for="add-{{ $optionSlug }}-item">
                        Menu item <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <select id="add-{{ $optionSlug }}-item"
                            class="form-select {{ $addFailed && $errors->has('menu_item_id') ? 'is-invalid' : '' }}"
                            name="menu_item_id"
                            required>
                        <option value="">Select menu item</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                @selected($addFailed && (string) old('menu_item_id') === (string) $item->id)>
                                {{ $item->name }}
                            </option>
                        @endforeach
                    </select>
                    @if($addFailed)
                        @error('menu_item_id')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    @endif
                </div>

                <div class="row g-3">
                    <div class="col-sm-7">
                        <label class="form-label" for="add-{{ $optionSlug }}-name">
                            {{ $label }} name <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <input type="text"
                               id="add-{{ $optionSlug }}-name"
                               class="form-control {{ $addFailed && $errors->has('name') ? 'is-invalid' : '' }}"
                               name="name"
                               value="{{ $addFailed ? old('name') : '' }}"
                               placeholder="e.g. {{ $type === 'variants' ? 'Large' : 'Extra cheese' }}"
                               required
                               maxlength="100">
                        @if($addFailed)
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @endif
                    </div>

                    <div class="col-sm-5">
                        <label class="form-label" for="add-{{ $optionSlug }}-price">
                            {{ $type === 'variants' ? 'Price' : 'Extra price' }} <span class="text-danger" aria-hidden="true">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">{{ config('restaurant.currency', 'UGX') }}</span>
                            <input type="number"
                                   id="add-{{ $optionSlug }}-price"
                                   class="form-control {{ $addFailed && $errors->has('price') ? 'is-invalid' : '' }}"
                                   name="price"
                                   value="{{ $addFailed ? old('price') : '' }}"
                                   min="0"
                                   step="0.01"
                                   placeholder="{{ $type === 'variants' ? '0.00' : '+ 0.00' }}"
                                   required>
                        </div>
                        @if($addFailed)
                            @error('price')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        @endif
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
                    Add {{ $label }}
                </button>

            </div>

        </form>

    </div>
</div>


{{-- =========================================================
     EDIT / DELETE MODALS
     IMPORTANT: rendered outside the list.
========================================================= --}}
@foreach($items as $item)
    @foreach($item->{$type} as $option)

        {{-- EDIT --}}
        <div class="modal fade"
             id="edit-{{ $optionSlug }}-{{ $option->id }}"
             tabindex="-1"
             aria-labelledby="edit-{{ $optionSlug }}-label-{{ $option->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.menu-options.update', [$type, $option->id]) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-header">

                        <div>
                            <h5 class="modal-title"
                                id="edit-{{ $optionSlug }}-label-{{ $option->id }}">
                                <i class="fas fa-pen-to-square me-2 text-primary"></i>
                                Edit {{ $label }}
                            </h5>

                            <small class="text-muted">
                                {{ $item->name }}
                            </small>
                        </div>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                    </div>

                    <div class="modal-body">

                        <div class="row g-3">
                            <div class="col-sm-7">
                                <label class="form-label" for="edit-{{ $optionSlug }}-name-{{ $option->id }}">
                                    Name <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <input type="text"
                                       id="edit-{{ $optionSlug }}-name-{{ $option->id }}"
                                       class="form-control"
                                       name="name"
                                       value="{{ $option->name }}"
                                       maxlength="100"
                                       required>
                            </div>

                            <div class="col-sm-5">
                                <label class="form-label" for="edit-{{ $optionSlug }}-price-{{ $option->id }}">
                                    {{ $type === 'variants' ? 'Price' : 'Extra price' }} <span class="text-danger" aria-hidden="true">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ config('restaurant.currency', 'UGX') }}</span>
                                    <input type="number"
                                           id="edit-{{ $optionSlug }}-price-{{ $option->id }}"
                                           class="form-control"
                                           name="price"
                                           value="{{ $option->price }}"
                                           min="0"
                                           step="0.01"
                                           required>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label" for="edit-{{ $optionSlug }}-order-{{ $option->id }}">
                                    Display order
                                </label>
                                <input type="number"
                                       id="edit-{{ $optionSlug }}-order-{{ $option->id }}"
                                       class="form-control"
                                       name="display_order"
                                       value="{{ $option->display_order }}"
                                       min="0">
                            </div>

                            <div class="col-sm-6 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           name="is_available"
                                           value="1"
                                           id="edit-{{ $optionSlug }}-available-{{ $option->id }}"
                                           @checked($option->is_available)>
                                    <label class="form-check-label fw-semibold"
                                           for="edit-{{ $optionSlug }}-available-{{ $option->id }}">
                                        Available
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
                            Update {{ $label }}
                        </button>

                    </div>

                </form>

            </div>
        </div>

        {{-- DELETE --}}
        <div class="modal fade"
             id="delete-{{ $optionSlug }}-{{ $option->id }}"
             tabindex="-1"
             aria-labelledby="delete-{{ $optionSlug }}-label-{{ $option->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content"
                      method="POST"
                      action="{{ route('admin.menu-options.destroy', [$type, $option->id]) }}">

                    @csrf
                    @method('DELETE')

                    <div class="modal-header border-0 pb-0">

                        <h5 class="modal-title"
                            id="delete-{{ $optionSlug }}-label-{{ $option->id }}">
                            <i class="fas fa-trash me-2 text-danger"></i>
                            Remove {{ $label }}
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                    </div>

                    <div class="modal-body text-center py-4">

                        <h5 class="mb-2">
                            Remove {{ $option->name }}?
                        </h5>

                        <p class="text-muted mb-0">
                            This {{ strtolower($label) }} will be removed from {{ $item->name }}.
                            Past orders keep their recorded name and price.
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
                            Remove
                        </button>

                    </div>

                </form>

            </div>
        </div>

    @endforeach
@endforeach

@endsection

@if($addFailed)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('add-{{ $optionSlug }}');

        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
