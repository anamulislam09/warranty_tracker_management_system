<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The customer owner manages ITS OWN users.
 * Roles/permissions are defined by the super admin; the owner only assigns them.
 */
class AccountUserController extends Controller
{
    private function own(User $user): void
    {
        abort_unless($user->account_id === auth()->user()->account_id && $user->role === 'member', 404);
    }

    private function formData(User $user): array
    {
        $roles = Role::with('permissions:id,slug')->where('is_active', true)->orderBy('name')->get();

        return [
            'user'        => $user,
            'roles'       => $roles,
            'rolePerms'   => $roles->mapWithKeys(fn ($r) => [$r->id => $r->permissions->pluck('slug')->all()]),
            'permissions' => Permission::where('is_active', true)->orderBy('group')->orderBy('label')->get()->groupBy('group'),
        ];
    }

    private function rules(?User $user = null): array
    {
        return [
            'name'        => 'required|string|max:100',
            'email'       => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'mobile'      => 'nullable|string|max:30',
            'status'      => 'required|in:active,inactive',
            'password'    => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'role_id'     => ['nullable', Rule::exists('roles', 'id')->where('is_active', true)],
            'permissions' => 'nullable|array',
            'permissions.*' => Rule::in(Permission::where('is_active', true)->pluck('slug')->all()),
        ];
    }

    public function index(Request $request)
    {
        $users = User::with('assignedRole')->where('account_id', $request->user()->account_id)
            ->orderByRaw("role = 'owner' desc")->orderBy('name')->get();

        return view('users.index', [
            'users'   => $users,
            'account' => $request->user()->account,
            'labels'  => Permission::pluck('label', 'slug'),
        ]);
    }

    public function create(Request $request)
    {
        if ($request->user()->account->userLimitReached()) {
            return redirect()->route('users.index')->with('error', 'User limit of your plan is reached. Please upgrade your plan.');
        }
        return view('users.form', $this->formData(new User(['status' => 'active'])));
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;
        if ($account->userLimitReached()) {
            return redirect()->route('users.index')->with('error', 'User limit of your plan is reached.');
        }

        $data = $request->validate($this->rules());
        $data['permissions'] = $request->input('permissions', []);

        User::create($data + [
            'account_id' => $account->id, 'role' => 'member', 'country_code' => $account->country_code,
        ]);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        $this->own($user);
        return view('users.form', $this->formData($user));
    }

    public function update(Request $request, User $user)
    {
        $this->own($user);

        $data = $request->validate($this->rules($user));
        $data['permissions'] = $request->input('permissions', []);
        if (empty($data['password'])) unset($data['password']);

        $user->update($data);
        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $this->own($user);
        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted.');
    }
}
