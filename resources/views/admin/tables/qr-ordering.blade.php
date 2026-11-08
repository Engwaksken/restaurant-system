@extends('layouts.app')

@section('title', 'QR Ordering')
@section('page-title', 'QR Ordering')

@section('content')
<div class="panel p-4">
    <p class="text-muted">Create a unique table ordering link. Scan the QR code with a camera or print the link as a QR code for your table.</p>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Table</th><th>Branch</th><th>Section</th><th>Ordering link</th><th></th></tr></thead>
        <tbody>
        @forelse($tables as $table)
            <tr>
                <td>{{ $table->name }}</td><td>{{ $table->branch?->name ?? '-' }}</td><td>{{ $table->floor?->name ?? '-' }}</td>
                <td>@if($table->qr_token)<code>{{ url('/?table=' . $table->qr_token) }}</code>@else<span class="text-muted">Not created</span>@endif</td>
                <td class="text-end">@if($canManage && !$table->qr_token)<form method="POST" action="{{ route('admin.qr-ordering.create', $table->id) }}">@csrf<button class="btn btn-sm btn-primary">Create QR order</button></form>@elseif($table->qr_token)<span class="badge text-bg-success">Ready</span>@endif</td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-muted py-4">No tables found in your branch.</td></tr>@endforelse
        </tbody>
    </table></div>
    {{ $tables->links() }}
</div>
@endsection
