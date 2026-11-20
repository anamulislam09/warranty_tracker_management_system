<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The list of permissions that can be given to a role. A "system" permission is used by the code: it can not be deleted or renamed. */
class PermissionController extends ApiController
{
    private function rules(?Permission $permission = null): array
    {
        return [
            'slug'      => ['required', 'max:100', 'regex:/^[a-z_]+(\.[a-z_]+)+$/', Rule::unique('permissions', 'slug')->ignore($permission)],
            'label'     => 'required|string|max:150',
            'group'     => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }

    private function row(Permission $p): array
    {
        return ['id' => $p->id, 'slug' => $p->slug, 'label' => $p->label, 'group' => $p->group, 'is_active' => (bool) $p->is_active, 'is_system' => (bool) $p->is_system];
    }

    public function index()
    {
        return $this->ok(Permission::orderBy('group')->orderBy('label')->get()->map(fn ($p) => $this->row($p))->values());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        $permission = Permission::create([
            'slug' => $data['slug'], 'label' => $data['label'], 'group' => $data['group'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true, 'is_system' => false,
        ]);

        return $this->ok($this->row($permission), 'Permission created successfully. To make it work, the website / app must check it somewhere.');
    }

    public function update(Request $request, int $id)
    {
        $permission = Permission::find($id);
        if (! $permission) {
            return $this->fail('Permission not found.', 'not_found');
        }
        $data = $request->validate($this->rules($permission));

        if ($permission->is_system) {
            unset($data['slug']);                           // system keys are used in the code: never change them
        }
        $permission->update([
            'label' => $data['label'], 'group' => $data['group'],
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $permission->is_active,
        ] + (isset($data['slug']) ? ['slug' => $data['slug']] : []));

        return $this->ok($this->row($permission), 'Permission updated successfully.');
    }

    public function destroy(int $id)
    {
        $permission = Permission::find($id);
        if (! $permission) {
            return $this->fail('Permission not found.', 'not_found');
        }
        if ($permission->is_system) {
            return $this->fail('A system permission cannot be deleted. Set it inactive instead.', 'system_permission');
        }
        $permission->delete();

        return $this->ok(null, 'Permission deleted successfully.');
    }
}
