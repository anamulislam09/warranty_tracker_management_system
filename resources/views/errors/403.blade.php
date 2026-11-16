@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')
@section('title', 'Access denied')
@section('content')
    <div class="row">
        <div class="col-11 col-md-8 mx-auto">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-shield-lock-fill text-danger" style="font-size:3.5rem"></i>
                    <h4 class="mt-3">Access Denied</h4>
                    <p class="text-muted mb-1">{{ $exception->getMessage() ?: 'You do not have permission to open this page.' }}</p>
                    <p class="text-muted">If you need this access, please ask your account owner.</p>
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-warning" onclick="window.history.back()"><i class="bi bi-arrow-left"></i> Go back</button>
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-house"></i> My home</a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
