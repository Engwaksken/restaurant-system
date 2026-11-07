@extends('layouts.app')

@section('title', 'Staff')
@section('page-title', 'Staff Accounts')

@section('content')
<div class="panel p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
            <h2 class="h5 mb-1">Staff</h2>
            <small class="text-muted">New accounts have no permissions. Grant them on <a href="{{ route('admin.settings.roles-permissions') }}">Roles &amp; Permissions</a>.</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @if(request('show') === 'all')
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.staff.index') }}">Hide deactivated</a>
            @else
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.staff.index', ['show' => 'all']) }}">Show deactivated</a>
            @endif
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-staff">
                <i class="fas fa-plus me-1"></i> Add staff account
            </button>
        </div>
    </div>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Name</th><th>Branch</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($staff as $member)
            <tr class="{{ $member->is_active ? '' : 'text-muted' }}">
                <td><strong>{{ $member->name }}</strong><small class="d-block">{{ $member->email }}</small></td>
                <td>{{ $member->branch?->name ?? 'All branches' }}</td>
                <td>@if($member->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Deactivated</span>@endif
                    @if(in_array($member->id, $wildcardIds, true))<span class="badge bg-dark">Administrator</span>@endif</td>
                <td>
                    @if(in_array($member->id, $wildcardIds, true))
                        <small class="text-muted">Managed by system administrators</small>
                    @else
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-staff-{{ $member->id }}" title="Edit">
                                <i class="fas fa-pen-to-square me-1"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#reset-staff-password-{{ $member->id }}" title="Reset password">
                                <i class="fas fa-key me-1"></i> Reset password
                            </button>
                            @if($member->id !== auth()->id())
                                @if($member->is_active)
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deactivate-staff-{{ $member->id }}">
                                        <i class="fas fa-user-slash me-1"></i> Deactivate
                                    </button>
                                @else
                                    <form method="POST" action="{{ route('admin.staff.status', $member->id) }}" class="d-inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">
                                        <button class="btn btn-sm btn-outline-success" type="submit">
                                            <i class="fas fa-user-check me-1"></i> Reactivate
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted">No staff accounts.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $staff->links() }}
</div>

{{-- =========================================================
     ADD STAFF MODAL
     OUTSIDE THE TABLE
========================================================= --}}
<div class="modal fade" id="add-staff" tabindex="-1" aria-labelledby="add-staff-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" method="POST" action="{{ route('admin.staff.store') }}" autocomplete="off" data-modal-form>
            @csrf

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="add-staff-label">
                        <i class="fas fa-user-plus me-2 text-primary"></i>
                        Add staff account
                    </h5>
                    <small class="text-muted">New accounts have no permissions until granted.</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="addStaffName">Full name <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('name') is-invalid @enderror" id="addStaffName" name="name" value="{{ old('name') }}" maxlength="255" placeholder="e.g. Jane Achieng" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="addStaffEmail">Email (used to sign in) <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('email') is-invalid @enderror" id="addStaffEmail" type="email" name="email" value="{{ old('email') }}" maxlength="255" placeholder="e.g. jane@restaurant.com" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="addStaffBranch">Branch @unless($restaurantWide)<span class="text-danger" aria-hidden="true">*</span>@endunless</label>
                        <select class="form-select @error('branch_id') is-invalid @enderror" id="addStaffBranch" name="branch_id" @unless($restaurantWide) required @endunless>
                            @if($restaurantWide)<option value="">All branches (restaurant-wide)</option>@endif
                            @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="addStaffPassword">Temporary password <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control @error('password') is-invalid @enderror" id="addStaffPassword" type="password" name="password" minlength="12" placeholder="At least 12 characters" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="addStaffPasswordConfirm">Confirm password <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control" id="addStaffPasswordConfirm" type="password" name="password_confirmation" minlength="12" placeholder="Repeat the password" required autocomplete="new-password">
                    </div>

                    <div class="col-12">
                        <small class="text-muted">At least 12 characters with letters and numbers.</small>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="fas fa-xmark me-1"></i>
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-1"></i>
                    Create account
                </button>
            </div>
        </form>
    </div>
</div>

{{-- =========================================================
     EDIT / PASSWORD / DEACTIVATE MODALS
     IMPORTANT: rendered outside <table>/<tbody>.
========================================================= --}}
@foreach($staff as $member)
    @unless(in_array($member->id, $wildcardIds, true))

        {{-- Edit staff --}}
        <div class="modal fade" id="edit-staff-{{ $member->id }}" tabindex="-1" aria-labelledby="edit-staff-label-{{ $member->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('admin.staff.update', $member->id) }}" data-modal-form>
                    @csrf @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-staff-label-{{ $member->id }}">
                                <i class="fas fa-pen-to-square me-2 text-primary"></i>
                                Edit staff account
                            </h5>
                            <small class="text-muted">{{ $member->name }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="editStaffName{{ $member->id }}">Full name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('name') is-invalid @enderror" id="editStaffName{{ $member->id }}" name="name" value="{{ $member->name }}" maxlength="255" placeholder="e.g. Jane Achieng" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="editStaffEmail{{ $member->id }}">Email (used to sign in) <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('email') is-invalid @enderror" id="editStaffEmail{{ $member->id }}" type="email" name="email" value="{{ $member->email }}" maxlength="255" placeholder="e.g. jane@restaurant.com" required>
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="editStaffBranch{{ $member->id }}">Branch @unless($restaurantWide)<span class="text-danger" aria-hidden="true">*</span>@endunless</label>
                                <select class="form-select @error('branch_id') is-invalid @enderror" id="editStaffBranch{{ $member->id }}" name="branch_id" @unless($restaurantWide) required @endunless>
                                    @if($restaurantWide)<option value="">All branches</option>@endif
                                    @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($member->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach
                                </select>
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reset password --}}
        <div class="modal fade" id="reset-staff-password-{{ $member->id }}" tabindex="-1" aria-labelledby="reset-staff-password-label-{{ $member->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('admin.staff.password', $member->id) }}" autocomplete="off" data-modal-form>
                    @csrf @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="reset-staff-password-label-{{ $member->id }}">
                                <i class="fas fa-key me-2 text-primary"></i>
                                Reset password
                            </h5>
                            <small class="text-muted">{{ $member->name }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="resetStaffPassword{{ $member->id }}">New password <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('password') is-invalid @enderror" id="resetStaffPassword{{ $member->id }}" type="password" name="password" minlength="12" placeholder="At least 12 characters" required autocomplete="new-password">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label" for="resetStaffPasswordConfirm{{ $member->id }}">Confirm new password <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control" id="resetStaffPasswordConfirm{{ $member->id }}" type="password" name="password_confirmation" minlength="12" placeholder="Repeat the new password" required autocomplete="new-password">
                        </div>

                        <small class="text-muted">At least 12 characters with letters and numbers. Share it with them securely.</small>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            <i class="fas fa-xmark me-1"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-key me-1"></i>
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if($member->is_active && $member->id !== auth()->id())
            {{-- Deactivate confirmation --}}
            <div class="modal fade" id="deactivate-staff-{{ $member->id }}" tabindex="-1" aria-labelledby="deactivate-staff-label-{{ $member->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('admin.staff.status', $member->id) }}" data-modal-form>
                        @csrf @method('PATCH')
                        <input type="hidden" name="is_active" value="0">

                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title" id="deactivate-staff-label-{{ $member->id }}">
                                Deactivate staff account
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body text-center py-4">
                            <div class="delete-icon-wrap bg-danger-subtle text-danger">
                                <i class="fas fa-user-slash fa-lg"></i>
                            </div>

                            <h5 class="mb-2">
                                Deactivate {{ $member->name }}?
                            </h5>

                            <p class="text-muted mb-0">
                                They will be signed out immediately and cannot sign in until the account is reactivated.
                            </p>
                        </div>

                        <div class="modal-footer border-0 justify-content-center pt-0 pb-4">
                            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                                <i class="fas fa-xmark me-1"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-danger px-4">
                                <i class="fas fa-user-slash me-1"></i>
                                Deactivate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

    @endunless
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
