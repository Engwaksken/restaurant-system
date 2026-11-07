{{-- Branded error page shell. Sections: code, title, message, icon. --}}
@php($brand = $brand ?? app(\App\Support\BrandResolver::class)->current())
<!doctype html>
<html lang="en">
<head>
    @include('public.partials.head')
    <title>@yield('title') · {{ $brand->name() }}</title>
    <meta name="robots" content="noindex">
</head>
<body>
<main class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center py-5" style="max-width: 520px;">
        <a href="{{ url('/') }}" class="site-brand justify-content-center mb-4">
            @include('public.partials.logo')
            <span class="site-brand-name">{{ $brand->name() }}</span>
        </a>
        <div class="display-1 fw-bold display-font text-brand mb-2" style="letter-spacing:-.04em">@yield('code')</div>
        <h1 class="h3 fw-bold mb-2">@yield('title')</h1>
        <p class="text-muted mb-4">@yield('message')</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ url('/') }}" class="btn btn-primary"><i class="fas fa-house me-2"></i>Back to home</a>
            @auth
                <a href="{{ url('/admin/dashboard') }}" class="btn btn-light border"><i class="fas fa-gauge me-2"></i>Dashboard</a>
            @endauth
        </div>
    </div>
</main>
</body>
</html>
