<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    @php $ok = $attempt->status === 'success'; $wait = in_array($attempt->status, ['pending', 'initiated']); @endphp
    <div class="container" style="max-width:420px">
        <div class="card shadow-sm text-center">
            <div class="card-body p-4">
                <i class="bi {{ $ok ? 'bi-check-circle-fill text-success' : ($wait ? 'bi-hourglass-split text-warning' : 'bi-x-circle-fill text-danger') }}" style="font-size:3.5rem"></i>
                <h4 class="mt-3">{{ \App\Models\Invoice::money($attempt->amount) }}</h4>
                <p class="mb-1">{{ $message }}</p>
                <p class="small text-muted">Bill: {{ $attempt->invoice->period_label }}</p>
                <a href="{{ $deepLink }}" class="btn btn-primary w-100">Back to the app</a>
            </div>
        </div>
    </div>
    <script>setTimeout(function () { window.location.href = @json($deepLink); }, 1200);</script>
</body>
</html>
