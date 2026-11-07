@extends('layouts.app')

@section('title', 'Attendance')
@section('page-title', 'Attendance')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $correctRows = $manages ? collect($records->items()) : collect();
    $activeCorrectId = session()->hasOldInput('clock_in_at') && ! session()->hasOldInput('user_id') && $errors->hasAny(['clock_in_at', 'clock_out_at', 'note']) && $correctRows->count() === 1 ? $correctRows->first()->id : null;
    $autoOpen = null;
    if ($errors->any()) {
        if (session()->hasOldInput('user_id')) {
            $autoOpen = 'add-attendance';
        } elseif ($activeCorrectId) {
            $autoOpen = 'edit-attendance-' . $activeCorrectId;
        }
    }
@endphp
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
            <button type="button" class="btn btn-outline-primary btn-sm mt-4" data-bs-toggle="modal" data-bs-target="#add-attendance">
                <i class="fas fa-plus me-1"></i>
                Record missed attendance
            </button>
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
                            <button type="button" class="btn btn-sm btn-outline-primary mt-1" data-bs-toggle="modal" data-bs-target="#edit-attendance-{{ $record->id }}">
                                <i class="fas fa-pen-to-square me-1"></i>Correct
                            </button>
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

{{-- Modals are outside the table. --}}
@if($manages)
    <div class="modal fade" id="add-attendance" tabindex="-1" aria-labelledby="add-attendance-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.attendance.store') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-attendance-label"><i class="fas fa-user-clock me-2 text-primary"></i>Record missed attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="manualUser">Staff member <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('user_id') is-invalid @enderror" id="manualUser" name="user_id" required>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('user_id') === (string) $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('branch_id') is-invalid @enderror" id="manualBranch" name="branch_id" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualIn">Clock in <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('clock_in_at') is-invalid @enderror" id="manualIn" type="datetime-local" name="clock_in_at" value="{{ old('clock_in_at') }}" required>
                            @error('clock_in_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="manualOut">Clock out <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('clock_out_at') is-invalid @enderror" id="manualOut" type="datetime-local" name="clock_out_at" value="{{ old('clock_out_at') }}" required>
                            @error('clock_out_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="manualNote">Reason <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('note') is-invalid @enderror" id="manualNote" name="note" minlength="5" maxlength="1000" value="{{ old('note') }}" required placeholder="Why is this being recorded manually?">
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                        Record
                    </button>
                </div>

            </form>
        </div>
    </div>
@endif

@foreach($correctRows as $record)
    @php
        $isActiveCorrect = $activeCorrectId === $record->id;
        $inValue = $record->clock_in_at->format('Y-m-d\TH:i');
        $outValue = $record->clock_out_at?->format('Y-m-d\TH:i');
    @endphp
    <div class="modal fade" id="edit-attendance-{{ $record->id }}" tabindex="-1" aria-labelledby="edit-attendance-label-{{ $record->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.attendance.update', $record->id) }}">
                @csrf
                @method('PUT')

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="edit-attendance-label-{{ $record->id }}"><i class="fas fa-pen-to-square me-2 text-primary"></i>Correct attendance</h5>
                        <small class="text-muted">{{ $record->user?->name }} · {{ $record->clock_in_at->format('D j M H:i') }}</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="correctIn{{ $record->id }}">Clock in <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control{{ $isActiveCorrect && $errors->has('clock_in_at') ? ' is-invalid' : '' }}" id="correctIn{{ $record->id }}" type="datetime-local" name="clock_in_at" value="{{ $isActiveCorrect ? old('clock_in_at', $inValue) : $inValue }}" required>
                        @if($isActiveCorrect)
                            @error('clock_in_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="correctOut{{ $record->id }}">Clock out</label>
                        <input class="form-control{{ $isActiveCorrect && $errors->has('clock_out_at') ? ' is-invalid' : '' }}" id="correctOut{{ $record->id }}" type="datetime-local" name="clock_out_at" value="{{ $isActiveCorrect ? old('clock_out_at', $outValue) : $outValue }}">
                        @if($isActiveCorrect)
                            @error('clock_out_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                    </div>

                    <div>
                        <label class="form-label" for="correctNote{{ $record->id }}">Reason for correction <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control{{ $isActiveCorrect && $errors->has('note') ? ' is-invalid' : '' }}" id="correctNote{{ $record->id }}" name="note" minlength="5" maxlength="1000" required placeholder="e.g. Forgot to clock out at end of shift" value="{{ $isActiveCorrect ? old('note') : '' }}">
                        @if($isActiveCorrect)
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
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
@endforeach

@if($autoOpen)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById(@json($autoOpen));

        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
@endsection
