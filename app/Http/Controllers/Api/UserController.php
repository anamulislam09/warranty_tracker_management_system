<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TeamUserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * The customer OWNER manages the other users of his account. Same rules as the website:
 * every user gets a role (made by the super admin); the owner can add extra permissions and turn off role permissions.
 * What the user may do = role permissions + extra - turned off.
 */
class UserController extends ApiController
{
    private function member(Request $request, int $id): ?User
    {
        return User::where('account_id', $request->user()->account_id)->where('role', 'member')->find($id);
    }

    private function activeSlugs(): array
    {
        return Permission::where('is_active', true)->pluck('slug')->all();
    }

    private function rules(?User $user = null): array
    {
        return [
            'name'          => 'required|string|max:100',
            'email'         => ['required', 'email', Rule::unique('users', 'email')->ignore($user)],
            'mobile'        => 'nullable|string|max:30',
            'status'        => 'required|in:active,inactive',
            'password'      => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'role_id'       => ['required', Rule::exists('roles', 'id')->where('is_active', true)],
            'permissions'   => 'nullable|array',
            'permissions.*' => Rule::in($this->activeSlugs()),
        ];
    }

    /** "permissions" in the request = the FINAL list. We store the difference to the role. */
    private function permissionSets(Request $request, int $roleId): array
    {
        $active    = $this->activeSlugs();
        $rolePerms = array_values(array_intersect(Role::with('permissions')->findOrFail($roleId)->permissions->pluck('slug')->all(), $active));
        $final     = array_values(array_intersect($request->input('permissions', []), $active));

        return [
            'permissions'        => array_values(array_diff($final, $rolePerms)),
            'denied_permissions' => array_values(array_diff($rolePerms, $final)),
        ];
    }

    public function index(Request $request)
    {
        $account = $request->user()->account->load('plan');
        $users = User::with('assignedRole')->where('account_id', $account->id)->orderByRaw("role = 'owner' desc")->orderBy('name')->get();

        return $this->ok(TeamUserResource::collection($users)->resolve($request), 'OK', [
            'used' => $users->count(), 'max_users' => (int) optional($account->plan)->max_users,     // 0 = unlimited
        ]);
    }

    /** Roles and permissions for the "add user" screen. */
    public function options()
    {
        return $this->ok([
            'roles' => Role::with('permissions:id,slug')->where('is_active', true)->orderBy('name')->get()->map(fn ($r) => [
                'id' => $r->id, 'name' => $r->name, 'description' => $r->description,
                'permissions' => $r->permissions->pluck('slug')->values(),
            ])->values(),
            'permissions' => Permission::where('is_active', true)->orderBy('group')->orderBy('label')->get()
                ->map(fn ($p) => ['slug' => $p->slug, 'label' => $p->label, 'group' => $p->group])->values(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $this->member($request, $id);

        return $user ? $this->ok($this->one(TeamUserResource::class, $user->load('assignedRole'))) : $this->fail('Not found.', 'not_found');
    }

    public function store(Request $request)
    {
        $account = $request->user()->account;
        if ($account->userLimitReached()) {
            return $this->fail('User limit of your plan is reached. Please upgrade your plan.', 'plan_limit_reached');
        }

        $data = $request->validate($this->rules());
        unset($data['permissions']);

        $user = User::create($data + $this->permissionSets($request, (int) $data['role_id']) + [
            'account_id' => $account->id, 'role' => 'member', 'country_code' => $account->country_code,
        ]);

        return $this->created($this->one(TeamUserResource::class, $user->load('assignedRole')), 'User created.');
    }

    public function update(Request $request, int $id)
    {
        $user = $this->member($request, $id);
        if (! $user) {
            return $this->fail('Not found.', 'not_found');
        }

        $data = $request->validate($this->rules($user));
        unset($data['permissions']);
        if (empty($data['password'])) unset($data['password']);

        $user->update($data + $this->permissionSets($request, (int) $data['role_id']));

        return $this->ok($this->one(TeamUserResource::class, $user->fresh('assignedRole')), 'User updated.');
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->member($request, $id);
        if (! $user) {
            return $this->fail('Not found.', 'not_found');
        }
        $user->tokens()->delete();                                   // the deleted user is logged out of every phone
        $user->delete();

        return $this->ok(null, 'User deleted.');
    }
}
