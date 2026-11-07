@extends('layouts.app')

@section('title', 'Reservations')
@section('page-title', 'Reservations')

@section('content')
@php
    $statusClasses = ['pending' => 'warning', 'confirmed' => 'primary', 'seated' => 'danger', 'completed' => 'success', 'cancelled' => 'secondary', 'no_show' => 'dark'];
    $actionLabels = ['confirmed' => 'Confirm', 'seated' => 'Seat', 'completed' => 'Complete', 'cancelled' => 'Cancel', 'no_show' => 'No-show'];
    $actionStyles = ['confirmed' => 'primary', 'seated' => 'success', 'completed' => 'success', 'cancelled' => 'outline-danger', 'no_show' => 'outline-dark'];
    $confirmTexts = ['cancelled' => 'Cancel this reservation?', 'no_show' => 'Mark this reservation as a no-show?'];
@endphp
<div class="row g-4">
    @if($canManage)
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">New reservation</h2>
        @if($branches->isEmpty())<p class="text-muted">An active branch is required.</p>@else
        <form method="POST" action="{{ route('admin.reservations.store') }}">
            @csrf
            <label class="form-label" for="rsvBranch">Branch</label>
            <select class="form-select mb-2" id="rsvBranch" name="branch_id" data-filters="rsvTable" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
            <label class="form-label" for="rsvName">Guest name</label>
            <input class="form-control mb-2" id="rsvName" name="guest_name" value="{{ old('guest_name') }}" maxlength="255" required>
            <label class="form-label" for="rsvPhone">Phone</label>
            <input class="form-control mb-2" id="rsvPhone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="40" required>
            <div class="row g-2">
                <div class="col-5"><label class="form-label" for="rsvGuests">Guests</label><input class="form-control mb-2" id="rsvGuests" type="number" name="guest_count" min="1" max="500" value="{{ old('guest_count', 2) }}" required></div>
                <div class="col-7"><label class="form-label" for="rsvAt">Date &amp; time</label><input class="form-control mb-2" id="rsvAt" type="datetime-local" name="reserved_at" value="{{ old('reserved_at') }}" required></div>
            </div>
            <label class="form-label" for="rsvTable">Table (optional)</label>
            <select class="form-select mb-2" id="rsvTable" name="restaurant_table_id">
                <option value="">Assign later</option>
                @foreach($tables as $table)<option value="{{ $table->id }}" data-branch="{{ $table->branch_id }}" @selected(old('restaurant_table_id') == $table->id)>{{ $table->name }} · {{ $table->capacity }} seats</option>@endforeach
            </select>
            <label class="form-label" for="rsvRequests">Special requests</label>
            <textarea class="form-control mb-3" id="rsvRequests" name="special_requests" maxlength="2000">{{ old('special_requests') }}</textarea>
            <button class="btn btn-primary" type="submit">Create reservation</button>
        </form>
        @endif
    </div></div>
    @endif
    <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}"><div class="panel p-4">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-auto"><label class="form-label" for="rsvDate">Date</label><input class="form-control" type="date" id="rsvDate" name="date" value="{{ $date->toDateString() }}"></div>
            <div class="col-auto"><label class="form-label" for="rsvStatus">Status</label>
                <select class="form-select" id="rsvStatus" name="status"><option value="">All</option>@foreach(array_keys($statusClasses) as $option)<option value="{{ $option }}" @selected($status === $option)>{{ \Illuminate\Support\Str::headline($option) }}</option>@endforeach</select>
            </div>
            <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Show</button></div>
        </form>
        <h2 class="h5">{{ $date->format('l, j F Y') }}</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Time</th><th>Guest</th><th>Party</th><th>Table</th><th>Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
            @forelse($reservations as $reservation)
                <tr>
                    <td><strong>{{ $reservation->reserved_at->format('H:i') }}</strong><small class="d-block text-muted">{{ $reservation->reservation_number }}</small></td>
                    <td>{{ $reservation->guest_name }}<small class="d-block text-muted">{{ $reservation->phone }}</small>@if($reservation->special_requests)<small class="d-block">{{ $reservation->special_requests }}</small>@endif</td>
                    <td>{{ $reservation->guest_count }}</td>
                    <td>{{ $reservation->table?->name ?? '—' }}<small class="d-block text-muted">{{ $reservation->branch?->name }}</small></td>
                    <td><span class="badge bg-{{ $statusClasses[$reservation->status] ?? 'secondary' }}">{{ \Illuminate\Support\Str::headline($reservation->status) }}</span></td>
                    @if($canManage)
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                        @foreach($transitions[$reservation->status] ?? [] as $next)
                            <form method="POST" action="{{ route('admin.reservations.status', $reservation->id) }}" @isset($confirmTexts[$next]) data-confirm="{{ $confirmTexts[$next] }}" onsubmit="return confirm(this.dataset.confirm)" @endisset>
                                @csrf @method('PATCH')
                                <input type="hidden" name="status" value="{{ $next }}">
                                <button class="btn btn-sm btn-{{ $actionStyles[$next] }}" type="submit">{{ $actionLabels[$next] }}</button>
                            </form>
                        @endforeach
                        </div>
                        @if(in_array($reservation->status, ['pending', 'confirmed'], true))
                        <details class="mt-1">
                            <summary class="small">Edit</summary>
                            <form method="POST" action="{{ route('admin.reservations.update', $reservation->id) }}" class="mt-2">
                                @csrf @method('PUT')
                                <input class="form-control form-control-sm mb-1" name="guest_name" value="{{ $reservation->guest_name }}" maxlength="255" required aria-label="Guest name">
                                <input class="form-control form-control-sm mb-1" type="tel" name="phone" value="{{ $reservation->phone }}" maxlength="40" required aria-label="Phone">
                                <input class="form-control form-control-sm mb-1" type="number" name="guest_count" value="{{ $reservation->guest_count }}" min="1" max="500" required aria-label="Guests">
                                <input class="form-control form-control-sm mb-1" type="datetime-local" name="reserved_at" value="{{ $reservation->reserved_at->format('Y-m-d\TH:i') }}" required aria-label="Date and time">
                                <select class="form-select form-select-sm mb-1" name="restaurant_table_id" aria-label="Table">
                                    <option value="">No table</option>
                                    @foreach($tables->where('branch_id', $reservation->branch_id) as $table)<option value="{{ $table->id }}" @selected($reservation->restaurant_table_id === $table->id)>{{ $table->name }} · {{ $table->capacity }} seats</option>@endforeach
                                </select>
                                <textarea class="form-control form-control-sm mb-1" name="special_requests" maxlength="2000" aria-label="Special requests">{{ $reservation->special_requests }}</textarea>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                            </form>
                        </details>
                        @endif
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
    @include('admin.tables._branch-filter')
@endif
@endsection
