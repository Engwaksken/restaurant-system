@extends('layouts.app')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
<div class="row g-4 mb-4">
    <div class="col-lg-4"><div class="panel p-4 h-100">
        <h2 class="h5">Time clock</h2>
        <form method="POST" action="{{ route('admin.attendance.clock') }}">
            @csrf
            @if($open)
                <p>Clocked in at <strong>{{ $open->clock_in_at->format('H:i') }}</strong> ({{ $open->branch?->name }}).</p>
                <input type="hidden" name="action" value="out">
                <button class="btn btn-danger btn-lg w-100" type="submit">Clock out</button>
            @else
                <input type="hidden" name="action" value="in">
                @unless(auth()->user()->branch_id)
                    <label class="form-label" for="clockBranch">Branch</label>
                    <select class="form-select mb-2" id="clockBranch" name="branch_id" required>@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                @endunless
                <button class="btn btn-success btn-lg w-100" type="submit">Clock in</button>
            @endif
        </form>
        @if($manages)
            <details class="mt-4">
                <summary>Record missed attendance</summary>
                <form method="POST" action="{{ route('admin.attendance.store') }}" class="mt-2">
                    @csrf
                    <select class="form-select form-select-sm mb-1" name="user_id" required aria-label="Staff member">@foreach($staff as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select>
                    <select class="form-select form-select-sm mb-1" name="branch_id" required aria-label="Branch">@foreach($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach</select>
                    <label class="form-label small mb-0" for="manualIn">In</label><input class="form-control form-control-sm mb-1" id="manualIn" type="datetime-local" name="clock_in_at" required>
                    <label class="form-label small mb-0" for="manualOut">Out</label><input class="form-control form-control-sm mb-1" id="manualOut" type="datetime-local" name="clock_out_at" required>
                    <input class="form-control form-control-sm mb-1" name="note" minlength="5" maxlength="1000" required placeholder="Why is this being recorded manually?" aria-label="Note">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Record</button>
                </form>
            </details>
        @endif
    </div></div>
    <div class="col-lg-8"><div class="panel p-4 h-100">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-auto"><label class="form-label" for="attFrom">From</label><input class="form-control" type="date" id="attFrom" name="date_from" value="{{ $from->toDateString() }}"></div>
            <div class="col-auto"><label class="form-label" for="attTo">To</label><input class="form-control" type="date" id="attTo" name="date_to" value="{{ $to->toDateString() }}"></div>
            @if($manages)
                <div class="col-auto"><label class="form-label" for="attUser">Staff</label><select class="form-select" id="attUser" name="user_id"><option value="">Everyone</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === $member->id)>{{ $member->name }}</option>@endforeach</select></div>
            @endif
            <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Show</button></div>
            <div class="col text-end small text-muted">Hours on this page: <strong>{{ number_format($totalHours, 2) }}</strong></div>
        </form>
        <div class="table-responsive"><table class="table table-sm align-middle">
            <thead><tr>@if($manages)<th>Staff</th>@endif<th>Branch</th><th>In</th><th>Out</th><th class="text-end">Hours</th><th>Note</th></tr></thead>
            <tbody>
            @forelse($records as $record)
                <tr>
                    @if($manages)<td>{{ $record->user?->name }}</td>@endif
                    <td>{{ $record->branch?->name }}</td>
                    <td>{{ $record->clock_in_at->format('D j M H:i') }}</td>
                    <td>{{ $record->clock_out_at?->format('H:i') ?? 'On shift' }}</td>
                    <td class="text-end">{{ $record->clock_out_at ? number_format($record->hours(), 2) : '—' }}</td>
                    <td class="small">{{ $record->note }}@if($record->editor)<span class="d-block text-muted">Edited by {{ $record->editor->name }}</span>@endif
                        @if($manages)
                            <details>
                                <summary>Correct</summary>
                                <form method="POST" action="{{ route('admin.attendance.update', $record->id) }}" class="mt-1">
                                    @csrf @method('PUT')
                                    <input class="form-control form-control-sm mb-1" type="datetime-local" name="clock_in_at" value="{{ $record->clock_in_at->format('Y-m-d\TH:i') }}" required aria-label="Clock in">
                                    <input class="form-control form-control-sm mb-1" type="datetime-local" name="clock_out_at" value="{{ $record->clock_out_at?->format('Y-m-d\TH:i') }}" aria-label="Clock out">
                                    <input class="form-control form-control-sm mb-1" name="note" minlength="5" maxlength="1000" required placeholder="Reason for correction" aria-label="Reason">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No attendance in this period.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $records->links() }}
    </div></div>
</div>
@endsection
