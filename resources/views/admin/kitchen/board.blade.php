@extends('layouts.app')

@section('title', 'Kitchen Display')
@section('page-title', 'Kitchen Display')

@section('content')
<x-admin.stats :items="$pageStats ?? []" />
@php
    $headings = [
        'pending' => ['New', 'fas fa-hourglass-start', 'route' => 'admin.kitchen.pending'],
        'preparing' => ['Preparing', 'fas fa-fire', 'route' => 'admin.kitchen.preparing'],
        'ready' => ['Ready', 'fas fa-bell-concierge', 'route' => 'admin.kitchen.ready'],
    ];
@endphp
<p class="text-muted small">Oldest orders first. This screen refreshes every 30 seconds.</p>
<div class="row g-4">
    @foreach($columns as $queue => $orders)
        <section class="col-lg-4" aria-labelledby="kitchen-{{ $queue }}">
            <div class="panel p-3 h-100">
                <h2 class="h5 d-flex justify-content-between" id="kitchen-{{ $queue }}">
                    <span><i class="{{ $headings[$queue][1] }}" aria-hidden="true"></i> {{ $headings[$queue][0] }}</span>
                    <a class="badge bg-dark text-decoration-none" href="{{ route($headings[$queue]['route']) }}">{{ $orders->count() }}</a>
                </h2>
                @forelse($orders as $order)
                    @include('admin.kitchen._ticket', ['order' => $order])
                @empty
                    <p class="text-muted">No orders.</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
@include('admin.kitchen._refresh')
@endsection
