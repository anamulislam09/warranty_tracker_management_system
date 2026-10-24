@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="page-title mb-0">Notifications</h3>
    <form method="POST" action="{{ route('notifications.readAll') }}">@csrf
        <button class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all"></i> Mark all as read</button></form>
</div>
<div class="card"><div class="list-group list-group-flush rounded-4">
    @forelse ($notifications as $n)
        <a href="{{ route('notifications.read', $n) }}" class="list-group-item list-group-item-action py-3 {{ $n->read_at ? '' : 'bg-primary-subtle' }}">
            <div class="d-flex justify-content-between gap-2">
                <div><div class="fw-semibold">@if(! $n->read_at)<i class="bi bi-circle-fill text-primary small me-1"></i>@endif{{ $n->title }}</div>
                    <div class="small text-muted">{{ $n->message }}</div></div>
                <small class="text-muted text-nowrap">{{ $n->created_at->diffForHumans() }}</small>
            </div>
        </a>
    @empty
        <div class="p-4 text-center text-muted">No notifications yet.</div>
    @endforelse
</div></div>
<div class="mt-3 d-flex justify-content-center">{{ $notifications->links() }}</div>
@endsection
