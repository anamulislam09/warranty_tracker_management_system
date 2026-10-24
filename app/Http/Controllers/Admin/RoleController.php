<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    private function rules(?Role $role = null): array
    {
        return [
            'name'          => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'description'   => 'nullable|string|max:255',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ];
    }

    private function form(Role $role)
    {
        return view('admin.roles.form', [
            'role'        => $role,
            'permissions' => Permission::where('is_active', true)->orderBy('group')->orderBy('label')->get()->groupBy('group'),
            'selected'    => $role->exists ? $role->permissions->pluck('id')->all() : [],
        ]);
    }

    public function index()
    {
        return view('admin.roles.index', ['roles' => Role::with('permissions')->withCount('users')->orderBy('name')->get()]);
    }

    public function create() { return $this->form(new Role(['is_active' => true])); }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $role = Role::create(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role created.');
    }

    public function edit(Role $role) { return $this->form($role->load('permissions')); }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate($this->rules($role));
        $role->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $role->permissions()->sync($data['permissions'] ?? []);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->with('error', 'This role is assigned to users. Set it inactive instead of deleting.');
        }
        $role->delete();
        return back()->with('success', 'Role deleted.');
    }
}
