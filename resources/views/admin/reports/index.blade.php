@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('content')
<div class="panel p-4">
    <form method="GET" class="row g-2 align-items-end mb-4">
        <div class="col-auto"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="{{ request('from') }}"></div>
        <div class="col-auto"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
        <div class="col-auto"><button class="btn btn-primary">Fetch report</button></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('admin.reports.sales.csv', request()->query()) }}">Export CSV</a></div>
        <div class="col-auto"><a class="btn btn-outline-secondary" href="{{ route('admin.reports.sales.pdf', request()->query()) }}">Export PDF</a></div>
    </form>
    <div class="row g-3 mb-4">
        @foreach([['Orders', number_format((int)$summary->order_count)], ['Revenue', number_format((float)$summary->revenue, 2)], ['Paid', number_format((float)$summary->paid, 2)], ['Outstanding', number_format((float)$summary->outstanding, 2)]] as [$label, $value])
            <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="fs-4 fw-bold">{{ $value }}</div></div></div></div>
        @endforeach
    </div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>
        @forelse($daily as $row)<tr><td>{{ $row->day }}</td><td>{{ number_format($row->orders) }}</td><td>{{ number_format((float)$row->revenue, 2) }}</td></tr>
        @empty<tr><td colspan="3" class="text-center text-muted">No sales found for this date range.</td></tr>@endforelse
    </tbody></table></div>
</div>
@endsection
