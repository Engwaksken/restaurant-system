@extends('errors.layout')

@php
    // Show the specific reason when the app supplied one (e.g. "Your account is not assigned to a restaurant.").
    $reason = isset($exception) ? trim((string) $exception->getMessage()) : '';
@endphp

@section('code', '403')
@section('title', 'Access denied')
@section('message')
{{ $reason !== '' && $reason !== 'Forbidden' && $reason !== 'This action is unauthorized.' ? $reason : "You don't have permission to view this page. If you think this is a mistake, ask an administrator." }}
@endsection
