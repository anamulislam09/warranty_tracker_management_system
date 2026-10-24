<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    public function index()
    {
        return view('admin.permissions.index', [
            'permissions' => Permission::orderBy('group')->orderBy('label')->get()->groupBy('group'),
        ]);
    }

    public function create() { return view('admin.permissions.form', ['permission' => new Permission(['is_active' => true, 'group' => 'General'])]); }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'  => ['required', 'max:100', 'regex:/^[a-z_]+(\.[a-z_]+)+$/', 'unique:permissions,slug'],
            'label' => 'required|string|max:150',
            'group' => 'required|string|max:50',
        ]);
        Permission::create($data + ['is_active' => $request->boolean('is_active'), 'is_system' => false]);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission created.');
    }

    public function edit(Permission $permission) { return view('admin.permissions.form', compact('permission')); }

    public function update(Request $request, Permission $permission)
    {
        $data = $request->validate([
            'slug'  => ['required', 'max:100', 'regex:/^[a-z_]+(\.[a-z_]+)+$/', Rule::unique('permissions', 'slug')->ignore($permission)],
            'label' => 'required|string|max:150',
            'group' => 'required|string|max:50',
        ]);
        if ($permission->is_system) unset($data['slug']);   // system keys are used in the code - never change them

        $permission->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.permissions.index')->with('success', 'Permission updated.');
    }

    public function destroy(Permission $permission)
    {
        if ($permission->is_system) {
            return back()->with('error', 'System permissions cannot be deleted. Set it inactive instead.');
        }
        $permission->delete();
        return back()->with('success', 'Permission deleted.');
    }
}
