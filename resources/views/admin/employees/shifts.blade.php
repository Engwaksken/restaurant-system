@extends('layouts.app')

@section('title', 'Shifts')
@section('page-title', 'Shifts')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $autoOpen = $manages && $errors->any() && session()->hasOldInput('starts_at') ? 'add-shift' : null;
@endphp
<div class="row g-4">
    <div class="col-12"><div class="panel p-4">
        @if($manages)
            <div class="d-flex justify-content-end mb-3">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#add-shift">
                    <i class="fas fa-plus me-1"></i>
                    Add shift
                </button>
            </div>
        @endif
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
                                <strong>{{ $shift->starts_at->format('H:i') }}-{{ $shift->ends_at->format('H:i') }}</strong>
                                <span class="d-block">{{ $shift->user?->name }}</span>
                                <span class="text-muted">{{ $shift->role_label }}{{ $shift->role_label ? ' · ' : '' }}{{ $shift->branch?->name }}</span>
                                @if($manages && $shift->starts_at->isFuture())
                                    <button type="button" class="btn btn-outline-danger btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#delete-shift-{{ $shift->id }}">
                                        <i class="fas fa-trash me-1"></i>
                                        Remove
                                    </button>
                                @endif
                            </div>
                        @empty
                                <span class="small text-muted">-</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div></div>
</div>

{{-- Modals are outside the rota grid. --}}
@if($manages)
    <div class="modal fade" id="add-shift" tabindex="-1" aria-labelledby="add-shift-label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.shifts.store') }}">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="add-shift-label"><i class="fas fa-calendar-plus me-2 text-primary"></i>Add shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="shiftUser">Staff <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('user_id') is-invalid @enderror" id="shiftUser" name="user_id" required>
                                @foreach($staff as $member)
                                    <option value="{{ $member->id }}" @selected((string) old('user_id') === (string) $member->id)>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="shiftBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                            <select class="form-select @error('branch_id') is-invalid @enderror" id="shiftBranch" name="branch_id" required>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="shiftStart">Starts <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('starts_at') is-invalid @enderror" id="shiftStart" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" required>
                            @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="shiftEnd">Ends <span class="text-danger" aria-hidden="true">*</span></label>
                            <input class="form-control @error('ends_at') is-invalid @enderror" id="shiftEnd" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" required>
                            @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="shiftRole">Role (optional)</label>
                            <input class="form-control @error('role_label') is-invalid @enderror" id="shiftRole" name="role_label" maxlength="100" placeholder="e.g. Grill, Cashier" value="{{ old('role_label') }}">
                            @error('role_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        <i class="fas fa-xmark me-1"></i>
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-1"></i>
                        Add shift
                    </button>
                </div>

            </form>
        </div>
    </div>

    @foreach($shifts as $shift)
        @if($shift->starts_at->isFuture())
            <div class="modal fade" id="delete-shift-{{ $shift->id }}" tabindex="-1" aria-labelledby="delete-shift-label-{{ $shift->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('admin.shifts.destroy', $shift->id) }}">
                        @csrf
                        @method('DELETE')

                        <div class="modal-header">
                            <h5 class="modal-title" id="delete-shift-label-{{ $shift->id }}"><i class="fas fa-trash me-2 text-danger"></i>Remove shift</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <p class="mb-1">
                                Remove the {{ $shift->starts_at->format('H:i') }}-{{ $shift->ends_at->format('H:i') }} shift on {{ $shift->starts_at->format('D j M') }} for <strong>{{ $shift->user?->name }}</strong>?
                            </p>
                            <small class="text-muted">{{ $shift->branch?->name }}</small>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                <i class="fas fa-xmark me-1"></i>
                                Cancel
                            </button>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash me-1"></i>
                                Remove
                            </button>
                        </div>

                    </form>
                </div>
            </div>
        @endif
    @endforeach
@endif

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
