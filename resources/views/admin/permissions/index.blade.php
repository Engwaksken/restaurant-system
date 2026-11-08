@extends('layouts.app')

@section('title', 'Staff permissions')
@section('page-title', 'Staff Permissions')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
<div class="panel p-4">
    <p class="text-muted">Permissions apply within your restaurant. Branch restrictions continue to apply. Wildcard administrators and tenant assignments are intentionally managed outside this screen.</p>
    @forelse($users as $staff)
        @php($granted = $staff->permissions->pluck('ability')->all())
        <div class="border rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 mb-1">
                    {{ $staff->name }}
                    @php($roleKey = in_array('admin', $granted, true) ? null : \App\Support\StaffRoles::match($granted))
                    @if(in_array('admin', $granted, true))
                    @elseif($roleKey)
                        <span class="badge bg-primary ms-1"><i class="{{ $roles[$roleKey]['icon'] }} me-1"></i>{{ $roles[$roleKey]['label'] }}</span>
                    @elseif(count($granted))
                        <span class="badge bg-info ms-1"><i class="fas fa-sliders me-1"></i>Custom · {{ count($granted) }} permissions</span>
                    @else
                        <span class="badge bg-warning ms-1"><i class="fas fa-user-lock me-1"></i>No access yet</span>
                    @endif
                </h2>
                <span class="text-muted">{{ $staff->email }} · {{ $staff->branch?->name ?? 'All branches' }}</span>
            </div>
            @if(in_array('admin', $granted, true))
                <span class="badge text-bg-danger">Wildcard admin, CLI managed</span>
            @else
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-permissions-{{ $staff->id }}">
                    <i class="fas fa-pen-to-square me-1"></i> Edit permissions
                </button>
            @endif
        </div>
    @empty
        <p class="text-muted">No users are assigned to this restaurant.</p>
    @endforelse
    {{ $users->links() }}
</div>

{{-- =========================================================
     EDIT PERMISSIONS MODALS
     OUTSIDE THE LIST
========================================================= --}}
@foreach($users as $staff)
    @php($granted = $staff->permissions->pluck('ability')->all())
    @unless(in_array('admin', $granted, true))
        <div class="modal fade" id="edit-permissions-{{ $staff->id }}" tabindex="-1" aria-labelledby="edit-permissions-label-{{ $staff->id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('admin.settings.roles-permissions.update', $staff->id) }}" data-modal-form>
                    @csrf @method('PUT')

                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="edit-permissions-label-{{ $staff->id }}">
                                <i class="fas fa-shield-halved me-2 text-primary"></i>
                                Edit permissions
                            </h5>
                            <small class="text-muted">{{ $staff->name }} · {{ $staff->email }}</small>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        @foreach(collect($errors->messages())->filter(fn ($messages, $key) => str_starts_with($key, 'abilities'))->flatten() as $message)
                            <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                        @endforeach

                        <div class="role-picker mb-3 p-3 rounded border bg-light">
                            <label class="form-label" for="role-start-{{ $staff->id }}"><i class="fas fa-user-shield me-1"></i>Start from a role</label>
                            <select class="form-select form-select-sm" id="role-start-{{ $staff->id }}" data-role-picker>
                                <option value="">Keep the current selection</option>
                                @foreach($roles as $key => $role)
                                    <option value="{{ $key }}" data-abilities="{{ implode(',', $role['abilities']) }}">{{ $role['label'] }} ({{ count($role['abilities']) }} permissions)</option>
                                @endforeach
                            </select>
                            <div class="form-text">Ticks that role's permissions below. You can still adjust them before saving.</div>
                        </div>

                        <div class="row">
                            @foreach($abilities as $ability => $label)
                                <div class="col-md-6 col-xl-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="abilities[]" value="{{ $ability }}" id="u{{ $staff->id }}-{{ Str::slug($ability) }}" @checked(in_array($ability, $granted, true))>
                                        <label class="form-check-label" for="u{{ $staff->id }}-{{ Str::slug($ability) }}">{{ $label }}</label>
                                    </div>
                                </div>
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
                            Save Permissions
                        </button>
                    </div>
                </form>
            </div>
        </div>
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

        modal.querySelectorAll('.invalid-feedback').forEach(function (element) {
            element.remove();
        });
    });

    document.querySelectorAll('form[data-modal-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            sessionStorage.setItem('pendingModal', this.closest('.modal').id);
        });
    });

    document.querySelectorAll('[data-role-picker]').forEach(function (picker) {
        picker.addEventListener('change', function () {
            const option = picker.selectedOptions[0];
            if (!option || !option.value) { return; }
            const wanted = option.dataset.abilities.split(',');
            picker.closest('form').querySelectorAll('input[name="abilities[]"]').forEach(function (box) {
                box.checked = wanted.includes(box.value);
            });
        });
    });

    if (target && window.bootstrap) {
        bootstrap.Modal.getOrCreateInstance(target).show();
    }

});
</script>
@endpush
