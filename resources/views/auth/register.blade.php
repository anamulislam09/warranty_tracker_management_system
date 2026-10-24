@extends('layouts.guest')
@section('title', 'Create Account')
@section('content')
<h3 class="fw-bold">Create Your Account</h3>
<p class="text-muted">Start tracking your warranties in just a few steps.</p>
<form method="POST" id="register-form" action="{{ route('register') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input name="name" class="form-control" placeholder="Enter your full name" value="{{ old('name') }}" required>
        <div class="form-text">2-100 characters</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com" value="{{ old('email') }}" required>
        <div class="form-text">Use a valid email address. This will be your login ID.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Mobile Number <small class="text-muted">(Optional)</small></label>
        <input name="mobile" class="form-control" placeholder="+880 1712 345678" value="{{ old('mobile') }}">
    </div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6">
            <label class="form-label">Country <span class="text-danger">*</span></label>
            <select name="country_code" class="form-select">
                @foreach (config('warranty.countries') as $code => $name)
                    <option value="{{ $code }}" @selected(old('country_code', 'BD') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label">Language <span class="text-danger">*</span></label>
            <select name="language" class="form-select">
                @foreach (config('warranty.languages') as $code => $name)
                    <option value="{{ $code }}" @selected(old('language', 'en') === $code)>{{ $name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Account Type <span class="text-danger">*</span></label>
        <select name="account_type" class="form-select">
            @foreach (['personal' => 'Personal', 'family' => 'Family', 'business' => 'Business'] as $v => $l)
                <option value="{{ $v }}" @selected(old('account_type') === $v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Create a strong password" required>
        <div class="form-text">Minimum 8 characters with letters and numbers.</div>
    </div>
    <div class="mb-3">
        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
        <input type="password" name="password_confirmation" class="form-control" placeholder="Confirm your password" required>
        <div id="match-msg" class="text-danger small mt-1 d-none"><i class="bi bi-exclamation-circle"></i> Password and confirm password do not match.</div>
    </div>
    <button class="btn btn-primary btn-lg w-100"><i class="bi bi-person-plus me-1"></i> Create Account</button>
</form>
<p class="text-center mt-3 mb-0">Already have an account? <a href="{{ route('login') }}" class="fw-semibold">Login</a></p>
<script>
(function () {
    const form = document.getElementById('register-form');
    const pass = form.querySelector('[name="password"]');
    const conf = form.querySelector('[name="password_confirmation"]');
    const msg  = document.getElementById('match-msg');

    // red inputs + message while the two passwords are different
    function check() {
        const bad = conf.value.length > 0 && pass.value !== conf.value;
        pass.classList.toggle('is-invalid', bad);
        conf.classList.toggle('is-invalid', bad);
        msg.classList.toggle('d-none', !bad);
        return !bad;
    }
    pass.addEventListener('input', check);
    conf.addEventListener('input', check);

    form.addEventListener('submit', function (e) {
        if (pass.value !== conf.value) {
            e.preventDefault();
            pass.classList.add('is-invalid');
            conf.classList.add('is-invalid');
            msg.classList.remove('d-none');
            conf.focus();
            if (window.toastr) toastr.error('Password and confirm password do not match.', 'Failed');
        }
    });
})();
</script>
@endsection
