@extends('layouts.app')

@section('title', 'Activity Logs')
@section('page-title', 'Activity Logs')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $formatValue = function ($value): string {
        if (is_bool($value)) { return $value ? 'yes' : 'no'; }
        if ($value === null) { return '-'; }
        if (is_scalar($value)) { return \Illuminate\Support\Str::limit((string) $value, 120); }
        return \Illuminate\Support\Str::limit(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 120);
    };
@endphp
<div class="panel p-4">
    <form method="GET" class="row g-2 align-items-end mb-3">
        <div class="col-6 col-md-3"><label class="form-label" for="logArea">Area</label>
            <select class="form-select" id="logArea" name="area"><option value="">All areas</option>@foreach($areas as $area)<option value="{{ $area }}" @selected(($filters['area'] ?? '') === $area)>{{ \Illuminate\Support\Str::headline($area) }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-3"><label class="form-label" for="logUser">Staff member</label>
            <select class="form-select" id="logUser" name="user_id"><option value="">Everyone</option>@foreach($staff as $member)<option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === $member->id)>{{ $member->name }}</option>@endforeach</select>
        </div>
        <div class="col-6 col-md-2"><label class="form-label" for="logFrom">From</label><input class="form-control" type="date" id="logFrom" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="logTo">To</label><input class="form-control" type="date" id="logTo" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
        <div class="col-12 col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary flex-fill" type="submit">Filter</button>
            @if(array_filter($filters))<a class="btn btn-outline-secondary" href="{{ route('admin.settings.activity-logs') }}" title="Clear filters">Clear</a>@endif
        </div>
    </form>
    <div class="table-responsive"><table class="table table-sm align-middle">
        <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            <tr>
                <td class="text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                <td>{{ $log->user?->name ?? ($log->user_id ? 'Deleted user #' . $log->user_id : 'System / customer') }}</td>
                <td><code>{{ $log->action }}</code></td>
                <td>@if($log->subject_type){{ \Illuminate\Support\Str::headline(class_basename($log->subject_type)) }} #{{ $log->subject_id }}@else - @endif</td>
                <td class="small">
                    @foreach((array) $log->properties as $key => $value)
                        <span class="d-block"><span class="text-muted">{{ \Illuminate\Support\Str::headline((string) $key) }}:</span> {{ $formatValue($value) }}</span>
                    @endforeach
                </td>
                <td class="small text-muted" title="{{ $log->user_agent }}">{{ $log->ip_address }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">No activity recorded.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
