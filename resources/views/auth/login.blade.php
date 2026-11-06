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
</head>

<body class="bg-light">

<div class="container">
    <div class="row min-vh-100 align-items-center justify-content-center">

        <div class="col-md-5 col-lg-4">

            <div class="card border-0 shadow">

                <div class="card-body p-4">

                    <h4 class="fw-bold text-center mb-1">
                        Restaurant Management
                    </h4>

                    <p class="text-muted text-center mb-4">
                        Sign in to continue
                    </p>

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif
<form method="POST"
      action="{{ route('admin.login.submit') }}">


@csrf

<div class="mb-3">
    <label class="form-label">
        Email
    </label>

    <input
        type="email"
        name="email"
        value="{{ old('email') }}"
        class="form-control"
        required
        autofocus>
</div>

<div class="mb-3">
    <label class="form-label">
        Password
    </label>

    <input
        type="password"
        name="password"
        class="form-control"
        required>
</div>

<div class="form-check mb-3">
    <input
        class="form-check-input"
        type="checkbox"
        name="remember"
        value="1"
        id="remember">

    <label
        class="form-check-label"
        for="remember">
        Remember me
    </label>
</div>

<button
    type="submit"
    class="btn btn-primary w-100">
    Sign In
</button>


</form>

                </div>
            </div>

        </div>
    </div>
</div>

</body>
</html>
