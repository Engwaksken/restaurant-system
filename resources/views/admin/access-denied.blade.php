@php($brand = app(\App\Support\BrandResolver::class)->current())
<!doctype html>
<html lang="en">
<head>
    @include('public.partials.head')
    <title>Access not available · {{ $brand->name() }}</title>
    <meta name="robots" content="noindex">
</head>
<body>
<main class="container d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="order-card p-4 p-md-5 text-center" style="max-width: 480px;">
        <a href="{{ url('/') }}" class="site-brand justify-content-center mb-4">
            @include('public.partials.logo')
            <span class="site-brand-name">{{ $brand->name() }}</span>
        </a>
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 64px; height: 64px; background: rgba(var(--brand-rgb), .1); color: var(--brand);">
            <i class="fas fa-lock fs-4"></i>
        </div>
        <h1 class="h4 fw-bold mb-2">Access not available</h1>
        <p class="text-muted mb-4">{{ $message }}</p>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-light border w-100">
                <i class="fas fa-right-from-bracket me-1"></i>Sign out
            </button>
        </form>
        <p class="small text-muted mt-3 mb-0">Signed in as {{ auth()->user()?->email }}</p>
    </div>
</main>
</body>
</html>
