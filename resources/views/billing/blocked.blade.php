@extends('layouts.app')
@section('title', 'Payment overdue')
@section('content')
    <div class="row">
        <div class="col-11 col-md-8 mx-auto">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-lock-fill text-danger" style="font-size:3rem"></i>
                    <h4 class="mt-3">Your account is restricted</h4>
                    <p class="text-muted mb-1">{{ $message }}</p>
                    <p class="text-muted">Please contact your account owner to pay the bill. You can use the system again as soon as the payment is done.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
