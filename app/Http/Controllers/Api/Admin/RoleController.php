<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Roles (Normal User, Staff, Manager ...) and their permissions. They are only for the users a customer creates:
 * the super admin and the customer owner can do everything without a role.
 * Permissions are sent as a list of SLUGS, for example ["products.view", "products.create"].
 */
class RoleController extends ApiController
{
    private function rules(?Role $role = null): array
    {
        return [
            'name'          => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'description'   => 'nullable|string|max:255',
            'is_active'     => 'nullable|boolean',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,slug',
        ];
    }

    private function row(Role $r): array
    {
        return [
            'id'          => $r->id,
            'name'        => $r->name,
            'description' => $r->description,
            'is_active'   => (bool) $r->is_active,
            'users_count' => (int) ($r->users_count ?? $r->users()->count()),
            'permissions' => $r->permissions->map(fn ($p) => ['id' => $p->id, 'slug' => $p->slug, 'label' => $p->label, 'group' => $p->group])->values(),
        ];
    }

    public function index()
    {
        return $this->ok(Role::with('permissions')->withCount('users')->orderBy('name')->get()->map(fn ($r) => $this->row($r))->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $role = Role::create([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);
        $role->permissions()->sync(Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id'));

        return $this->ok($this->row($role->load('permissions')->loadCount('users')), 'Role created successfully.');
    }

    public function update(Request $request, int $id)
    {
        $role = Role::find($id);
        if (! $role) {
            return $this->fail('Role not found.', 'not_found');
        }
        $data = $request->validate($this->rules($role));

        $role->update([
            'name' => $data['name'], 'description' => $data['description'] ?? null,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $role->is_active,
        ]);
        // the list that is sent REPLACES the old permissions of the role
        $role->permissions()->sync(Permission::whereIn('slug', $data['permissions'] ?? [])->pluck('id'));

        return $this->ok($this->row($role->load('permissions')->loadCount('users')), 'Role updated successfully.');
    }

    public function destroy(int $id)
    {
        $role = Role::find($id);
        if (! $role) {
            return $this->fail('Role not found.', 'not_found');
        }
        if ($role->users()->exists()) {
            return $this->fail('This role is given to users, so it cannot be deleted. Set it inactive instead.', 'in_use');
        }
        $role->delete();

        return $this->ok(null, 'Role deleted successfully.');
    }
}
