@extends('layouts.app')

@section('title', 'Roles')
@section('page-title', 'Roles')
@section('page-subtitle', 'Ready-made permission sets for each job in your restaurant')

@section('content')
<x-admin.stats :items="$pageStats" />

<x-admin.page-header title="Staff roles" subtitle="Assigning a role replaces that person's permissions with the role's set. You can fine-tune anyone afterwards on Roles & Permissions.">
    <a href="{{ route('admin.settings.roles-permissions') }}" class="btn btn-outline-secondary"><i class="fas fa-sliders me-2"></i>Fine-tune permissions</a>
</x-admin.page-header>

<div class="row g-3 mb-4">
    @foreach($roles as $key => $role)
        <div class="col-md-6 col-xxl-4">
            <div class="panel p-4 h-100 d-flex flex-column role-card tone-{{ $role['tone'] }}">
                <div class="d-flex align-items-start gap-3 mb-2">
                    <span class="stat-icon" aria-hidden="true"><i class="{{ $role['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h3 class="h6 mb-1">{{ $role['label'] }}</h3>
                        <span class="small text-muted">
                            <i class="fas fa-users me-1"></i>{{ $counts->get($key, 0) }} {{ \Illuminate\Support\Str::plural('person', $counts->get($key, 0)) }}
                            <span class="mx-1">&middot;</span>
                            <i class="fas fa-key me-1"></i>{{ count($role['abilities']) }} permissions
                        </span>
                    </div>
                </div>
                <p class="small text-muted mb-3">{{ $role['description'] }}</p>

                <details class="role-permissions mb-3">
                    <summary class="small fw-semibold">View permissions</summary>
                    <ul class="list-unstyled small mt-2 mb-0 d-grid gap-1">
                        @foreach($role['abilities'] as $ability)
                            <li><i class="fas fa-check text-success me-2"></i>{{ $abilities[$ability] ?? $ability }}</li>
                        @endforeach
                    </ul>
                </details>

                <button type="button" class="btn btn-sm btn-primary mt-auto align-self-start" data-bs-toggle="modal" data-bs-target="#assign-role-{{ $key }}" @disabled($assignable->isEmpty())>
                    <i class="fas fa-user-plus me-1"></i> Assign to staff
                </button>
            </div>
        </div>
    @endforeach
</div>

<div class="panel p-4">
    <h2 class="h5 mb-3">Who has which role</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Staff member</th><th>Branch</th><th>Role</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>
            @forelse($members as $member)
                @php($assigned = $assignments[$member->id])
                <tr>
                    <td>
                        <strong>{{ $member->name }}</strong>
                        @if($member->is_active === false)<span class="badge bg-secondary ms-1">Deactivated</span>@endif
                        <small class="d-block text-muted">{{ $member->email }}</small>
                    </td>
                    <td>{{ $member->branch?->name ?? 'All branches' }}</td>
                    <td>
                        @if($assigned === 'admin')
                            <span class="badge bg-danger"><i class="fas fa-crown me-1"></i>Full administrator</span>
                        @elseif($assigned === 'none')
                            <span class="badge bg-warning"><i class="fas fa-user-lock me-1"></i>No access yet</span>
                        @elseif($assigned === 'custom')
                            <span class="badge bg-info"><i class="fas fa-sliders me-1"></i>Custom</span>
                        @else
                            <span class="badge bg-primary"><i class="{{ $roles[$assigned]['icon'] }} me-1"></i>{{ $roles[$assigned]['label'] }}</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($assigned !== 'admin')
                            <a href="{{ route('admin.settings.roles-permissions') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen-to-square me-1"></i>Edit access</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No staff accounts yet. Create them on the Staff page.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $members->links() }}
</div>

@foreach($roles as $key => $role)
    <div class="modal fade" id="assign-role-{{ $key }}" tabindex="-1" aria-labelledby="assign-role-label-{{ $key }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.roles.assign', $key) }}">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="assign-role-label-{{ $key }}"><i class="{{ $role['icon'] }} me-2 text-primary"></i>Assign {{ $role['label'] }}</h5>
                        <small class="text-muted">{{ count($role['abilities']) }} permissions will be granted.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="assign-user-{{ $key }}">Staff member <span class="text-danger">*</span></label>
                    <select class="form-select" id="assign-user-{{ $key }}" name="user_id" required>
                        <option value="" selected disabled>Choose a person</option>
                        @foreach($assignable as $member)
                            <option value="{{ $member->id }}">{{ $member->name }} ({{ $member->email }})</option>
                        @endforeach
                    </select>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="fas fa-triangle-exclamation me-1"></i>
                        This replaces the person's current permissions. Full administrators can't be changed here.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check me-1"></i>Assign role</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
