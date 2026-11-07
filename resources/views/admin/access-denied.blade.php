<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access not available</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container">
    <div class="row min-vh-100 align-items-center justify-content-center py-4">
        <div class="col-sm-10 col-md-7 col-lg-5">
            <div class="card border-0 shadow">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning-emphasis mb-3" style="width: 56px; height: 56px;">
                        <i class="fas fa-lock fs-4"></i>
                    </div>
                    <h1 class="h4 fw-bold mb-2">Access not available</h1>
                    <p class="text-muted mb-4">{{ $message }}</p>

                    <div class="d-grid gap-2">
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-right-from-bracket me-1"></i>Sign out
                            </button>
                        </form>
                    </div>

                    <p class="small text-muted mt-3 mb-0">Signed in as {{ auth()->user()?->email }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
