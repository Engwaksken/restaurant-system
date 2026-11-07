{{-- Shared <head> assets for public pages. Expects $brand. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="{{ $brand->accent() }}">
@if($brand->faviconUrl())
    <link rel="icon" href="{{ $brand->faviconUrl() }}">
    <link rel="apple-touch-icon" href="{{ $brand->faviconUrl() }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
<link href="{{ asset('css/restaurant-public.css') }}?v={{ @filemtime(public_path('css/restaurant-public.css')) }}" rel="stylesheet">
<style>:root{ {!! $brand->cssVariables() !!} }</style>
