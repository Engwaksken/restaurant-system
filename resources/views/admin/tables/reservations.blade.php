@extends('layouts.app')

@section('title', 'Reservations')
@section('page-title', 'Reservations')
@section('own-validation-errors', '1')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $statusClasses = ['pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'danger', 'completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'dark'];
    $actionLabels = ['confirmed' => 'Confirm', 'seated' => 'Seat', 'completed' => 'Complete', 'cancelled' => 'Cancel', 'no_show' => 'No-show'];
    $actionStyles = ['confirmed' => 'primary', 'seated' => 'success', 'completed' => 'success', 'cancelled' => 'outline-danger', 'no_show' => 'outline-dark'];
    $confirmTexts = ['cancelled' => 'Cancel this reservation?', 'no_show' => 'Mark this reservation as a no-show?'];
    $addFailed = $errors->any() && old('branch_id') !== null;
@endphp
<div class="row g-4">
    @if($errors->any() && !$addFailed)
    <div class="col-12">
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-0" role="alert">
            <div class="d-flex gap-2">
                <i class="fas fa-triangle-exclamation mt-1"></i>
                <div>
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif
    <div class="col-12"><div class="panel p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto"><label class="form-label" for="rsvDate">Date</label><input class="form-control" type="date" id="rsvDate" name="date" value="{{ $date->toDateString() }}"></div>
                <div class="col-auto"><label class="form-label" for="rsvStatus">Status</label>
                    <select class="form-select" id="rsvStatus" name="status"><option value="">All</option>@foreach(array_keys($statusClasses) as $option)<option value="{{ $option }}" @selected($status === $option)>{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach</select>
                </div>
                <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Show</button></div>
            </form>
            @if($canManage && $branches->isNotEmpty())
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-reservation">
                    <i class="fas fa-plus me-1"></i>
                    Add reservation
                </button>
            @endif
        </div>
        @if($canManage && $branches->isEmpty())
            <p class="text-muted">An active branch is required.</p>
        @endif
        <h2 class="h5">{{ $date->format('l, j F Y') }}</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Time</th><th>Guest</th><th>Party</th><th>Table</th><th>Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
            @forelse($reservations as $reservation)
                <tr>
                    <td><strong>{{ $reservation->reserved_at->format('H:i') }}</strong><small class="d-block text-muted">{{ $reservation->reservation_number }}</small></td>
                    <td>{{ $reservation->guest_name }}<small class="d-block text-muted">{{ $reservation->phone }}</small>@if($reservation->special_requests)<small class="d-block">{{ $reservation->special_requests }}</small>@endif</td>
                    <td>{{ $reservation->guest_count }}</td>
                    <td>{{ $reservation->table?->name ?? '-' }}<small class="d-block text-muted">{{ $reservation->branch?->name }}</small></td>
                    <td><span class="badge bg-{{ $statusClasses[$reservation->status] ?? 'secondary' }}">{{ \Illuminate\Support\Str::headline($reservation->status) }}</span></td>
                    @if($canManage)
                    <td>
                        <div class="d-flex flex-wrap align-items-center gap-1">
                        @foreach($transitions[$reservation->status] ?? [] as $next)
                            @isset($confirmTexts[$next])
                                <button type="button"
                                        class="btn btn-sm btn-{{ $actionStyles[$next] }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#confirm-reservation-{{ $reservation->id }}-{{ $next }}">
                                    {{ $actionLabels[$next] }}
                                </button>
                            @else
                                <form method="POST" action="{{ route('admin.reservations.status', $reservation->id) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $next }}">
                                    <button class="btn btn-sm btn-{{ $actionStyles[$next] }}" type="submit">{{ $actionLabels[$next] }}</button>
                                </form>
                            @endisset
                        @endforeach
                        @if(in_array($reservation->status, ['pending', 'confirmed'], true))
                            <button type="button"
                                    class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-reservation-{{ $reservation->id }}"
                                    title="Edit">
                                <i class="fas fa-pen-to-square"></i>
                            </button>
                        @endif
                        </div>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">No reservations for this date.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $reservations->links() }}
    </div></div>
</div>

@if($canManage)
    @if($branches->isNotEmpty())
        {{-- =========================================================
             ADD RESERVATION MODAL
             OUTSIDE THE TABLE
        ========================================================= --}}
        <div class="modal fade"
             id="add-reservation"
             tabindex="-1"
             aria-labelledby="add-reservation-label"
             aria-hidden="true">

            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

                <form class="modal-content" method="POST" action="{{ route('admin.reservations.store') }}">

                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title" id="add-reservation-label">
                            <i class="fas fa-calendar-plus me-2 text-primary"></i>
                            Add reservation
                        </h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        @if($addFailed)
                            <div class="alert alert-danger py-2" role="alert">
                                <strong>Please correct the following:</strong>
                                <ul class="mb-0 ps-3">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label" for="rsvBranch">Branch <span class="text-danger" aria-hidden="true">*</span></label>
                                <select class="form-select @error('branch_id') is-invalid @enderror" id="rsvBranch" name="branch_id" data-filters="rsvTable" required>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                @error('branch_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="rsvName">Guest name <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('guest_name') is-invalid @enderror"
                                       id="rsvName"
                                       name="guest_name"
                                       value="{{ old('guest_name') }}"
                                       maxlength="255"
                                       required
                                       placeholder="e.g. Jane Doe">
                                @error('guest_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="rsvPhone">Phone <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('phone') is-invalid @enderror"
                                       id="rsvPhone"
                                       type="tel"
                                       name="phone"
                                       value="{{ old('phone') }}"
                                       maxlength="40"
                                       required
                                       placeholder="e.g. 0700 000 000">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="rsvGuests">Guests <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('guest_count') is-invalid @enderror"
                                       id="rsvGuests"
                                       type="number"
                                       name="guest_count"
                                       min="1"
                                       max="500"
                                       value="{{ old('guest_count', 2) }}"
                                       required>
                                @error('guest_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="rsvAt">Date &amp; time <span class="text-danger" aria-hidden="true">*</span></label>
                                <input class="form-control @error('reserved_at') is-invalid @enderror"
                                       id="rsvAt"
                                       type="datetime-local"
                                       name="reserved_at"
                                       value="{{ old('reserved_at') }}"
                                       required>
                                @error('reserved_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="rsvTable">Table <small class="text-muted">(optional)</small></label>
                                <select class="form-select @error('restaurant_table_id') is-invalid @enderror" id="rsvTable" name="restaurant_table_id">
                                    <option value="">Assign later</option>
                                    @foreach($tables as $table)
                                        <option value="{{ $table->id }}" data-branch="{{ $table->branch_id }}" @selected(old('restaurant_table_id') == $table->id)>{{ $table->name }} · {{ $table->capacity }} seats</option>
                                    @endforeach
                                </select>
                                @error('restaurant_table_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="rsvRequests">Special requests <small class="text-muted">(optional)</small></label>
                                <textarea class="form-control @error('special_requests') is-invalid @enderror"
                                          id="rsvRequests"
                                          name="special_requests"
                                          rows="3"
                                          maxlength="2000"
                                          placeholder="e.g. Window seat, high chair">{{ old('special_requests') }}</textarea>
                                @error('special_requests')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
                            Create reservation
                        </button>
                    </div>

                </form>

            </div>
        </div>
    @endif

    {{-- =========================================================
         EDIT RESERVATION / STATUS CONFIRMATION MODALS
         IMPORTANT: rendered outside <table>/<tbody>.
    ========================================================= --}}
    @foreach($reservations as $reservation)

        @if(in_array($reservation->status, ['pending', 'confirmed'], true))
            <div class="modal fade"
                 id="edit-reservation-{{ $reservation->id }}"
                 tabindex="-1"
                 aria-labelledby="edit-reservation-label-{{ $reservation->id }}"
                 aria-hidden="true">

                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

                    <form class="modal-content" method="POST" action="{{ route('admin.reservations.update', $reservation->id) }}">

                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title" id="edit-reservation-label-{{ $reservation->id }}">
                                    <i class="fas fa-pen-to-square me-2 text-primary"></i>
                                    Edit reservation
                                </h5>
                                <small class="text-muted">{{ $reservation->reservation_number }} · {{ $reservation->branch?->name }}</small>
                            </div>

                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label" for="edit-rsv-name-{{ $reservation->id }}">Guest name <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input class="form-control"
                                           id="edit-rsv-name-{{ $reservation->id }}"
                                           name="guest_name"
                                           value="{{ $reservation->guest_name }}"
                                           maxlength="255"
                                           required
                                           placeholder="e.g. Jane Doe">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="edit-rsv-phone-{{ $reservation->id }}">Phone <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input class="form-control"
                                           id="edit-rsv-phone-{{ $reservation->id }}"
                                           type="tel"
                                           name="phone"
                                           value="{{ $reservation->phone }}"
                                           maxlength="40"
                                           required
                                           placeholder="e.g. 0700 000 000">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label" for="edit-rsv-guests-{{ $reservation->id }}">Guests <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input class="form-control"
                                           id="edit-rsv-guests-{{ $reservation->id }}"
                                           type="number"
                                           name="guest_count"
                                           value="{{ $reservation->guest_count }}"
                                           min="1"
                                           max="500"
                                           required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label" for="edit-rsv-at-{{ $reservation->id }}">Date &amp; time <span class="text-danger" aria-hidden="true">*</span></label>
                                    <input class="form-control"
                                           id="edit-rsv-at-{{ $reservation->id }}"
                                           type="datetime-local"
                                           name="reserved_at"
                                           value="{{ $reservation->reserved_at->format('Y-m-d\TH:i') }}"
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="edit-rsv-table-{{ $reservation->id }}">Table <small class="text-muted">(optional)</small></label>
                                    <select class="form-select" id="edit-rsv-table-{{ $reservation->id }}" name="restaurant_table_id">
                                        <option value="">No table</option>
                                        @foreach($tables->where('branch_id', $reservation->branch_id) as $table)
                                            <option value="{{ $table->id }}" @selected($reservation->restaurant_table_id === $table->id)>{{ $table->name }} · {{ $table->capacity }} seats</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="edit-rsv-requests-{{ $reservation->id }}">Special requests <small class="text-muted">(optional)</small></label>
                                    <textarea class="form-control"
                                              id="edit-rsv-requests-{{ $reservation->id }}"
                                              name="special_requests"
                                              rows="3"
                                              maxlength="2000"
                                              placeholder="e.g. Window seat, high chair">{{ $reservation->special_requests }}</textarea>
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
                                Save Reservation
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        @endif

        @foreach($transitions[$reservation->status] ?? [] as $next)
            @isset($confirmTexts[$next])
                <div class="modal fade"
                     id="confirm-reservation-{{ $reservation->id }}-{{ $next }}"
                     tabindex="-1"
                     aria-labelledby="confirm-reservation-label-{{ $reservation->id }}-{{ $next }}"
                     aria-hidden="true">

                    <div class="modal-dialog modal-dialog-centered">

                        <form class="modal-content" method="POST" action="{{ route('admin.reservations.status', $reservation->id) }}">

                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $next }}">

                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title" id="confirm-reservation-label-{{ $reservation->id }}-{{ $next }}">
                                    {{ $actionLabels[$next] }} Reservation
                                </h5>

                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>

                            <div class="modal-body text-center py-4">
                                <i class="fas fa-triangle-exclamation fa-2x text-{{ $actionStyles[$next] === 'outline-dark' ? 'dark' : 'danger' }} mb-3 d-block"></i>

                                <h5 class="mb-2">
                                    {{ $confirmTexts[$next] }}
                                </h5>

                                <p class="text-muted mb-0">
                                    {{ $reservation->guest_name }} · {{ $reservation->reservation_number }} · {{ $reservation->reserved_at->format('H:i') }}
                                </p>
                            </div>

                            <div class="modal-footer border-0 justify-content-center pt-0 pb-4">
                                <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                                    <i class="fas fa-xmark me-1"></i>
                                    Cancel
                                </button>

                                <button type="submit" class="btn btn-{{ $actionStyles[$next] }} px-4">
                                    <i class="fas fa-check me-1"></i>
                                    {{ $actionLabels[$next] }}
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            @endisset
        @endforeach

    @endforeach
@endif

@if($addFailed)
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('add-reservation');
        if (modal && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });
    </script>
    @endpush
@endif
@if($canManage)
    @include('admin.tables._branch-filter')
@endif
@endsection
