@extends('layouts.app')

@section('title', 'Tables')
@section('page-title', 'Restaurant Tables')

@section('content')
@php
    $statusClasses = ['available' => 'success', 'reserved' => 'info', 'occupied' => 'danger', 'cleaning' => 'warning', 'inactive' => 'secondary'];
@endphp
<div class="row g-4">
    @if($canManage)
    <div class="col-lg-4"><div class="panel p-4">
        <h2 class="h5">Add table</h2>
        @if($branches->isEmpty())<p class="text-muted">An active branch is required.</p>@else
        <form method="POST" action="{{ route('admin.tables.store') }}">
            @csrf
            <label class="form-label" for="tableBranch">Branch</label>
            <select class="form-select mb-2" id="tableBranch" name="branch_id" data-filters="tableFloor" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select>
            <label class="form-label" for="tableFloor">Section (optional)</label>
            <select class="form-select mb-2" id="tableFloor" name="floor_id"><option value="">None</option>@foreach($floors as $floor)<option value="{{ $floor->id }}" data-branch="{{ $floor->branch_id }}" @selected(old('floor_id') == $floor->id)>{{ $floor->name }}</option>@endforeach</select>
            <label class="form-label" for="tableName">Name</label>
            <input class="form-control mb-2" id="tableName" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="e.g. T12">
            <label class="form-label" for="tableCapacity">Seats</label>
            <input class="form-control mb-3" id="tableCapacity" type="number" name="capacity" min="1" max="500" value="{{ old('capacity', 4) }}" required>
            <button class="btn btn-primary" type="submit">Create table</button>
        </form>
        @endif
    </div></div>
    @endif
    <div class="{{ $canManage ? 'col-lg-8' : 'col-12' }}"><div class="panel p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h5 mb-0">Tables</h2>
            <form method="GET">
                <label class="visually-hidden" for="tableStatusFilter">Status</label>
                <select class="form-select form-select-sm" id="tableStatusFilter" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach(array_keys($statusClasses) as $option)<option value="{{ $option }}" @selected(request('status') === $option)>{{ ucfirst($option) }}</option>@endforeach
                </select>
            </form>
        </div>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Table</th><th>Section</th><th>Seats</th><th>Status</th>@if($canManage)<th>Actions</th>@endif</tr></thead>
            <tbody>
            @forelse($tables as $table)
                <tr>
                    <td><strong>{{ $table->name }}</strong><small class="d-block text-muted">{{ $table->branch?->name }}</small></td>
                    <td>{{ $table->floor?->name ?? '—' }}</td>
                    <td>{{ $table->capacity }}</td>
                    <td><span class="badge bg-{{ $statusClasses[$table->status] ?? 'secondary' }}">{{ ucfirst($table->status) }}</span></td>
                    @if($canManage)
                    <td>
                        <form method="POST" action="{{ route('admin.tables.status', $table->id) }}" class="d-flex gap-1 mb-1">
                            @csrf @method('PATCH')
                            <label class="visually-hidden" for="table-status-{{ $table->id }}">Status for {{ $table->name }}</label>
                            <select class="form-select form-select-sm" id="table-status-{{ $table->id }}" name="status">
                                @foreach($manualStatuses as $option)<option value="{{ $option }}" @selected($table->status === $option)>{{ ucfirst($option) }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-secondary" type="submit">Set</button>
                        </form>
                        <details>
                            <summary class="small">Edit</summary>
                            <form method="POST" action="{{ route('admin.tables.update', $table->id) }}" class="mt-2">
                                @csrf @method('PUT')
                                <input class="form-control form-control-sm mb-1" name="name" value="{{ $table->name }}" maxlength="255" required aria-label="Name">
                                <input class="form-control form-control-sm mb-1" type="number" name="capacity" value="{{ $table->capacity }}" min="1" max="500" required aria-label="Seats">
                                <select class="form-select form-select-sm mb-1" name="floor_id" aria-label="Section">
                                    <option value="">No section</option>
                                    @foreach($floors->where('branch_id', $table->branch_id) as $floor)<option value="{{ $floor->id }}" @selected($table->floor_id === $floor->id)>{{ $floor->name }}</option>@endforeach
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                            </form>
                        </details>
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">No tables configured.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $tables->links() }}
    </div></div>
</div>
@if($canManage)
    @include('admin.tables._branch-filter')
@endif
@endsection
