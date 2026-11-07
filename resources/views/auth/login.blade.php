<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Login · Restaurant Management System</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet">
    <link
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        rel="stylesheet">
</head>

<body class="bg-light">

<div class="container">
    <div class="row min-vh-100 align-items-center justify-content-center py-4">

        <div class="col-sm-10 col-md-6 col-lg-4">

            <div class="card border-0 shadow">

                <div class="card-body p-4">

                    <div class="text-center mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-dark text-white mb-2" style="width: 52px; height: 52px;">
                            <i class="fas fa-utensils"></i>
                        </div>
                        <h1 class="h4 fw-bold mb-1">
                            Staff sign in
                        </h1>
                        <p class="text-muted small mb-0">
                            Use the email and password your administrator gave you.
                        </p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger small py-2" role="alert">
                            <i class="fas fa-circle-exclamation me-1"></i>{{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.login.submit') }}">
                        @csrf

                        <div class="small text-muted mb-3">
                            <span class="text-danger" aria-hidden="true">*</span> Required
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="loginEmail">
                                Email <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input
                                    type="email"
                                    id="loginEmail"
                                    name="email"
                                    value="{{ old('email') }}"
                                    class="form-control"
                                    placeholder="e.g. name@restaurant.com"
                                    autocomplete="username"
                                    required
                                    aria-required="true"
                                    autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="loginPassword">
                                Password <span class="text-danger" aria-hidden="true">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input
                                    type="password"
                                    id="loginPassword"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                    aria-required="true">
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

                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-right-to-bracket me-1"></i>Sign in
                        </button>

                    </form>

                    <p class="text-center text-muted small mt-4 mb-0">
                        Forgot your password? Ask an administrator to reset it.
                    </p>

                </div>

            </div>

        </div>
    </div>
</div>

</body>
</html>
