<span class="text-nowrap">
@if ($account->status !== 'active')
    <form method="POST" action="{{ route('admin.accounts.status', $account) }}" class="d-inline">@csrf @method('PATCH')
        <input type="hidden" name="status" value="active">
        <button class="btn btn-sm btn-success">{{ $account->status === 'pending' ? 'Approve' : 'Activate' }}</button></form>
@endif
@if ($account->status !== 'inactive')
    <form method="POST" action="{{ route('admin.accounts.status', $account) }}" class="d-inline" data-confirm="Make this customer inactive? Their users will not be able to login.">@csrf @method('PATCH')
        <input type="hidden" name="status" value="inactive">
        <button class="btn btn-sm btn-outline-danger">Deactivate</button></form>
@endif
</span>
