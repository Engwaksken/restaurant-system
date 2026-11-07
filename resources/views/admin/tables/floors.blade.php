@extends('layouts.app')

@section('title', 'Floors / Sections')
@section('page-title', 'Floors / Sections')
@section('own-validation-errors', '1')

@section('content')
@php
    $addFailed = $errors->any() && old('branch_id') !== null;
@endphp
<div class="row g-4">
    @if($errors->any() && !$addFailed)
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-0" role="alert">
            <div class="d-flex gap-2">
                <i class="fas fa-triangle-exclamation mt-1"></i>
                <div>
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif
    <div class="col-12"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h2 class="h5 mb-0">Sections</h2>
            @if($canManage && $branches->isNotEmpty())
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#add-floor">
                    <i class="fas fa-plus me-1"></i>
                    Add section
                </button>
            @endif
        </div>
        @if($canManage && $branches->isEmpty())
            <p class="text-muted">An active branch is required.</p>
        @endif
        @forelse($floors as $floor)
            <div class="border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>{{ $floor->name }}</strong> <small class="text-muted">{{ $floor->branch?->name }} · {{ $floor->tables_count }} tables</small>
                </div>
                @if($canManage)
                    <div class="d-inline-flex gap-1">
                        <button type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#edit-floor-{{ $floor->id }}"
                                title="Edit">
                            <i class="fas fa-pen-to-square"></i>
                        </button>
                        @if($floor->tables_count === 0)
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#delete-floor-{{ $floor->id }}"
                                    title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <p class="text-muted">No floors or sections configured.</p>
        @endforelse
    </div></div>
</div>

@if($canManage && $branches->isNotEmpty())
    {{-- =========================================================
         ADD SECTION MODAL
         OUTSIDE THE TABLE
    ========================================================= --}}
    <div class="modal fade"
         id="add-floor"
         tabindex="-1"
         aria-labelledby="add-floor-label"
         aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">

            <form class="modal-content" method="POST" action="{{ route('admin.floors.store') }}">

                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-floor-label">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>
                        Add floor or section
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    @if($addFailed)
                        <div class="alert alert-danger py-2" role="alert">
                            <strong>Please correct the following:</strong>
                            <ul class="mb-0 ps-3">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mb-3">
                        <label class="form-label" for="floorBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select @error('branch_id') is-invalid @enderror" id="floorBranch" name="branch_id" required>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="floorName">Name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror"
                               id="floorName"
                               name="name"
                               value="{{ old('name') }}"
                               maxlength="255"
                               required
                               placeholder="e.g. Terrace">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="floorOrder">Display order</label>
                        <input class="form-control @error('display_order') is-invalid @enderror"
                               id="floorOrder"
                               type="number"
                               name="display_order"
                               min="0"
                               value="{{ old('display_order', 0) }}">
                        @error('display_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-floppy-disk me-1"></i>
                        Create section
                    </button>
                </div>

            </form>

        </div>
    </div>
@endif

@if($canManage)
    {{-- =========================================================
         EDIT / DELETE MODALS
         IMPORTANT: rendered outside <table>/<tbody>.
    ========================================================= --}}
    @foreach($floors as $floor)

        <div class="modal fade"
             id="edit-floor-{{ $floor->id }}"
             tabindex="-1"
             aria-labelledby="edit-floor-label-{{ $floor->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" method="POST" action="{{ route('admin.floors.update', $floor->id) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-floor-label-{{ $floor->id }}">
                                <i class="fas fa-pen-to-square me-2 text-primary"></i>
                                Edit section
                            </h5>
                            <small class="text-muted">{{ $floor->branch?->name }}</small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label" for="edit-floor-name-{{ $floor->id }}">Name <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control"
                                   id="edit-floor-name-{{ $floor->id }}"
                                   name="name"
                                   value="{{ $floor->name }}"
                                   maxlength="255"
                                   required
                                   placeholder="e.g. Terrace">
                        </div>

                        <div class="mb-1">
                            <label class="form-label" for="edit-floor-order-{{ $floor->id }}">Display order</label>
                            <input class="form-control"
                                   id="edit-floor-order-{{ $floor->id }}"
                                   type="number"
                                   name="display_order"
                                   min="0"
                                   value="{{ $floor->display_order }}">
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save Section
                        </button>
                    </div>

                </form>

            </div>
        </div>

        @if($floor->tables_count === 0)
            <div class="modal fade"
                 id="delete-floor-{{ $floor->id }}"
                 tabindex="-1"
                 aria-labelledby="delete-floor-label-{{ $floor->id }}"
                 aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered">

                    <form class="modal-content" method="POST" action="{{ route('admin.floors.destroy', $floor->id) }}">

                        @csrf
                        @method('DELETE')

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title" id="delete-floor-label-{{ $floor->id }}">
                                Delete Section
                            </h5>

                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body text-center py-4">
                            <i class="fas fa-trash fa-2x text-danger mb-3 d-block"></i>

                            <h5 class="mb-2">
                                Delete {{ $floor->name }}?
                            </h5>

                            <p class="text-muted mb-0">
                                This section will be removed. Sections that still have tables cannot be deleted.
                            </p>
                        </div>

                        <div class="modal-footer border-0 justify-content-center pt-0 pb-4">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                                <i class="fas fa-xmark me-1"></i>
                                Cancel
                            </button>

                            <button type="submit" class="btn btn-danger px-4">
                                <i class="fas fa-trash me-1"></i>
                                Delete
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        @endif

    @endforeach
@endif

@if($addFailed)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('add-floor');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
@endsection
