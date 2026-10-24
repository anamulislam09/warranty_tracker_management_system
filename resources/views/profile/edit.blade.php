@extends('layouts.app')
@section('title', 'My Profile')
@section('content')
<h3 class="page-title mb-1">My Profile</h3>
<p class="text-muted">Manage your personal information and account settings.</p>
<div class="row g-3">
    <div class="col-lg-7"><div class="card"><div class="card-body">
        <h5 class="section-title mb-3"><i class="bi bi-person text-primary"></i> Personal Information</h5>
        <form method="POST" action="{{ route('profile.update') }}">@csrf @method('PUT')
            <div class="mb-3"><label class="form-label">Full Name <span class="text-danger">*</span></label>
                <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required></div>
            <div class="mb-3"><label class="form-label">Email Address</label>
                <input class="form-control" value="{{ $user->email }}" disabled>
                <div class="form-text">Email is your login ID. Contact support to change it.</div></div>
            <div class="mb-3"><label class="form-label">Mobile Number</label>
                <input name="mobile" class="form-control" value="{{ old('mobile', $user->mobile) }}" placeholder="+880 1712 345678"></div>
            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6"><label class="form-label">Country <span class="text-danger">*</span></label>
                    <select name="country_code" class="form-select">@foreach (config('warranty.countries') as $c => $n)<option value="{{ $c }}" @selected(old('country_code', $user->country_code) === $c)>{{ $n }}</option>@endforeach</select></div>
                <div class="col-12 col-md-6"><label class="form-label">Time Zone <span class="text-danger">*</span></label>
                    <select name="timezone" class="form-select">@foreach (config('warranty.timezones') as $c => $n)<option value="{{ $c }}" @selected(old('timezone', $user->timezone) === $c)>{{ $n }}</option>@endforeach</select></div>
            </div>
            <div class="mb-3"><label class="form-label">Preferred Language <span class="text-danger">*</span></label>
                <select name="language" class="form-select">@foreach (config('warranty.languages') as $c => $n)<option value="{{ $c }}" @selected(old('language', $user->language) === $c)>{{ $n }}</option>@endforeach</select></div>
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
        </form>
    </div></div></div>

    <div class="col-lg-5"><div class="card"><div class="card-body">
        <h5 class="section-title mb-3"><i class="bi bi-lock text-primary"></i> Change Password</h5>
        <form method="POST" action="{{ route('profile.password') }}">@csrf @method('PUT')
            <div class="mb-3"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" required>
                <div class="form-text">Minimum 8 characters with letters and numbers.</div></div>
            <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" name="password_confirmation" class="form-control" required></div>
            <button class="btn btn-outline-primary">Update Password</button>
        </form>
    </div></div></div>
</div>
@endsection
