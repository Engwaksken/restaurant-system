@extends('layouts.app')

@section('title', 'Shifts')
@section('page-title', 'Shifts')

@section('content')
<div class="row g-4">
    @if($manages)
    <div class="col-lg-3"><div class="panel p-4">
        <h2 class="h5">Add shift</h2>
        <form method="POST" action="{{ route('admin.shifts.store') }}">
            @csrf
            <label class="form-label" for="shiftUser">Staff</label>
            <select class="form-select mb-2" id="shiftUser" name="user_id" required>@foreach($staff as $member)<option value="{{ $member->id }}" @selected(old('user_id') == $member->id)>{{ $member->name }}</option>@endforeach</select>
            <label class="form-label" for="shiftBranch">Branch</label>
            <select class="form-select mb-2" id="shiftBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
            <label class="form-label" for="shiftStart">Starts</label>
            <input class="form-control mb-2" id="shiftStart" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
            <label class="form-label" for="shiftEnd">Ends</label>
            <input class="form-control mb-2" id="shiftEnd" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required>
            <label class="form-label" for="shiftRole">Role (optional)</label>
            <input class="form-control mb-3" id="shiftRole" name="role_label" maxlength="100" placeholder="e.g. Grill, Cashier" value="{{ old('role_label') }}">
            <button class="btn btn-primary" type="submit">Add shift</button>
        </form>
    </div></div>
    @endif
    <div class="{{ $manages ? 'col-lg-9' : 'col-12' }}"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.shifts.index', ['week' => $week->copy()->subWeek()->toDateString()]) }}">← Previous week</a>
            <h2 class="h5 mb-0">{{ $manages ? 'Rota' : 'My shifts' }} · week of {{ $week->format('j M Y') }}</h2>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.shifts.index', ['week' => $week->copy()->addWeek()->toDateString()]) }}">Next week →</a>
        </div>
        <div class="row g-2">
            @foreach($days as $day)
                <div class="col-md-6 col-xl">
                    <div class="border rounded p-2 h-100 {{ $day->isToday() ? 'border-primary' : '' }}">
                        <strong class="d-block small">{{ $day->format('D j') }}</strong>
                        @forelse($shifts->filter(fn ($shift) => $shift->starts_at->isSameDay($day)) as $shift)
                            <div class="small border-top pt-1 mt-1">
                                <strong>{{ $shift->starts_at->format('H:i') }}–{{ $shift->ends_at->format('H:i') }}</strong>
                                <span class="d-block">{{ $shift->user?->name }}</span>
                                <span class="text-muted">{{ $shift->role_label }}{{ $shift->role_label ? ' · ' : '' }}{{ $shift->branch?->name }}</span>
                                @if($manages && $shift->starts_at->isFuture())
                                    <form method="POST" action="{{ route('admin.shifts.destroy', $shift->id) }}" onsubmit="return confirm('Remove this shift?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 text-danger" type="submit">Remove</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <span class="small text-muted">—</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div></div>
</div>
@endsection
