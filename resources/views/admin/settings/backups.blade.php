@extends('layouts.app')

@section('title', 'Backups')
@section('page-title', 'Backups')
@section('page-subtitle', 'Download a copy of your restaurant data and keep it somewhere safe')

@section('content')
<x-admin.stats :items="$pageStats" />

<div class="row g-4">
    <div class="col-xl-8">
        <div class="panel p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Your backups</h2>
                    <small class="text-muted">Each backup contains only this restaurant's data: menu, orders, customers, stock, staff, finance and settings.</small>
                </div>
                <form method="POST" action="{{ route('admin.settings.backups.store') }}">
                    @csrf
                    <button class="btn btn-primary" type="submit"><i class="fas fa-plus me-2"></i>Create backup now</button>
                </form>
            </div>

            @if($backups->isEmpty())
                <x-admin.empty icon="fas fa-database" title="No backups yet" message="Create your first backup, then download it to your computer or cloud storage." />
            @else
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Backup</th><th>Created</th><th class="text-end">Size</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                        @foreach($backups as $backup)
                            <tr>
                                <td><i class="fas fa-file-zipper text-primary me-2"></i><span class="font-monospace small">{{ $backup['name'] }}</span></td>
                                <td>{{ $backup['created_at']->format('j M Y, H:i') }}<small class="d-block text-muted">{{ $backup['created_at']->diffForHumans() }}</small></td>
                                <td class="text-end">{{ $backup['size'] >= 1048576 ? number_format($backup['size'] / 1048576, 1) . ' MB' : number_format($backup['size'] / 1024, 1) . ' KB' }}</td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.settings.backups.download', $backup['name']) }}"><i class="fas fa-download me-1"></i>Download</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#delete-backup-{{ $loop->index }}" aria-label="Delete {{ $backup['name'] }}"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-xl-4">
        <form method="POST" action="{{ route('admin.settings.backups.schedule') }}" class="panel p-4 mb-3">
            @csrf
            @method('PUT')
            <div class="panel-section-title mb-3"><i class="fas fa-calendar-check me-2"></i>Schedule</div>
            @foreach(['frequency', 'keep'] as $key)
                @php($field = $group['fields'][$key])
                <div class="mb-3">
                    <label class="form-label" for="{{ $key }}">{{ $field['label'] }}</label>
                    @if($field['type'] === 'select')
                        <select class="form-select" id="{{ $key }}" name="{{ $key }}">
                            @foreach($field['options'] as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @selected((string) old($key, $values[$key]) === (string) $optionValue)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input class="form-control @error($key) is-invalid @enderror" type="number" min="1" max="60" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}">
                        @error($key)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @endif
                    @if(!empty($field['help']))<div class="form-text">{{ $field['help'] }}</div>@endif
                </div>
            @endforeach
            <button class="btn btn-primary w-100" type="submit"><i class="fas fa-floppy-disk me-2"></i>Save schedule</button>
        </form>
        <div class="panel p-4 small text-muted">
            <div class="panel-section-title mb-2"><i class="fas fa-circle-info me-2"></i>Good to know</div>
            <ul class="mb-0 ps-3">
                <li>Backups are ZIP files with one JSON file per table.</li>
                <li>Passwords, payment keys and other secrets are never included.</li>
                <li>Uploaded images (logo, menu photos) are not included; keep a copy of those separately.</li>
                <li>For a full server restore, ask your hosting provider for a database backup as well.</li>
            </ul>
        </div>
    </div>
</div>

@foreach($backups as $backup)
    <div class="modal fade" id="delete-backup-{{ $loop->index }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.settings.backups.destroy', $backup['name']) }}">
                @csrf
                @method('DELETE')
                <div class="modal-body text-center p-4">
                    <div class="delete-icon-wrap bg-danger-subtle text-danger"><i class="fas fa-trash fs-4"></i></div>
                    <h5 class="mb-2">Delete this backup?</h5>
                    <p class="text-muted mb-0">{{ $backup['name'] }} will be permanently removed from the server.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete backup</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
