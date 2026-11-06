@extends('layouts.app')
@section('title','Dashboard') @section('page-title','Dashboard')
@section('content')
<div class="row g-3">
@foreach(['sales'=>'Today Sales','orders'=>'Today Orders','payments'=>'Payments','pending'=>'Pending Orders','menu_items'=>'Available Menu'] as $k=>$label)
<div class="col-6 col-lg"><div class="stat-card"><span>{{ $label }}</span><strong>@if(in_array($k,['sales','payments'])){{ config('restaurant.currency') }} {{ number_format($stats[$k]) }}@else{{ number_format($stats[$k]) }}@endif</strong></div></div>
@endforeach
</div>
<div class="panel mt-4"><div class="d-flex justify-content-between align-items-center"><div><h5>Restaurant Operations</h5><p class="text-muted mb-0">Use POS to capture new orders and monitor current sales.</p></div><a class="btn btn-primary" href="{{ route('admin.pos.index') }}">Open POS</a></div></div>
@endsection
