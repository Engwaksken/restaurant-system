<header class="brand-bar">
    <div class="container d-flex align-items-center justify-content-between py-3">
        <a class="brand-link" href="{{ route('home') }}">
            @include('public.partials.logo')
            <span>{{ $brand->name() }}</span>
        </a>
        <a class="btn btn-sm btn-glass" href="{{ route('home') }}">
            <i class="fas fa-arrow-left me-1"></i>Back to menu
        </a>
    </div>
</header>
