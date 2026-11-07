@extends('layouts.app')

@section('title', 'Feedback')
@section('page-title', 'Customer Feedback')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="panel p-3"><small class="text-muted d-block">Responses</small><strong class="fs-4">{{ (int) $summary->responses }}</strong></div></div>
    <div class="col-md-4"><div class="panel p-3"><small class="text-muted d-block">Average rating</small><strong class="fs-4">{{ $summary->responses ? number_format((float) $summary->average_rating, 1) . ' / 5' : '—' }}</strong></div></div>
    <div class="col-md-4"><div class="panel p-3"><small class="text-muted d-block">Low ratings (1–2 <i class="fas fa-star"></i>)</small><strong class="fs-4 {{ (int) $summary->low_ratings > 0 ? 'text-danger' : '' }}">{{ (int) $summary->low_ratings }}</strong></div></div>
</div>
<div class="panel p-4">
    <form method="GET" class="row g-2 align-items-end mb-3">
        @if($branches->count() > 1)
        <div class="col-auto"><label class="form-label" for="feedbackBranch">Branch</label>
            <select class="form-select" id="feedbackBranch" name="branch_id"><option value="">All branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) ($filters['branch_id'] ?? 0) === $branch->id)>{{ $branch->name }}</option>@endforeach</select>
        </div>
        @endif
        <div class="col-auto"><label class="form-label" for="feedbackRating">Rating</label>
            <select class="form-select" id="feedbackRating" name="rating"><option value="">All ratings</option>@for($score = 5; $score >= 1; $score--)<option value="{{ $score }}" @selected((int) ($filters['rating'] ?? 0) === $score)>{{ $score }} {{ $score === 1 ? 'star' : 'stars' }}</option>@endfor</select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary" type="submit">Filter</button></div>
    </form>
    <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Date</th><th>Rating</th><th>Comment</th><th>Order</th><th>Customer</th><th>Branch</th></tr></thead>
        <tbody>
        @forelse($feedback as $entry)
            <tr>
                <td>{{ $entry->created_at->format('Y-m-d H:i') }}</td>
                <td class="text-nowrap {{ $entry->rating <= 2 ? 'text-danger' : '' }}" aria-label="{{ $entry->rating }} out of 5">@for($star = 1; $star <= 5; $star++)<i class="{{ $star <= $entry->rating ? 'fas' : 'far' }} fa-star"></i>@endfor</td>
                <td>{{ $entry->comment ?? '—' }}</td>
                <td>@if($entry->order)<a href="{{ route('admin.orders.show', $entry->order) }}">{{ $entry->order->order_number }}</a>@endif</td>
                <td>@if($entry->customer)@if($canViewCustomers)<a href="{{ route('admin.customers.show', $entry->customer->id) }}">{{ $entry->customer->name }}</a>@else{{ $entry->customer->name }}@endif @else — @endif</td>
                <td>{{ $entry->branch?->name }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-muted">No feedback received.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $feedback->links() }}
</div>
@endsection
