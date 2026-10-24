@extends('layouts.guest')
@section('title', 'Login')
@section('content')
<h3 class="fw-bold">Welcome back</h3>
<p class="text-muted">Login to manage your warranties.</p>
<form method="POST" action="{{ route('login') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">Email Address <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control form-control-lg" placeholder="you@example.com" value="{{ old('email') }}" required autofocus>
    </div>
    <div class="mb-3">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control form-control-lg" placeholder="Your password" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label" for="remember">Remember me</label>
    </div>
    <button class="btn btn-primary btn-lg w-100">Login</button>
</form>
<p class="text-center mt-3 mb-0">New customer? <a href="{{ route('register') }}" class="fw-semibold">Create account</a></p>
@endsection
