@extends('layouts.app')

@section('title', 'Branches')
@section('page-title', 'Branches')

@section('content')
<div class="panel p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h2 class="h5 mb-0">Your branches</h2>
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-branch">
            <i class="fas fa-plus me-1"></i> Add branch
        </button>
    </div>
    @foreach($branches as $branch)
        <div class="border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <strong>{{ $branch->name }}</strong> <code>{{ $branch->code }}</code>
                @if($branch->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif
                <small class="text-muted d-block">{{ $branch->staff_count }} active staff</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-branch-{{ $branch->id }}" title="Edit">
                    <i class="fas fa-pen-to-square me-1"></i> Edit
                </button>
                @if($branch->is_active)
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivate-branch-{{ $branch->id }}">
                        <i class="fas fa-ban me-1"></i> Deactivate
                    </button>
                @else
                    <form method="POST" action="{{ route('admin.settings.branches.status', $branch->id) }}" class="d-inline">
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="{{ $branch->is_active ? 0 : 1 }}">
                        <button class="btn btn-sm btn-outline-success" type="submit">
                            <i class="fas fa-circle-check me-1"></i> Activate
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endforeach
</div>

{{-- =========================================================
     ADD BRANCH MODAL
     OUTSIDE THE LIST
========================================================= --}}
<div class="modal fade" id="add-branch" tabindex="-1" aria-labelledby="add-branch-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('admin.settings.branches.store') }}" data-modal-form>
            @csrf

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="add-branch-label">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>
                        Add branch
                    </h5>
                    <small class="text-muted">Create a new branch for this restaurant.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                @include('admin.settings._branch-fields', ['branch' => null])
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>
                    Create Branch
                </button>
            </div>
        </form>
    </div>
</div>

{{-- =========================================================
     EDIT / DEACTIVATE MODALS
     IMPORTANT: rendered outside the list.
========================================================= --}}
@foreach($branches as $branch)

    {{-- Edit branch --}}
    <div class="modal fade" id="edit-branch-{{ $branch->id }}" tabindex="-1" aria-labelledby="edit-branch-label-{{ $branch->id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('admin.settings.branches.update', $branch->id) }}" data-modal-form>
                @csrf @method('PUT')

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="edit-branch-label-{{ $branch->id }}">
                            <i class="fas fa-pen-to-square me-2 text-primary"></i>
                            Edit branch
                        </h5>
                        <small class="text-muted">{{ $branch->name }}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @include('admin.settings._branch-fields', ['branch' => $branch])
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

    @if($branch->is_active)
        {{-- Deactivate confirmation --}}
        <div class="modal fade" id="deactivate-branch-{{ $branch->id }}" tabindex="-1" aria-labelledby="deactivate-branch-label-{{ $branch->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('admin.settings.branches.status', $branch->id) }}" data-modal-form>
                    @csrf @method('PATCH')
                    <input type="hidden" name="is_active" value="0">

                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title" id="deactivate-branch-label-{{ $branch->id }}">
                            Deactivate branch
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body text-center py-4">
                        <div class="delete-icon-wrap bg-danger-subtle text-danger">
                            <i class="fas fa-ban fa-lg"></i>
                        </div>

                        <h5 class="mb-2">
                            Deactivate {{ $branch->name }}?
                        </h5>

                        <p class="text-muted mb-0">
                            It will no longer accept orders until it is reactivated.
                        </p>
                    </div>

                    <div class="modal-footer border-0 justify-content-center pt-0 pb-4">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="fas fa-ban me-1"></i>
                            Deactivate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endforeach
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    const hasErrors = @json($errors->any());
    const pendingId = sessionStorage.getItem('pendingModal');
    sessionStorage.removeItem('pendingModal');

    const target = hasErrors && pendingId ? document.getElementById(pendingId) : null;

    document.querySelectorAll('.modal').forEach(function (modal) {
        if (modal === target) {
            return;
        }

        modal.querySelectorAll('.is-invalid').forEach(function (element) {
            element.classList.remove('is-invalid');
        });

        modal.querySelectorAll('.invalid-feedback').forEach(function (element) {
            element.remove();
        });
    });

    document.querySelectorAll('form[data-modal-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            sessionStorage.setItem('pendingModal', this.closest('.modal').id);
        });
    });

    if (target && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(target).show();
    }

});
</script>
@endpush
