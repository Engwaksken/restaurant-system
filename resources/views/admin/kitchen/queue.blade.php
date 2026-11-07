@extends('layouts.app')

@section('title', 'Kitchen · ' . $title)
@section('page-title', 'Kitchen · ' . $title)

@section('content')
<div class="panel p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 mb-0"><i class="{{ $icon }}" aria-hidden="true"></i> {{ $title }}</h2>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.kitchen.index') }}">Kitchen board</a>
    </div>
    <div class="row g-3">
        @forelse($orders as $order)
            <div class="col-md-6 col-xl-4">@include('admin.kitchen._ticket', ['order' => $order])</div>
        @empty
            <p class="text-muted">No orders.</p>
        @endforelse
    </div>
    {{ $orders->links() }}
</div>
@if($queue !== 'completed')
    @include('admin.kitchen._refresh')
@endif
@endsection
