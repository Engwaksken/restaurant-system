@extends('layouts.app')

@section('title', 'Staff permissions')
@section('page-title', 'Staff Permissions')

@section('content')
<div class="panel p-4">
    <p class="text-muted">Permissions apply within your restaurant. Branch restrictions continue to apply. Wildcard administrators and tenant assignments are intentionally managed outside this screen.</p>
    @forelse($users as $staff)
        @php($granted = $staff->permissions->pluck('ability')->all())
        <form class="border rounded p-3 mb-3" method="POST" action="{{ route('admin.settings.roles-permissions.update', $staff->id) }}">
            @csrf @method('PUT')
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div><h2 class="h6 mb-1">{{ $staff->name }}</h2><span class="text-muted">{{ $staff->email }} · {{ $staff->branch_id ? 'Branch ' . $staff->branch_id : 'All assigned branches' }}</span></div>
                @if(in_array('admin', $granted, true))<span class="badge text-bg-danger">Wildcard admin — CLI managed</span>@else<button class="btn btn-primary btn-sm" type="submit">Save permissions</button>@endif
            </div>
            @if(in_array('admin', $granted, true))
                <p class="mb-0 text-muted">Permissions are not editable here for wildcard administrators.</p>
            @else
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
            @endif
        </form>
    @empty
        <p class="text-muted">No users are assigned to this restaurant.</p>
    @endforelse
    {{ $users->links() }}
</div>
@endsection
