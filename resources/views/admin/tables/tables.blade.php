@extends('layouts.app')

@section('title', 'Tables')
@section('page-title', 'Restaurant Tables')
@section('own-validation-errors', '1')

@section('content')
@php
    $statusClasses = ['available' => 'success', 'reserved' => 'info', 'occupied' => 'danger', 'cleaning' => 'warning', 'inactive' => 'secondary'];
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
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h2 class="h5 mb-0">Tables</h2>
                @if($canManage && $branches->isNotEmpty())
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#add-table">
                        <i class="fas fa-plus me-1"></i>
                        Add table
                    </button>
                @endif
            </div>
            <form method="GET">
                <label class="visually-hidden" for="tableStatusFilter">Status</label>
                <select class="form-select form-select-sm" id="tableStatusFilter" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(array_keys($statusClasses) as $option)<option value="{{ $option }}" @selected(request('status') === $option)>{{ ucfirst($option) }}</option>@endforeach
                </select>
            </form>
        </div>
        @if($canManage && $branches->isEmpty())
            <p class="text-muted">An active branch is required.</p>
        @endif
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Table</th><th>Section</th><th>Seats</th><th>Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
            @forelse($tables as $table)
                <tr>
                    <td><strong>{{ $table->name }}</strong><small class="d-block text-muted">{{ $table->branch?->name }}</small></td>
                    <td>{{ $table->floor?->name ?? '—' }}</td>
                    <td>{{ $table->capacity }}</td>
                    <td><span class="badge bg-{{ $statusClasses[$table->status] ?? 'secondary' }}">{{ ucfirst($table->status) }}</span></td>
                    @if($canManage)
                    <td>
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <form method="POST" action="{{ route('admin.tables.status', $table->id) }}" class="d-flex gap-1">
                                @csrf @method('PATCH')
                                <label class="visually-hidden" for="table-status-{{ $table->id }}">Status for {{ $table->name }}</label>
                                <select class="form-select form-select-sm" id="table-status-{{ $table->id }}" name="status">
                                    @foreach($manualStatuses as $option)<option value="{{ $option }}" @selected($table->status === $option)>{{ ucfirst($option) }}</option>@endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Set</button>
                            </form>
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-table-{{ $table->id }}"
                                    title="Edit">
                                <i class="fas fa-pen-to-square"></i>
                            </button>
                        </div>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">No tables configured.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $tables->links() }}
    </div></div>
</div>

@if($canManage)
    @if($branches->isNotEmpty())
        {{-- =========================================================
             ADD TABLE MODAL
             OUTSIDE THE TABLE
        ========================================================= --}}
        <div class="modal fade"
             id="add-table"
             tabindex="-1"
             aria-labelledby="add-table-label"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" method="POST" action="{{ route('admin.tables.store') }}">

                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="add-table-label">
                            <i class="fas fa-plus-circle me-2 text-primary"></i>
                            Add table
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
                            <label class="form-label" for="tableBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('branch_id') is-invalid @enderror" id="tableBranch" name="branch_id" data-filters="tableFloor" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="tableFloor">Section <small class="text-muted">(optional)</small></label>
                            <select class="form-select @error('floor_id') is-invalid @enderror" id="tableFloor" name="floor_id">
                                <option value="">None</option>
                                @foreach($floors as $floor)
                                    <option value="{{ $floor->id }}" data-branch="{{ $floor->branch_id }}" @selected(old('floor_id') == $floor->id)>{{ $floor->name }}</option>
                                @endforeach
                            </select>
                            @error('floor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="tableName">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('name') is-invalid @enderror"
                                       id="tableName"
                                       name="name"
                                       value="{{ old('name') }}"
                                       maxlength="255"
                                       required
                                       placeholder="e.g. T12">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-5">
                                <label class="form-label" for="tableCapacity">Seats <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('capacity') is-invalid @enderror"
                                       id="tableCapacity"
                                       type="number"
                                       name="capacity"
                                       min="1"
                                       max="500"
                                       value="{{ old('capacity', 4) }}"
                                       required>
                                @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Create table
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endif

    {{-- =========================================================
         EDIT TABLE MODALS
         IMPORTANT: rendered outside <table>/<tbody>.
    ========================================================= --}}
    @foreach($tables as $table)

        <div class="modal fade"
             id="edit-table-{{ $table->id }}"
             tabindex="-1"
             aria-labelledby="edit-table-label-{{ $table->id }}"
             aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">

                <form class="modal-content" method="POST" action="{{ route('admin.tables.update', $table->id) }}">

                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-table-label-{{ $table->id }}">
                                <i class="fas fa-pen-to-square me-2 text-primary"></i>
                                Edit table
                            </h5>
                            <small class="text-muted">{{ $table->branch?->name }}</small>
                        </div>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-3">
                            <label class="form-label" for="edit-table-floor-{{ $table->id }}">Section <small class="text-muted">(optional)</small></label>
                            <select class="form-select" id="edit-table-floor-{{ $table->id }}" name="floor_id">
                                <option value="">No section</option>
                                @foreach($floors->where('branch_id', $table->branch_id) as $floor)
                                    <option value="{{ $floor->id }}" @selected($table->floor_id === $floor->id)>{{ $floor->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="edit-table-name-{{ $table->id }}">Name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control"
                                       id="edit-table-name-{{ $table->id }}"
                                       name="name"
                                       value="{{ $table->name }}"
                                       maxlength="255"
                                       required
                                       placeholder="e.g. T12">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label" for="edit-table-capacity-{{ $table->id }}">Seats <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control"
                                       id="edit-table-capacity-{{ $table->id }}"
                                       type="number"
                                       name="capacity"
                                       value="{{ $table->capacity }}"
                                       min="1"
                                       max="500"
                                       required>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save Table
                        </button>
                    </div>

                </form>

            </div>
        </div>

    @endforeach
@endif

@if($addFailed)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('add-table');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
@if($canManage)
    @include('admin.tables._branch-filter')
@endif
@endsection
