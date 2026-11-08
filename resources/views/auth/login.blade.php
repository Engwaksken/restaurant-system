<!doctype html>
<html lang="en">
<head>
    @include('public.partials.head')
    <title>Staff sign in · {{ $brand->name() }}</title>
    <meta name="robots" content="noindex">
</head>

<body>

<div class="auth-shell">
    <aside class="auth-aside">
        @if($brand->heroUrl())
            <div class="hero-media" style="background-image: url('{{ $brand->heroUrl() }}')"></div>
        @else
            <div class="hero-pattern"></div>
        @endif

        <a href="{{ route('home') }}" class="site-brand">
            @include('public.partials.logo')
            <span class="min-w-0">
                <span class="site-brand-name d-block text-white">{{ $brand->name() }}</span>
                <span class="site-brand-tag text-white-50">Restaurant Management</span>
            </span>
        </a>

        <div class="d-none d-lg-block">
            <h1 class="mb-3">Run every shift<br>from one place.</h1>
            <p class="text-white-50 mb-4" style="max-width: 420px">Orders, kitchen, tables, stock, staff and finances for {{ $brand->name() }}, all in one dashboard.</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach(['fa-cash-register' => 'Point of sale', 'fa-fire-burner' => 'Kitchen display', 'fa-boxes-stacked' => 'Inventory', 'fa-chart-line' => 'Reports'] as $icon => $label)
                    <span class="hero-badge" style="padding: 6px 14px"><i class="fas {{ $icon }} text-brand"></i>{{ $label }}</span>
                @endforeach
            </div>
        </div>

        <small class="text-white-50 d-none d-lg-block">&copy; {{ now()->year }} {{ $brand->name() }}</small>
    </aside>

    <main class="auth-main">
        <div class="auth-card">
            <span class="eyebrow">Staff area</span>
            <h2 class="h3 fw-bold mt-2 mb-1">Welcome back</h2>
            <p class="text-muted mb-4">Sign in with the email and password your administrator gave you.</p>

            @if ($errors->any())
                <div class="alert alert-danger small py-2 d-flex align-items-center gap-2" role="alert">
                    <i class="fas fa-circle-exclamation"></i>{{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.submit') }}">
                @csrf

                <div class="small text-muted mb-3">
                    <span class="text-danger" aria-hidden="true">*</span> Required
                </div>

                <div class="mb-3">
                    <label class="form-label" for="loginEmail">
                        Email <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="fas fa-envelope text-muted"></i></span>
                        <input
                            type="email"
                            id="loginEmail"
                            name="email"
                            value="{{ old('email') }}"
                            class="form-control fs-6"
                            placeholder="e.g. name@restaurant.com"
                            autocomplete="username"
                            required
                            aria-required="true"
                            autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="loginPassword">
                        Password <span class="text-danger" aria-hidden="true">*</span>
                    </label>
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white"><i class="fas fa-lock text-muted"></i></span>
                        <input
                            type="password"
                            id="loginPassword"
                            name="password"
                            class="form-control fs-6"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                            aria-required="true">
                        <button class="btn btn-light border" type="button" id="togglePassword" aria-label="Show password" style="border-radius: 0 12px 12px 0">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="remember"
                            value="1"
                            id="remember"
                            @checked(old('remember'))>
                        <label class="form-check-label small" for="remember">
                            Remember me
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-right-to-bracket me-2"></i>Sign in
                </button>
            </form>

            <p class="text-center text-muted small mt-4 mb-0">
                Forgot your password? Ask an administrator to reset it.
            </p>
            <p class="text-center small mt-2 mb-0">
                <a href="{{ route('home') }}"><i class="fas fa-arrow-left me-1"></i>Back to {{ $brand->name() }}</a>
            </p>
        </div>
    </main>
</div>

<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('loginPassword');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    this.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
});
</script>

</body>
</html>
