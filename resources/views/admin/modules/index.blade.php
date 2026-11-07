@extends('layouts.app')

@section('title', $definition['title'])
@section('page-title', $definition['title'])

@section('own-validation-errors', '1')

@section('content')
<div class="container-fluid px-0">

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex gap-2 align-items-start">
                <i class="fas fa-triangle-exclamation mt-1"></i>
                <div>
                    <strong>Please correct the highlighted fields.</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-3">
        <div>
            <div class="text-muted small mb-1">
                <i class="fas fa-folder-open me-1"></i>
                {{ $definition['group'] }}
            </div>

            <h2 class="h5 fw-bold mb-1">
                <i class="{{ $definition['icon'] }} text-primary me-2"></i>
                {{ $definition['title'] }}
            </h2>

            <p class="text-muted mb-0">{{ $definition['description'] }}</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Dashboard
            </a>

            @if($allowCreate)
                <button type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#addModuleModal">
                    <i class="fas fa-plus me-1"></i>
                    Add {{ $definition['title'] }}
                </button>
            @endif
        </div>
    </div>

    @if(count($stats))
        <div class="row g-3 mb-3">
            @foreach($stats as $stat)
                <div class="col-6 col-md-4 col-xl-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body py-3">
                            <div class="d-flex align-items-center justify-content-between gap-3">
                                <div>
                                    <small class="text-muted d-block mb-1">{{ $stat['label'] }}</small>
                                    <strong class="fs-5">
                                        {{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}
                                    </strong>
                                </div>

                                <span class="rounded-3 d-flex align-items-center justify-content-center {{ $stat['available'] ? 'bg-primary-subtle text-primary' : 'bg-light text-muted' }}"
                                      style="width:44px;height:44px;min-width:44px;">
                                    <i class="{{ $definition['icon'] }}"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <strong>
                        <i class="{{ $definition['icon'] }} text-primary me-2"></i>
                        {{ $definition['title'] }}
                    </strong>
                    <div class="text-muted small">Manage {{ strtolower($definition['title']) }}.</div>
                </div>

                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.location.reload()">
                    <i class="fas fa-rotate me-1"></i>
                    Refresh
                </button>
            </div>
        </div>

        @if($records)
            <div class="card-body border-bottom">
                <form method="GET" action="{{ url()->current() }}" class="row g-2">
                    <div class="col-12 col-lg-7">
                        <div class="input-group">
                            <span class="input-group-text bg-white">
                                <i class="fas fa-magnifying-glass text-muted"></i>
                            </span>
                            <input type="search"
                                   name="search"
                                   class="form-control"
                                   value="{{ request('search') }}"
                                   placeholder="Search {{ strtolower($definition['title']) }}...">
                        </div>
                    </div>

                    <div class="col-7 col-lg-3">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="active" @selected(request('status') === 'active')>Active</option>
                            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                            <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                        </select>
                    </div>

                    <div class="col-5 col-lg-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-filter me-1"></i>
                            Filter
                        </button>

                        <a href="{{ url()->current() }}" class="btn btn-outline-secondary" title="Reset filters">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">#</th>
                            @foreach($columns as $column)
                                <th>{{ \Illuminate\Support\Str::headline($column) }}</th>
                            @endforeach
                            @if($allowEdit || $allowDelete)
                                <th class="text-end" style="width:140px;">Actions</th>
                            @endif
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($records as $record)
                            <tr>
                                <td>{{ $record->id ?? $loop->iteration }}</td>

                                @foreach($columns as $column)
                                    @php($value = $record->{$column} ?? null)
                                    <td>
                                        @if(in_array($column, ['is_active', 'active', 'enabled'], true))
                                            <span class="badge {{ (bool) $value ? 'text-bg-success' : 'text-bg-secondary' }}">
                                                {{ (bool) $value ? 'Active' : 'Inactive' }}
                                            </span>
                                        @elseif($column === 'status')
                                            <span class="badge text-bg-light border text-dark">
                                                {{ $value ? \Illuminate\Support\Str::headline((string) $value) : '—' }}
                                            </span>
                                        @elseif(str_ends_with($column, '_at') && $value)
                                            {{ \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') }}
                                        @elseif(is_numeric($value) && in_array($column, ['amount', 'price', 'cost', 'cost_price', 'total'], true))
                                            {{ number_format((float) $value, 2) }}
                                        @else
                                            {{ $value === null || $value === '' ? '—' : \Illuminate\Support\Str::limit((string) $value, 70) }}
                                        @endif
                                    </td>
                                @endforeach

                                @if($allowEdit || $allowDelete)
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if($allowEdit)
                                                <button type="button"
                                                        class="btn btn-outline-primary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editModuleModal{{ $record->id }}"
                                                        title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </button>
                                            @endif

                                            @if($allowDelete)
                                                <button type="button"
                                                        class="btn btn-outline-danger js-delete-module"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteModuleModal"
                                                        data-action="{{ route('admin.modules.destroy', ['module' => $module, 'id' => $record->id]) }}"
                                                        data-label="{{ $record->name ?? $record->title ?? $record->label ?? ('Record #' . $record->id) }}"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 1 + count($columns) + (($allowEdit || $allowDelete) ? 1 : 0) }}" class="text-center py-5">
                                    <div class="mx-auto mb-3 rounded-circle bg-light text-muted d-flex align-items-center justify-content-center"
                                         style="width:68px;height:68px;">
                                        <i class="{{ $definition['icon'] }} fa-xl"></i>
                                    </div>
                                    <h3 class="h6 fw-bold mb-1">No records found</h3>
                                    <p class="text-muted mb-3">No {{ strtolower($definition['title']) }} match the current filters.</p>

                                    @if($allowCreate)
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModuleModal">
                                            <i class="fas fa-plus me-1"></i>
                                            Add New
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($records->hasPages())
                <div class="card-footer bg-white py-3">
                    {{ $records->links() }}
                </div>
            @endif
        @else
            <div class="card-body py-5 text-center">
                <div class="mx-auto mb-3 rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center"
                     style="width:76px;height:76px;">
                    <i class="{{ $definition['icon'] }} fa-2x"></i>
                </div>

                <h3 class="h5 fw-bold">{{ $definition['title'] }}</h3>
                <p class="text-muted mx-auto mb-0" style="max-width:620px;">{{ $definition['description'] }}</p>
            </div>
        @endif
    </div>
</div>

@if($allowCreate)
<div class="modal fade" id="addModuleModal" tabindex="-1" aria-labelledby="addModuleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="POST" action="{{ route('admin.modules.store', ['module' => $module]) }}" class="modal-content">
            @csrf

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="addModuleModalLabel">
                        <i class="{{ $definition['icon'] }} text-primary me-2"></i>
                        Add {{ $definition['title'] }}
                    </h5>
                    <small class="text-muted">Enter the details below.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    @foreach($fields as $field)
                        @include('admin.modules.partials.field', [
                            'field' => $field,
                            'value' => old($field['name']),
                            'prefix' => 'add',
                        ])
                    @endforeach
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-floppy-disk me-1"></i>
                    Save
                </button>
            </div>
        </form>
    </div>
</div>
@endif

@if($allowEdit && $records)
    @foreach($records as $record)
        <div class="modal fade" id="editModuleModal{{ $record->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST"
                      action="{{ route('admin.modules.update', ['module' => $module, 'id' => $record->id]) }}"
                      class="modal-content">
                    @csrf
                    @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title">
                                <i class="fas fa-pen text-primary me-2"></i>
                                Edit {{ $definition['title'] }}
                            </h5>
                            <small class="text-muted">Update the selected record.</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            @foreach($fields as $field)
                                @include('admin.modules.partials.field', [
                                    'field' => $field,
                                    'value' => $record->{$field['name']} ?? null,
                                    'prefix' => 'edit_' . $record->id,
                                ])
                            @endforeach
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-floppy-disk me-1"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endif

@if($allowDelete)
<div class="modal fade" id="deleteModuleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-triangle-exclamation text-danger me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="mb-1">Are you sure you want to delete this record?</p>
                <strong id="deleteModuleLabel"></strong>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" id="deleteModuleForm">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-1"></i>
                        Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-delete-module').forEach(function (button) {
        button.addEventListener('click', function () {
            const form = document.getElementById('deleteModuleForm');
            const label = document.getElementById('deleteModuleLabel');

            if (form) {
                form.action = button.dataset.action || '';
            }

            if (label) {
                label.textContent = button.dataset.label || '';
            }
        });
    });

    @if($errors->any() && $allowCreate)
        const addModal = document.getElementById('addModuleModal');
        if (addModal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(addModal).show();
        }
    @endif
});
</script>
@endpush
