<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Warranty Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @include('partials.select2-css')
    @include('partials.notify-css')
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
</head>
<body>
<div class="auth-wrap d-flex align-items-center py-4">
    <div class="container">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-lg-6 auth-hero">
                <div class="d-flex align-items-center gap-2 mb-4 fs-4 fw-bold">
                    <i class="bi bi-shield-check text-primary fs-2"></i> Warranty <span class="text-primary">Tracker</span>
                </div>
                <small class="text-uppercase text-muted fw-semibold">Keep every warranty in one place</small>
                <h1 class="display-5 fw-bold mt-2" style="color:#0b1f4d">Never Miss a <br><span>Warranty Expiry</span></h1>
                <p class="text-muted fs-5">Store your products, invoices and warranty details safely. Get timely reminders and stay worry-free.</p>
                <div class="d-none d-md-block">
                    @foreach ([['bi-bell','#19b37a','Get expiry reminders','Email and app notifications'],['bi-file-earmark-text','#3b82f6','Store documents','Invoices, warranty cards, receipts'],['bi-shield-lock','#8b5cf6','Keep everything secure','Your data is always protected'],['bi-phone','#f97316','Access anywhere','Use on web and mobile']] as [$ic,$col,$t,$s])
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="feature-dot" style="background:{{ $col }}"><i class="bi {{ $ic }}"></i></span>
                            <div><div class="fw-semibold">{{ $t }}</div><small class="text-muted">{{ $s }}</small></div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card p-3 p-md-4 mx-auto" style="max-width:520px">
                    <div class="card-body">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@include('partials.select2-js')
@include('partials.notify-js')
@include('partials.password-toggle')
</body>
</html>
