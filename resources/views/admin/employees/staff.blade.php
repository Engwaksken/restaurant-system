@extends('layouts.app')

@section('title', 'Staff')
@section('page-title', 'Staff Accounts')

@section('content')
<div class="row g-4">
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add staff account</h2>
        <form method="POST" action="{{ route('admin.staff.store') }}" autocomplete="off">
            @csrf
            <label class="form-label" for="staffName">Full name</label>
            <input class="form-control mb-2" id="staffName" name="name" value="{{ old('name') }}" maxlength="255" required>
            <label class="form-label" for="staffEmail">Email (used to sign in)</label>
            <input class="form-control mb-2" id="staffEmail" type="email" name="email" value="{{ old('email') }}" maxlength="255" required>
            <label class="form-label" for="staffBranch">Branch</label>
            <select class="form-select mb-2" id="staffBranch" name="branch_id" @unless($restaurantWide) required @endunless>
                @if($restaurantWide)<option value="">All branches (restaurant-wide)</option>@endif
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach
            </select>
            <label class="form-label" for="staffPassword">Temporary password</label>
            <input class="form-control mb-2" id="staffPassword" type="password" name="password" minlength="12" required autocomplete="new-password">
            <label class="form-label" for="staffPasswordConfirm">Confirm password</label>
            <input class="form-control mb-1" id="staffPasswordConfirm" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password">
            <small class="text-muted d-block mb-3">At least 12 characters with letters and numbers.</small>
            <button class="btn btn-primary" type="submit">Create account</button>
        </form>
        <p class="small text-muted mt-3 mb-0">New accounts have no permissions. Grant them on <a href="{{ route('admin.settings.roles-permissions') }}">Roles &amp; Permissions</a>.</p>
    </div></div>
    <div class="col-lg-8"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Staff</h2>
            @if(request('show') === 'all')
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.staff.index') }}">Hide deactivated</a>
            @else
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.staff.index', ['show' => 'all']) }}">Show deactivated</a>
            @endif
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
                            <details>
                                <summary class="small">Manage</summary>
                                <form method="POST" action="{{ route('admin.staff.update', $member->id) }}" class="mt-2">
                                    @csrf @method('PUT')
                                    <input class="form-control form-control-sm mb-1" name="name" value="{{ $member->name }}" maxlength="255" required aria-label="Name">
                                    <input class="form-control form-control-sm mb-1" type="email" name="email" value="{{ $member->email }}" maxlength="255" required aria-label="Email">
                                    <select class="form-select form-select-sm mb-1" name="branch_id" aria-label="Branch" @unless($restaurantWide) required @endunless>
                                        @if($restaurantWide)<option value="">All branches</option>@endif
                                        @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected($member->branch_id === $branch->id)>{{ $branch->name }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                                <form method="POST" action="{{ route('admin.staff.password', $member->id) }}" class="mt-2" autocomplete="off">
                                    @csrf @method('PUT')
                                    <input class="form-control form-control-sm mb-1" type="password" name="password" minlength="12" required placeholder="New password" aria-label="New password" autocomplete="new-password">
                                    <input class="form-control form-control-sm mb-1" type="password" name="password_confirmation" minlength="12" required placeholder="Confirm" aria-label="Confirm new password" autocomplete="new-password">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Reset password</button>
                                </form>
                                @if($member->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.staff.status', $member->id) }}" class="mt-2" @if($member->is_active) onsubmit="return confirm('Deactivate this account? They will be signed out immediately.')" @endif>
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="is_active" value="{{ $member->is_active ? 0 : 1 }}">
                                    <button class="btn btn-sm {{ $member->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $member->is_active ? 'Deactivate' : 'Reactivate' }}</button>
                                </form>
                                @endif
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">No staff accounts.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $staff->links() }}
    </div></div>
</div>
@endsection
