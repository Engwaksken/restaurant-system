@extends('layouts.app')
@section('title','Dashboard') @section('page-title','Dashboard')
@section('content')
@php
    $statCards = [
        'sales' => ['label' => 'Today Sales', 'money' => true, 'route' => 'admin.orders.index', 'text' => 'Total of today’s orders, excluding cancelled orders.'],
        'orders' => ['label' => 'Today Orders', 'money' => false, 'route' => 'admin.orders.index', 'text' => 'Number of orders placed today.'],
        'payments' => ['label' => 'Payments', 'money' => true, 'route' => 'admin.orders.index', 'text' => 'Payments received today, less refunds paid out today.'],
        'pending' => ['label' => 'Pending Orders', 'money' => false, 'route' => 'admin.orders.index', 'text' => 'Orders that are pending, confirmed or being prepared.'],
        'menu_items' => ['label' => 'Available Menu', 'money' => false, 'route' => 'admin.menu-items.index', 'text' => 'Menu items currently marked as available.'],
    ];
@endphp
<div class="row g-3">
@foreach($statCards as $k => $card)
<div class="col-6 col-lg"><button type="button" class="stat-card w-100 text-start border-0" data-bs-toggle="modal" data-bs-target="#statModal-{{ $k }}"><span>{{ $card['label'] }}</span><strong>@if($card['money']){{ config('restaurant.currency') }} {{ number_format($stats[$k]) }}@else{{ number_format($stats[$k]) }}@endif</strong></button></div>
@endforeach
</div>
<div class="panel mt-4"><div class="d-flex justify-content-between align-items-center"><div><h5>Restaurant Operations</h5><p class="text-muted mb-0">Use POS to capture new orders and monitor current sales.</p></div><a class="btn btn-primary" href="{{ route('admin.pos.index') }}">Open POS</a></div></div>

@foreach($statCards as $k => $card)
<div class="modal fade" id="statModal-{{ $k }}" tabindex="-1" aria-labelledby="statModalLabel-{{ $k }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statModalLabel-{{ $k }}">{{ $card['label'] }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fs-3 fw-bold mb-2">@if($card['money']){{ config('restaurant.currency') }} {{ number_format($stats[$k]) }}@else{{ number_format($stats[$k]) }}@endif</p>
                <p class="text-muted mb-0">{{ $card['text'] }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-primary" href="{{ route($card['route']) }}">View details</a>
            </div>
        </div>
    </div>
</div>
@endforeach
@endsection
